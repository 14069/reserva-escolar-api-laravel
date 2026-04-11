<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Account\ChangePasswordRequest;
use App\Services\Account\AccountService;
use App\Services\Auth\ApiTokenAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class AccountController
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly ApiTokenAuthService $authService,
    ) {
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId);

        $this->accountService->changePassword($payload, $authUser);

        return ApiResponse::success(null, 'Senha atualizada com sucesso.');
    }
}
