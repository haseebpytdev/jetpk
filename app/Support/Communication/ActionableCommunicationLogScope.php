<?php

namespace App\Support\Communication;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Distinguishes actionable queued communication_logs from historical residual noise
 * (OPS-DEF-0021). Does not mutate, resend, or delete rows.
 */
final class ActionableCommunicationLogScope
{
    /**
     * Auth/login noise that was left queued with no delivery job — not actionable.
     *
     * @var list<string>
     */
    public const HISTORICAL_NOISE_EVENTS = [
        'admin_login_success',
        'staff_login_success',
        'agent_login_success',
        'customer_login_success',
        'auth_new_device_login',
        'login_success',
    ];

    /**
     * @param  Builder<\App\Models\CommunicationLog>  $query
     * @return Builder<\App\Models\CommunicationLog>
     */
    public static function actionableQueued(Builder $query, ?Carbon $notOlderThan = null): Builder
    {
        $cutoff = $notOlderThan ?? now()->subDays(7);

        return $query
            ->where('status', 'queued')
            ->whereNotIn('event', self::HISTORICAL_NOISE_EVENTS)
            ->where('created_at', '>=', $cutoff)
            ->whereNull('sent_at');
    }

    /**
     * @param  Builder<\App\Models\CommunicationLog>  $query
     * @return Builder<\App\Models\CommunicationLog>
     */
    public static function historicalQueuedResidual(Builder $query): Builder
    {
        return $query
            ->where('status', 'queued')
            ->where(function (Builder $inner): void {
                $inner->whereIn('event', self::HISTORICAL_NOISE_EVENTS)
                    ->orWhere('created_at', '<', now()->subDays(7))
                    ->orWhereNotNull('sent_at');
            });
    }
}
