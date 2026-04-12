<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class AccountService
{
    public function changePassword(array $payload, User $authUser): void
    {
        $schoolId = (int) $payload['school_id'];
        $userId = (int) $payload['user_id'];
        $authUserId = (int) $authUser->id;

        if ($authUserId !== $userId) {
            throw new HttpResponseException(
                ApiResponse::error('Você não tem permissão para alterar esta senha.', 403, 'ACCOUNT_PASSWORD_FORBIDDEN')
            );
        }

        $user = User::query()
            ->where('id', $authUserId)
            ->where('school_id', $schoolId)
            ->where('active', 1)
            ->first(['id', 'password']);

        if ($user === null) {
            throw new HttpResponseException(
                ApiResponse::error('Usuário não encontrado.', 404, 'ACCOUNT_USER_NOT_FOUND')
            );
        }

        if (! $this->passwordMatches((string) $payload['current_password'], (string) $user->password)) {
            throw new HttpResponseException(
                ApiResponse::error('A senha atual informada não confere.', 401, 'ACCOUNT_CURRENT_PASSWORD_INVALID')
            );
        }

        if ($this->passwordMatches((string) $payload['new_password'], (string) $user->password)) {
            throw new HttpResponseException(
                ApiResponse::error('A nova senha deve ser diferente da atual.', 400, 'ACCOUNT_NEW_PASSWORD_SAME_AS_CURRENT')
            );
        }

        $user->forceFill([
            'password' => Hash::make($payload['new_password']),
        ])->save();
    }

    private function passwordMatches(string $plainPassword, string $storedPassword): bool
    {
        try {
            if (Hash::check($plainPassword, $storedPassword)) {
                return true;
            }
        } catch (RuntimeException) {
            // Allow password verification for imported legacy users.
        }

        if (password_verify($plainPassword, $storedPassword)) {
            return true;
        }

        return hash_equals($storedPassword, $plainPassword);
    }
}
