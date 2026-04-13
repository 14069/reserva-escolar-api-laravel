<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\Internal\DiagnosticService;
use App\Services\Internal\InternalAccessService;
use App\Services\Notification\BookingReminderService;
use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class InternalController
{
    public function __construct(
        private readonly InternalAccessService $internalAccessService,
        private readonly DiagnosticService $diagnosticService,
        private readonly BookingReminderService $bookingReminderService,
    ) {}

    public function checkDatabaseConnection(Request $request): JsonResponse
    {
        $this->internalAccessService->requireDiagnosticAccess($request);
        $startedAt = microtime(true);
        Log::info('Internal database check started');

        try {
            $result = $this->diagnosticService->checkDatabaseConnection();
            Log::info('Internal database check completed', [
                'driver' => $result['driver'] ?? null,
                'status' => $result['status'] ?? null,
                'duration_ms' => $this->durationMs($startedAt),
            ]);
        } catch (Throwable $exception) {
            $this->logInternalFailure('Internal database check failed', $startedAt, $exception);

            return ApiResponse::error('Falha ao verificar conexão com banco', 500);
        }

        return ApiResponse::success($result, 'Conexão com o banco verificada com sucesso.');
    }

    public function sendBookingCompletionReminders(Request $request): JsonResponse
    {
        $this->internalAccessService->requireCronAccess($request);
        $startedAt = microtime(true);
        Log::info('Internal booking reminders started');

        try {
            $result = $this->bookingReminderService->sendCompletionReminders();
            Log::info('Internal booking reminders completed', [
                'evaluated_count' => $result['evaluated_count'] ?? 0,
                'created_count' => $result['created_count'] ?? 0,
                'booking_count' => count($result['booking_ids'] ?? []),
                'duration_ms' => $this->durationMs($startedAt),
            ]);
        } catch (Throwable $exception) {
            $this->logInternalFailure('Internal booking reminders failed', $startedAt, $exception);

            return ApiResponse::error('Falha ao processar lembretes', 500);
        }

        return ApiResponse::success($result, 'Lembretes processados com sucesso.');
    }

    private function logInternalFailure(string $message, float $startedAt, Throwable $exception): void
    {
        $context = array_filter([
            'error_class' => $exception::class,
            'status_code' => $exception instanceof HttpResponseException
                ? $exception->getResponse()?->getStatusCode()
                : 500,
            'duration_ms' => $this->durationMs($startedAt),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        if ($exception instanceof HttpResponseException) {
            Log::warning($message, $context);

            return;
        }

        Log::error($message, [
            ...$context,
            'error' => $exception->getMessage(),
        ]);
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
