<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');
        $now = now();
        $users = [];

        // 1 Admin
        $users[] = [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => $password,
            'role' => 'admin',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // 1 Finance
        $users[] = [
            'name' => 'Finance Staff',
            'email' => 'finance@example.com',
            'password' => $password,
            'role' => 'finance',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // 10 Sales Users
        for ($i = 1; $i <= 10; $i++) {
            $users[] = [
                'name' => "Sales Agent $i",
                'email' => "sales$i@example.com",
                'password' => $password,
                'role' => 'sales',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('users')->insert($users);
    }
}
