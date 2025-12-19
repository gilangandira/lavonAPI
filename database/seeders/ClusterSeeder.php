<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cluster;

class ClusterSeeder extends Seeder
{
    public function run(): void
    {
        $clusters = [
            // Luxury / Expensive Types
            ['type' => 'Grand Diamond (120/150)', 'luas_tanah' => 150, 'luas_bangunan' => 120, 'price' => 2500000000, 'stock' => 5],
            ['type' => 'Royal Emerald (100/120)', 'luas_tanah' => 120, 'luas_bangunan' => 100, 'price' => 1800000000, 'stock' => 8],
            ['type' => 'Golden Sapphire (90/110)', 'luas_tanah' => 110, 'luas_bangunan' => 90, 'price' => 1500000000, 'stock' => 10],
            ['type' => 'Platinum Hill (150/200)', 'luas_tanah' => 200, 'luas_bangunan' => 150, 'price' => 3200000000, 'stock' => 3],
            ['type' => 'Silver Creek (80/100)', 'luas_tanah' => 100, 'luas_bangunan' => 80, 'price' => 1200000000, 'stock' => 15],
            ['type' => 'Titanium View (200/300)', 'luas_tanah' => 300, 'luas_bangunan' => 200, 'price' => 5500000000, 'stock' => 2],
            ['type' => 'Bronze Lake (70/90)', 'luas_tanah' => 90, 'luas_bangunan' => 70, 'price' => 950000000, 'stock' => 20],
            ['type' => 'Crystal Palace (300/500)', 'luas_tanah' => 500, 'luas_bangunan' => 300, 'price' => 8500000000, 'stock' => 1],
            ['type' => 'Pearl Horizon (60/80)', 'luas_tanah' => 80, 'luas_bangunan' => 60, 'price' => 850000000, 'stock' => 25],
            ['type' => 'Ruby Garden (110/130)', 'luas_tanah' => 130, 'luas_bangunan' => 110, 'price' => 2100000000, 'stock' => 6],
        ];

        foreach ($clusters as $cluster) {
            Cluster::create($cluster);
        }
    }
}
