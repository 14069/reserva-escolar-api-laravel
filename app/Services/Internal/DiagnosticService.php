<?php

declare(strict_types=1);

namespace App\Services\Internal;

use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DiagnosticService
{
    public function checkDatabaseConnection(): array
    {
        try {
            $result = DB::selectOne('SELECT 1 AS connection_ok');

            return [
                'status' => ((int) ($result->connection_ok ?? 0)) === 1 ? 'ok' : 'unknown',
                'driver' => DB::connection()->getDriverName(),
            ];
        } catch (Throwable $exception) {
            throw new HttpResponseException(
                ApiResponse::error('Falha ao verificar conexão com o banco de dados.', 500, 'INTERNAL_SERVER_ERROR')
            );
        }
    }
}
