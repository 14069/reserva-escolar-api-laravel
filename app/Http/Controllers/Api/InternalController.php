<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\Internal\DiagnosticService;
use App\Services\Internal\InternalAccessService;
use App\Services\Notification\BookingReminderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InternalController
{
    public function __construct(
        private readonly InternalAccessService $internalAccessService,
        private readonly DiagnosticService $diagnosticService,
        private readonly BookingReminderService $bookingReminderService,
    ) {
    }

    public function checkDatabaseConnection(Request $request): JsonResponse
    {
        $this->internalAccessService->requireDiagnosticAccess($request);
        \Log::info('Internal: checking database connection');

        try {
            $result = $this->diagnosticService->checkDatabaseConnection();
            \Log::info('Internal: database connection check completed', ['result' => $result]);
        } catch (\Exception $e) {
            \Log::error('Internal: database connection check failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Falha ao verificar conexão com banco', 500);
        }

        return ApiResponse::success($result, 'Conexão com o banco verificada com sucesso.');
    }

    public function sendBookingCompletionReminders(Request $request): JsonResponse
    {
        $this->internalAccessService->requireCronAccess($request);
        \Log::info('Internal: starting booking completion reminders');

        try {
            $result = $this->bookingReminderService->sendCompletionReminders();
            \Log::info('Internal: booking completion reminders completed', ['result' => $result]);
        } catch (\Exception $e) {
            \Log::error('Internal: booking completion reminders failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Falha ao processar lembretes', 500);
        }

        return ApiResponse::success($result, 'Lembretes processados com sucesso.');
    }
}
