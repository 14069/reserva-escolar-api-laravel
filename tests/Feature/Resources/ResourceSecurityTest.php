<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ResourceSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::dropIfExists('schools');
    }

    public function test_categories_endpoint_should_require_authentication(): void
    {
        $response = $this->getJson('/resource-categories?school_id=1');

        $response->assertStatus(401);
    }

    public function test_resource_creation_requires_technician_role(): void
    {
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

        Schema::create('resource_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

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

        \DB::table('resource_categories')->insert([
            'id' => 1,
            'name' => 'Audiovisual',
        ]);

        $response = $this->postJson('/resources-admin', [
            'school_id' => $schoolId,
            'user_id' => 1,
            'name' => 'Projetor',
            'category_id' => 1,
        ], [
            'Authorization' => 'Bearer user-token',
        ]);

        $response->assertStatus(403);
    }
}
