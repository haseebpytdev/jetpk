<?php

namespace App\Support\Sabre\Scenario;

use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Enums\SupplierProvider;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancelReadiness;
use Illuminate\Support\Facades\Storage;

/**
 * Validates prior QR unticketed cancellation lifecycle evidence before post-cancel retrieve (no raw locator).
 */
final class SabreGdsQrUnticketedPostCancelPriorCancellationGate
{
    public const PRODUCTION_PRIOR_CANCELLATION_LIFECYCLE_RUN_ID = '5f265d7f-834f-4f4b-8376-4df358a4e9d7';

    /** Documented production evidence hash (must match Phase 14 artifact {@code locator_sha256} when present). */
    public const PRODUCTION_LOCATOR_SHA256_DOCUMENTED = '91d49acf4c96f5b5aac022e60943a837f8340d60e2a2a800381ffeee9192dd5b';

    /**
     * @return array<string, mixed>
     */
    public function evaluate(
        Booking $booking,
        string $priorLifecycleRunId,
        ?string $expectedLocatorSha256,
        bool $productionSend,
    ): array {
        $blockers = [];
        $artifact = $this->loadCancelArtifact($priorLifecycleRunId);
        $artifactConfirmed = $this->artifactIndicatesConfirmedCancellation($artifact, $expectedLocatorSha256);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $cancelMeta = is_array($meta[SabreGdsCancelReadiness::META_KEY] ?? null)
            ? $meta[SabreGdsCancelReadiness::META_KEY]
            : [];
        $metaConfirmed = ($cancelMeta['supplier_cancel_verified'] ?? false) === true
            && trim((string) ($cancelMeta['classification'] ?? '')) !== '';

        $successfulCancelAttempts = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->whereIn('action', ['cancel_pnr', 'cancel_booking'])
            ->where('status', 'success')
            ->count();

        $priorConfirmed = $artifactConfirmed || $metaConfirmed || $successfulCancelAttempts >= 1;
        $priorAmbiguous = $this->artifactIndicatesAmbiguous($artifact)
            || $this->hasAmbiguousCancelAttempt($booking);

        if ($priorLifecycleRunId === '') {
            $blockers[] = 'prior_cancellation_lifecycle_run_id_required';
        }
        if ($productionSend && $priorLifecycleRunId !== self::PRODUCTION_PRIOR_CANCELLATION_LIFECYCLE_RUN_ID) {
            $blockers[] = 'production_prior_cancellation_lifecycle_run_id_mismatch';
        }
        if ($artifact !== null && $expectedLocatorSha256 !== null && $expectedLocatorSha256 !== '') {
            $artifactHash = (string) ($artifact['locator_sha256'] ?? '');
            if ($artifactHash !== '' && ! hash_equals($artifactHash, $expectedLocatorSha256)) {
                $blockers[] = 'prior_cancellation_locator_sha256_mismatch';
            }
        }
        if (! $priorConfirmed) {
            $blockers[] = 'prior_cancellation_not_confirmed';
        }
        if ($priorAmbiguous) {
            $blockers[] = 'prior_cancellation_ambiguous';
        }
        if ($artifact !== null) {
            if ((int) ($artifact['supplier_cancellation_call_count'] ?? 0) !== 1) {
                $blockers[] = 'prior_supplier_cancellation_call_count_not_one';
            }
            if (($artifact['manual_reconciliation_required'] ?? false) === true) {
                $blockers[] = 'prior_manual_reconciliation_required';
            }
            if (($artifact['cancellation_outcome_state'] ?? '') !== 'cancellation_confirmed') {
                $blockers[] = 'prior_cancellation_outcome_not_confirmed';
            }
        }

        $postCancelMeta = is_array($meta['qr_unticketed_post_cancel_retrieve'] ?? null)
            ? $meta['qr_unticketed_post_cancel_retrieve']
            : [];
        if (($postCancelMeta['cancellation_closure_verified'] ?? false) === true) {
            $blockers[] = 'phase_15_closure_already_verified';
        }

        return [
            'prior_cancellation_lifecycle_run_id' => $priorLifecycleRunId,
            'prior_cancellation_confirmed' => $priorConfirmed && ! $priorAmbiguous,
            'prior_cancellation_ambiguous' => $priorAmbiguous,
            'prior_cancellation_artifact_present' => $artifact !== null,
            'successful_cancel_attempt_count' => $successfulCancelAttempts,
            'checks_passed' => $blockers === [],
            'blockers' => $blockers,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function loadCancelArtifact(string $priorLifecycleRunId): ?array
    {
        if ($priorLifecycleRunId === '') {
            return null;
        }
        $relative = SabreGdsQrUnticketedCancelLifecycle::ARTIFACT_DIRECTORY.'/'.$priorLifecycleRunId.'-send.json';
        if (! Storage::disk('local')->exists($relative)) {
            return null;
        }
        $decoded = json_decode((string) Storage::disk('local')->get($relative), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>|null  $artifact
     */
    protected function artifactIndicatesConfirmedCancellation(?array $artifact, ?string $expectedLocatorSha256): bool
    {
        if ($artifact === null) {
            return false;
        }
        if (($artifact['cancellation_outcome_state'] ?? '') !== 'cancellation_confirmed') {
            return false;
        }
        if ($expectedLocatorSha256 !== null && $expectedLocatorSha256 !== '') {
            $artifactHash = (string) ($artifact['locator_sha256'] ?? '');
            if ($artifactHash !== '' && ! hash_equals($expectedLocatorSha256, $artifactHash)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>|null  $artifact
     */
    protected function artifactIndicatesAmbiguous(?array $artifact): bool
    {
        if ($artifact === null) {
            return false;
        }

        return ($artifact['manual_reconciliation_required'] ?? false) === true
            || ($artifact['cancellation_outcome_state'] ?? '') === 'cancellation_ambiguous';
    }

    protected function hasAmbiguousCancelAttempt(Booking $booking): bool
    {
        return SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->whereIn('action', ['cancel_pnr', 'cancel_booking'])
            ->where('status', 'needs_review')
            ->exists();
    }
}
