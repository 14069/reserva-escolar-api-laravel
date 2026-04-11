<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CreateBookingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('notifications');
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

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->text('metadata_json')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function test_create_booking_requires_authentication(): void
    {
        $response = $this->postJson('/bookings', [
            'school_id' => 1,
            'resource_id' => 1,
            'user_id' => 1,
            'class_group_id' => 1,
            'subject_id' => 1,
            'booking_date' => '2026-04-11',
            'lesson_ids' => [1],
        ]);

        $response->assertStatus(401);
    }

    public function test_create_booking_validates_required_fields(): void
    {
        $schoolId = DB::table('schools')->insertGetId([
            'school_name' => 'Escola Teste',
            'school_code' => 'ETI001',
            'password' => Hash::make('school-secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
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
            'user_id' => 1,
            'class_group_id' => null,
            'subject_id' => null,
            'booking_date' => '',
            'lesson_ids' => [],
        ], [
            'Authorization' => 'Bearer valid-token',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_create_booking_fails_with_invalid_school(): void
    {
        $schoolId = DB::table('schools')->insertGetId([
            'school_name' => 'Escola 1',
            'school_code' => 'ETI001',
            'password' => Hash::make('school-secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
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

        DB::table('resources')->insert([
            'id' => 1,
            'school_id' => $schoolId,
            'category_id' => 1,
            'name' => 'Laboratorio 01',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('class_groups')->insert([
            'id' => 1,
            'school_id' => $schoolId,
            'name' => '1 Ano A',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('subjects')->insert([
            'id' => 1,
            'school_id' => $schoolId,
            'name' => 'Ciencias',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('lesson_slots')->insert([
            'id' => 1,
            'school_id' => $schoolId,
            'lesson_number' => 1,
            'label' => '1a Aula',
            'start_time' => '08:00:00',
            'end_time' => '08:50:00',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/bookings', [
            'school_id' => $schoolId + 999,
            'resource_id' => 1,
            'user_id' => 1,
            'class_group_id' => 1,
            'subject_id' => 1,
            'booking_date' => '2026-04-11',
            'lesson_ids' => [1],
        ], [
            'Authorization' => 'Bearer valid-token',
        ]);

        $response->assertStatus(403);
    }
}
