<?php

namespace Tests\Feature;

use Tests\TestCase;

class SmokeTestApiTest extends TestCase
{
    /**
     * Test health endpoint.
     */
    public function test_health_endpoint(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200);
    }

    /**
     * Test login endpoint with valid credentials.
     */
    public function test_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/login', [
            'email' => 'tecnico.ci@example.com',
            'password' => 'teste123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token']);
    }

    /**
     * Test login endpoint with invalid credentials.
     */
    public function test_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/login', [
            'email' => 'tecnico.ci@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test that authenticated user can access bookings.
     */
    public function test_authenticated_user_can_access_bookings(): void
    {
        $response = $this->postJson('/login', [
            'email' => 'tecnico.ci@example.com',
            'password' => 'teste123',
        ]);

        $token = $response->json('token');

        $response = $this->getJson('/bookings', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200);
    }
}
