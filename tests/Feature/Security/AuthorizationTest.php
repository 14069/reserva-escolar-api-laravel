<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AuthorizationTest extends TestCase
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

    public function test_regular_user_cannot_access_admin_endpoints(): void
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
            'name' => 'Usuário Regular',
            'email' => 'user@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'active' => 1,
            'api_token' => 'user-token',
            'api_token_expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tentar acessar rota admin (admin/teachers)
        $response = $this->getJson('/admin/teachers?school_id=' . $schoolId, [
            'Authorization' => 'Bearer user-token',
        ]);

        // Deve retornar 401/403
        $this->assertTrue(in_array($response->status(), [401, 403]));
    }

    public function test_technician_can_access_admin_endpoints(): void
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
            'email' => 'tech@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'technician',
            'active' => 1,
            'api_token' => 'tech-token',
            'api_token_expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tentar acessar rota admin (admin/teachers) - deve ser permitido
        $response = $this->getJson('/admin/teachers?school_id=' . $schoolId, [
            'Authorization' => 'Bearer tech-token',
        ]);

        // Deve retornar 200 (mesmo que lista vazia)
        $this->assertTrue(in_array($response->status(), [200, 422]));
    }

    public function test_user_from_different_school_cannot_access(): void
    {
        $school1Id = \DB::table('schools')->insertGetId([
            'school_name' => 'Escola 1',
            'school_code' => 'ETI001',
            'password' => Hash::make('school-secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $school2Id = \DB::table('schools')->insertGetId([
            'school_name' => 'Escola 2',
            'school_code' => 'ETI002',
            'password' => Hash::make('school-secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('users')->insert([
            'school_id' => $school1Id,
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

        // Tentar acessar com school_id diferente
        $response = $this->getJson('/notifications?school_id=' . $school2Id . '&page=1', [
            'Authorization' => 'Bearer user-token',
        ]);

        // Pode retornar 401 ou 403
        $this->assertTrue(in_array($response->status(), [401, 403]));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $schoolId = \DB::table('schools')->insertGetId([
            'school_name' => 'Escola Teste',
            'school_code' => 'ETI001',
            'password' => Hash::make('school-secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/notifications?school_id=' . $schoolId . '&page=1', [
            'Authorization' => 'Bearer invalid-token-12345',
        ]);

        $response->assertStatus(401);
    }

    public function test_expired_token_is_rejected(): void
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
            'api_token' => 'expired-token',
            'api_token_expires_at' => now()->subHours(1),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/notifications?school_id=' . $schoolId . '&page=1', [
            'Authorization' => 'Bearer expired-token',
        ]);

        $response->assertStatus(401);
    }
}
