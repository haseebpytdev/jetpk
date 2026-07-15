<?php

namespace App\Support\Suppliers;

use App\Models\Booking;
use App\Support\Bookings\SabreAdminManualPnrFallbackReadiness;
use App\Support\Bookings\SupplierBookingAttemptGuard;
use App\Support\FlightSearch\FareSelectionIntegrityValidator;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

/**
 * Duplicate protection and pre-mutation guards for supplier create actions.
 */
final class SupplierMutationGuard
{
    public const REASON_DUPLICATE_PNR = 'duplicate_supplier_booking_guard';

    public const REASON_FARE_MISMATCH = 'branded_fare_context_mismatch';

    public const REASON_NO_ELIGIBLE_STRATEGY = 'supplier_no_eligible_create_strategy';

    public const REASON_LOCK_ACTIVE = 'duplicate_protection_lock_active';

    public function __construct(
        protected SupplierBookingAttemptGuard $attemptGuard,
    ) {}

    /**
     * @return array{allowed: bool, reason_code: string, message: string}
     */
    public function assertCreateAllowed(Booking $booking, string $provider): array
    {
        if (trim((string) ($booking->pnr ?? '')) !== '') {
            return $this->blocked(self::REASON_DUPLICATE_PNR, 'Booking already has a PNR.');
        }
        if (trim((string) ($booking->supplier_reference ?? '')) !== '') {
            return $this->blocked(self::REASON_DUPLICATE_PNR, 'Booking already has a supplier reference.');
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null)
            ? $meta['normalized_offer_snapshot']
            : (is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot'] : []);
        $integrity = (new FareSelectionIntegrityValidator)->validate($meta, $snapshot, $snapshot, []);
        if (($integrity['consistent'] ?? false) !== true) {
            return $this->blocked(self::REASON_FARE_MISMATCH, 'Selected fare context is inconsistent.');
        }

        $retry = $this->attemptGuard->assertRetryAllowed($booking, $provider);
        if (($retry['allowed'] ?? false) !== true) {
            return $this->blocked(
                (string) ($retry['reason_code'] ?? self::REASON_DUPLICATE_PNR),
                (string) ($retry['message'] ?? 'Supplier create retry is not allowed.'),
            );
        }

        return ['allowed' => true, 'reason_code' => 'eligible', 'message' => ''];
    }

    /**
     * @return array{allowed: bool, reason_code: string, message: string, blocking_conditions: list<string>}
     */
    public function assertAdminFallbackCreateAllowed(Booking $booking, string $provider, string $strategyCode): array
    {
        $readiness = app(SabreAdminManualPnrFallbackReadiness::class)
            ->evaluate($booking, $strategyCode);

        if (($readiness['allowed'] ?? false) === true) {
            return [
                'allowed' => true,
                'reason_code' => 'eligible',
                'message' => '',
                'blocking_conditions' => [],
            ];
        }

        $blocking = is_array($readiness['blocking_conditions'] ?? null)
            ? $readiness['blocking_conditions']
            : [];
        $reasonCode = (string) ($readiness['reason_code'] ?? self::REASON_NO_ELIGIBLE_STRATEGY);

        return [
            'allowed' => false,
            'reason_code' => $reasonCode,
            'message' => $this->adminFallbackBlockedMessage($reasonCode, $blocking),
            'blocking_conditions' => $blocking,
        ];
    }

    public function acquireCreateLock(Booking $booking, string $provider, int $seconds = 120): ?Lock
    {
        $lock = Cache::lock('supplier_create_'.$provider.'_'.$booking->id, $seconds);
        if (! $lock->get()) {
            return null;
        }

        return $lock;
    }

    /**
     * @return array{allowed: bool, reason_code: string, message: string}
     */
    protected function blocked(string $reason, string $message): array
    {
        return ['allowed' => false, 'reason_code' => $reason, 'message' => $message];
    }

    /**
     * @param  list<string>  $blockingConditions
     */
    protected function adminFallbackBlockedMessage(string $reasonCode, array $blockingConditions): string
    {
        if ($blockingConditions !== []) {
            return 'Admin manual PNR fallback blocked: '.implode(', ', $blockingConditions).'.';
        }

        return 'Admin manual PNR fallback blocked ('.$reasonCode.').';
    }
}
