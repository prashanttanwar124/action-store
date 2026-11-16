<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'phone' => '5550101001',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'priya@example.com'],
            [
                'name' => 'Priya Sharma',
                'phone' => '5550101002',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'rahul@example.com'],
            [
                'name' => 'Rahul Khanna',
                'phone' => '5550101003',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'amit@example.com'],
            [
                'name' => 'Amit Patel',
                'phone' => '5550101004',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'vikram@example.com'],
            [
                'name' => 'Vikram Malhotra',
                'phone' => '5550101005',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'ananya@example.com'],
            [
                'name' => 'Ananya Roy',
                'phone' => '5550101006',
                'password' => bcrypt('password'),
                'email_verified_at' => null,
            ]
        );

        $this->call([
            ProductSeeder::class,
            SupplierSeeder::class,
            RecipeKitSeeder::class,
            SliderSeeder::class,
            AdminSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
