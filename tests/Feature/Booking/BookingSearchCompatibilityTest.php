<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class BookingSearchCompatibilityTest extends TestCase
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
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->text('completion_feedback')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_lessons', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('lesson_slot_id');
        });
    }

    public function test_admin_booking_search_supports_formatted_date_on_sqlite(): void
    {
        $schoolId = DB::table('schools')->insertGetId([
            'school_name' => 'Escola Teste',
            'school_code' => 'ETI001',
            'password' => Hash::make('school-secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $teacherId = DB::table('users')->insertGetId([
            'school_id' => $schoolId,
            'name' => 'Professor Teste',
            'email' => 'professor@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'teacher',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $technicianToken = hash('sha256', 'tech-token');
        DB::table('users')->insert([
            'school_id' => $schoolId,
            'name' => 'Tecnico Teste',
            'email' => 'tecnico@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'technician',
            'active' => 1,
            'api_token' => $technicianToken,
            'api_token_expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resourceId = DB::table('resources')->insertGetId([
            'school_id' => $schoolId,
            'category_id' => 1,
            'name' => 'Laboratorio 01',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classGroupId = DB::table('class_groups')->insertGetId([
            'school_id' => $schoolId,
            'name' => '1 Ano A',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subjectId = DB::table('subjects')->insertGetId([
            'school_id' => $schoolId,
            'name' => 'Ciencias',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lessonSlotId = DB::table('lesson_slots')->insertGetId([
            'school_id' => $schoolId,
            'lesson_number' => 1,
            'label' => '1a Aula',
            'start_time' => '08:00:00',
            'end_time' => '08:50:00',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bookingId = DB::table('bookings')->insertGetId([
            'school_id' => $schoolId,
            'user_id' => $teacherId,
            'resource_id' => $resourceId,
            'class_group_id' => $classGroupId,
            'subject_id' => $subjectId,
            'booking_date' => '2026-04-22',
            'status' => 'scheduled',
            'purpose' => 'Experimento',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('booking_lessons')->insert([
            'booking_id' => $bookingId,
            'lesson_slot_id' => $lessonSlotId,
        ]);

        $response = $this->getJson('/bookings?school_id='.$schoolId.'&search=22/04/2026', [
            'Authorization' => 'Bearer tech-token',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.booking_date', '2026-04-22')
            ->assertJsonPath('data.0.resource_name', 'Laboratorio 01');
    }
}
