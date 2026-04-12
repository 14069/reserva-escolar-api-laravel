<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class ApiRouteCompatibilityTest extends TestCase
{
    public function test_index_php_returns_same_api_status_payload(): void
    {
        $response = $this->getJson('/index.php');

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

    public function test_login_php_alias_preserves_validation_json_format(): void
    {
        $response = $this->postJson('/login.php', []);

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
                    'school_code',
                    'email',
                    'password',
                ],
            ]);
    }

    public function test_admin_canonical_teachers_route_is_available(): void
    {
        $response = $this->getJson('/admin/teachers?school_id=1');

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

    public function test_resource_categories_alias_is_available(): void
    {
        $response = $this->getJson('/resources/categories?school_id=1');

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

    public function test_lesson_slots_alias_is_available(): void
    {
        $response = $this->getJson('/lesson-slots?school_id=1');

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
