<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;

/**
 * E5J: Config-gated soft-block for known failed / host-NOOP Sabre pre-checkout evidence.
 *
 * No live Sabre calls, PNR creation, or ticketing. Customer redirect uses a safe message only.
 */
final class SabrePreCheckoutKnownFailureSoftBlock
{
    public const CUSTOMER_REDIRECT_MESSAGE = 'This fare may no longer be available from the airline. Please select another available option.';

    public static function configEnabled(): bool
    {
        return (bool) config('suppliers.sabre.precheckout_known_failure_soft_block_enabled', false);
    }

    public static function customerRedirectMessage(): string
    {
        return self::CUSTOMER_REDIRECT_MESSAGE;
    }

    /**
     * @param  array<string, mixed>  $dryRun
     */
    public static function isEligibleDryRun(array $dryRun): bool
    {
        $recommendedAction = (string) ($dryRun['recommended_checkout_action'] ?? '');
        if ($recommendedAction !== SabrePreCheckoutSellabilityDryRun::ACTION_BLOCKED_SAME_OFFER) {
            return false;
        }

        $dryRunStatus = (string) ($dryRun['dry_run_status'] ?? '');

        return in_array($dryRunStatus, [
            SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_FAILED,
            SabreCertifiedRouteSelector::EVIDENCE_STATUS_HOST_NOOP_BLOCKED,
        ], true);
    }

    /**
     * @param  array<string, mixed>  $dryRun
     */
    public static function wouldSoftBlock(array $dryRun): bool
    {
        return self::configEnabled() && self::isEligibleDryRun($dryRun);
    }

    public static function wouldSoftBlockFromMeta(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        if ($provider !== SupplierProvider::Sabre->value) {
            return false;
        }

        $dryRunSnapshot = is_array($meta['pre_checkout_sellability_dry_run'] ?? null)
            ? $meta['pre_checkout_sellability_dry_run']
            : null;

        if ($dryRunSnapshot !== null && ($dryRunSnapshot['status'] ?? '') !== '') {
            return self::wouldSoftBlock([
                'dry_run_status' => (string) ($dryRunSnapshot['status'] ?? ''),
                'recommended_checkout_action' => (string) ($dryRunSnapshot['recommended_checkout_action'] ?? ''),
            ]);
        }

        $dryRun = app(SabrePreCheckoutSellabilityDryRun::class)->evaluate($booking);

        return self::wouldSoftBlock($dryRun);
    }

    /**
     * @param  array<string, mixed>  $dryRun
     */
    public static function softBlockReason(array $dryRun): ?string
    {
        if (! self::isEligibleDryRun($dryRun)) {
            return null;
        }

        return (string) ($dryRun['dry_run_status'] ?? '');
    }
}
