<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;

final class LoginService
{
    public function __construct(
        private readonly ApiTokenAuthService $tokenAuthService,
    ) {}

    public function login(array $credentials): array
    {
        $user = User::query()
            ->select([
                'users.id',
                'users.school_id',
                'users.name',
                'users.email',
                'users.password',
                'users.role',
                'users.active',
                'schools.school_name',
                'schools.school_code',
            ])
            ->join('schools', 'schools.id', '=', 'users.school_id')
            ->where('schools.school_code', $credentials['school_code'])
            ->where('users.email', $credentials['email'])
            ->where('users.active', 1)
            ->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            throw new HttpResponseException(
                ApiResponse::error('Credenciais inválidas.', 401, 'LOGIN_INVALID_CREDENTIALS')
            );
        }

        $plainToken = $this->tokenAuthService->issueTokenFor($user);

        return [
            'id' => (int) $user->id,
            'school_id' => (int) $user->school_id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'school_name' => $user->school_name,
            'school_code' => $user->school_code,
            'api_token' => $plainToken,
            'api_token_expires_at' => optional($user->fresh())->api_token_expires_at?->format('Y-m-d H:i:s'),
        ];
    }
}
