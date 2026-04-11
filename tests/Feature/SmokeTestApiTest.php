<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SmokeTestApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('booking_lessons');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('lesson_slots');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('class_groups');
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
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name');
            $table->smallInteger('active')->default(1);
            $table->timestamps();
        });

        Schema::create('class_groups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('name');
            $table->smallInteger('active')->default(1);
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('name');
            $table->smallInteger('active')->default(1);
            $table->timestamps();
        });

        Schema::create('lesson_slots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->integer('lesson_number');
            $table->string('label');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->smallInteger('active')->default(1);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('resource_id');
            $table->unsignedBigInteger('class_group_id');
            $table->unsignedBigInteger('subject_id');
            $table->date('booking_date');
            $table->string('status', 20)->default('scheduled');
            $table->text('purpose')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by_user_id')->nullable();
            $table->text('completion_feedback')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_lessons', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('lesson_slot_id');
        });

        $schoolId = DB::table('schools')->insertGetId([
            'school_name' => 'Escola CI',
            'school_code' => 'CI001',
            'password' => Hash::make('school-secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'school_id' => $schoolId,
            'name' => 'Tecnico CI',
            'email' => 'tecnico.ci@example.com',
            'password' => Hash::make('teste123'),
            'role' => 'technician',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Test health endpoint.
     */
    public function test_health_endpoint(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200);
    }

    /**
     * Test login endpoint with valid credentials.
     */
    public function test_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/login', [
            'school_code' => 'CI001',
            'email' => 'tecnico.ci@example.com',
            'password' => 'teste123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['api_token', 'school_id', 'id']]);
    }

    /**
     * Test login endpoint with invalid credentials.
     */
    public function test_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/login', [
            'school_code' => 'CI001',
            'email' => 'tecnico.ci@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test that authenticated user can access bookings.
     */
    public function test_authenticated_user_can_access_bookings(): void
    {
        $response = $this->postJson('/login', [
            'school_code' => 'CI001',
            'email' => 'tecnico.ci@example.com',
            'password' => 'teste123',
        ]);

        $token = $response->json('data.api_token');
        $schoolId = $response->json('data.school_id');

        $response = $this->getJson('/bookings?school_id=' . $schoolId, [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200);
    }
}
