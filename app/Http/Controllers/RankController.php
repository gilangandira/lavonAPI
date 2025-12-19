<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RankController extends Controller
{
    public function index()
    {
        // Aggregate sales by user
        // We want: User Name, Total Sales Count, Total Revenue
        $rankings = User::withCount('sales')
            ->withSum('sales', 'price') // Assuming 'price' is the revenue metric, or could use payment_amount
            ->has('sales') // Only show users with sales
            ->get()
            ->sortByDesc('sales_sum_price') // Sort by revenue by default
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rankings
        ]);
    }
}
