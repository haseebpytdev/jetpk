<?php

namespace App\Support\Bookings;

use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Active supplier_booking_attempt and cache-lock guards for create_pnr (IATI + shared preflight).
 */
final class SupplierBookingAttemptGuard
{
    /** @var list<string> */
    public const ACTIVE_STATUSES = ['pending', 'processing', 'in_progress'];

    private static ?int $inFlightAttemptId = null;

    public function timeoutMinutes(): int
    {
        return max(1, (int) config('ota.supplier_booking_attempt_timeout_minutes', 10));
    }

    public function lockTtlSeconds(): int
    {
        return max(60, $this->timeoutMinutes() * 60);
    }

    public function lockKey(Booking $booking, string $provider, string $action = 'create_pnr'): string
    {
        $provider = strtolower(trim($provider)) ?: 'unknown';

        return sprintf('ota:supplier-booking:%d:%s:%s', $booking->id, $provider, $action);
    }

    public function setInFlightAttemptId(?int $attemptId): void
    {
        self::$inFlightAttemptId = $attemptId;
    }

    public function inFlightAttemptId(): ?int
    {
        return self::$inFlightAttemptId;
    }

    public static function resetInFlightAttemptId(): void
    {
        self::$inFlightAttemptId = null;
    }

    public function acquireLock(Booking $booking, string $provider, string $action = 'create_pnr'): ?Lock
    {
        $lock = Cache::lock($this->lockKey($booking, $provider, $action), $this->lockTtlSeconds());

        return $lock->get() ? $lock : null;
    }

    public function isLockActive(Booking $booking, string $provider, string $action = 'create_pnr'): bool
    {
        $lock = Cache::lock($this->lockKey($booking, $provider, $action), 1);
        if ($lock->get()) {
            $lock->release();

            return false;
        }

        return true;
    }

    public function releaseStaleAttempts(Booking $booking, ?string $provider = null, string $action = 'create_pnr'): int
    {
        $released = 0;
        $cutoff = now()->subMinutes($this->timeoutMinutes());

        $query = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', $action)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereNull('completed_at');

        if ($provider !== null && trim($provider) !== '') {
            $query->where('provider', strtolower(trim($provider)));
        }

        foreach ($query->get() as $attempt) {
            $anchor = $attempt->attempted_at ?? $attempt->created_at;
            if ($anchor !== null && $anchor->greaterThan($cutoff)) {
                continue;
            }

            $attempt->forceFill([
                'status' => 'failed',
                'error_code' => 'supplier_booking_stale_attempt',
                'error_message' => 'Stale supplier booking attempt released.',
                'completed_at' => now(),
                'safe_summary' => array_merge(
                    is_array($attempt->safe_summary) ? $attempt->safe_summary : [],
                    [
                        'reason_code' => 'supplier_booking_stale_attempt_released',
                        'released_at' => now()->toIso8601String(),
                    ],
                ),
            ])->save();

            Log::warning('supplier_booking_stale_attempt_released', [
                'booking_id' => $booking->id,
                'attempt_id' => $attempt->id,
                'provider' => $attempt->provider,
                'action' => $action,
                'attempt_age_seconds' => $anchor !== null ? $anchor->diffInSeconds(now()) : null,
            ]);

            $released++;
        }

        return $released;
    }

    public function resolveActiveAttempt(
        Booking $booking,
        ?string $provider = null,
        string $action = 'create_pnr',
        ?int $excludeAttemptId = null,
    ): ?SupplierBookingAttempt {
        $this->releaseStaleAttempts($booking, $provider, $action);

        $excludeAttemptId ??= self::$inFlightAttemptId;

        $query = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', $action)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereNull('completed_at')
            ->orderByDesc('id');

        if ($provider !== null && trim($provider) !== '') {
            $query->where('provider', strtolower(trim($provider)));
        }

        if ($excludeAttemptId !== null) {
            $query->where('id', '!=', $excludeAttemptId);
        }

        $cutoff = now()->subMinutes($this->timeoutMinutes());

        foreach ($query->get() as $attempt) {
            $anchor = $attempt->attempted_at ?? $attempt->created_at;
            if ($anchor === null || $anchor->greaterThan($cutoff)) {
                return $attempt;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     blocked: bool,
     *     reason_code: string|null,
     *     error_message: string|null,
     *     active_attempt_id: int|null,
     *     active_attempt_status: string|null,
     *     active_attempt_completed_at: string|null,
     *     active_attempt_age_seconds: int|null,
     *     lock_key: string,
     *     lock_active: bool,
     *     lock_ttl_seconds: int
     * }
     */
    public function assertRetryAllowed(
        Booking $booking,
        string $provider,
        string $action = 'create_pnr',
        ?int $excludeAttemptId = null,
    ): array {
        $provider = strtolower(trim($provider)) ?: 'unknown';
        $lockKey = $this->lockKey($booking, $provider, $action);
        $active = $this->resolveActiveAttempt($booking, $provider, $action, $excludeAttemptId);
        $lockActive = $this->isLockActive($booking, $provider, $action);

        $diagnostics = [
            'blocked' => false,
            'reason_code' => null,
            'error_message' => null,
            'active_attempt_id' => $active?->id,
            'active_attempt_status' => $active !== null ? (string) $active->status : null,
            'active_attempt_completed_at' => $active?->completed_at?->toIso8601String(),
            'active_attempt_age_seconds' => $this->attemptAgeSeconds($active),
            'lock_key' => $lockKey,
            'lock_active' => $lockActive,
            'lock_ttl_seconds' => $this->lockTtlSeconds(),
        ];

        if ($active !== null) {
            $diagnostics['blocked'] = true;
            $diagnostics['reason_code'] = 'supplier_booking_in_progress';
            $diagnostics['error_message'] = 'Supplier booking already in progress.';

            return $diagnostics;
        }

        if ($lockActive && $excludeAttemptId === null && self::$inFlightAttemptId === null) {
            $diagnostics['blocked'] = true;
            $diagnostics['reason_code'] = 'supplier_booking_lock_active';
            $diagnostics['error_message'] = 'Supplier booking already in progress.';

            return $diagnostics;
        }

        return $diagnostics;
    }

    /**
     * @return array{
     *     active_supplier_booking_attempt_id: int|null,
     *     active_supplier_booking_attempt_status: string|null,
     *     active_supplier_booking_attempt_age_seconds: int|null,
     *     supplier_booking_lock_active: bool,
     *     supplier_booking_lock_key: string,
     *     last_supplier_attempt_status: string|null,
     *     last_supplier_attempt_error_code: string|null
     * }
     */
    public function readinessDiagnostics(Booking $booking, string $provider, string $action = 'create_pnr'): array
    {
        $provider = strtolower(trim($provider)) ?: 'unknown';
        $active = $this->resolveActiveAttempt($booking, $provider, $action);
        $latest = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', $action)
            ->when($provider !== '', fn ($q) => $q->where('provider', $provider))
            ->orderByDesc('id')
            ->first();

        return [
            'active_supplier_booking_attempt_id' => $active?->id,
            'active_supplier_booking_attempt_status' => $active !== null ? (string) $active->status : null,
            'active_supplier_booking_attempt_age_seconds' => $this->attemptAgeSeconds($active),
            'supplier_booking_lock_active' => $this->isLockActive($booking, $provider, $action),
            'supplier_booking_lock_key' => $this->lockKey($booking, $provider, $action),
            'last_supplier_attempt_status' => $latest !== null ? (string) $latest->status : null,
            'last_supplier_attempt_error_code' => $latest !== null ? (string) ($latest->error_code ?? '') : null,
        ];
    }

    public function blockedSafeSummary(array $diagnostics): array
    {
        return array_filter([
            'reason_code' => $diagnostics['reason_code'] ?? null,
            'active_attempt_id' => $diagnostics['active_attempt_id'] ?? null,
            'active_attempt_status' => $diagnostics['active_attempt_status'] ?? null,
            'active_attempt_completed_at' => $diagnostics['active_attempt_completed_at'] ?? null,
            'active_attempt_age_seconds' => $diagnostics['active_attempt_age_seconds'] ?? null,
            'lock_key' => $diagnostics['lock_key'] ?? null,
            'lock_active' => $diagnostics['lock_active'] ?? null,
            'lock_ttl' => $diagnostics['lock_ttl_seconds'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    protected function attemptAgeSeconds(?SupplierBookingAttempt $attempt): ?int
    {
        if ($attempt === null) {
            return null;
        }

        $anchor = $attempt->attempted_at ?? $attempt->created_at;
        if ($anchor === null) {
            return null;
        }

        return (int) Carbon::parse($anchor)->diffInSeconds(now());
    }
}
