<?php

namespace App\Http\Controllers;


use App\Models\Customer;
use App\Models\Cluster;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

class CustomersController extends Controller
{
    public function stats()
    {
        $total = Customer::count();
        $newThisMonth = Customer::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        
        $statusCounts = Customer::select('criteria', DB::raw('count(*) as total'))
            ->groupBy('criteria')
            ->pluck('total', 'criteria'); // returns keyed array [ 'Booked' => 5, 'Deposited' => 2 ]

        return response()->json([
            'success' => true,
            'data' => [
                'total_customers' => $total,
                'new_this_month' => $newThisMonth,
                'status_breakdown' => $statusCounts,
                // 'active' metric could be implemented if we define what 'active' means, 
                // for now let's just return total for active or a simple heuristic
                'active_customers' => Customer::has('sales')->count() 
            ]
        ]);
    }
    public function index(Request $request)
{
    $query = Customer::with('cluster');

    if ($request->search) {
        $query->where(function ($q) use ($request) {
            $q->where('name', 'like', "%{$request->search}%")
              ->orWhere('nik', 'like', "%{$request->search}%")
              ->orWhere('phone', 'like', "%{$request->search}%");
        });
    }

    if ($request->cluster_id) {
        $query->where('cluster_id', $request->cluster_id);
    }
    
    if ($request->criteria) {
        $query->where('criteria', $request->criteria);
    }

    if ($request->has('all')) {
        return response()->json([
            'success' => true,
            'data' => $query->get()
        ]);
    }

    $customers = $query->latest()->paginate(10);

    return response()->json([
        'success' => true,
        'data' => $customers
    ]);
}


    public function store(Request $request)
    {
        $request->validate([
            'nik'       => 'required|unique:customers',
            'name'      => 'required',
            'phone'     => 'required',
            'address'   => 'required',
            'email'     => 'required|email|unique:customers',
            'criteria'  => 'required|in:Visited,Deposited,Booked,Process',
            'cicilan'   => 'nullable|integer',
            'cluster_id'=> 'required|exists:clusters,id',
            'payment_method' => 'nullable|string',
            'payment_amount' => 'nullable|numeric|min:0',
            'interest_rate'  => 'nullable|numeric|min:0'
        ]);

        $customer = Customer::create($request->except('interest_rate')); // Do not save interest_rate to customer

        // Automatically create Sale if Booked or Deposited
        if (in_array($request->criteria, ['Booked', 'Deposited'])) {
             $cluster = Cluster::find($request->cluster_id);
             if ($cluster) {
                 $paymentAmount = $request->payment_amount ?? 0;
                 $cicilanCount = $request->cicilan ?? 0;
                 $interestRate = $request->interest_rate ?? 0;

                 // Calculate Monthly Installment
                 $monthlyInstallment = 0;
                 if ($cicilanCount > 0) {
                     $remaining = $cluster->price - $paymentAmount;
                     if ($remaining > 0) {
                         // Interest Logic (Annuity)
                         if ($interestRate > 0) {
                             $r = ($interestRate / 12) / 100;
                             $n = $cicilanCount;
                             if ($r == 0) {
                                 $monthlyInstallment = $remaining / $n;
                             } else {
                                 // PMT
                                 $monthlyInstallment = ($remaining * $r * pow(1 + $r, $n)) / (pow(1 + $r, $n) - 1);
                             }
                         } else {
                             $monthlyInstallment = $remaining / $cicilanCount;
                         }
                     }
                 }
                 
                 $sale = \App\Models\Sale::create([
                     'user_id' => $request->user()->id ?? 1,
                     'customer_id' => $customer->id,
                     'cluster_id' => $cluster->id,
                     'locked_type' => $cluster->type,
                     'price' => $cluster->price,
                     'status' => $request->criteria,
                     'sale_date' => now(),
                     'booking_date' => now(),
                     'payment_amount' => $paymentAmount,
                     'cicilan_count' => $cicilanCount,
                     'payment_method' => $request->payment_method ?? 'Cash',
                     'monthly_installment' => $monthlyInstallment,
                     'interest_rate' => $interestRate
                 ]);

                 // Create Payment Record if amount > 0
                 if ($paymentAmount > 0) {
                     \App\Models\Payment::create([
                         'sale_id' => $sale->id,
                         'amount' => $paymentAmount,
                         'payment_date' => now(),
                         'payment_method' => $request->payment_method ?? 'Cash',
                         'payment_type' => 'Initial Payment', // or 'Booking Fee' / 'Down Payment'
                         'notes' => 'Initial payment upon customer creation (' . $request->criteria . ')',
                     ]);
                 }
             }
        }

        return response()->json([
            'success' => true,
            'message' => 'Customer created.',
            'data' => $customer
        ], 201);
    }

    public function show(Customer $customer)
    {
        $customer->load('cluster');

        return response()->json([
            'success' => true,
            'data' => $customer
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $request->validate([
            'nik'       => 'required|unique:customers,nik,' . $customer->id,
            'name'      => 'required',
            'phone'     => 'required',
            'address'   => 'required',
            'email'     => 'required|email|unique:customers,email,' . $customer->id,
            'criteria'  => 'required|in:Visited,Deposited,Booked,Process',
            'cicilan'   => 'nullable|integer',
            'cluster_id'=> 'required|exists:clusters,id',
            'payment_method' => 'nullable|string'
        ]);

        $customer->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Customer updated.',
            'data' => $customer
        ]);
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted.'
        ]);
    }
}
