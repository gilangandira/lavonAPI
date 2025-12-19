<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Cluster;
use App\Models\Customer;

class ReportController extends Controller
{
    public function salesReport(Request $req)
    {
        $req->validate([
            'start' => 'nullable|date',
            'end'   => 'nullable|date',
            'page'  => 'nullable|integer',
        ]);

        $start = $req->start ?? date('Y-m-01');
        $end   = $req->end ?? date('Y-m-t');
        $page = $req->page ?? 1;
        $perPage = 10;

        // Paginated sales table
        $sales = Sale::with(['customer','cluster'])
            ->whereBetween('booking_date', [$start, $end])
            ->latest('booking_date')
            ->paginate($perPage, ['*'], 'page', $page);

        // Chart data: daily counts per criteria
        $chartData = Sale::selectRaw('DATE(sales.booking_date) as date')
            ->selectRaw("SUM(CASE WHEN customers.criteria = 'Visited' THEN 1 ELSE 0 END) as visited")
            ->selectRaw("SUM(CASE WHEN customers.criteria = 'Deposited' THEN 1 ELSE 0 END) as deposited")
            ->selectRaw("SUM(CASE WHEN customers.criteria = 'Booked' THEN 1 ELSE 0 END) as booked")
            ->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->whereBetween('sales.booking_date', [$start, $end])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($item) => [
                'date' => $item->date,
                'visited' => (int)$item->visited,
                'deposited' => (int)$item->deposited,
                'booked' => (int)$item->booked,
            ]);

        // Summary totals
        $totalRevenue = Sale::whereBetween('booking_date', [$start, $end])->sum('price');
        $totalOrders  = Sale::whereBetween('booking_date', [$start, $end])->count();
        $totalCustomers = Customer::count();

        // Top 5 customers
        $topCustomers = Customer::withSum(['sales' => function($q) use ($start, $end){
            $q->whereBetween('booking_date', [$start, $end]);
        }], 'price')
        ->orderByDesc('sales_sum_price')
        ->take(5)
        ->get();

        // Cluster sales (Radar)
        $clusterRadarQuery = Cluster::withCount(['sales' => function ($q) use ($start, $end) {
            $q->whereBetween('booking_date', [$start, $end]);
        }])
        ->orderByDesc('sales_count')
        ->take(6)
        ->get();



        // Monthly growth (Compare with same period last month)
        $currentRevenue = $totalRevenue;
        
        $startCarbon = \Carbon\Carbon::parse($start);
        $endCarbon   = \Carbon\Carbon::parse($end);
        
        $prevStart = $startCarbon->copy()->subMonth()->format('Y-m-d');
        $prevEnd   = $endCarbon->copy()->subMonth()->format('Y-m-d');

        $prevRevenue = Sale::whereBetween('booking_date', [$prevStart, $prevEnd])->sum('price');

        $growthRate = $prevRevenue > 0
            ? round((($currentRevenue - $prevRevenue) / $prevRevenue) * 100, 2)
            : ($currentRevenue > 0 ? 100 : 0);

        // Latest customers paginated
        $latestCustomers = Customer::orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'filters' => [
                'start' => $start,
                'end'   => $end,
            ],
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_orders'  => $totalOrders,
                'monthly_growth_rate' => $growthRate,
                'total_customers' => $totalCustomers,
            ],
            'chart_data' => $chartData, // ✅ already array of {date, visited, deposited, booked}
            'top_customers' => $topCustomers,
            'cluster_radar' => [
                'labels' => $clusterRadarQuery->pluck('type'),
                'totals' => $clusterRadarQuery->pluck('sales_count'),
            ],
            'sales' => $sales,
            'latest_customers' => $latestCustomers,
        ]);
    }

    public function exportSales(Request $req)
    {
        try {
            $start = $req->start;
            $end = $req->end;

            $query = Sale::with(['customer', 'cluster', 'user', 'payments'])->latest('booking_date');

            if ($start && $end) {
                $query->whereBetween('booking_date', [$start, $end]);
            }

            $sales = $query->get();

            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=sales_recap.csv",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            $callback = function() use ($sales) {
                $file = fopen('php://output', 'w');
                // Add BOM for Excel UTF-8
                fputs($file, "\xEF\xBB\xBF"); 

                // Headers
                fputcsv($file, [
                    'ID', 
                    'Date', 
                    'Customer Name', 
                    'NIK', 
                    'Unit Type', 
                    'Sales Agent',
                    'Status', 
                    'Price', 
                    'Total Paid', 
                    'Remaining', 
                    'Monthly Installment',
                    'Last Payment Date'
                ]);

                foreach ($sales as $sale) {
                    // Determine Sales Agent Name
                    $agent = $sale->user ? $sale->user->name : '-';
                    $customerName = $sale->customer ? $sale->customer->name : '-';
                    $customerNik = $sale->customer ? "'".$sale->customer->nik : '-'; 
                    $remaining = $sale->price - $sale->payment_amount;
                    $lastPayment = $sale->payments->last() ? $sale->payments->last()->payment_date : '-';

                    fputcsv($file, [
                        $sale->id,
                        $sale->booking_date,
                        $customerName,
                        $customerNik,
                        $sale->locked_type ?? ($sale->cluster->type ?? '-'),
                        $agent,
                        $sale->status,
                        $sale->price,
                        $sale->payment_amount,
                        $remaining,
                        $sale->monthly_installment,
                        $lastPayment
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
