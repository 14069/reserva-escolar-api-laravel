<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\ApiTokenAuthService;
use App\Services\Auth\LoginService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class AuthController
{
    public function __construct(
        private readonly LoginService $loginService,
        private readonly ApiTokenAuthService $authService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $payload = $this->loginService->login($request->validated());

        return ApiResponse::success(
            $payload,
            'Login realizado com sucesso.'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $this->authService->authenticate($request);

        try {
            $user->update([
                'api_token' => null,
                'api_token_expires_at' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Logout error - failed to clear token', ['error' => $e->getMessage()]);

            return ApiResponse::error('Falha ao fazer logout', 500);
        }

        return ApiResponse::success(null, 'Logout realizado com sucesso.');
    }
}
