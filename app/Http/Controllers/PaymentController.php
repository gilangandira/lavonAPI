<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    // GET /api/payments
    public function index(Request $request)
    {
        $query = Payment::with(['sale.customer', 'sale.cluster'])->latest();

        if ($request->search) {
             // Simple search by note or related customer name
             $search = $request->search;
             $query->where('note', 'like', "%{$search}%")
                   ->orWhereHas('sale.customer', function($q) use ($search) {
                       $q->where('name', 'like', "%{$search}%");
                   });
        }

        $payments = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }

    // POST /api/payments (Manual entry if needed later)
    public function store(Request $request)
    {
        // Currently handled via Sales entry, but good to have
        $validated = $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'amount' => 'required|numeric',
            'payment_date' => 'required|date',
            'method' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $payment = Payment::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded',
            'data' => $payment
        ], 201);
    }
}
