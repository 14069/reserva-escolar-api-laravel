<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ChangePasswordTest extends TestCase
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

    public function test_change_password_requires_authentication(): void
    {
        $response = $this->postJson('/account/change-password', [
            'school_id' => 1,
            'user_id' => 1,
            'current_password' => 'old123',
            'new_password' => 'new123',
        ]);

        $response->assertStatus(401);
    }

    public function test_change_password_validates_current_password(): void
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
            'password' => Hash::make('correct-password'),
            'role' => 'user',
            'active' => 1,
            'api_token' => 'user-token',
            'api_token_expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/account/change-password', [
            'school_id' => $schoolId,
            'user_id' => 1,
            'current_password' => 'wrong-password',
            'new_password' => 'new-password',
        ], [
            'Authorization' => 'Bearer user-token',
        ]);

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_change_password_successfully_updates(): void
    {
        $schoolId = DB::table('schools')->insertGetId([
            'school_name' => 'Escola Teste',
            'school_code' => 'ETI001',
            'password' => Hash::make('school-secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $oldPassword = 'old-password-123';
        $newPassword = 'new-password-456';

        DB::table('users')->insert([
            'school_id' => $schoolId,
            'name' => 'Usuário',
            'email' => 'user@example.com',
            'password' => Hash::make($oldPassword),
            'role' => 'user',
            'active' => 1,
            'api_token' => 'user-token',
            'api_token_expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = (int) DB::table('users')->where('email', 'user@example.com')->value('id');

        $response = $this->postJson('/account/change-password', [
            'school_id' => $schoolId,
            'user_id' => $userId,
            'current_password' => $oldPassword,
            'new_password' => $newPassword,
        ], [
            'Authorization' => 'Bearer user-token',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Senha atualizada com sucesso.',
            ]);

        // Verificar que a senha foi atualizada
        $user = DB::table('users')->where('email', 'user@example.com')->first();
        $this->assertTrue(Hash::check($newPassword, $user->password));
        $this->assertFalse(Hash::check($oldPassword, $user->password));
    }
}
