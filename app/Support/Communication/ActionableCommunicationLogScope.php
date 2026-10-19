<?php

namespace App\Support\Communication;

use Illuminate\Database\Eloquent\Builder;

/**
 * Distinguishes actionable queued communication_logs from historical residual noise
 * (OPS-DEF-0021). Does not mutate, resend, or delete rows.
 *
 * Age alone is never sufficient to hide a non-noise queued message.
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
     * Queued, undelivered, non-noise rows remain actionable regardless of age.
     *
     * @param  Builder<\App\Models\CommunicationLog>  $query
     * @return Builder<\App\Models\CommunicationLog>
     */
    public static function actionableQueued(Builder $query): Builder
    {
        return $query
            ->where('status', 'queued')
            ->whereNotIn('event', self::HISTORICAL_NOISE_EVENTS)
            ->whereNull('sent_at');
    }

    /**
     * Historical residual = known login/auth noise still marked queued (or already sent).
     * Does not age-hide legitimate booking/payment/ops messages.
     *
     * @param  Builder<\App\Models\CommunicationLog>  $query
     * @return Builder<\App\Models\CommunicationLog>
     */
    public static function historicalQueuedResidual(Builder $query): Builder
    {
        return $query
            ->where('status', 'queued')
            ->where(function (Builder $inner): void {
                $inner->whereIn('event', self::HISTORICAL_NOISE_EVENTS)
                    ->orWhereNotNull('sent_at');
            });
    }
}
