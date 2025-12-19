<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Customer;
use App\Models\Cluster;
use App\Models\Sale;
use App\Models\Payment;
use Carbon\Carbon;

class SaleSeeder extends Seeder
{
    public function run(): void
    {
        $salesUsers = User::where('role', 'sales')->get();
        if ($salesUsers->isEmpty()) return;

        $customers = Customer::all(); // 100 customers
        
        foreach ($customers as $customer) {
            // Assign a random sales agent
            $seller = $salesUsers->random();
            
            // Assume customer bought the cluster they were interested in (Customer->cluster_id)
            // Or re-assign random? Let's use the one in Customer profile for consistency
            $cluster = Cluster::find($customer->cluster_id);
            if (!$cluster) continue; // Should not happen

            $price = $cluster->price;
            
            // Randomize Date (Last 3 months)
            $daysAgo = rand(0, 90);
            $contractDate = Carbon::now()->subDays($daysAgo);

            // Initial Payment (Booking Fee)
            $bookingFee = 10000000; // 10jt
            
            $sale = Sale::create([
                'user_id' => $seller->id,
                'customer_id' => $customer->id,
                'cluster_id' => $cluster->id,
                'locked_type' => $cluster->type,
                'price' => $price, // High price
                'status' => 'Process',
                'booking_date' => $contractDate,
                'payment_amount' => $bookingFee,
                'cicilan_count' => 60, // 5 years
                'sale_date' => $contractDate,
                'monthly_installment' => ($price - $bookingFee) / 60,
            ]);
            
            // Sync customer status
            if ($customer->criteria === 'Deposited') {
                 $customer->update(['criteria' => 'Process']);
            }

            // Record Initial Payment linked to Sale
            Payment::create([
                'sale_id' => $sale->id,
                'amount' => $bookingFee,
                'payment_date' => $contractDate,
                'method' => 'Transfer',
                'note' => 'Booking Fee'
            ]);

            // Create MANY subsequent payments (to simulate history)
            // Random 1 to 10 payments per customer
            $numPayments = rand(1, 10);
            $currentDate = $contractDate->copy();

            for ($p = 0; $p < $numPayments; $p++) {
                $payAmount = rand(5000000, 20000000); // Random installment
                $currentDate->addDays(rand(15, 30)); // Monthly-ish
                
                if ($currentDate->isFuture()) break;

                // Update Sale Total Paid
                $sale->refresh(); // Get latest
                $sale->update([
                    'payment_amount' => $sale->payment_amount + $payAmount
                ]);

                Payment::create([
                    'sale_id' => $sale->id,
                    'amount' => $payAmount,
                    'payment_date' => $currentDate,
                    'method' => 'Transfer',
                    'note' => 'Cicilan Ke-' . ($p + 1)
                ]);
            }
        }
    }
}
