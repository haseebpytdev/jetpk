<?php

namespace App\Services\Notifications;

use Illuminate\Database\QueryException;

final class NotificationUniqueConstraint
{
    public static function causedBy(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        $message = $e->getMessage();

        if ($sqlState === '23000' || $sqlState === '23505') {
            return true;
        }

        if ($driverCode === 1062 || $driverCode === 19) {
            return true;
        }

        return str_contains($message, 'UNIQUE constraint failed')
            || str_contains($message, 'Duplicate entry')
            || str_contains($message, 'unique constraint');
    }
}
