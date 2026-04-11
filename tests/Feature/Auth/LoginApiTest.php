<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LoginApiTest extends TestCase
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

    public function test_login_returns_expected_payload_when_service_succeeds(): void
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
            'name' => 'Técnico',
            'email' => 'tecnico@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'technician',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/login', [
            'school_code' => 'ETI001',
            'email' => 'tecnico@example.com',
            'password' => 'secret123',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Login realizado com sucesso.',
                'data' => [
                    'school_id' => (int) $schoolId,
                    'role' => 'technician',
                ],
            ])
            ->assertJsonPath('data.name', 'Técnico')
            ->assertJsonPath('data.email', 'tecnico@example.com');

        $this->assertNotEmpty($response->json('data.api_token'));
        $this->assertNotEmpty($response->json('data.api_token_expires_at'));
    }

    public function test_login_validation_uses_api_error_shape(): void
    {
        $response = $this->postJson('/login', [
            'school_code' => '',
            'email' => 'email-invalido',
            'password' => '',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Dados inválidos.',
                'meta' => [
                    'error_code' => 'VALIDATION_ERROR',
                ],
            ]);
    }
}
