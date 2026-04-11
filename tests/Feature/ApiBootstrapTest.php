<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class ApiBootstrapTest extends TestCase
{
    public function test_root_returns_api_status_payload(): void
    {
        $response = $this->getJson('/');

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Reserva Escolar API V2 online.',
                'data' => [
                    'service' => 'reserva_escolar_api',
                    'status' => 'ok',
                ],
            ]);
    }

    public function test_health_returns_expected_payload(): void
    {
        $response = $this->getJson('/health');

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'healthy',
                'data' => [
                    'status' => 'ok',
                ],
            ]);
    }

    public function test_get_login_returns_method_not_allowed_json(): void
    {
        $response = $this->getJson('/login');

        $response
            ->assertStatus(405)
            ->assertJson([
                'success' => false,
                'message' => 'Método não permitido.',
                'meta' => [
                    'error_code' => 'METHOD_NOT_ALLOWED',
                    'status_code' => 405,
                ],
            ]);
    }

    public function test_register_school_validation_returns_api_json_format(): void
    {
        $response = $this->postJson('/register-school', []);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Dados inválidos.',
                'meta' => [
                    'error_code' => 'VALIDATION_ERROR',
                    'status_code' => 422,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'school_name',
                    'school_code',
                    'school_password',
                    'technician_name',
                    'technician_email',
                    'technician_password',
                    'lesson_count',
                ],
            ]);
    }

    public function test_protected_route_without_token_returns_auth_required(): void
    {
        $response = $this->getJson('/my-bookings?school_id=1&user_id=1');

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Autenticação obrigatória.',
                'meta' => [
                    'error_code' => 'AUTH_REQUIRED',
                    'status_code' => 401,
                ],
            ]);
    }
}
