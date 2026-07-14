<?php

namespace App\Services\Suppliers\Iati;

use App\Models\Agency;
use App\Services\Suppliers\OfferValidationService;
use App\Support\Booking\AgentBookingContext;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Illuminate\Support\Facades\Log;

/**
 * Public selected-offer IATI fare confirmation (/fare) before passenger-page handoff.
 * Non-mutating: no book/option/order/ticket/cancel/email.
 */
class IatiSelectedOfferRevalidationGate
{
    public function __construct(
        protected OfferValidationService $offerValidationService,
        protected IatiFareRevalidationService $fareRevalidationService,
    ) {}

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>|null  $searchPayload
     * @return array{
     *     success: bool,
     *     status: string,
     *     message: string,
     *     block_code: string|null,
     *     diagnostic: string|null,
     *     revalidation: array<string, mixed>,
     *     meta_patch: array<string, mixed>
     * }
     */
    public function refreshSelectedOffer(
        Agency $agency,
        array $offer,
        array $criteria,
        ?array $searchPayload = null,
        ?string $selectedFareOptionId = null,
        ?string $searchId = null,
    ): array {
        $offerId = (string) ($offer['offer_id'] ?? $offer['id'] ?? '');
        $searchContext = array_merge($criteria, [
            'search_id' => $searchId ?? (string) ($searchPayload['search_id'] ?? ''),
            'source_channel' => AgentBookingContext::SOURCE_CHANNEL_PUBLIC_GUEST,
        ]);

        $selection = FlightOfferDisplayPresenter::applySelectedFareFamilyOptionToOffer($offer, $selectedFareOptionId);
        if ($selection['error_code'] !== null) {
            return $this->failedBrandedSelectionResponse(
                $offer,
                $offerId,
                $selectedFareOptionId,
                $selection,
            );
        }

        $offerSnapshot = $selection['offer'];
        $validation = $this->offerValidationService->validateSelectedOffer($agency, $offerSnapshot, $searchContext);
        $report = $this->fareRevalidationService->buildPublicRevalidationReport($validation, $offerSnapshot, $selectedFareOptionId);
        $publicStatus = (string) ($report['revalidation_status'] ?? 'failed');
        $now = now()->toIso8601String();

        $metaPatch = [
            'selected_offer_revalidation_status' => $validation->is_valid ? 'success' : 'failed',
            'selected_offer_last_revalidated_at' => $now,
            'last_revalidated_at' => $now,
            'revalidation_status' => $validation->is_valid ? 'success' : 'failed',
            'iati_revalidation_status' => $publicStatus,
            'iati_price_changed' => (bool) ($report['price_changed'] ?? false),
            'selected_fare_option_id' => $selectedFareOptionId,
        ];

        if ($validation->is_valid && $validation->validated_offer !== null) {
            $validatedArray = $validation->validated_offer->toArray();
            $metaPatch = array_merge($metaPatch, [
                'fare_breakdown' => $validatedArray['fare_breakdown'] ?? $offerSnapshot['fare_breakdown'] ?? [],
                'raw_payload' => $validatedArray['raw_payload'] ?? $offerSnapshot['raw_payload'] ?? [],
            ]);
        }

        if ($validation->is_valid && in_array($publicStatus, ['valid', 'changed'], true)) {
            Log::info('flight_search.iati_selected_offer_revalidation.success', [
                'offer_id' => $offerId,
                'revalidation_status' => $publicStatus,
                'price_changed' => (bool) ($report['price_changed'] ?? false),
            ]);

            return [
                'success' => true,
                'status' => 'success',
                'message' => (string) ($report['safe_customer_message'] ?? 'Fare confirmed.'),
                'block_code' => null,
                'diagnostic' => null,
                'revalidation' => $report,
                'meta_patch' => $metaPatch,
            ];
        }

        Log::info('flight_search.iati_selected_offer_revalidation.failed', [
            'offer_id' => $offerId,
            'revalidation_status' => $publicStatus,
            'error_code' => $validation->meta['error_code'] ?? null,
        ]);

        return [
            'success' => false,
            'status' => $publicStatus === 'expired' ? 'expired' : 'failed',
            'message' => (string) ($report['safe_customer_message'] ?? 'We could not confirm this fare with the airline.'),
            'block_code' => $publicStatus === 'expired' ? 'offer_expired' : 'selected_offer_revalidation_failed',
            'diagnostic' => 'iati_fare_confirmation_failed',
            'revalidation' => $report,
            'meta_patch' => $metaPatch,
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array{
     *     offer: array<string, mixed>,
     *     resolved: array{match_field: string, option: array<string, mixed>, brand: array<string, mixed>, index: int}|null,
     *     error_code: string|null,
     *     error_message: string|null
     * }  $selection
     * @return array{
     *     success: bool,
     *     status: string,
     *     message: string,
     *     block_code: string|null,
     *     diagnostic: string|null,
     *     revalidation: array<string, mixed>,
     *     meta_patch: array<string, mixed>
     * }
     */
    protected function failedBrandedSelectionResponse(
        array $offer,
        string $offerId,
        ?string $selectedFareOptionId,
        array $selection,
    ): array {
        $validation = $this->fareRevalidationService->buildSelectedFareOptionFailureValidation(
            $offer,
            $selection,
            $selectedFareOptionId,
        );
        $report = $this->fareRevalidationService->buildPublicRevalidationReport($validation, $offer, $selectedFareOptionId);
        $publicStatus = (string) ($report['revalidation_status'] ?? 'failed');

        Log::info('flight_search.iati_selected_offer_revalidation.failed', [
            'offer_id' => $offerId,
            'revalidation_status' => $publicStatus,
            'error_code' => $selection['error_code'],
        ]);

        return [
            'success' => false,
            'status' => $publicStatus === 'expired' ? 'expired' : 'failed',
            'message' => (string) ($selection['error_message'] ?? $report['safe_customer_message'] ?? ''),
            'block_code' => 'selected_offer_revalidation_failed',
            'diagnostic' => (string) ($selection['error_code'] ?? 'selected_fare_option_not_found'),
            'revalidation' => $report,
            'meta_patch' => [
                'selected_offer_revalidation_status' => 'failed',
                'revalidation_status' => 'failed',
                'iati_revalidation_status' => $publicStatus,
                'selected_fare_option_id' => $selectedFareOptionId,
            ],
        ];
    }
}
