<?php

declare(strict_types=1);

namespace Tests\Feature\Internal;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class InternalEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('RESERVA_DIAGNOSTIC_TOKEN=test-diagnostic-token');
        $_ENV['RESERVA_DIAGNOSTIC_TOKEN'] = 'test-diagnostic-token';
        $_SERVER['RESERVA_DIAGNOSTIC_TOKEN'] = 'test-diagnostic-token';

        putenv('RESERVA_CRON_TOKEN=test-cron-token');
        $_ENV['RESERVA_CRON_TOKEN'] = 'test-cron-token';
        $_SERVER['RESERVA_CRON_TOKEN'] = 'test-cron-token';

        Schema::dropIfExists('notifications');
        Schema::dropIfExists('booking_lessons');
        Schema::dropIfExists('lesson_slots');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('resources');

        Schema::create('resources', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('name');
            $table->smallInteger('active')->default(1);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('resource_id');
            $table->unsignedBigInteger('user_id');
            $table->date('booking_date');
            $table->string('status', 20)->default('scheduled');
            $table->timestamps();
        });

        Schema::create('lesson_slots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->integer('lesson_number');
            $table->string('label');
            $table->time('end_time')->nullable();
            $table->smallInteger('active')->default(1);
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
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->text('metadata_json')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_diagnostic_endpoint_requires_token(): void
    {
        $response = $this->getJson('/check-supabase-connection');

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'meta' => [
                    'error_code' => 'DIAGNOSTIC_ACCESS_DENIED',
                ],
            ]);
    }

    public function test_diagnostic_endpoint_returns_success_with_valid_token(): void
    {
        $response = $this->getJson('/check-supabase-connection', [
            'X-Reserva-Diagnostic-Token' => 'test-diagnostic-token',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Conexão com o banco verificada com sucesso.',
                'data' => [
                    'status' => 'ok',
                    'driver' => 'sqlite',
                ],
            ]);
    }

    public function test_booking_reminder_endpoint_requires_cron_token(): void
    {
        $response = $this->postJson('/send-booking-completion-reminders');

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'meta' => [
                    'error_code' => 'CRON_ACCESS_DENIED',
                ],
            ]);
    }

    public function test_booking_reminder_endpoint_returns_success_with_valid_token(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 14:00:00'));

        $resourceId = \DB::table('resources')->insertGetId([
            'school_id' => 1,
            'name' => 'Projetor 1',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bookingId = \DB::table('bookings')->insertGetId([
            'school_id' => 1,
            'resource_id' => $resourceId,
            'user_id' => 7,
            'booking_date' => '2026-04-10',
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lessonSlotId = \DB::table('lesson_slots')->insertGetId([
            'school_id' => 1,
            'lesson_number' => 1,
            'label' => '1ª aula',
            'end_time' => '13:00:00',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('booking_lessons')->insert([
            'booking_id' => $bookingId,
            'lesson_slot_id' => $lessonSlotId,
        ]);

        $response = $this->postJson('/send-booking-completion-reminders', [], [
            'X-Reserva-Cron-Token' => 'test-cron-token',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Lembretes processados com sucesso.',
                'data' => [
                    'evaluated_count' => 1,
                    'created_count' => 1,
                    'booking_ids' => [$bookingId],
                ],
            ]);

        $this->assertDatabaseHas('notifications', [
            'school_id' => 1,
            'user_id' => 7,
            'type' => 'booking_reminder_complete',
            'booking_id' => $bookingId,
        ]);
    }
}
