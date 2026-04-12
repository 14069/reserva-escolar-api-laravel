<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

final class ApiTimestamp
{
    public static function serialize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse((string) $value)->toIso8601String();
    }
}
