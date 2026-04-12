<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\School\RegisterSchoolRequest;
use App\Services\School\SchoolRegistrationService;
use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
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
        $validated = $request->validated();

        try {
            $result = $this->schoolRegistrationService->register($validated);

            Log::info('School registration successful', ['school_id' => $result['school_id'] ?? null]);

            return ApiResponse::success($result, 'Escola cadastrada com sucesso.', 201);
        } catch (Throwable $exception) {
            $this->logFailure((string) ($validated['school_code'] ?? ''), $exception);

            throw $exception;
        }
    }

    private function logFailure(string $schoolCode, Throwable $exception): void
    {
        $context = array_filter([
            'school_code_fingerprint' => $this->fingerprintSchoolCode($schoolCode),
            'error_class' => $exception::class,
            'status_code' => $exception instanceof HttpResponseException
                ? $exception->getResponse()?->getStatusCode()
                : 500,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        if ($exception instanceof HttpResponseException) {
            Log::warning('School registration failed', $context);

            return;
        }

        Log::error('School registration failed', [
            ...$context,
            'error' => $exception->getMessage(),
        ]);
    }

    private function fingerprintSchoolCode(string $schoolCode): ?string
    {
        $normalized = strtolower(trim($schoolCode));
        if ($normalized === '') {
            return null;
        }

        return substr(hash('sha256', $normalized), 0, 12);
    }
}
