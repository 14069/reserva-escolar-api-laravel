<?php

declare(strict_types=1);

namespace App\Services\Internal;

use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

final class InternalAccessService
{
    public function requireDiagnosticAccess(Request $request): void
    {
        $configuredToken = trim((string) env('RESERVA_DIAGNOSTIC_TOKEN', ''));
        $providedToken = trim((string) $request->header('X-Reserva-Diagnostic-Token', ''));

        if ($configuredToken !== '') {
            if ($providedToken === '' || ! hash_equals($configuredToken, $providedToken)) {
                throw new HttpResponseException(
                    ApiResponse::error('Acesso não autorizado.', 401, 'DIAGNOSTIC_ACCESS_DENIED')
                );
            }

            return;
        }

        if (strtolower((string) env('APP_ENV', 'production')) === 'production') {
            throw new HttpResponseException(
                ApiResponse::error('Recurso não disponível.', 404, 'DIAGNOSTIC_UNAVAILABLE')
            );
        }

        if (! $this->isLocalRequest($request)) {
            throw new HttpResponseException(
                ApiResponse::error('Acesso não autorizado.', 401, 'DIAGNOSTIC_ACCESS_DENIED')
            );
        }
    }

    public function requireCronAccess(Request $request): void
    {
        $configuredToken = trim((string) env('RESERVA_CRON_TOKEN', ''));
        $providedToken = trim((string) $request->header('X-Reserva-Cron-Token', ''));

        if ($configuredToken !== '') {
            if ($providedToken === '' || ! hash_equals($configuredToken, $providedToken)) {
                throw new HttpResponseException(
                    ApiResponse::error('Acesso não autorizado ao job.', 401, 'CRON_ACCESS_DENIED')
                );
            }

            return;
        }

        if (! $this->isLocalRequest($request)) {
            throw new HttpResponseException(
                ApiResponse::error('Acesso não autorizado ao job.', 401, 'CRON_ACCESS_DENIED')
            );
        }
    }

    private function isLocalRequest(Request $request): bool
    {
        $remoteAddress = trim((string) $request->ip());

        return in_array($remoteAddress, ['127.0.0.1', '::1', ''], true);
    }
}
