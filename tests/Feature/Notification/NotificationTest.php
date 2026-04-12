<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class NotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('notifications');
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

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type')->nullable();
            $table->string('title');
            $table->string('message');
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->text('metadata_json')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_get_unread_count_returns_correct_number(): void
    {
        $schoolId = \DB::table('schools')->insertGetId([
            'school_name' => 'Escola Teste',
            'school_code' => 'ETI001',
            'password' => Hash::make('school-secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = \DB::table('users')->insertGetId([
            'school_id' => $schoolId,
            'name' => 'Usuário',
            'email' => 'user@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'active' => 1,
            'api_token' => 'user-token',
            'api_token_expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Criar 3 notificações não lidas
        \DB::table('notifications')->insert([
            ['school_id' => $schoolId, 'user_id' => $userId, 'title' => 'Not 1', 'message' => 'Message 1', 'read_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['school_id' => $schoolId, 'user_id' => $userId, 'title' => 'Not 2', 'message' => 'Message 2', 'read_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['school_id' => $schoolId, 'user_id' => $userId, 'title' => 'Not 3', 'message' => 'Message 3', 'read_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->getJson('/notifications/unread-count?school_id='.$schoolId, [
            'Authorization' => 'Bearer user-token',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'unread_count' => 2,
                ],
            ]);
    }

    public function test_mark_all_read_updates_all_notifications(): void
    {
        $schoolId = \DB::table('schools')->insertGetId([
            'school_name' => 'Escola Teste',
            'school_code' => 'ETI001',
            'password' => Hash::make('school-secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = \DB::table('users')->insertGetId([
            'school_id' => $schoolId,
            'name' => 'Usuário',
            'email' => 'user@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'active' => 1,
            'api_token' => 'user-token',
            'api_token_expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('notifications')->insert([
            ['school_id' => $schoolId, 'user_id' => $userId, 'title' => 'Not 1', 'message' => 'Message 1', 'read_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['school_id' => $schoolId, 'user_id' => $userId, 'title' => 'Not 2', 'message' => 'Message 2', 'read_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->postJson('/notifications/read-all?school_id='.$schoolId, [], [
            'Authorization' => 'Bearer user-token',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'updated_count' => 2,
                ],
            ]);

        $unreadCount = \DB::table('notifications')
            ->where('school_id', $schoolId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        $this->assertEquals(0, $unreadCount);
    }

    public function test_list_notifications_returns_iso_8601_timestamps_with_offset(): void
    {
        $schoolId = \DB::table('schools')->insertGetId([
            'school_name' => 'Escola Teste',
            'school_code' => 'ETI001',
            'password' => Hash::make('school-secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = \DB::table('users')->insertGetId([
            'school_id' => $schoolId,
            'name' => 'Usuário',
            'email' => 'user@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'active' => 1,
            'api_token' => 'user-token',
            'api_token_expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('notifications')->insert([
            'school_id' => $schoolId,
            'user_id' => $userId,
            'type' => 'booking_created',
            'title' => 'Not 1',
            'message' => 'Message 1',
            'booking_id' => 10,
            'metadata_json' => json_encode(['booking_date' => '2026-04-12']),
            'read_at' => '2026-04-12 10:15:00',
            'created_at' => '2026-04-12 09:30:00',
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/notifications?school_id='.$schoolId, [
            'Authorization' => 'Bearer user-token',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.0.created_at', '2026-04-12T09:30:00-03:00')
            ->assertJsonPath('data.0.read_at', '2026-04-12T10:15:00-03:00');
    }

    public function test_mark_read_uses_application_clock(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 12, 14, 15, 16, 'America/Araguaina'));

        try {
            $schoolId = \DB::table('schools')->insertGetId([
                'school_name' => 'Escola Teste',
                'school_code' => 'ETI001',
                'password' => Hash::make('school-secret'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $userId = \DB::table('users')->insertGetId([
                'school_id' => $schoolId,
                'name' => 'Usuário',
                'email' => 'user@example.com',
                'password' => Hash::make('secret123'),
                'role' => 'user',
                'active' => 1,
                'api_token' => 'user-token',
                'api_token_expires_at' => now()->addHours(24),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $notificationId = \DB::table('notifications')->insertGetId([
                'school_id' => $schoolId,
                'user_id' => $userId,
                'type' => 'booking_created',
                'title' => 'Not 1',
                'message' => 'Message 1',
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $response = $this->postJson('/notifications/read?school_id='.$schoolId, [
                'notification_id' => $notificationId,
            ], [
                'Authorization' => 'Bearer user-token',
            ]);

            $response->assertOk();

            $storedReadAt = \DB::table('notifications')
                ->where('id', $notificationId)
                ->value('read_at');

            $this->assertNotNull($storedReadAt);
            $this->assertTrue(
                Carbon::parse((string) $storedReadAt)->equalTo(now()),
                'Expected read_at to use the Laravel application clock.'
            );
        } finally {
            Carbon::setTestNow();
        }
    }
}
