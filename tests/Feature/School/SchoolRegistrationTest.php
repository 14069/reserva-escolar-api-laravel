<?php

declare(strict_types=1);

namespace Tests\Feature\School;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SchoolRegistrationTest extends TestCase
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
        Schema::dropIfExists('resource_categories');
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

        Schema::create('resource_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
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
            $table->unsignedInteger('lesson_number');
            $table->string('label');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->smallInteger('active')->default(1);
            $table->timestamps();
        });

        DB::table('resource_categories')->insert([
            ['name' => 'audiovisual', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'chromebooks', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'espacos', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_register_school_creates_school_user_resources_and_lessons(): void
    {
        Log::spy();

        $response = $this->postJson('/register-school', [
            'school_name' => 'Escola Nova',
            'school_code' => 'ESC001',
            'school_password' => 'segredo123',
            'technician_name' => 'Tecnico Responsavel',
            'technician_email' => 'tecnico@escola.test',
            'technician_password' => 'senha123',
            'chromebooks_count' => 2,
            'audiovisual_count' => 1,
            'spaces_count' => 1,
            'class_groups' => ['1A', '1B'],
            'subjects' => ['Matematica', 'Historia'],
            'lesson_count' => 5,
        ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Escola cadastrada com sucesso.',
                'data' => [
                    'school_name' => 'Escola Nova',
                    'school_code' => 'ESC001',
                ],
            ]);

        $schoolId = $response->json('data.school_id');

        $this->assertDatabaseHas('schools', [
            'id' => $schoolId,
            'school_name' => 'Escola Nova',
            'school_code' => 'ESC001',
        ]);

        $this->assertDatabaseHas('users', [
            'school_id' => $schoolId,
            'email' => 'tecnico@escola.test',
            'role' => 'technician',
        ]);

        $this->assertSame(4, DB::table('resources')->where('school_id', $schoolId)->count());
        $this->assertSame(2, DB::table('class_groups')->where('school_id', $schoolId)->count());
        $this->assertSame(2, DB::table('subjects')->where('school_id', $schoolId)->count());
        $this->assertSame(5, DB::table('lesson_slots')->where('school_id', $schoolId)->count());

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'School registration successful'
                    && isset($context['school_id'])
                    && ! isset($context['school_code']);
            });
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
    }

    public function test_register_school_logs_sanitized_context_when_registration_fails(): void
    {
        Log::spy();

        DB::table('schools')->insert([
            'school_name' => 'Escola Existente',
            'school_code' => 'ESC001',
            'password' => Hash::make('segredo123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/register-school', [
            'school_name' => 'Escola Nova',
            'school_code' => 'ESC001',
            'school_password' => 'segredo123',
            'technician_name' => 'Tecnico Responsavel',
            'technician_email' => 'tecnico@escola.test',
            'technician_password' => 'senha123',
            'chromebooks_count' => 2,
            'audiovisual_count' => 1,
            'spaces_count' => 1,
            'class_groups' => ['1A', '1B'],
            'subjects' => ['Matematica', 'Historia'],
            'lesson_count' => 5,
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('meta.error_code', 'SCHOOL_CODE_CONFLICT');

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'School registration failed'
                    && ($context['status_code'] ?? null) === 409
                    && ($context['error_class'] ?? null) === HttpResponseException::class
                    && isset($context['school_code_fingerprint'])
                    && ! array_key_exists('trace', $context)
                    && ! array_key_exists('error', $context);
            });
    }
}
