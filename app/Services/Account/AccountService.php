<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;

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

        if (!Hash::check($payload['current_password'], $user->password)) {
            throw new HttpResponseException(
                ApiResponse::error('A senha atual informada não confere.', 401, 'ACCOUNT_CURRENT_PASSWORD_INVALID')
            );
        }

        if (Hash::check($payload['new_password'], $user->password)) {
            throw new HttpResponseException(
                ApiResponse::error('A nova senha deve ser diferente da atual.', 400, 'ACCOUNT_NEW_PASSWORD_SAME_AS_CURRENT')
            );
        }

        $user->forceFill([
            'password' => Hash::make($payload['new_password']),
        ])->save();
    }
}
