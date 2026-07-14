<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Data\BaggageAllowanceData;
use App\Data\FareBreakdownData;
use App\Data\NormalizedFlightOfferData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Support\Bookings\PiaNdcBookingStatusInterpreter;
use Carbon\Carbon;

/**
 * Normalizes PIA NDC XML parse output into OTA internal DTOs.
 */
class PiaNdcResponseNormalizer
{
    /**
     * @param  array<string, mixed>  $parsedResponse
     * @return list<NormalizedFlightOfferData>
     */
    public function normalizeSearchResponse(
        array $parsedResponse,
        SupplierConnection $connection,
        string $correlationId,
    ): array {
        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $shoppingRef = trim((string) ($parsed['shopping_response_ref_id'] ?? ''));
        $offers = is_array($parsed['offers'] ?? null) ? $parsed['offers'] : [];
        $dataLists = is_array($parsed['data_lists'] ?? null) ? $parsed['data_lists'] : [];
        $segmentsById = $this->indexSegments($dataLists);
        $journeysById = $this->indexJourneys($dataLists);
        $normalized = [];

        foreach ($offers as $offer) {
            if (! is_array($offer)) {
                continue;
            }
            $built = $this->buildOffer($offer, $connection, $correlationId, $shoppingRef, $segmentsById, $journeysById, $dataLists);
            if ($built !== null) {
                $normalized[] = $built;
            }
            if (count($normalized) >= 150) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * Hitit OfferPrice may return zero totals or fee-only SF amounts — not full fare revalidation.
     * OrderCreate must rely on AirShopping provider_context until Hitit confirms OfferPrice structure.
     *
     * @param  array<string, mixed>  $parsedResponse
     * @param  array<string, mixed>  $sourceContext
     */
    public function normalizeOfferPriceResponse(
        array $parsedResponse,
        array $sourceContext = [],
        ?float $airShoppingTotal = null,
    ): array {
        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $pricedOffers = is_array($parsed['priced_offers'] ?? null) ? $parsed['priced_offers'] : [];
        $summary = is_array($parsed['offer_price_summary'] ?? null) ? $parsed['offer_price_summary'] : [];
        $firstPriced = is_array($pricedOffers[0] ?? null) ? $pricedOffers[0] : [];
        $firstItem = is_array($firstPriced['offer_items'][0] ?? null) ? $firstPriced['offer_items'][0] : [];
        $pricedOfferRef = trim((string) ($summary['priced_offer_ref_id'] ?? $firstPriced['offer_id'] ?? ''));

        $providerContext = array_merge($sourceContext, array_filter([
            'provider' => SupplierProvider::PiaNdc->value,
            'shopping_response_ref_id' => trim((string) ($sourceContext['shopping_response_ref_id'] ?? $parsed['shopping_response_ref_id'] ?? '')),
            'offer_ref_id' => trim((string) ($sourceContext['offer_ref_id'] ?? '')),
            'priced_offer_ref_id' => $pricedOfferRef !== '' ? $pricedOfferRef : null,
            'offer_item_ref_id' => trim((string) ($sourceContext['offer_item_ref_id'] ?? $firstItem['offer_item_id'] ?? '')),
            'pax_ref_id' => trim((string) ($sourceContext['pax_ref_id'] ?? ($firstItem['pax_refs'][0] ?? 'ADTPax-1'))),
            'owner_code' => trim((string) ($sourceContext['owner_code'] ?? $firstPriced['owner_code'] ?? '')),
            'payment_time_limit' => trim((string) ($summary['payment_time_limit'] ?? $firstItem['payment_time_limit'] ?? $sourceContext['payment_time_limit'] ?? '')),
            'fare_basis' => trim((string) ($sourceContext['fare_basis'] ?? $firstItem['fare_basis'] ?? '')),
            'fare_type_code' => trim((string) ($sourceContext['fare_type_code'] ?? $firstItem['fare_type_code'] ?? '')),
            'rbd' => trim((string) ($sourceContext['rbd'] ?? $firstItem['rbd'] ?? '')),
            'cabin_type' => trim((string) ($sourceContext['cabin_type'] ?? $firstItem['cabin'] ?? '')),
        ], fn ($value) => $value !== null && $value !== ''));

        if (is_array($sourceContext['offer_item_refs'] ?? null) && $sourceContext['offer_item_refs'] !== []) {
            $providerContext['offer_item_refs'] = $sourceContext['offer_item_refs'];
        }

        $baseAmount = (float) ($summary['base'] ?? 0);
        $taxAmount = (float) ($summary['tax'] ?? 0);
        $offerPriceTotal = (float) ($summary['total'] ?? 0);
        $feeAmountTotal = (float) ($summary['fee_amount_total'] ?? 0);
        $feeDescriptions = is_array($summary['fee_descriptions'] ?? null) ? $summary['fee_descriptions'] : [];
        $fees = is_array($summary['fees'] ?? null) ? $summary['fees'] : [];
        $pricedOfferCount = count($pricedOffers);
        $providerErrors = is_array($parsedResponse['errors'] ?? null) ? $parsedResponse['errors'] : [];
        $providerWarnings = is_array($parsedResponse['warnings'] ?? null) ? $parsedResponse['warnings'] : [];

        $rawPricedOfferPresent = $pricedOfferCount > 0;
        $zeroPrice = $rawPricedOfferPresent && $offerPriceTotal <= 0.009;
        $feeOnlyPrice = $this->isFeeOnlyOfferPrice($baseAmount, $taxAmount, $offerPriceTotal, $feeAmountTotal);
        $partialPrice = $feeOnlyPrice || ($offerPriceTotal > 0.009 && $baseAmount <= 0.009 && $taxAmount <= 0.009);

        $fareComparisonAvailable = $airShoppingTotal !== null && $airShoppingTotal > 0.009;
        $fareDifference = $fareComparisonAvailable ? $offerPriceTotal - $airShoppingTotal : null;
        $fareDifferencePercent = $fareComparisonAvailable && $airShoppingTotal > 0.009
            ? (($fareDifference / $airShoppingTotal) * 100)
            : null;
        $withinTolerance = ! $fareComparisonAvailable || (
            $fareDifference !== null
            && abs($fareDifference) <= max(10.0, abs($airShoppingTotal) * 0.02)
        );

        $commerciallyValidPrice = $providerErrors === []
            && $pricedOfferCount > 0
            && $offerPriceTotal > 0.009
            && ($baseAmount > 0.009 || $taxAmount > 0.009)
            && ! $feeOnlyPrice
            && $withinTolerance;

        if ($zeroPrice && $providerErrors === []) {
            $providerWarnings[] = [
                'code' => 'zero_price',
                'message' => 'OfferPrice returned zero total amount.',
                'type' => 'W',
            ];
        }
        if ($feeOnlyPrice && $providerErrors === []) {
            $providerWarnings[] = [
                'code' => 'fee_only_price',
                'message' => 'OfferPrice returned fee-only amount, not full fare.',
                'type' => 'W',
            ];
        }

        return [
            'priced_offer_count' => $pricedOfferCount,
            'total_amount' => $offerPriceTotal,
            'offer_price_total' => $offerPriceTotal,
            'base_amount' => $baseAmount,
            'tax_amount' => $taxAmount,
            'fee_amount_total' => $feeAmountTotal,
            'fee_descriptions' => $feeDescriptions,
            'fees' => $fees,
            'currency' => (string) ($summary['currency'] ?? 'PKR'),
            'priced_offer_ref_id' => $pricedOfferRef,
            'zero_price' => $zeroPrice,
            'partial_price' => $partialPrice,
            'fee_only_price' => $feeOnlyPrice,
            'commercially_valid_price' => $commerciallyValidPrice,
            'raw_priced_offer_present' => $rawPricedOfferPresent,
            'fare_comparison_available' => $fareComparisonAvailable,
            'air_shopping_total' => $airShoppingTotal,
            'fare_difference' => $fareDifference,
            'fare_difference_percent' => $fareDifferencePercent,
            'provider_context' => $providerContext,
            'provider_errors' => $providerErrors,
            'provider_warnings' => $providerWarnings,
        ];
    }

    private function isFeeOnlyOfferPrice(float $baseAmount, float $taxAmount, float $totalAmount, float $feeAmountTotal): bool
    {
        if ($baseAmount > 0.009 || $taxAmount > 0.009 || $totalAmount <= 0.009 || $feeAmountTotal <= 0.009) {
            return false;
        }

        return abs($totalAmount - $feeAmountTotal) <= 0.009;
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @param  array<string, mixed>  $existingContext
     * @return array<string, mixed>
     */
    public function normalizeBookingResponse(array $parsedResponse, array $existingContext = []): array
    {
        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $order = is_array($parsed['order'] ?? null) ? $parsed['order'] : [];
        $bookingRefs = is_array($parsed['booking_refs'] ?? null) ? $parsed['booking_refs'] : [];
        $firstBookingRef = is_array($bookingRefs[0] ?? null) ? $bookingRefs[0] : [];
        $bookingReference = trim((string) ($firstBookingRef['booking_id'] ?? ''));
        $airlineCode = trim((string) ($firstBookingRef['airline_desig_code'] ?? ''));
        $orderId = trim((string) ($order['order_id'] ?? ''));
        if ($bookingReference === '') {
            $bookingReference = $orderId;
        }
        $airlineLocator = $airlineCode !== '' && $bookingReference !== ''
            ? $airlineCode.'/'.$bookingReference
            : ($bookingReference !== '' ? $bookingReference : null);

        return [
            'provider_booking_reference' => $orderId,
            'pnr' => $orderId !== '' ? $orderId : $bookingReference,
            'booking_reference' => $bookingReference,
            'airline_locator' => $airlineLocator,
            'order_status' => trim((string) ($order['status'] ?? '')),
            'status' => trim((string) ($order['status'] ?? 'option')) ?: 'option',
            'ticketing_status' => 'pending_ticketing',
            'last_ticketing_date' => trim((string) ($order['payment_time_limit'] ?? $parsed['payment_time_limit'] ?? '')),
            'payment_time_limit' => trim((string) ($order['payment_time_limit'] ?? $parsed['payment_time_limit'] ?? '')),
            'provider_context' => array_merge($existingContext, [
                'order_id' => $orderId,
                'owner_code' => trim((string) ($order['owner_code'] ?? $existingContext['owner_code'] ?? '')),
                'payment_time_limit' => trim((string) ($order['payment_time_limit'] ?? $parsed['payment_time_limit'] ?? '')),
            ]),
            'supplier_messages' => $this->warningMessages($parsedResponse),
        ];
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    public function normalizeRetrieveResponse(array $parsedResponse, array $existing = []): array
    {
        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $order = is_array($parsed['order'] ?? null) ? $parsed['order'] : [];
        $tickets = is_array($parsed['ticket_doc_infos'] ?? null) ? $parsed['ticket_doc_infos'] : [];

        $ticketNumbers = [];
        foreach ($tickets as $ticket) {
            if (is_array($ticket) && ($ticket['ticket_number'] ?? '') !== '') {
                $ticketNumbers[] = (string) $ticket['ticket_number'];
            }
        }
        $hasBlockingTickets = $this->hasBlockingTicketNumbers($tickets);

        $result = array_filter([
            'order_id' => trim((string) ($order['order_id'] ?? $existing['order_id'] ?? '')),
            'owner_code' => trim((string) ($order['owner_code'] ?? $existing['owner_code'] ?? '')),
            'status' => trim((string) ($order['status'] ?? '')) ?: null,
            'ticketing_status' => $hasBlockingTickets
                ? 'ticketed'
                : ($ticketNumbers !== [] ? 'voided' : ($existing['ticketing_status'] ?? 'pending_ticketing')),
            'ticket_numbers' => $ticketNumbers !== [] ? $ticketNumbers : null,
            'ticket_doc_infos' => $tickets !== [] ? $tickets : null,
            'has_blocking_ticket_numbers' => $hasBlockingTickets,
            'payment_time_limit' => trim((string) ($order['payment_time_limit'] ?? $parsed['payment_time_limit'] ?? $existing['payment_time_limit'] ?? '')) ?: null,
        ], fn ($v) => $v !== null && $v !== '');

        return $result;
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    public function normalizeOrderRetrieveDiagnosticResponse(array $parsedResponse, array $existing = []): array
    {
        $base = $this->normalizeRetrieveResponse($parsedResponse, $existing);
        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $order = is_array($parsed['order'] ?? null) ? $parsed['order'] : [];
        $bookingRefs = is_array($parsed['booking_refs'] ?? null) ? $parsed['booking_refs'] : [];
        $firstBookingRef = is_array($bookingRefs[0] ?? null) ? $bookingRefs[0] : [];
        $dataLists = is_array($parsed['data_lists'] ?? null) ? $parsed['data_lists'] : [];
        $segments = is_array($dataLists['pax_segments'] ?? null) ? $dataLists['pax_segments'] : [];
        $paxList = is_array($dataLists['pax_list'] ?? null) ? $dataLists['pax_list'] : [];
        $tickets = is_array($parsed['ticket_doc_infos'] ?? null) ? $parsed['ticket_doc_infos'] : [];

        $orderId = trim((string) ($base['order_id'] ?? $order['order_id'] ?? ''));
        $bookingReference = trim((string) ($firstBookingRef['booking_id'] ?? $orderId));
        $airlineCode = trim((string) ($firstBookingRef['airline_desig_code'] ?? ''));
        $airlineLocator = $airlineCode !== '' && $bookingReference !== ''
            ? $airlineCode.'/'.$bookingReference
            : ($bookingReference !== '' ? $bookingReference : null);

        $ticketNumbers = is_array($base['ticket_numbers'] ?? null) ? $base['ticket_numbers'] : [];
        $totalPrice = is_array($order['total_price'] ?? null) ? $order['total_price'] : [];
        $orderLevelStatus = trim((string) ($order['status'] ?? ''));

        return array_merge($base, [
            'pnr' => $orderId !== '' ? $orderId : $bookingReference,
            'booking_reference' => $bookingReference,
            'airline_locator' => $airlineLocator,
            'order_status' => $orderLevelStatus !== '' ? $orderLevelStatus : null,
            'segment_statuses' => is_array($parsed['segment_operating_statuses'] ?? null)
                ? $parsed['segment_operating_statuses']
                : [],
            'service_statuses' => is_array($parsed['order_service_statuses'] ?? null)
                ? $parsed['order_service_statuses']
                : [],
            'order_item_statuses' => is_array($parsed['order_item_statuses'] ?? null)
                ? $parsed['order_item_statuses']
                : [],
            'payment_time_limit' => trim((string) ($order['payment_time_limit'] ?? $parsed['payment_time_limit'] ?? $base['payment_time_limit'] ?? '')),
            'segments' => $this->sanitizeRetrieveSegments($segments),
            'segment_count' => count($segments),
            'passenger_count' => count($paxList),
            'ticket_numbers' => $ticketNumbers,
            'ticket_doc_infos' => $tickets !== [] ? $tickets : null,
            'has_ticket_numbers' => $ticketNumbers !== [],
            'has_blocking_ticket_numbers' => $this->hasBlockingTicketNumbers($tickets),
            'total_amount' => (float) ($totalPrice['total'] ?? 0),
            'currency' => (string) ($totalPrice['currency'] ?? 'PKR'),
            'provider_errors' => is_array($parsedResponse['errors'] ?? null) ? $parsedResponse['errors'] : [],
            'provider_warnings' => is_array($parsedResponse['warnings'] ?? null) ? $parsedResponse['warnings'] : [],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<array<string, mixed>>
     */
    private function sanitizeRetrieveSegments(array $segments): array
    {
        $sanitized = [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $sanitized[] = array_filter([
                'departure_airport' => $segment['departure_airport'] ?? null,
                'arrival_airport' => $segment['arrival_airport'] ?? null,
                'departure_at' => $segment['departure_at'] ?? null,
                'arrival_at' => $segment['arrival_at'] ?? null,
                'carrier' => $segment['carrier'] ?? null,
                'flight_number' => $segment['flight_number'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');
        }

        return $sanitized;
    }

    /**
     * @param  list<array<string, mixed>>  $ticketDocInfos
     */
    public function hasBlockingTicketNumbers(array $ticketDocInfos): bool
    {
        foreach ($ticketDocInfos as $ticket) {
            if (! is_array($ticket)) {
                continue;
            }
            $number = trim((string) ($ticket['ticket_number'] ?? ''));
            if ($number === '') {
                continue;
            }
            if (str_starts_with(strtoupper($number), 'FAKE')) {
                continue;
            }

            $couponStatuses = is_array($ticket['coupon_status_codes'] ?? null) ? $ticket['coupon_status_codes'] : [];
            if ($couponStatuses === []) {
                return true;
            }

            foreach ($couponStatuses as $status) {
                if ($this->isActiveCouponStatus((string) $status)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isActiveCouponStatus(string $status): bool
    {
        $normalized = strtoupper(trim($status));

        return in_array($normalized, ['O', 'OPEN'], true);
    }

    public function isVoidedCouponStatus(string $status): bool
    {
        return strtoupper(trim($status)) === 'V';
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @return array{amount: float, currency: string}
     */
    public function normalizeTicketPreviewResponse(array $parsedResponse): array
    {
        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $preview = is_array($parsed['ticket_preview'] ?? null) ? $parsed['ticket_preview'] : [];
        $order = is_array($parsed['order'] ?? null) ? $parsed['order'] : [];
        $price = is_array($order['total_price'] ?? null) ? $order['total_price'] : [];

        return [
            'amount' => (float) ($preview['amount'] ?? $price['total'] ?? 0),
            'currency' => (string) ($preview['currency'] ?? $price['currency'] ?? 'PKR'),
        ];
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @return array<string, mixed>
     */
    public function normalizeTicketingResponse(array $parsedResponse, array $existingContext = []): array
    {
        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $tickets = is_array($parsed['ticket_doc_infos'] ?? null) ? $parsed['ticket_doc_infos'] : [];
        $ticketNumbers = [];
        foreach ($tickets as $ticket) {
            if (is_array($ticket) && ($ticket['ticket_number'] ?? '') !== '') {
                $ticketNumbers[] = (string) $ticket['ticket_number'];
            }
        }
        $hasBlockingTickets = $this->hasBlockingTicketNumbers($tickets);

        return [
            'ticketing_status' => $hasBlockingTickets ? 'ticketed' : 'ticketing_failed',
            'ticket_numbers' => $ticketNumbers,
            'ticket_doc_infos' => $tickets !== [] ? $tickets : null,
            'has_blocking_ticket_numbers' => $hasBlockingTickets,
            'provider_context' => array_merge($existingContext, array_filter([
                'ticket_numbers' => $ticketNumbers,
                'ticket_doc_infos' => $tickets !== [] ? $tickets : null,
                'has_blocking_ticket_numbers' => $hasBlockingTickets,
                'ticketing_status' => $hasBlockingTickets ? 'ticketed' : 'ticketing_failed',
                'ticketed_at' => $hasBlockingTickets ? now()->toIso8601String() : null,
            ], fn ($value) => $value !== null)),
            'supplier_messages' => $this->warningMessages($parsedResponse),
        ];
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @return array<string, mixed>
     */
    public function normalizeCancelPreviewResponse(array $parsedResponse): array
    {
        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $preview = is_array($parsed['cancel_preview'] ?? null) ? $parsed['cancel_preview'] : [];

        return [
            'penalty' => $preview['penalty'] ?? null,
            'refundable_amount' => $preview['refundable'] ?? null,
            'currency' => (string) ($preview['currency'] ?? 'PKR'),
            'cancellation_status' => 'preview',
        ];
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @return array<string, mixed>
     */
    public function normalizeCancelCommitResponse(array $parsedResponse): array
    {
        return $this->normalizeCancelDiagnosticResponse(
            parsedResponse: $parsedResponse,
            httpStatus: isset($parsedResponse['_ota_diagnostic']['http_status'])
                ? (int) $parsedResponse['_ota_diagnostic']['http_status']
                : 200,
            providerErrorCode: null,
            providerErrorMessage: null,
        );
    }

    /**
     * Preview-only cancel diagnostic — never sets cancellation_status=cancelled.
     *
     * @param  ?array<string, mixed>  $parsedResponse
     * @return array<string, mixed>
     */
    public function normalizeCancelPreviewDiagnosticResponse(
        ?array $parsedResponse,
        ?int $httpStatus,
        ?string $providerErrorCode,
        ?string $providerErrorMessage,
        ?array $soapFault = null,
    ): array {
        $parsedResponse = is_array($parsedResponse) ? $parsedResponse : [];
        $soapFault = $soapFault ?? (is_array($parsedResponse['soap_fault'] ?? null) ? $parsedResponse['soap_fault'] : null);
        $faultCode = is_array($soapFault) ? trim((string) ($soapFault['code'] ?? '')) : '';
        $faultString = is_array($soapFault) ? trim((string) ($soapFault['message'] ?? '')) : '';

        if ($faultString !== '' && ($providerErrorMessage === null || $providerErrorMessage === '')) {
            $providerErrorMessage = $faultString;
        }

        $httpOk = $httpStatus !== null && $httpStatus >= 200 && $httpStatus < 300;
        $hasFailure = ! $httpOk
            || $providerErrorCode !== null
            || $faultCode !== ''
            || $faultString !== '';

        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $preview = is_array($parsed['cancel_preview'] ?? null) ? $parsed['cancel_preview'] : [];
        $order = is_array($parsed['order'] ?? null) ? $parsed['order'] : [];
        $orderStatus = trim((string) ($order['status'] ?? ''));

        $base = [
            'order_id' => trim((string) ($order['order_id'] ?? '')) ?: null,
            'order_status' => $orderStatus !== '' ? $orderStatus : null,
            'penalty' => $preview['penalty'] ?? null,
            'refundable_amount' => $preview['refundable'] ?? null,
            'currency' => (string) ($preview['currency'] ?? 'PKR'),
            'preview_response_refs' => array_filter([
                'shopping_response_ref_id' => trim((string) ($parsed['shopping_response_ref_id'] ?? '')) ?: null,
            ]),
            'supplier_messages' => $this->warningMessages($parsedResponse),
            'provider_errors' => is_array($parsedResponse['errors'] ?? null) ? $parsedResponse['errors'] : [],
            'provider_warnings' => is_array($parsedResponse['warnings'] ?? null) ? $parsedResponse['warnings'] : [],
        ];

        if ($hasFailure) {
            return array_merge($base, [
                'success' => false,
                'cancel_preview_status' => 'failed',
                'soap_fault_code' => $faultCode !== '' ? $faultCode : null,
                'soap_fault_string' => $faultString !== '' ? $faultString : null,
                'provider_error_code' => $providerErrorCode,
                'provider_error_message' => $providerErrorMessage,
            ]);
        }

        return array_merge($base, [
            'success' => true,
            'cancel_preview_status' => 'preview',
            'soap_fault_code' => null,
            'soap_fault_string' => null,
            'provider_error_code' => null,
            'provider_error_message' => null,
        ]);
    }

    /**
     * Fault-safe cancel diagnostic normalization — never marks cancelled on HTTP/SOAP/provider failure.
     *
     * @param  ?array<string, mixed>  $parsedResponse
     * @return array<string, mixed>
     */
    public function normalizeCancelDiagnosticResponse(
        ?array $parsedResponse,
        ?int $httpStatus,
        ?string $providerErrorCode,
        ?string $providerErrorMessage,
        ?array $soapFault = null,
    ): array {
        $parsedResponse = is_array($parsedResponse) ? $parsedResponse : [];
        $soapFault = $soapFault ?? (is_array($parsedResponse['soap_fault'] ?? null) ? $parsedResponse['soap_fault'] : null);
        $faultCode = is_array($soapFault) ? trim((string) ($soapFault['code'] ?? '')) : '';
        $faultString = is_array($soapFault) ? trim((string) ($soapFault['message'] ?? '')) : '';

        if ($faultString !== '' && ($providerErrorMessage === null || $providerErrorMessage === '')) {
            $providerErrorMessage = $faultString;
        }

        $httpOk = $httpStatus !== null && $httpStatus >= 200 && $httpStatus < 300;
        $hasFailure = ! $httpOk
            || $providerErrorCode !== null
            || $faultCode !== ''
            || $faultString !== '';

        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $order = is_array($parsed['order'] ?? null) ? $parsed['order'] : [];
        $orderStatus = trim((string) ($order['status'] ?? ''));

        if ($hasFailure) {
            return [
                'success' => false,
                'cancellation_status' => 'failed',
                'order_id' => trim((string) ($order['order_id'] ?? '')) ?: null,
                'order_status' => $orderStatus !== '' ? $orderStatus : null,
                'soap_fault_code' => $faultCode !== '' ? $faultCode : null,
                'soap_fault_string' => $faultString !== '' ? $faultString : null,
                'provider_error_code' => $providerErrorCode,
                'provider_error_message' => $providerErrorMessage,
                'supplier_messages' => $this->warningMessages($parsedResponse),
                'provider_errors' => is_array($parsedResponse['errors'] ?? null) ? $parsedResponse['errors'] : [],
                'provider_warnings' => is_array($parsedResponse['warnings'] ?? null) ? $parsedResponse['warnings'] : [],
            ];
        }

        $cancellationStatus = $this->inferCancellationStatus($orderStatus);

        return [
            'success' => $cancellationStatus === 'cancelled',
            'cancellation_status' => $cancellationStatus,
            'order_id' => trim((string) ($order['order_id'] ?? '')) ?: null,
            'order_status' => $orderStatus !== '' ? $orderStatus : null,
            'soap_fault_code' => null,
            'soap_fault_string' => null,
            'provider_error_code' => null,
            'provider_error_message' => null,
            'supplier_messages' => $this->warningMessages($parsedResponse),
            'provider_errors' => is_array($parsedResponse['errors'] ?? null) ? $parsedResponse['errors'] : [],
            'provider_warnings' => is_array($parsedResponse['warnings'] ?? null) ? $parsedResponse['warnings'] : [],
        ];
    }

    private function inferCancellationStatus(string $orderStatus): string
    {
        $normalized = strtoupper(trim($orderStatus));
        if (in_array($normalized, ['CANCELLED', 'CANCELED', 'CLOSED', 'VOIDED'], true)) {
            return 'cancelled';
        }

        return 'unknown';
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @return array<string, mixed>
     */
    public function normalizeVoidResponse(array $parsedResponse, array $existing = []): array
    {
        $parsed = is_array($parsedResponse['parsed'] ?? null) ? $parsedResponse['parsed'] : [];
        $order = is_array($parsed['order'] ?? null) ? $parsed['order'] : [];
        $tickets = is_array($parsed['ticket_doc_infos'] ?? null) ? $parsed['ticket_doc_infos'] : [];
        $ticketNumbers = [];
        foreach ($tickets as $ticket) {
            if (is_array($ticket) && ($ticket['ticket_number'] ?? '') !== '') {
                $ticketNumbers[] = (string) $ticket['ticket_number'];
            }
        }
        if ($ticketNumbers === [] && is_array($existing['ticket_numbers'] ?? null)) {
            $ticketNumbers = array_values(array_map('strval', $existing['ticket_numbers']));
        }

        $paymentTimeLimit = trim((string) (
            $order['payment_time_limit']
            ?? $parsed['payment_time_limit']
            ?? $existing['payment_time_limit']
            ?? ''
        ));

        return array_filter([
            'void_status' => 'voided',
            'ticketing_status' => 'voided',
            'interpreted_status' => PiaNdcBookingStatusInterpreter::STATUS_OPTION_PNR_AFTER_VOID,
            'ticket_numbers' => $ticketNumbers !== [] ? $ticketNumbers : null,
            'ticket_doc_infos' => $tickets !== [] ? $tickets : null,
            'has_blocking_ticket_numbers' => $this->hasBlockingTicketNumbers($tickets),
            'payment_time_limit' => $paymentTimeLimit !== '' ? $paymentTimeLimit : null,
            'order_status' => trim((string) ($order['status'] ?? '')) ?: null,
            'supplier_messages' => $this->warningMessages($parsedResponse),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, array<string, mixed>>  $segmentsById
     * @param  array<string, array<string, mixed>>  $journeysById
     * @param  array<string, mixed>  $dataLists
     */
    private function buildOffer(
        array $offer,
        SupplierConnection $connection,
        string $correlationId,
        string $shoppingRef,
        array $segmentsById,
        array $journeysById,
        array $dataLists,
    ): ?NormalizedFlightOfferData {
        $offerId = trim((string) ($offer['offer_id'] ?? ''));
        if ($offerId === '') {
            return null;
        }

        $journeyRefs = is_array($offer['journey_refs'] ?? null) ? $offer['journey_refs'] : [];
        $segmentRows = $this->resolveSegmentRows($journeyRefs, $journeysById, $segmentsById);
        if ($segmentRows === []) {
            return null;
        }

        $first = $segmentRows[0];
        $last = $segmentRows[array_key_last($segmentRows)];
        $departureAt = (string) ($first['departure_at'] ?? '');
        $arrivalAt = (string) ($last['arrival_at'] ?? '');
        $duration = $this->journeyDurationMinutes($segmentRows, $departureAt, $arrivalAt);
        $ownerCode = trim((string) ($offer['owner_code'] ?? ''));
        $price = is_array($offer['total_price'] ?? null) ? $offer['total_price'] : ['total' => 0, 'base' => 0, 'tax' => 0, 'currency' => 'PKR'];
        $offerItems = is_array($offer['offer_items'] ?? null) ? $offer['offer_items'] : [];
        $firstItem = $this->selectPrimaryOfferItem($offerItems);
        $paxRefs = is_array($firstItem['pax_refs'] ?? null) ? $firstItem['pax_refs'] : [];
        $offerItemRefs = [];
        foreach ($offerItems as $item) {
            if (! is_array($item)) {
                continue;
            }
            $itemPaxRefs = is_array($item['pax_refs'] ?? null) ? $item['pax_refs'] : $paxRefs;
            foreach ($itemPaxRefs as $paxRef) {
                $offerItemRefs[] = [
                    'offer_item_ref_id' => (string) ($item['offer_item_id'] ?? ''),
                    'pax_ref_id' => (string) $paxRef,
                ];
            }
            if ($offerItemRefs === [] && ($item['offer_item_id'] ?? '') !== '') {
                $offerItemRefs[] = [
                    'offer_item_ref_id' => (string) $item['offer_item_id'],
                    'pax_ref_id' => (string) ($paxRefs[0] ?? 'ADTPax-1'),
                ];
            }
        }

        $fareFamily = $this->resolveFareFamily($offer, $firstItem);
        $baggage = $this->resolveBaggageAllowance($firstItem, $dataLists);

        $providerContext = [
            'provider' => SupplierProvider::PiaNdc->value,
            'shopping_response_ref_id' => $shoppingRef,
            'offer_ref_id' => $offerId,
            'owner_code' => $ownerCode,
            'offer_item_refs' => $offerItemRefs,
            'offer_item_ref_id' => (string) ($firstItem['offer_item_id'] ?? 'OfferItem-1'),
            'pax_ref_id' => (string) ($paxRefs[0] ?? 'ADTPax-1'),
            'pax_journey_ref_ids' => $journeyRefs,
            'pax_segment_ref_ids' => array_values(array_filter(array_map(fn ($s) => (string) ($s['pax_segment_id'] ?? ''), $segmentRows))),
            'fare_basis' => (string) ($firstItem['fare_basis'] ?? ''),
            'fare_type_code' => (string) ($firstItem['fare_type_code'] ?? ''),
            'rbd' => (string) ($firstItem['rbd'] ?? $first['rbd'] ?? ''),
            'cabin_type' => (string) ($firstItem['cabin'] ?? 'Y'),
            'payment_time_limit' => (string) ($firstItem['payment_time_limit'] ?? ''),
            'search_correlation_id' => $correlationId,
        ];

        $validatingCarrier = strtoupper((string) ($ownerCode ?: ($first['airline_code'] ?? 'PK')));
        $carrierDisplay = NormalizedFlightOfferData::deriveMultiSegmentCarrierDisplay(
            $segmentRows,
            $validatingCarrier,
            'PIA / Pakistan International Airlines',
        );

        return new NormalizedFlightOfferData(
            offer_id: 'pia-ndc-'.substr(sha1($offerId.$correlationId), 0, 16),
            supplier_provider: SupplierProvider::PiaNdc->value,
            supplier_connection_id: $connection->id,
            airline_code: $carrierDisplay['primary_display_carrier'] ?: ($validatingCarrier ?: 'PK'),
            airline_name: $carrierDisplay['headline_airline_name'],
            flight_number: $carrierDisplay['headline_flight_number'],
            origin: (string) ($first['origin'] ?? ''),
            destination: (string) ($last['destination'] ?? ''),
            departure_at: $departureAt,
            arrival_at: $arrivalAt,
            duration_minutes: $duration,
            stops: max(0, count($segmentRows) - 1),
            cabin: $this->normalizeCabin((string) ($firstItem['cabin'] ?? 'ECONOMY')),
            fare_family: $fareFamily,
            refundable: false,
            seats_left: null,
            segments: $segmentRows,
            baggage: $baggage,
            fare_breakdown: new FareBreakdownData(
                base_fare: (float) ($price['base'] ?? 0),
                taxes: (float) ($price['tax'] ?? 0),
                supplier_fees: 0,
                supplier_total: (float) ($price['total'] ?? 0),
                currency: (string) ($price['currency'] ?? 'PKR'),
            ),
            expires_at: ($firstItem['payment_time_limit'] ?? '') !== '' ? (string) $firstItem['payment_time_limit'] : null,
            raw_reference: $offerId,
            raw_payload: ['provider_context' => $providerContext],
            marketing_carrier_chain: $carrierDisplay['marketing_carrier_chain'],
            operating_carrier_chain: $carrierDisplay['operating_carrier_chain'],
            validating_carrier: $validatingCarrier ?: 'PK',
            primary_display_carrier: $carrierDisplay['primary_display_carrier'] ?: ($validatingCarrier ?: 'PK'),
            mixed_carrier: $carrierDisplay['mixed_carrier'],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $offerItems
     * @return array<string, mixed>
     */
    private function selectPrimaryOfferItem(array $offerItems): array
    {
        foreach ($offerItems as $item) {
            if (! is_array($item)) {
                continue;
            }
            $paxRefs = is_array($item['pax_refs'] ?? null) ? $item['pax_refs'] : [];
            $isAdult = $paxRefs === [] || in_array('ADTPax-1', $paxRefs, true) || in_array('ADT', $paxRefs, true);
            if (($item['mandatory'] ?? true) && $isAdult) {
                return $item;
            }
        }

        return is_array($offerItems[0] ?? null) ? $offerItems[0] : [];
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $firstItem
     */
    private function resolveFareFamily(array $offer, array $firstItem): ?string
    {
        $priceClass = trim((string) ($offer['price_class_text'] ?? ''));
        if ($priceClass !== '') {
            return $priceClass;
        }

        $fareType = trim((string) ($firstItem['fare_type_code'] ?? ''));
        if ($fareType === '') {
            return null;
        }

        return match (strtoupper($fareType)) {
            'ECO' => 'ECO LIGHT',
            default => $fareType,
        };
    }

    /**
     * @param  array<string, mixed>  $firstItem
     * @param  array<string, mixed>  $dataLists
     */
    private function resolveBaggageAllowance(array $firstItem, array $dataLists): BaggageAllowanceData
    {
        $serviceDefinitions = is_array($dataLists['service_definitions'] ?? null) ? $dataLists['service_definitions'] : [];
        $baggageAllowances = is_array($dataLists['baggage_allowances'] ?? null) ? $dataLists['baggage_allowances'] : [];
        $definitionsById = [];
        foreach ($serviceDefinitions as $definition) {
            if (! is_array($definition)) {
                continue;
            }
            $id = (string) ($definition['service_definition_id'] ?? '');
            if ($id !== '') {
                $definitionsById[$id] = $definition;
            }
        }
        $allowancesById = [];
        foreach ($baggageAllowances as $allowance) {
            if (! is_array($allowance)) {
                continue;
            }
            $id = (string) ($allowance['baggage_allowance_id'] ?? '');
            if ($id !== '') {
                $allowancesById[$id] = $allowance;
            }
        }

        $checked = null;
        foreach (is_array($firstItem['service_definition_ref_ids'] ?? null) ? $firstItem['service_definition_ref_ids'] : [] as $serviceRef) {
            $definition = $definitionsById[(string) $serviceRef] ?? null;
            if (! is_array($definition)) {
                continue;
            }
            $bagRef = (string) ($definition['baggage_allowance_ref_id'] ?? '');
            $allowance = $bagRef !== '' ? ($allowancesById[$bagRef] ?? null) : null;
            if (! is_array($allowance)) {
                continue;
            }
            $weight = trim((string) ($allowance['maximum_weight'] ?? ''));
            $unit = trim((string) ($allowance['unit_code'] ?? 'KG'));
            if ($weight !== '') {
                $checked = $weight.' '.$unit;

                break;
            }
        }

        return new BaggageAllowanceData(checked: $checked, cabin: null, summary: $checked);
    }

    private function normalizeCabin(string $cabin): string
    {
        $normalized = strtoupper(trim($cabin));

        return match ($normalized) {
            'Y', 'ECONOMY' => 'ECONOMY',
            'C', 'J', 'BUSINESS' => 'BUSINESS',
            'F', 'FIRST' => 'FIRST',
            default => $normalized !== '' ? $normalized : 'ECONOMY',
        };
    }

    /**
     * @param  array<string, mixed>  $dataLists
     * @return array<string, array<string, mixed>>
     */
    private function indexSegments(array $dataLists): array
    {
        $indexed = [];
        foreach (is_array($dataLists['pax_segments'] ?? null) ? $dataLists['pax_segments'] : [] as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $id = (string) ($segment['pax_segment_id'] ?? '');
            if ($id !== '') {
                $indexed[$id] = $segment;
            }
        }

        return $indexed;
    }

    /**
     * @param  array<string, mixed>  $dataLists
     * @return array<string, array<string, mixed>>
     */
    private function indexJourneys(array $dataLists): array
    {
        $indexed = [];
        foreach (is_array($dataLists['pax_journeys'] ?? null) ? $dataLists['pax_journeys'] : [] as $journey) {
            if (! is_array($journey)) {
                continue;
            }
            $id = (string) ($journey['pax_journey_id'] ?? '');
            if ($id !== '') {
                $indexed[$id] = $journey;
            }
        }

        return $indexed;
    }

    /**
     * @param  list<string>  $journeyRefs
     * @param  array<string, array<string, mixed>>  $journeysById
     * @param  array<string, array<string, mixed>>  $segmentsById
     * @return list<array<string, mixed>>
     */
    private function resolveSegmentRows(array $journeyRefs, array $journeysById, array $segmentsById): array
    {
        $rows = [];
        foreach ($journeyRefs as $journeyRef) {
            $journey = $journeysById[$journeyRef] ?? null;
            if (! is_array($journey)) {
                continue;
            }
            foreach (is_array($journey['pax_segment_refs'] ?? null) ? $journey['pax_segment_refs'] : [] as $segmentRef) {
                $segment = $segmentsById[(string) $segmentRef] ?? null;
                if (! is_array($segment)) {
                    continue;
                }
                $rows[] = [
                    'pax_segment_id' => (string) ($segment['pax_segment_id'] ?? ''),
                    'airline_code' => (string) ($segment['marketing_carrier'] ?? $segment['carrier'] ?? 'PK'),
                    'operating_airline_code' => (string) ($segment['operating_carrier'] ?? $segment['carrier'] ?? 'PK'),
                    'flight_number' => (string) ($segment['flight_number'] ?? ''),
                    'operating_flight_number' => (string) ($segment['operating_flight_number'] ?? $segment['flight_number'] ?? ''),
                    'origin' => (string) ($segment['departure_airport'] ?? ''),
                    'destination' => (string) ($segment['arrival_airport'] ?? ''),
                    'departure_at' => (string) ($segment['departure_at'] ?? ''),
                    'arrival_at' => (string) ($segment['arrival_at'] ?? ''),
                    'departure_airport' => (string) ($segment['departure_airport'] ?? ''),
                    'arrival_airport' => (string) ($segment['arrival_airport'] ?? ''),
                    'duration_minutes' => $this->isoDurationMinutes((string) ($segment['duration'] ?? '')),
                    'aircraft_type' => (string) ($segment['aircraft_type'] ?? ''),
                    'segment_type_code' => (string) ($segment['segment_type_code'] ?? ''),
                    'rbd' => (string) ($segment['rbd'] ?? ''),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $segmentRows
     */
    private function journeyDurationMinutes(array $segmentRows, string $departureAt, string $arrivalAt): int
    {
        $segmentDuration = 0;
        foreach ($segmentRows as $segment) {
            $segmentDuration += (int) ($segment['duration_minutes'] ?? 0);
        }
        if ($segmentDuration > 0) {
            return $segmentDuration;
        }

        return $this->durationMinutes($departureAt, $arrivalAt);
    }

    private function isoDurationMinutes(string $duration): int
    {
        if ($duration === '' || ! preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/', $duration, $matches)) {
            return 0;
        }

        return ((int) ($matches[1] ?? 0) * 60) + (int) ($matches[2] ?? 0);
    }

    private function durationMinutes(string $departureAt, string $arrivalAt): int
    {
        try {
            $dep = Carbon::parse($departureAt);
            $arr = Carbon::parse($arrivalAt);

            return max(0, (int) $dep->diffInMinutes($arr));
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @return list<string>
     */
    private function warningMessages(array $parsedResponse): array
    {
        $messages = [];
        foreach (is_array($parsedResponse['warnings'] ?? null) ? $parsedResponse['warnings'] : [] as $warning) {
            if (is_array($warning) && ($warning['message'] ?? '') !== '') {
                $messages[] = (string) $warning['message'];
            }
        }

        return $messages;
    }
}
