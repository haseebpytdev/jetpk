<?php

namespace App\Support\Sabre\Scenario;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Services\Booking\BookingService;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\Gds\SabreFlightSearchNormalizer;
use App\Support\Bookings\IatiPersistedContextResolver;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use App\Support\References\CompactReferenceGenerator;
use App\Support\Security\SensitiveDataRedactor;

/**
 * Creates draft bookings from scenario-selected Sabre GDS offers for {@see SabreGdsLiveScenarioRunner}.
 */
final class SabreGdsLiveScenarioRunnerBookingFactory
{
    public function __construct(
        protected BookingService $bookingService,
        protected SabreBookingPayloadBuilder $payloadBuilder,
        protected SabreFlightSearchNormalizer $normalizer,
        protected CompactReferenceGenerator $referenceGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array{
     *     passenger: array<string, mixed>,
     *     contact: array<string, mixed>
     * }  $passengerBundle
     * @param  array{
     *     row: array<string, mixed>,
     *     snap: array<string, mixed>
     * }  $candidate
     * @param  array<string, mixed>|null  $selectedFareFamilyOption
     */
    public function create(
        SupplierConnection $connection,
        array $scenario,
        array $passengerBundle,
        array $candidate,
        ?array $selectedFareFamilyOption,
        ?string $fareOptionKey,
    ): Booking {
        $agency = Agency::query()->where('slug', 'asif-travels')->first()
            ?? Agency::query()->orderBy('id')->firstOrFail();

        $snap = is_array($candidate['snap'] ?? null) ? $candidate['snap'] : [];
        $row = is_array($candidate['row'] ?? null) ? $candidate['row'] : [];
        $prepared = $this->prepareCandidateOfferContext(
            $connection,
            $scenario,
            $snap,
            $row,
            $selectedFareFamilyOption,
            $fareOptionKey,
        );
        $snap = $prepared['snap'];
        $handoff = $prepared['handoff'];
        $selectedTotal = $prepared['selected_total'];
        $selectedFareFamilyOption = is_array($prepared['meta']['selected_fare_family_option'] ?? null)
            ? $prepared['meta']['selected_fare_family_option']
            : $selectedFareFamilyOption;

        $origin = (string) ($scenario['origin'] ?? '');
        $destination = (string) ($scenario['destination'] ?? '');
        $route = (string) ($row['route'] ?? $origin.'-'.$destination);
        $travelDate = (string) ($scenario['departure_date'] ?? '');
        $airline = (string) ($row['validating_carrier'] ?? '');
        $currency = (string) ($row['currency'] ?? 'PKR');

        $booking = $this->bookingService->createDraftBooking($agency);
        $booking->forceFill([
            'supplier' => SupplierProvider::Sabre->value,
            'route' => $route,
            'airline' => $airline,
            'travel_date' => $travelDate !== '' ? $travelDate : null,
            'payment_status' => 'unpaid',
            'source_channel' => 'scenario_runner',
            'selected_fare_total' => $selectedTotal > 0 ? $selectedTotal : null,
            'revalidated_fare_total' => $selectedTotal > 0 ? $selectedTotal : null,
            'meta' => $prepared['meta'],
        ])->save();

        $passenger = $passengerBundle['passenger'];
        $this->bookingService->attachPassengers($booking, [[
            'passenger_index' => (int) ($passenger['passenger_index'] ?? 1),
            'passenger_type' => (string) ($passenger['passenger_type'] ?? 'adult'),
            'is_lead_passenger' => true,
            'title' => $passenger['title'] ?? null,
            'first_name' => (string) ($passenger['first_name'] ?? ''),
            'last_name' => (string) ($passenger['last_name'] ?? ''),
            'date_of_birth' => $passenger['date_of_birth'] ?? null,
            'gender' => $passenger['gender'] ?? null,
            'nationality' => $passenger['nationality'] ?? null,
            'country_of_residence' => $passenger['country_of_residence'] ?? null,
            'passport_number' => $passenger['passport_number'] ?? null,
            'passport_issuing_country' => $passenger['passport_issuing_country'] ?? null,
            'passport_issue_date' => $passenger['passport_issue_date'] ?? null,
            'passport_expiry_date' => $passenger['passport_expiry_date'] ?? null,
        ]]);

        $contact = $passengerBundle['contact'];
        $this->bookingService->attachContact($booking, [
            'email' => (string) ($contact['email'] ?? ''),
            'phone' => (string) ($contact['phone'] ?? ''),
            'country' => (string) ($contact['country'] ?? ''),
        ]);

        if ($selectedTotal > 0) {
            $this->bookingService->attachFareBreakdown($booking, [
                'total' => $selectedTotal,
                'currency' => $currency,
            ]);
        }

        $booking->forceFill([
            'status' => BookingStatus::Pending,
            'booking_reference' => $this->referenceGenerator->generateUnique('bookings', 'booking_reference', 8),
            'submitted_at' => now(),
        ])->save();

        return $booking->fresh([
            'passengers', 'contact', 'fareBreakdown', 'supplierBookings', 'supplierBookingAttempts',
        ]);
    }

    /**
     * In-memory booking for plan-mode mixed-carrier payload preflight (no persist, no live PNR).
     *
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $snap
     * @param  array<string, mixed>  $row
     */
    public function buildPlanPreflightBooking(
        SupplierConnection $connection,
        array $scenario,
        array $snap,
        array $row,
    ): Booking {
        $prepared = $this->prepareCandidateOfferContext($connection, $scenario, $snap, $row, null, null);
        $origin = (string) ($scenario['origin'] ?? '');
        $destination = (string) ($scenario['destination'] ?? '');
        $route = (string) ($row['route'] ?? ($origin !== '' && $destination !== '' ? $origin.'-'.$destination : ''));

        $booking = new Booking([
            'supplier' => SupplierProvider::Sabre->value,
            'route' => $route,
            'airline' => (string) ($row['validating_carrier'] ?? ''),
            'travel_date' => (string) ($scenario['departure_date'] ?? '') ?: null,
            'selected_fare_total' => $prepared['selected_total'] > 0 ? $prepared['selected_total'] : null,
            'revalidated_fare_total' => $prepared['selected_total'] > 0 ? $prepared['selected_total'] : null,
            'meta' => $prepared['meta'],
        ]);

        $passenger = new BookingPassenger([
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
            'first_name' => 'Plan',
            'last_name' => 'Preflight',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'nationality' => 'PK',
        ]);
        $contact = new BookingContact([
            'email' => 'plan-preflight@example.invalid',
            'phone' => '3000000000',
            'country' => 'PK',
        ]);
        $booking->setRelation('passengers', collect([$passenger]));
        $booking->setRelation('contact', $contact);

        return $booking;
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $snap
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $selectedFareFamilyOption
     * @return array{
     *     snap: array<string, mixed>,
     *     handoff: array<string, mixed>,
     *     selected_total: float,
     *     meta: array<string, mixed>
     * }
     */
    protected function prepareCandidateOfferContext(
        SupplierConnection $connection,
        array $scenario,
        array $snap,
        array $row,
        ?array $selectedFareFamilyOption,
        ?string $fareOptionKey,
    ): array {
        $snap['supplier_provider'] = SupplierProvider::Sabre->value;
        $snap['supplier_connection_id'] = $connection->id;

        if ($fareOptionKey !== null && trim($fareOptionKey) !== '') {
            $applied = FlightOfferDisplayPresenter::applySelectedFareFamilyOptionToOffer($snap, trim($fareOptionKey));
            if (is_array($applied['offer'] ?? null)) {
                $snap = $applied['offer'];
            }
        }

        $snap = $this->normalizer->ensureSabreBookingContextOnCachedOffer($snap);
        $snap = $this->repairOfferSegmentBookingClasses($snap);
        $handoff = is_array($snap['sabre_booking_context'] ?? null) ? $snap['sabre_booking_context'] : [];
        $segments = is_array($snap['segments'] ?? null) ? array_values($snap['segments']) : [];
        if ($segments !== []) {
            $bookingClasses = $this->segmentStringListFromSegments($segments, 'booking_class');
            $fareBasisCodes = $this->segmentStringListFromSegments($segments, 'fare_basis_code');
            if ($this->nonEmptySegmentStringList($bookingClasses)) {
                $handoff['booking_classes_by_segment'] = $bookingClasses;
            }
            if ($this->nonEmptySegmentStringList($fareBasisCodes)) {
                $handoff['fare_basis_codes_by_segment'] = $fareBasisCodes;
            }
            $handoff['segment_slice_count'] = count($segments);
            $snap['sabre_booking_context'] = $handoff;
        }

        $selectedTotal = (float) ($selectedFareFamilyOption['displayed_price']
            ?? $selectedFareFamilyOption['price_total']
            ?? $row['total_fare']
            ?? 0);

        if ($selectedFareFamilyOption === null && $segments !== []) {
            $selectedFareFamilyOption = [
                'brand_code' => strtoupper(trim((string) ($handoff['selected_brand_code'] ?? $handoff['brand_code'] ?? 'ECONOMY'))),
                'displayed_price' => $selectedTotal > 0 ? $selectedTotal : null,
                'booking_classes_by_segment' => $handoff['booking_classes_by_segment'] ?? [],
                'fare_basis_codes_by_segment' => $handoff['fare_basis_codes_by_segment'] ?? [],
            ];
        }

        $sanitizedFare = $this->payloadBuilder->sanitizeSelectedFareFamilyForSabreContext(
            $selectedFareFamilyOption,
            $fareOptionKey,
        );
        if ($sanitizedFare !== []) {
            $handoff = $this->payloadBuilder->mergeSelectedFareFamilyIntoSabreBookingContext($handoff, $sanitizedFare);
            $snap['sabre_booking_context'] = $handoff;
        }
        if ($segments !== [] && $this->nonEmptySegmentStringList($handoff['booking_classes_by_segment'] ?? [])) {
            $handoff['ready_for_booking_payload'] = true;
            $handoff['validating_carrier'] = strtoupper(trim((string) ($row['validating_carrier'] ?? $snap['validating_carrier'] ?? '')));
            $snap['sabre_booking_context'] = $handoff;
        }

        $origin = (string) ($scenario['origin'] ?? '');
        $destination = (string) ($scenario['destination'] ?? '');
        $travelDate = (string) ($scenario['departure_date'] ?? '');
        $currency = (string) ($row['currency'] ?? 'PKR');
        $searchCriteria = [
            'trip_type' => (string) ($scenario['trip_type'] ?? 'one_way'),
            'origin' => $origin,
            'destination' => $destination,
            'depart_date' => $travelDate,
            'return_date' => $scenario['return_date'] ?? null,
        ];

        $meta = IatiPersistedContextResolver::enrichMetaForPersistence(
            [
                'flight_offer_snapshot' => SensitiveDataRedactor::redact($snap),
                'normalized_offer_snapshot' => SensitiveDataRedactor::redact($snap),
                'distribution_channel' => 'gds',
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $connection->id,
                'search_criteria' => $searchCriteria,
                'origin_channel' => 'scenario_runner',
                'payment_mode' => 'pay_later_booking_request',
                'defer_supplier_booking_to_manual_review' => false,
                'validated_at' => now()->toIso8601String(),
                'supplier_total' => $selectedTotal,
                'supplier_currency' => $currency,
                'selected_fare_family_option' => $selectedFareFamilyOption,
                'fare_option_key' => $fareOptionKey,
                'sabre_booking_context' => $handoff,
                'scenario_runner' => true,
                'scenario_preset' => $scenario['preset'] ?? null,
            ],
            SupplierProvider::Sabre->value,
            $fareOptionKey ?? '',
        );

        return [
            'snap' => $snap,
            'handoff' => $handoff,
            'selected_total' => $selectedTotal,
            'meta' => $meta,
        ];
    }

    /**
     * @param  array<string, mixed>  $snap
     * @return array<string, mixed>
     */
    protected function repairOfferSegmentBookingClasses(array $snap): array
    {
        $segments = is_array($snap['segments'] ?? null) ? array_values($snap['segments']) : [];
        if ($segments === []) {
            return $snap;
        }

        $raw = is_array($snap['raw_payload'] ?? null) ? $snap['raw_payload'] : [];
        $shopCtx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $ctxBooking = is_array($shopCtx['booking_class'] ?? null) ? array_values($shopCtx['booking_class']) : [];

        $repaired = false;
        foreach ($segments as $index => $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $bookingClass = strtoupper(trim((string) ($segment['booking_class'] ?? '')));
            if ($bookingClass !== '') {
                continue;
            }
            $fallback = strtoupper(trim((string) (
                $segment['segment_cabin_code']
                ?? $segment['res_book_desig']
                ?? $segment['class_of_service']
                ?? ($ctxBooking[$index] ?? '')
            )));
            if ($fallback !== '' && strlen($fallback) <= 2) {
                $segments[$index]['booking_class'] = $fallback;
                $repaired = true;
            }
        }

        if (! $repaired) {
            return $snap;
        }

        $snap['segments'] = $segments;
        $handoff = is_array($snap['sabre_booking_context'] ?? null) ? $snap['sabre_booking_context'] : [];
        $handoff['booking_classes_by_segment'] = $this->segmentStringListFromSegments($segments, 'booking_class');
        if ($this->nonEmptySegmentStringList($handoff['booking_classes_by_segment'])) {
            $handoff['ready_for_booking_payload'] = true;
        }
        $snap['sabre_booking_context'] = $handoff;

        return $snap;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    protected function segmentStringListFromSegments(array $segments, string $key): array
    {
        $values = [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $values[] = strtoupper(trim((string) ($segment[$key] ?? '')));
        }

        return array_values($values);
    }

    /**
     * @param  mixed  $values
     */
    protected function nonEmptySegmentStringList(mixed $values): bool
    {
        if (! is_array($values) || $values === []) {
            return false;
        }
        foreach ($values as $value) {
            if (trim((string) $value) === '') {
                return false;
            }
        }

        return true;
    }
}
