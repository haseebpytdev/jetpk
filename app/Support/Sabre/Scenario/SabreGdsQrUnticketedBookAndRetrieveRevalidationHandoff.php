<?php

namespace App\Support\Sabre\Scenario;

use App\Models\SupplierConnection;

/**
 * Production QR unticketed book-and-retrieve: revalidation evidence required before PNR create.
 */
final class SabreGdsQrUnticketedBookAndRetrieveRevalidationHandoff
{
    public function __construct(
        private readonly SabreGdsAuthoritativeRevalidatedBookingContextBuilder $contextBuilder,
        private readonly SabreGdsQrUnticketedPostRevalidationFinalOfferValidator $finalOfferValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $evidence
     */
    public function allowsPnrCreate(array $evidence): bool
    {
        if (($evidence['revalidation_success'] ?? false) !== true) {
            return false;
        }

        if (($evidence['freshness_satisfied'] ?? false) !== true) {
            return false;
        }

        $diagnostics = is_array($evidence['revalidation_diagnostics'] ?? null)
            ? $evidence['revalidation_diagnostics']
            : [];

        $unique = (int) ($diagnostics['unique_usable_linkage_match_count']
            ?? $evidence['unique_usable_linkage_match_count']
            ?? 0);
        $ambiguous = (int) ($diagnostics['ambiguous_linkage_match_count']
            ?? $evidence['ambiguous_linkage_match_count']
            ?? -1);

        if ($unique !== 1 || $ambiguous !== 0) {
            return false;
        }

        foreach (['pricing_complete', 'fare_basis_complete', 'usable_fare_linkage'] as $key) {
            $value = $diagnostics[$key] ?? $evidence[$key] ?? null;
            if ($value !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return list<string>
     */
    public function blockReasons(array $evidence): array
    {
        $reasons = [];
        if (($evidence['revalidation_success'] ?? false) !== true) {
            $reasons[] = 'revalidation_success_false';
        }
        if (($evidence['freshness_satisfied'] ?? false) !== true) {
            $reasons[] = 'freshness_not_satisfied';
        }

        $diagnostics = is_array($evidence['revalidation_diagnostics'] ?? null)
            ? $evidence['revalidation_diagnostics']
            : [];

        if ((int) ($diagnostics['unique_usable_linkage_match_count'] ?? $evidence['unique_usable_linkage_match_count'] ?? 0) !== 1) {
            $reasons[] = 'unique_usable_linkage_not_one';
        }
        if ((int) ($diagnostics['ambiguous_linkage_match_count'] ?? $evidence['ambiguous_linkage_match_count'] ?? -1) !== 0) {
            $reasons[] = 'ambiguous_linkage_present';
        }

        foreach (['pricing_complete', 'fare_basis_complete', 'usable_fare_linkage'] as $key) {
            if (($diagnostics[$key] ?? $evidence[$key] ?? null) !== true) {
                $reasons[] = $key.'_not_true';
            }
        }

        return $reasons;
    }

    /**
     * @param  array<string, mixed>  $shoppingOfferSnap
     * @param  array<string, mixed>  $revalidationEvidence
     * @param  array<string, mixed>  $continuityEvidence
     * @param  array{
     *     passenger: array<string, mixed>,
     *     contact: array<string, mixed>
     * }  $passengerBundle
     */
    public function buildAuthoritativeContext(
        SupplierConnection $connection,
        array $shoppingOfferSnap,
        array $revalidationEvidence,
        array $continuityEvidence,
        array $passengerBundle,
        ?string $lifecycleRunId = null,
    ): SabreGdsAuthoritativeRevalidatedBookingContext {
        return $this->contextBuilder->build(
            $connection,
            $shoppingOfferSnap,
            $revalidationEvidence,
            $continuityEvidence,
            $passengerBundle,
            $lifecycleRunId,
        );
    }

    /**
     * @param  array{
     *     passenger: array<string, mixed>,
     *     contact: array<string, mixed>
     * }  $passengerBundle
     * @return array<string, mixed>
     */
    public function validateFinalOffer(
        SabreGdsAuthoritativeRevalidatedBookingContext $context,
        array $revalidationEvidence,
        array $passengerBundle,
    ): array {
        return $this->finalOfferValidator->validate($context, $revalidationEvidence, $passengerBundle);
    }
}
