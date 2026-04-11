<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\School\RegisterSchoolRequest;
use App\Services\School\SchoolRegistrationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SchoolRegistrationController
{
    public function __construct(
        private readonly SchoolRegistrationService $schoolRegistrationService,
    ) {}

    public function store(RegisterSchoolRequest $request): JsonResponse
    {
        $result = $this->schoolRegistrationService->register($request->validated());

        return ApiResponse::success($result, 'Escola cadastrada com sucesso.', 201);
    }
}
