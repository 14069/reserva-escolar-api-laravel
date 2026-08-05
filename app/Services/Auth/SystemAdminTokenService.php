<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\SystemAdmin;
use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

final class SystemAdminTokenService
{
    public function authenticate(Request $request): SystemAdmin
    {
        $token = $this->extractBearerToken($request);

        if ($token === null) {
            throw new HttpResponseException(
                ApiResponse::error('Autenticação obrigatória.', 401, 'AUTH_REQUIRED')
            );
        }

        $hashedToken = hash('sha256', $token);
        $admin = SystemAdmin::query()
            ->whereIn('api_token', [$hashedToken, $token])
            ->first();

        if ($admin === null || ! $admin->active) {
            throw new HttpResponseException(
                ApiResponse::error('Sessão inválida ou expirada.', 401, 'AUTH_SESSION_INVALID')
            );
        }

        if ($admin->api_token === $token) {
            $admin->forceFill(['api_token' => $hashedToken])->save();
        }

        if ($admin->api_token_expires_at === null || $admin->api_token_expires_at->isPast()) {
            $admin->forceFill(['api_token' => null, 'api_token_expires_at' => null])->save();

            throw new HttpResponseException(
                ApiResponse::error('Sessão inválida ou expirada.', 401, 'AUTH_SESSION_EXPIRED')
            );
        }

        return $admin;
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
