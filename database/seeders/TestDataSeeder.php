<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\SystemAdmin;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed test data for CI/testing environment.
     */
    public function run(): void
    {
        // Create test school
        $school = School::firstOrCreate(
            ['school_code' => env('RESERVA_TEST_SCHOOL_CODE', 'CI001')],
            [
                'school_name' => 'Escola CI',
                'password' => Hash::make('teste123'),
            ]
        );

        // Create test technician user
        User::firstOrCreate(
            ['email' => env('RESERVA_TEST_EMAIL', 'tecnico.ci@example.com')],
            [
                'school_id' => $school->id,
                'name' => 'Tecnico CI',
                'password' => Hash::make(env('RESERVA_TEST_PASSWORD', 'teste123')),
                'role' => 'technician',
                'active' => true,
            ]
        );

        // Create test admin user
        User::firstOrCreate(
            ['email' => 'admin.ci@example.com'],
            [
                'school_id' => $school->id,
                'name' => 'Admin CI',
                'password' => Hash::make('teste123'),
                'role' => 'admin',
                'active' => true,
            ]
        );

        // Create test teacher user
        User::firstOrCreate(
            ['email' => 'professor.ci@example.com'],
            [
                'school_id' => $school->id,
                'name' => 'Professor CI',
                'password' => Hash::make('teste123'),
                'role' => 'teacher',
                'active' => true,
            ]
        );

        // Create test system admin
        SystemAdmin::firstOrCreate(
            ['email' => 'admin.geral.ci@example.com'],
            [
                'name' => 'Admin Geral CI',
                'password' => Hash::make('teste123'),
                'active' => true,
            ]
        );
    }
}
