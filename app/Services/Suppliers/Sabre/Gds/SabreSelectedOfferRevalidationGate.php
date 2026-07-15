<?php

namespace App\Services\Suppliers\Sabre\Gds;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Support\Bookings\SabreHostRejectionFingerprintMatcher;
use App\Support\FlightSearch\SabreOfferFreshness;
use Illuminate\Support\Facades\Log;

/**
 * Sprint 11K-F: Selected-offer revalidation gate at checkout transition (no PNR, no cancellation).
 */
class SabreSelectedOfferRevalidationGate
{
    public function __construct(
        protected SabreBookingService $sabreBookingService,
        protected SabreOfferFreshness $freshness,
        protected SabreHostRejectionFingerprintMatcher $fingerprintMatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>|null  $searchPayload
     * @param  array<string, mixed>|null  $bookingMeta
     * @return array{
     *     allowed: bool,
     *     block_code: string|null,
     *     message: string|null,
     *     diagnostic: string|null,
     *     freshness_meta: array<string, mixed>,
     *     meta_patch: array<string, mixed>
     * }
     */
    public function evaluateCheckoutTransition(
        Agency $agency,
        array $offer,
        array $criteria,
        ?string $searchId,
        ?array $searchPayload = null,
        ?array $bookingMeta = null,
    ): array {
        $bookingMeta = $this->freshness->mergeRevalidationMetaFromOffer($offer, $bookingMeta);
        $bookingMeta = $this->fingerprintMatcher->applyMatchToBookingMeta($offer, $agency->id, $bookingMeta);
        $freshnessMeta = $this->freshness->buildOfferFreshnessMeta($offer, $searchPayload, $bookingMeta);

        $block = $this->freshness->blocksCheckoutTransition($freshnessMeta);
        if ($block !== null && ($block['code'] ?? '') === 'offer_stale_before_checkout') {
            return $this->blockedResult($block, $freshnessMeta, $bookingMeta);
        }

        if (! $this->freshness->requiresRevalidationBeforeCheckout(
            (string) ($freshnessMeta['offer_freshness_status'] ?? SabreOfferFreshness::STATUS_FRESH),
            is_array($freshnessMeta['high_risk_reasons'] ?? null) ? $freshnessMeta['high_risk_reasons'] : [],
            (string) ($freshnessMeta['revalidation_status'] ?? ''),
            $this->freshness->parseTimestamp(is_string($freshnessMeta['last_revalidated_at'] ?? null) ? (string) $freshnessMeta['last_revalidated_at'] : null),
        )) {
            return $this->allowedWithFreshness($freshnessMeta, $bookingMeta);
        }

        if ($this->freshness->hasValidRecentRevalidation($freshnessMeta)) {
            return $this->allowedWithFreshness($freshnessMeta, $bookingMeta);
        }

        if (! $this->sabreLiveRevalidationAvailable()) {
            $block = [
                'code' => 'selected_offer_revalidation_required',
                'message' => $this->freshness->customerSafeMessage('selected_offer_revalidation_required'),
                'diagnostic' => SabreOfferFreshness::DIAG_SELECTED_OFFER_REVALIDATION_REQUIRED,
            ];

            return $this->blockedResult($block, $freshnessMeta, array_merge($bookingMeta, [
                'selected_offer_revalidation_status' => 'skipped_live_disabled',
            ]));
        }

        $revalidation = $this->runSelectedOfferRevalidation($agency, $offer, $criteria);
        $metaPatch = array_merge($bookingMeta, $revalidation['meta_patch']);
        $metaPatch = $this->fingerprintMatcher->applyMatchToBookingMeta($offer, $agency->id, $metaPatch);
        $freshnessMeta = $this->freshness->buildOfferFreshnessMeta($offer, $searchPayload, $metaPatch);

        if (! ($revalidation['success'] ?? false)) {
            $block = [
                'code' => 'selected_offer_revalidation_failed',
                'message' => $this->freshness->customerSafeMessage('selected_offer_revalidation_failed'),
                'diagnostic' => SabreOfferFreshness::DIAG_SELECTED_OFFER_REVALIDATION_FAILED,
            ];

            return $this->blockedResult($block, $freshnessMeta, $metaPatch);
        }

        return $this->allowedWithFreshness($freshnessMeta, $metaPatch);
    }

    public function sabreLiveRevalidationAvailable(): bool
    {
        return (bool) config('suppliers.sabre.booking_enabled', false)
            && (bool) config('suppliers.sabre.booking_live_call_enabled', false)
            && (bool) config('suppliers.sabre.revalidate_before_booking', false);
    }

    /**
     * Customer-initiated selected-offer refresh (Sprint 11K-G). No PNR, no cancellation.
     *
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>|null  $searchPayload
     * @return array{
     *     success: bool,
     *     status: string,
     *     message: string,
     *     block_code: string|null,
     *     diagnostic: string|null,
     *     freshness_meta: array<string, mixed>,
     *     meta_patch: array<string, mixed>
     * }
     */
    public function refreshSelectedOffer(
        Agency $agency,
        array $offer,
        array $criteria,
        ?array $searchPayload = null,
    ): array {
        $bookingMeta = $this->freshness->mergeRevalidationMetaFromOffer($offer, []);
        $bookingMeta = $this->fingerprintMatcher->applyMatchToBookingMeta($offer, $agency->id, $bookingMeta);
        $freshnessMeta = $this->freshness->buildOfferFreshnessMeta($offer, $searchPayload, $bookingMeta);

        if ($this->freshness->hasValidRecentRevalidation($freshnessMeta)
            && $this->freshness->blocksCheckoutTransition($freshnessMeta) === null) {
            return $this->refreshSuccessResult($freshnessMeta, $bookingMeta, 'already_fresh');
        }

        if ($this->sabreLiveRevalidationAvailable()) {
            $revalidation = $this->runSelectedOfferRevalidation($agency, $offer, $criteria);
            $metaPatch = array_merge($bookingMeta, $revalidation['meta_patch']);
            $metaPatch = $this->fingerprintMatcher->applyMatchToBookingMeta($offer, $agency->id, $metaPatch);
            $freshnessMeta = $this->freshness->buildOfferFreshnessMeta($offer, $searchPayload, $metaPatch);

            if (($revalidation['success'] ?? false) === true) {
                return $this->refreshSuccessResult($freshnessMeta, $metaPatch, 'revalidated');
            }

            return [
                'success' => false,
                'status' => 'failed',
                'message' => $this->freshness->customerSafeMessage('selected_offer_revalidation_failed'),
                'block_code' => 'selected_offer_revalidation_failed',
                'diagnostic' => SabreOfferFreshness::DIAG_SELECTED_OFFER_REVALIDATION_FAILED,
                'freshness_meta' => $freshnessMeta,
                'meta_patch' => array_merge($metaPatch, [
                    'sabre_checkout_freshness_block' => [
                        'classification' => SabreOfferFreshness::DIAG_SELECTED_OFFER_REVALIDATION_FAILED,
                        'code' => 'selected_offer_revalidation_failed',
                        'at' => now()->toIso8601String(),
                    ],
                ]),
            ];
        }

        return [
            'success' => false,
            'status' => 'search_refresh_required',
            'message' => $this->freshness->customerSafeMessage('selected_offer_revalidation_required'),
            'block_code' => 'selected_offer_revalidation_required',
            'diagnostic' => SabreOfferFreshness::DIAG_SELECTED_OFFER_REVALIDATION_REQUIRED,
            'freshness_meta' => $freshnessMeta,
            'meta_patch' => array_merge($bookingMeta, [
                'selected_offer_revalidation_status' => 'skipped_live_disabled',
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $freshnessMeta
     * @param  array<string, mixed>  $metaPatch
     * @return array<string, mixed>
     */
    protected function refreshSuccessResult(array $freshnessMeta, array $metaPatch, string $reason): array
    {
        return [
            'success' => true,
            'status' => 'success',
            'message' => $this->freshness->customerSafeMessage('refresh_search_success'),
            'block_code' => null,
            'diagnostic' => null,
            'freshness_meta' => $freshnessMeta,
            'meta_patch' => array_merge($metaPatch, [
                'offer_freshness' => $freshnessMeta,
                'selected_offer_refresh_reason' => $reason,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $criteria
     * @return array{success: bool, meta_patch: array<string, mixed>}
     */
    protected function runSelectedOfferRevalidation(Agency $agency, array $offer, array $criteria): array
    {
        $connection = $this->resolveConnection($agency, $offer);
        if ($connection === null) {
            return [
                'success' => false,
                'meta_patch' => [
                    'selected_offer_revalidation_status' => 'failed',
                    'selected_offer_revalidation_reason' => 'missing_connection',
                ],
            ];
        }

        $gate = $this->sabreBookingService->validateNormalizedSabreOffer($offer);
        if (! $gate->success) {
            return [
                'success' => false,
                'meta_patch' => [
                    'selected_offer_revalidation_status' => 'failed',
                    'selected_offer_revalidation_reason' => (string) ($gate->safe_context['reason'] ?? 'validation_failed'),
                ],
            ];
        }

        $draft = $this->sabreBookingService->prepareBookingPayload($offer, [
            'passengers' => $this->minimalPassengerStubForRevalidation($criteria),
        ]);

        if (($draft['_valid'] ?? false) !== true) {
            return [
                'success' => false,
                'meta_patch' => [
                    'selected_offer_revalidation_status' => 'failed',
                    'selected_offer_revalidation_reason' => (string) ($draft['code'] ?? 'draft_invalid'),
                ],
            ];
        }

        $apiDraft = $draft;
        unset($apiDraft['_valid']);

        $outcome = $this->sabreBookingService->runRevalidationBeforeBooking($apiDraft, $connection);
        $success = ($outcome['success'] ?? false) === true;

        Log::info('sabre.checkout.selected_offer_revalidation', [
            'success' => $success,
            'reason_code' => (string) ($outcome['reason_code'] ?? ''),
            'connection_id' => $connection->id,
        ]);

        $now = now()->toIso8601String();

        return [
            'success' => $success,
            'meta_patch' => [
                'selected_offer_revalidation_status' => $success ? 'success' : 'failed',
                'selected_offer_last_revalidated_at' => $now,
                'last_revalidated_at' => $now,
                'revalidation_status' => $success ? 'success' : 'failed',
                'selected_offer_revalidation_reason' => $success
                    ? null
                    : (string) ($outcome['reason_code'] ?? 'sabre_revalidation_failed'),
                'selected_offer_revalidation_at' => $now,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return list<array<string, mixed>>
     */
    protected function minimalPassengerStubForRevalidation(array $criteria): array
    {
        $adults = max(1, (int) ($criteria['adults'] ?? 1));

        return array_fill(0, $adults, [
            'type' => 'adult',
            'first_name' => 'Revalidation',
            'last_name' => 'Gate',
        ]);
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function resolveConnection(Agency $agency, array $offer): ?SupplierConnection
    {
        $connectionId = (int) ($offer['supplier_connection_id'] ?? 0);
        if ($connectionId > 0) {
            return SupplierConnection::query()
                ->where('agency_id', $agency->id)
                ->where('id', $connectionId)
                ->where(function ($query): void {
                    $query->where('is_active', true)
                        ->orWhere('status', SupplierConnectionStatus::Active->value);
                })
                ->first();
        }

        return SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->where(function ($query): void {
                $query->where('is_active', true)
                    ->orWhere('status', SupplierConnectionStatus::Active->value);
            })
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array{code: string, message: string, diagnostic: string}  $block
     * @param  array<string, mixed>  $freshnessMeta
     * @param  array<string, mixed>  $metaPatch
     * @return array<string, mixed>
     */
    protected function blockedResult(array $block, array $freshnessMeta, array $metaPatch): array
    {
        return [
            'allowed' => false,
            'block_code' => (string) ($block['code'] ?? ''),
            'message' => (string) ($block['message'] ?? ''),
            'diagnostic' => (string) ($block['diagnostic'] ?? ''),
            'freshness_meta' => $freshnessMeta,
            'meta_patch' => array_merge($metaPatch, [
                'offer_freshness' => $freshnessMeta,
                'sabre_checkout_freshness_block' => [
                    'classification' => (string) ($block['diagnostic'] ?? ''),
                    'code' => (string) ($block['code'] ?? ''),
                    'at' => now()->toIso8601String(),
                ],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $freshnessMeta
     * @param  array<string, mixed>  $metaPatch
     * @return array<string, mixed>
     */
    protected function allowedWithFreshness(array $freshnessMeta, array $metaPatch): array
    {
        return [
            'allowed' => true,
            'block_code' => null,
            'message' => null,
            'diagnostic' => null,
            'freshness_meta' => $freshnessMeta,
            'meta_patch' => array_merge($metaPatch, [
                'offer_freshness' => $freshnessMeta,
            ]),
        ];
    }
}
