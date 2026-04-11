<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CreateBookingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('bookings');
        Schema::dropIfExists('lesson_slots');
        Schema::dropIfExists('resources');
        Schema::dropIfExists('users');
        Schema::dropIfExists('schools');

        Schema::create('schools', function (Blueprint $table): void {
            $table->id();
            $table->string('school_name');
            $table->string('school_code')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('role', 20);
            $table->smallInteger('active')->default(1);
            $table->string('api_token', 64)->nullable();
            $table->timestamp('api_token_expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('resources', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('resource_code')->unique();
            $table->string('resource_name');
            $table->string('category', 20);
            $table->smallInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('lesson_slots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('lesson_slot_code')->unique();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('class_group_name');
            $table->string('teacher_name');
            $table->string('subject_name');
            $table->smallInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('resource_id');
            $table->unsignedBigInteger('lesson_slot_id');
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function test_create_booking_requires_authentication(): void
    {
        $response = $this->postJson('/bookings', [
            'school_id' => 1,
            'resource_id' => 1,
            'lesson_slot_id' => 1,
        ]);

        $response->assertStatus(401);
    }

    public function test_create_booking_validates_required_fields(): void
    {
        $schoolId = \DB::table('schools')->insertGetId([
            'school_name' => 'Escola Teste',
            'school_code' => 'ETI001',
            'password' => Hash::make('school-secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('users')->insert([
            'school_id' => $schoolId,
            'name' => 'Usuário',
            'email' => 'user@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'active' => 1,
            'api_token' => 'valid-token',
            'api_token_expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/bookings', [
            'school_id' => $schoolId,
            'resource_id' => null,
            'lesson_slot_id' => null,
        ], [
            'Authorization' => 'Bearer valid-token',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_create_booking_fails_with_invalid_school(): void
    {
        $schoolId = \DB::table('schools')->insertGetId([
            'school_name' => 'Escola 1',
            'school_code' => 'ETI001',
            'password' => Hash::make('school-secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('users')->insert([
            'school_id' => $schoolId,
            'name' => 'Usuário',
            'email' => 'user@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'active' => 1,
            'api_token' => 'valid-token',
            'api_token_expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/bookings', [
            'school_id' => $schoolId + 999,
            'resource_id' => 1,
            'lesson_slot_id' => 1,
        ], [
            'Authorization' => 'Bearer valid-token',
        ]);

        $response->assertStatus(401);
    }
}
