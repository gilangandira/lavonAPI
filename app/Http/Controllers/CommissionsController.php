<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommissionsController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        // Get all users who have sales in the specified month
        // Commission rule: 1.2% of Price
        // "Bagi user yang sudah menjual" -> Based on Sale Date or Booking Date? Usually Booking Date or Sale Date.
        // Let's use 'sale_date' as the official date.

        $users = User::with(['sales' => function ($query) use ($year, $month) {
            $query->whereYear('sale_date', $year)
                  ->whereMonth('sale_date', $month);
        }])->get();

        $data = $users->map(function ($user) {
            $salesCount = $user->sales->count();
            $totalSalesValue = $user->sales->sum('price');
            $commission = $totalSalesValue * 0.012; // 1.2%

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'sales_count' => $salesCount,
                'total_sales_value' => $totalSalesValue,
                'commission' => $commission,
            ];
        })->filter(function ($item) {
            // Only show users with commissions > 0 (or sales > 0)
            return $item['sales_count'] > 0;
        })->values(); // Reset keys

        // Sort by commission desc
        $data = $data->sortByDesc('commission')->values();

        return response()->json([
            'success' => true,
            'year' => (int)$year,
            'month' => (int)$month,
            'data' => $data
        ]);
    }
}
