<?php

namespace App\Support\Dashboard;

use Illuminate\Database\Eloquent\Builder;

/**
 * Distinguishes unresolved operational notification failures from retained QA/test history.
 */
final class CommunicationFailureClassifier
{
    public static function constrain(Builder $query, bool $operationalOnly, bool $qaOnly): void
    {
        if ($operationalOnly === $qaOnly) {
            return;
        }

        $applyQa = static function (Builder $inner): void {
            $inner->where('event', 'settings_test_email')
                ->orWhere('event', 'like', '%test%')
                ->orWhere('event', 'like', '%demo%')
                ->orWhere('recipient_email', 'like', 'jp-dash-%')
                ->orWhere('recipient_email', 'like', '%qa%')
                ->orWhere('recipient_email', 'like', '%+test@%');
        };

        if ($qaOnly) {
            $query->where($applyQa);

            return;
        }

        $query->where(function (Builder $inner) use ($applyQa): void {
            $inner->whereNot(function (Builder $qa) use ($applyQa): void {
                $applyQa($qa);
            });
        });
    }
}
