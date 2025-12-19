<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\Cluster;
use Faker\Factory as Faker;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID'); // Use Indonesian locale
        $clusters = Cluster::all();

        if ($clusters->isEmpty()) return;

        for ($i = 0; $i < 100; $i++) {
            Customer::create([
                'nik' => $faker->unique()->numerify('16##############'), // 16 digits
                'name' => $faker->name,
                'phone' => $faker->phoneNumber,
                'address' => $faker->address,
                'email' => $faker->unique()->safeEmail,
                'cicilan' => $faker->randomElement(['12','24','36','48','60']),
                'criteria' => $faker->randomElement(['Visited', 'Deposited', 'Booked', 'Process']),
                'cluster_id' => $clusters->random()->id,
                // Optional: cicilan, payment_method can be seeded too if needed
            ]);
        }
    }
}