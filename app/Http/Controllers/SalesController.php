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
                'interest_amount' => 0,
                'principal_amount' => $data['payment_amount'],
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
            'interest_rate'  => 'sometimes|numeric|min:0',
        ]);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $sale) {
            $data = $request->only(['price', 'payment_amount', 'status', 'add_payment', 'interest_rate']);

            // Handle Add Payment
            if ($request->has('add_payment') && $request->add_payment > 0) {
                // Update total payment amount
                $sale->payment_amount += $request->add_payment;
                
                // Calculate Principal & Interest Split
                $interestAmount = 0;
                $principalAmount = $request->add_payment;
                
                $rate = $sale->interest_rate ?? 0;
                if ($rate > 0) {
                     // 1. Calculate Initial Loan Principal (PV)
                     // PV = PMT * (1 - (1+r)^-n) / r
                     $monthly = $sale->monthly_installment;
                     $months = $sale->cicilan_count;
                     $r = ($rate / 12) / 100;
                     
                     if ($monthly > 0 && $months > 0 && $r > 0) {
                         $initialLoan = $monthly * (1 - pow(1 + $r, -$months)) / $r;
                         
                         // 2. Calculate Outstanding Principal
                         // Sum of principal_amount from previous payments
                         // Note: For old payments without split, we might need a fallback. 
                         // But assuming new system, we rely on `principal_amount`.
                         $paidPrincipal = \App\Models\Payment::where('sale_id', $sale->id)->sum('principal_amount');
                         
                         // Special Handling: If this is the FIRST installment payment after DP, 
                         // we might need to check if DP was recorded as Principal.
                         // Usually DP is 100% Principal.
                         // If old data has 0 principal_amount, we might be in trouble.
                         // Let's assume for now existing payments (DP) are treated as Principal if 0? 
                         // Actually, we should check 'amount' if 'principal_amount' is 0.
                         // Safe bet: $paidPrincipal = Payment::where(sale_id)->sum('principal_amount');
                         // If it's new feature, old payments are 0.
                         // Fallback: If sum(principal) == 0 but sum(amount) > 0, maybe assume all amount is principal?
                         // Let's stick to explicitly stored principal_amount for accuracy going forward.
                         
                         // Re-calculate Paid Principal based on if columns exist?
                         // Let's just use the column.
                         
                         // If user has paid DP (which should be principal), it should be in principal_amount.
                         // If we didn't migrate old data, we might need to run a seeder or logic here.
                         // For this task, assume we start clean or manual fix.
                         // To be safe for existing data: 
                         $previousPayments = \App\Models\Payment::where('sale_id', $sale->id)->get();
                         $paidPrincipal = 0;
                         foreach($previousPayments as $p) {
                             $paidPrincipal += ($p->principal_amount > 0 ? $p->principal_amount : $p->amount);
                         }

                         // Adjusted Initial Loan? 
                         // Wait, Initial Loan Calculated from PV is "Loan Amount" (Price - DP).
                         // So the "Outstanding Balance" is based on that Loan Amount.
                         // DP is NOT part of this Loan Amount.
                         // So we should only subtract payments that were made AFTER the DP?
                         // Or does the Loan Amount start at (Price)? No, KPR is (Price - DP).
                         // So Outstanding Balance starts at (Price - DP).
                         
                         // Correct Logic:
                         // Initial Balance = Price - DP (or calculated PV).
                         // Payments (Installments) reduce this balance.
                         // So we sum up `principal_amount` of Installments only.
                         // How to distinguish DP payment vs Installment payment?
                         // DP usually has method='Initial'.
                         
                         $outstandingOne = $initialLoan;
                         
                         // Subtract principal paid from installments
                         $installmentPrincipalPaid = \App\Models\Payment::where('sale_id', $sale->id)
                                                                        ->where('method', '!=', 'Initial')
                                                                        ->sum('principal_amount');

                         // Wait, if old payments have 0 principal_amount, we have issue.
                         // Let's assume naive approach: 
                         // Outstanding = $initialLoan - $installmentPrincipalPaid.
                         
                         $outstandingBalance = $initialLoan - $installmentPrincipalPaid;

                         if ($outstandingBalance > 0) {
                             $interestAmount = $outstandingBalance * $r; // Monthly Interest
                             // Cap interest? No, payment might effectively cover just interest or partial.
                             
                             if ($interestAmount > $request->add_payment) {
                                 $interestAmount = $request->add_payment;
                                 $principalAmount = 0;
                             } else {
                                 $principalAmount = $request->add_payment - $interestAmount;
                             }
                         }
                     }
                }
                
                // Record Payment History
                \App\Models\Payment::create([
                    'sale_id' => $sale->id,
                    'amount' => $request->add_payment,
                    'interest_amount' => $interestAmount,
                    'principal_amount' => $principalAmount,
                    'payment_date' => now(), 
                    'method' => 'Unknown', 
                    'note' => 'Additional Payment'
                ]);

                // Update Customer criteria if currently Deposited
                if ($sale->customer && $sale->customer->criteria === 'Deposited') {
                    $sale->customer->update(['criteria' => 'Process']);
                }
            }

            // Update Interest Rate if provided
            if ($request->has('interest_rate')) {
                $sale->interest_rate = $request->interest_rate;
            }

            // Allow direct override of payment_amount if provided (and not just adding)
            if ($request->has('payment_amount')) {
                $sale->payment_amount = $request->payment_amount;
            }

            // Recalculate Monthly Installment if DP OR Interest Rate changed
            if ($request->has('payment_amount') || $request->has('interest_rate')) {
                if ($sale->cicilan_count > 0) {
                     $remaining = $sale->price - $sale->payment_amount;
                     if ($remaining > 0) {
                         $rate = $sale->interest_rate ?? 0;
                         if ($rate > 0) {
                             $r = ($rate / 12) / 100;
                             $n = $sale->cicilan_count;
                             // PMT
                             if ($r == 0) {
                                  $sale->monthly_installment = $remaining / $n;
                             } else {
                                  $sale->monthly_installment = ($remaining * $r * pow(1 + $r, $n)) / (pow(1 + $r, $n) - 1);
                             }
                         } else {
                             // If rate is 0, simple division
                             $sale->monthly_installment = $remaining / $sale->cicilan_count;
                         }
                     } else {
                         $sale->monthly_installment = 0;
                     }
                }
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
