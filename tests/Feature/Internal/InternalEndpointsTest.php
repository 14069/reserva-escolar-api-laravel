<?php

declare(strict_types=1);

namespace Tests\Feature\Internal;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                ],
            ]);
        $this->assertContains($response->json('data.driver'), ['sqlite', 'pgsql', 'mysql']);
    }

    public function test_diagnostic_endpoint_rejects_query_string_token(): void
    {
        $response = $this->getJson('/check-supabase-connection?diagnostic_token=test-diagnostic-token');

        $response
            ->assertStatus(401)
            ->assertJsonPath('meta.error_code', 'DIAGNOSTIC_ACCESS_DENIED');
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
        Log::spy();
        Carbon::setTestNow(Carbon::parse('2026-04-10 14:00:00'));

        try {
            $resourceId = DB::table('resources')->insertGetId([
                'school_id' => 1,
                'name' => 'Projetor 1',
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $bookingId = DB::table('bookings')->insertGetId([
                'school_id' => 1,
                'resource_id' => $resourceId,
                'user_id' => 7,
                'booking_date' => '2026-04-10',
                'status' => 'scheduled',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $lessonSlotId = DB::table('lesson_slots')->insertGetId([
                'school_id' => 1,
                'lesson_number' => 1,
                'label' => '1ª aula',
                'end_time' => '13:00:00',
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('booking_lessons')->insert([
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

            Log::shouldHaveReceived('info')
                ->withArgs(function (...$args): bool {
                    if (count($args) !== 2) {
                        return false;
                    }

                    [$message, $context] = $args;

                    return $message === 'Internal booking reminders completed'
                        && is_array($context)
                        && ($context['evaluated_count'] ?? null) === 1
                        && ($context['created_count'] ?? null) === 1
                        && ($context['booking_count'] ?? null) === 1
                        && is_int($context['duration_ms'] ?? null);
                });
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_booking_reminder_endpoint_does_not_repeat_same_booking_on_following_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 14:00:00'));

        try {
            $resourceId = DB::table('resources')->insertGetId([
                'school_id' => 1,
                'name' => 'Projetor 1',
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $bookingId = DB::table('bookings')->insertGetId([
                'school_id' => 1,
                'resource_id' => $resourceId,
                'user_id' => 7,
                'booking_date' => '2026-04-10',
                'status' => 'scheduled',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $lessonSlotId = DB::table('lesson_slots')->insertGetId([
                'school_id' => 1,
                'lesson_number' => 1,
                'label' => '1ª aula',
                'end_time' => '13:00:00',
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('booking_lessons')->insert([
                'booking_id' => $bookingId,
                'lesson_slot_id' => $lessonSlotId,
            ]);

            $firstResponse = $this->postJson('/send-booking-completion-reminders', [], [
                'X-Reserva-Cron-Token' => 'test-cron-token',
            ]);

            $firstResponse
                ->assertOk()
                ->assertJsonPath('data.created_count', 1);

            Carbon::setTestNow(Carbon::parse('2026-04-11 09:00:00'));

            $secondResponse = $this->postJson('/send-booking-completion-reminders', [], [
                'X-Reserva-Cron-Token' => 'test-cron-token',
            ]);

            $secondResponse
                ->assertOk()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'evaluated_count' => 1,
                        'created_count' => 0,
                        'booking_ids' => [],
                    ],
                ]);

            $this->assertSame(
                1,
                DB::table('notifications')
                    ->where('type', 'booking_reminder_complete')
                    ->where('booking_id', $bookingId)
                    ->count()
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_booking_reminder_endpoint_rejects_query_string_token(): void
    {
        $response = $this->postJson('/send-booking-completion-reminders?cron_token=test-cron-token');

        $response
            ->assertStatus(401)
            ->assertJsonPath('meta.error_code', 'CRON_ACCESS_DENIED');
    }

    public function test_booking_reminder_endpoint_logs_structured_failure_context(): void
    {
        Log::spy();
        Carbon::setTestNow(Carbon::parse('2026-04-10 14:00:00'));

        try {
            $resourceId = DB::table('resources')->insertGetId([
                'school_id' => 1,
                'name' => 'Projetor 1',
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $bookingId = DB::table('bookings')->insertGetId([
                'school_id' => 1,
                'resource_id' => $resourceId,
                'user_id' => 7,
                'booking_date' => '2026-04-10',
                'status' => 'scheduled',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $lessonSlotId = DB::table('lesson_slots')->insertGetId([
                'school_id' => 1,
                'lesson_number' => 1,
                'label' => '1ª aula',
                'end_time' => '13:00:00',
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('booking_lessons')->insert([
                'booking_id' => $bookingId,
                'lesson_slot_id' => $lessonSlotId,
            ]);

            Schema::dropIfExists('notifications');

            $response = $this->postJson('/send-booking-completion-reminders', [], [
                'X-Reserva-Cron-Token' => 'test-cron-token',
            ]);

            $response
                ->assertStatus(500)
                ->assertJsonPath('success', false);

            Log::shouldHaveReceived('error')
                ->once()
                ->withArgs(function (string $message, array $context): bool {
                    return $message === 'Internal booking reminders failed'
                        && is_string($context['error_class'] ?? null)
                        && ($context['status_code'] ?? null) === 500
                        && is_string($context['error'] ?? null)
                        && ($context['error'] ?? '') !== ''
                        && is_int($context['duration_ms'] ?? null)
                        && ! array_key_exists('trace', $context);
                });
        } finally {
            Carbon::setTestNow();
        }
    }
}
