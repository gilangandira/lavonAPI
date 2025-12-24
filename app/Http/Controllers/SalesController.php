<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Cluster;
use App\Models\Sale;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $query = Sale::with(['cluster', 'customer'])->latest();

        // 1. Search (Customer Name or Cluster Type)
        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                // Search by Customer Name
                $q->whereHas('customer', function($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%");
                })
                // OR search by Cluster Type
                ->orWhereHas('cluster', function($q3) use ($search) {
                    $q3->where('type', 'like', "%{$search}%");
                });
            });
        }

        // 2. Filter by Status
        if ($status = $request->input('status')) {
            if ($status === 'Paid') {
                $query->where('status', 'Paid')
                      ->orWhereRaw('payment_amount >= price');
            } elseif ($status === 'Process') {
                $query->where('status', '!=', 'Paid')
                      ->where('status', '!=', 'Booked')
                      ->where('status', '!=', 'Deposited')
                      ->whereRaw('payment_amount < price');
            } elseif (in_array($status, ['Booked', 'Deposited'])) {
                $query->where('status', $status);
            } elseif (in_array($status, ['Overdue', 'Due Soon', 'Normal'])) {
                // Advanced filtering based on computed logic
                // Using raw SQL for date calculations (assuming MySQL/MariaDB)
                
                // Logic replication:
                // Months Paid = floor(payment_amount / monthly_installment)
                // Next Due Date = sale_date + interval (months_paid + 1) month
                // Diff = DATEDIFF(next_due_date, NOW())
                
                $query->where('status', '!=', 'Paid')
                      ->whereRaw('payment_amount < price')
                      ->whereRaw('monthly_installment > 0') // Avoid div by zero
                      ->whereNotNull('sale_date')
                      ->where(function ($q) use ($status) {
                          $sqlNextDueDate = "DATE_ADD(sale_date, INTERVAL (FLOOR(payment_amount / monthly_installment) + 1) MONTH)";
                          $sqlDiff = "DATEDIFF($sqlNextDueDate, NOW())";

                          if ($status === 'Overdue') {
                              $q->whereRaw("$sqlDiff < 0");
                          } elseif ($status === 'Due Soon') {
                              $q->whereRaw("$sqlDiff >= 0 AND $sqlDiff <= 7");
                          } elseif ($status === 'Normal') {
                              $q->whereRaw("$sqlDiff > 7");
                          }
                      });
            }
        }

        if ($request->has('all')) {
            $sales = $query->get();
        } else {
            $sales = $query->paginate(10);
        }

        return response()->json([
            'success' => true,
            'data' => $sales
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id'    => 'required|exists:customers,id',
            'cluster_id'     => 'required|exists:clusters,id',
            'price'          => 'required|numeric',
            'sale_date'      => 'required|date',
            'booking_date'   => 'required|date',
            'payment_amount' => 'required|numeric',
            'cicilan_count'  => 'required|integer',
            'interest_rate'  => 'nullable|numeric|min:0',
            'locked_type'    => 'nullable|string', 
        ]);

        $data = $request->all();

        // Calculate status automatically
        if ($data['payment_amount'] >= $data['price']) {
            $data['status'] = 'Paid';
        } else {
            $data['status'] = 'Process';
        }
        
        // Assign User ID
        $data['user_id'] = $request->user()->id;

        // Snapshot existing cluster type if not provided
        if (empty($data['locked_type'])) {
            $cluster = Cluster::find($request->cluster_id);
            $data['locked_type'] = $cluster ? $cluster->type : 'Unknown';
        }

        // Calculate Monthly Installment (Fixed value)
        // (Price - Initial Payment) / months
        if ($data['cicilan_count'] > 0) {
            $remaining = $data['price'] - $data['payment_amount'];
            if ($remaining > 0) {
                // Interest logic
                $rate = isset($data['interest_rate']) ? $data['interest_rate'] : 0;
                
                if ($rate > 0) {
                    $r = ($rate / 12) / 100; // Monthly rate decimal
                    $n = $data['cicilan_count']; // Months
                    $P = $remaining;

                    // PMT Formula: P * r * (1+r)^n / ((1+r)^n - 1)
                    if ($r == 0) {
                        $monthlyPayment = $P / $n;
                    } else {
                        $monthlyPayment = ($P * $r * pow(1 + $r, $n)) / (pow(1 + $r, $n) - 1);
                    }

                    $data['monthly_installment'] = $monthlyPayment;
                } else {
                    $data['monthly_installment'] = $remaining / $data['cicilan_count'];
                }
            } else {
                $data['monthly_installment'] = 0;
            }
        } else {
            $data['monthly_installment'] = 0;
        }

        $sale = Sale::create($data);

        // Record Initial Payment
        if ($data['payment_amount'] > 0) {
            \App\Models\Payment::create([
                'sale_id' => $sale->id,
                'amount' => $data['payment_amount'],
                'payment_date' => $data['booking_date'], // Use booking date as payment date
                'method' => 'Initial',
                'note' => 'Booking Fee / DP'
            ]);

            // Update Customer criteria if currently Deposited
            $customer = \App\Models\Customer::find($data['customer_id']);
            if ($customer && $customer->criteria === 'Deposited') {
                $customer->update(['criteria' => 'Process']);
            }
        }


        return response()->json([
            'success' => true,
            'message' => 'Sale created successfully.',
            'data' => $sale
        ], 201);
    }

    public function show(Sale $sale)
    {
        $sale->load(['customer', 'cluster']);

        return response()->json([
            'success' => true,
            'data' => $sale
        ]);
    }

    public function update(Request $request, Sale $sale)
    {
        $request->validate([
            'price'          => 'sometimes|numeric',
            'payment_amount' => 'sometimes|numeric',
            'add_payment'    => 'sometimes|numeric', 
            'status'         => 'sometimes|string',
        ]);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $sale) {
            $data = $request->only(['price', 'payment_amount', 'status', 'add_payment', 'interest_rate']);

            // Handle Add Payment
            if ($request->has('add_payment') && $request->add_payment > 0) {
                // Update total payment amount
                $sale->payment_amount += $request->add_payment;
                
                // Record Payment History
                \App\Models\Payment::create([
                    'sale_id' => $sale->id,
                    'amount' => $request->add_payment,
                    'payment_date' => now(), 
                    'method' => 'Unknown', 
                    'note' => 'Additional Payment'
                ]);

                // Update Customer criteria if currently Deposited
                if ($sale->customer && $sale->customer->criteria === 'Deposited') {
                    $sale->customer->update(['criteria' => 'Process']);
                }
            }

            // Allow direct override of payment_amount if provided (and not just adding)
            if ($request->has('payment_amount')) {
                $sale->payment_amount = $request->payment_amount;
            }

            if ($request->has('price')) {
                $sale->price = $request->price;
            }

            // Auto update status
            if ($sale->payment_amount >= $sale->price) {
                $sale->status = 'Paid';
            } else {
                $sale->status = 'Process';
            }

            // Override status if explicitly provided
            if ($request->has('status')) {
                $sale->status = $request->status;
            }

            $sale->save();

            return response()->json([
                'success' => true,
                'message' => 'Sale updated successfully.',
                'data' => $sale
            ]);
        });
    }

    public function destroy(Sale $sale)
    {
        $sale->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sale deleted.'
        ]);
    }
}
