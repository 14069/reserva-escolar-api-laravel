<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemAdmin;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

final class SystemAdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $admin = SystemAdmin::where('email', $request->string('email')->lower()->toString())->first();

        if ($admin === null || ! $admin->active || ! Hash::check($request->string('password')->toString(), $admin->password)) {
            return ApiResponse::error('Credenciais inválidas.', 401, 'LOGIN_INVALID_CREDENTIALS');
        }

        $plainToken = bin2hex(random_bytes(32));
        $expiresAt = Carbon::now()->addHours(12);

        $admin->forceFill([
            'api_token' => hash('sha256', $plainToken),
            'api_token_expires_at' => $expiresAt,
            'last_login_at' => Carbon::now(),
        ])->save();

        return ApiResponse::success([
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'api_token' => $plainToken,
            'api_token_expires_at' => $expiresAt->toDateTimeString(),
        ], 'Login realizado com sucesso.');
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $this->extractBearerToken($request);

        if ($token !== null) {
            $hashedToken = hash('sha256', $token);
            SystemAdmin::where('api_token', $hashedToken)->orWhere('api_token', $token)
                ->update(['api_token' => null, 'api_token_expires_at' => null]);
        }

        return ApiResponse::success(null, 'Logout realizado com sucesso.');
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = trim((string) $request->header('Authorization', ''));
        if ($header === '' || ! preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }
}
