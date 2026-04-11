<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // If in testing environment, seed test data
        if (app()->environment('testing')) {
            $this->call(TestDataSeeder::class);
        } else {
            // Production seeding (if needed)
            // $this->call([...]);
        }
    }
}
