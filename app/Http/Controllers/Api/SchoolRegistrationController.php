<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\School\RegisterSchoolRequest;
use App\Services\School\SchoolRegistrationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SchoolRegistrationController
{
    public function __construct(
        private readonly SchoolRegistrationService $schoolRegistrationService,
    ) {}

    public function store(RegisterSchoolRequest $request): JsonResponse
    {
        try {
            Log::info('School registration request received', [
                'school_code' => $request->input('school_code'),
                'school_name' => $request->input('school_name'),
            ]);

            $validated = $request->validated();

            Log::info('School registration data validated', [
                'school_code' => $validated['school_code'] ?? null,
            ]);

            $result = $this->schoolRegistrationService->register($validated);

            Log::info('School registration successful', ['school_id' => $result['school_id'] ?? null]);

            return ApiResponse::success($result, 'Escola cadastrada com sucesso.', 201);
        } catch (Throwable $exception) {
            Log::error('School registration error', [
                'error' => $exception->getMessage(),
                'error_class' => get_class($exception),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }
}
