<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class ApiTokenAuthService
{
    public function authenticate(
        Request $request,
        ?int $schoolId = null,
        ?string $requiredRole = null
    ): User {
        $token = $this->extractBearerToken($request);
        if ($token === null) {
            throw new HttpResponseException(
                ApiResponse::error('Autenticação obrigatória.', 401, 'AUTH_REQUIRED')
            );
        }

        $hashedToken = hash('sha256', $token);
        $user = User::query()
            ->whereIn('api_token', [$hashedToken, $token])
            ->first();

        if ($user === null || (int) $user->active !== 1) {
            throw new HttpResponseException(
                ApiResponse::error('Sessão inválida ou expirada.', 401, 'AUTH_SESSION_INVALID')
            );
        }

        if ($user->api_token === $token) {
            $user->forceFill(['api_token' => $hashedToken])->save();
            $user->api_token = $hashedToken;
        }

        if ($user->api_token_expires_at === null || $user->api_token_expires_at->isPast()) {
            $user->forceFill([
                'api_token' => null,
                'api_token_expires_at' => null,
            ])->save();

            throw new HttpResponseException(
                ApiResponse::error('Sessão inválida ou expirada.', 401, 'AUTH_SESSION_EXPIRED')
            );
        }

        if ($schoolId !== null && (int) $user->school_id !== $schoolId) {
            throw new HttpResponseException(
                ApiResponse::error('Você não tem acesso a esta escola.', 403, 'AUTH_SCHOOL_FORBIDDEN')
            );
        }

        if ($requiredRole !== null && $user->role !== $requiredRole) {
            throw new HttpResponseException(
                ApiResponse::error('Você não tem permissão para esta operação.', 403, 'AUTH_ROLE_FORBIDDEN')
            );
        }

        return $user;
    }

    public function issueTokenFor(User $user): string
    {
        $plainToken = bin2hex(random_bytes(32));

        $user->forceFill([
            'api_token' => hash('sha256', $plainToken),
            'api_token_expires_at' => Carbon::now()->addHours(12),
        ])->save();

        return $plainToken;
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = trim((string) $request->header('Authorization', ''));
        if ($header === '') {
            return null;
        }

        if (! preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }
}
