<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LogoutApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function test_logout_clears_api_token(): void
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
            'name' => 'Técnico',
            'email' => 'tecnico@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'technician',
            'active' => 1,
            'api_token' => 'valid-token-123',
            'api_token_expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/logout', [], [
            'Authorization' => 'Bearer valid-token-123',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Logout realizado com sucesso.',
            ]);

        $user = \DB::table('users')->where('id', $userId)->first();
        $this->assertNull($user->api_token);
        $this->assertNull($user->api_token_expires_at);
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/logout', []);

        $response->assertStatus(401);
    }
}
