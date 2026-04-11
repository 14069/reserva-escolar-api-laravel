<?php

declare(strict_types=1);

namespace App\Services\Booking;

use Illuminate\Support\Facades\DB;
use Throwable;

final class BookingDatabaseSupport
{
    private array $columnExistsCache = [];

    public function getDriver(): string
    {
        return DB::connection()->getDriverName();
    }

    public function columnExists(string $tableName, string $columnName): bool
    {
        $cacheKey = $this->getDriver() . ':' . $tableName . ':' . $columnName;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        $query = match ($this->getDriver()) {
            'pgsql' => "
                SELECT 1
                FROM information_schema.columns
                WHERE table_schema = 'public'
                  AND table_name = ?
                  AND column_name = ?
                LIMIT 1
            ",
            default => "
                SELECT 1
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND column_name = ?
                LIMIT 1
            ",
        };

        $exists = DB::selectOne($query, [$tableName, $columnName]) !== null;
        $this->columnExistsCache[$cacheKey] = $exists;

        return $exists;
    }

    public function acquireBookingCreationLock(
        int $schoolId,
        int $resourceId,
        string $bookingDate,
        int $timeoutSeconds = 10
    ): string|null|false {
        $lockName = sprintf('booking:%d:%d:%s', $schoolId, $resourceId, $bookingDate);

        if ($this->getDriver() === 'pgsql') {
            $lockKey1 = $schoolId;
            $lockKey2 = $this->normalizeSigned32BitFromString(sprintf('%u', crc32($lockName)));
            $deadlineAt = microtime(true) + max(1, $timeoutSeconds);

            do {
                $row = DB::selectOne('SELECT pg_try_advisory_xact_lock(?, ?) AS locked', [
                    $lockKey1,
                    $lockKey2,
                ]);

                if ((bool) ($row->locked ?? false)) {
                    return null;
                }

                usleep(200000);
            } while (microtime(true) < $deadlineAt);

            return false;
        }

        $row = DB::selectOne('SELECT GET_LOCK(?, ?) AS lock_acquired', [
            $lockName,
            $timeoutSeconds,
        ]);

        return ((int) ($row->lock_acquired ?? 0) === 1) ? $lockName : false;
    }

    public function releaseBookingCreationLock(?string $lockName): void
    {
        if ($lockName === null || $lockName === '' || $this->getDriver() === 'pgsql') {
            return;
        }

        try {
            DB::statement('SELECT RELEASE_LOCK(?)', [$lockName]);
        } catch (Throwable $error) {
            logger()->error('Failed to release booking creation lock.', [
                'message' => $error->getMessage(),
            ]);
        }
    }

    private function normalizeSigned32BitFromString(string $value): int
    {
        $intValue = (int) $value;
        if ($intValue > 2147483647) {
            $intValue -= 4294967296;
        }

        return $intValue;
    }
}
