<?php

namespace App\Http\Controllers\Frontend;

use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Enums\UserAccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\StoreBookingPassengersRequest;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingHoldSession;
use App\Models\SupplierBookingAttempt;
use App\Models\User;
use App\Services\Booking\BookingDraftService;
use App\Services\Booking\BookingProviderRouter;
use App\Services\Booking\BookingService;
use App\Services\Booking\InternationalRouteDetector;
use App\Services\Bookings\FareHoldService;
use App\Services\Communication\BookingCommunicationService;
use App\Services\FlightSearch\FlightDeparturePolicy;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Suppliers\OfferValidationService;
use App\Services\Suppliers\Sabre\Gds\SabreSelectedOfferRevalidationGate;
use App\Services\Suppliers\Sabre\SabreBookingOfferRefreshService;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreFlightSearchNormalizer;
use App\Services\TravelData\AirlineBrandingService;
use App\Support\Bookings\CheckoutSupplierIdentity;
use App\Support\Bookings\ComplexItineraryPolicy;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreHostRejectionFingerprintMatcher;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use App\Support\FlightSearch\SabreOfferFreshness;
use App\Support\Geo\CountryList;
use App\Support\ProviderUnstableTestMode;
use App\Support\PublicBooking;
use App\Support\Security\SensitiveDataRedactor;
use App\Support\Ui\MobileViewPreference;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use InvalidArgumentException;

class BookingController extends Controller
{
    /** Session flag: next passengers GET after a stale-offer recovery redirect must not run recovery again (prevents redirect loops). */
    public const SESSION_BOOKING_AFTER_STALE_RECOVERY = 'ota_booking_after_stale_recovery';

    public function __construct(
        protected BookingDraftService $bookingDraft,
        protected FlightSearchService $flightSearch,
        protected FlightSearchResultStore $searchStore,
        protected BookingService $bookingService,
        protected FareHoldService $fareHoldService,
        protected OfferValidationService $offerValidationService,
        protected BookingProviderRouter $bookingProviderRouter,
        protected AirlineBrandingService $airlineBranding,
        protected FlightDeparturePolicy $departurePolicy,
        protected SabreBookingService $sabreBookingService,
        protected SabreBookingOfferRefreshService $sabreOfferRefreshService,
        protected SabreSelectedOfferRevalidationGate $sabreSelectedOfferRevalidationGate,
        protected SabreOfferFreshness $sabreOfferFreshness,
        protected BookingCommunicationService $bookingCommunicationService,
        protected MobileViewPreference $mobileViewPreference,
    ) {}

    public function passengers(StoreBookingPassengersRequest $request): View|RedirectResponse
    {
        $this->logBookingRouteEntry($request);

        if ($request->isMethod('post')) {
            $validated = $request->validated();

            $selectedOfferId = trim((string) ($validated['offer_id'] ?? $validated['flight_id'] ?? ''));
            if ($selectedOfferId === '') {
                return redirect()->route('flights.search')->withErrors(['flight_id' => __('Selected flight is required.')]);
            }

            $merge = [
                'flight_id' => $selectedOfferId,
                'offer_id' => $selectedOfferId,
                'search_id' => trim((string) ($validated['search_id'] ?? '')),
                'fare_option_key' => trim((string) ($validated['fare_option_key'] ?? '')),
                'search_from' => $request->string('from')->toString(),
                'search_to' => $request->string('to')->toString(),
                'search_depart' => $request->string('depart')->toString(),
                'trip_type' => $request->string('trip_type', 'one_way')->toString(),
                'return_date' => $request->string('return_date')->toString(),
                'cabin' => $request->string('cabin', 'economy')->toString(),
                'adults' => max(1, (int) $request->input('adults', 1)),
                'children' => max(0, (int) $request->input('children', 0)),
                'infants' => max(0, (int) $request->input('infants', 0)),
            ];
            $this->bookingDraft->merge($merge);

            $searchId = trim((string) ($merge['search_id'] ?? ''));
            $criteria = $this->resolveCheckoutSearchCriteria($merge, $searchId);

            $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->first();
            if ($agency === null) {
                return redirect()->route('flights.search')->withErrors(['flight_id' => __('Booking is temporarily unavailable.')]);
            }

            $offer = null;
            if ($searchId !== '') {
                $offer = $this->searchStore->findOffer($searchId, $selectedOfferId);
            }
            // Offer may be missing from the cached slice (MAX_STORED_OFFERS); fall back to a fresh search before treating the session as expired.
            if ($offer === null) {
                $offers = $this->flightSearch->search($criteria, $agency, 'public_guest');
                $offer = collect($offers)->firstWhere('id', $selectedOfferId);
            }

            if ($offer === null) {
                return redirect()->route('flights.search')->withErrors(['flight_id' => __('Selected flight is no longer available.')]);
            }

            if (! $this->departurePolicy->offerMeetsLeadTimeForBooking($offer, $criteria)) {
                return redirect()->route('flights.search')
                    ->withErrors(['flight_id' => __(FlightDeparturePolicy::SAME_DAY_LEAD_MESSAGE)]);
            }

            $offer = $this->prepareSabreOfferForCheckoutHandoff($offer);

            $resultsQuery = $this->buildFlightsResultsQuery($criteria);

            $freshnessRedirect = $this->guardSabreOfferFreshnessAtCheckout(
                $agency,
                $offer,
                $criteria,
                $searchId,
                $resultsQuery,
            );
            if ($freshnessRedirect !== null) {
                return $freshnessRedirect;
            }

            $fareOptionRedirect = $this->applyValidatedFareOptionSelection(
                trim((string) ($validated['fare_option_key'] ?? '')),
                $searchId,
                $selectedOfferId,
                $offer,
                $criteria,
                $resultsQuery,
                $agency,
            );
            if ($fareOptionRedirect !== null) {
                return $fareOptionRedirect;
            }

            if (($offer['conversion_status'] ?? 'same_currency') === 'conversion_missing') {
                return redirect()->route('flights.results', $resultsQuery)
                    ->withErrors(['flight_id' => __('This fare requires currency review before booking.')]);
            }

            $validation = $this->offerValidationService->validateSelectedOffer($agency, $offer, $criteria + [
                'source_channel' => 'public_guest',
            ]);
            if ($validation->status === 'price_changed') {
                return $this->redirectToBookingPassengers([
                    'flight_id' => $selectedOfferId,
                    'offer_id' => $selectedOfferId,
                    'search_id' => $searchId,
                    'from' => $criteria['origin'],
                    'to' => $criteria['destination'],
                    'depart' => $criteria['depart_date'],
                    'trip_type' => (string) ($criteria['trip_type'] ?? 'one_way'),
                    'return_date' => (string) ($criteria['return_date'] ?? ''),
                    'cabin' => (string) ($criteria['cabin'] ?? 'economy'),
                    'adults' => (int) ($criteria['adults'] ?? 1),
                    'children' => (int) ($criteria['children'] ?? 0),
                    'infants' => (int) ($criteria['infants'] ?? 0),
                ], 'Fare changed during validation. Please review the updated fare before continuing.')
                    ->with('validation_result', $validation->toArray());
            }

            $normalizedValidated = null;
            $pricing = [];
            $protection = [];
            $passengerPricing = null;
            $passengerPricingAvailable = false;
            $holdSessionId = 0;

            $postCheckoutReady = $validation->is_valid && $validation->validated_offer !== null;

            if (! $postCheckoutReady) {
                if (in_array((string) $validation->status, ['unavailable', 'expired'], true)) {
                    if ($request->session()->get(self::SESSION_BOOKING_AFTER_STALE_RECOVERY)) {
                        $request->session()->forget(self::SESSION_BOOKING_AFTER_STALE_RECOVERY);

                        return redirect()->route('flights.results', $resultsQuery)
                            ->withErrors(['flight_id' => __('That fare is no longer available. Please select another updated option.')]);
                    }
                    $unstablePack = $this->tryCheckoutProviderUnstableTestMode(
                        $agency,
                        $criteria,
                        $offer,
                        $selectedOfferId,
                        $searchId,
                        (string) $validation->status,
                    );
                    if ($unstablePack !== null) {
                        $validation = $unstablePack['validation'];
                        $normalizedValidated = $unstablePack['normalizedValidated'];
                        $pricing = $unstablePack['pricing'];
                        $offer = $unstablePack['offer'];
                        $protection = $unstablePack['protection'];
                        $holdSessionId = $unstablePack['hold_session']->id;
                        $normalizedFare = is_array($normalizedValidated['fare_breakdown'] ?? null) ? $normalizedValidated['fare_breakdown'] : [];
                        $passengerPricing = is_array($normalizedFare['passenger_pricing'] ?? null) ? $normalizedFare['passenger_pricing'] : null;
                        $passengerPricingAvailable = (bool) ($normalizedFare['passenger_pricing_available'] ?? (is_array($passengerPricing) && $passengerPricing !== []));
                        $this->bookingDraft->merge([
                            'checkout_protection' => $protection,
                            'hold_session_id' => $holdSessionId,
                        ]);
                        $postCheckoutReady = true;
                    }

                    if (! $postCheckoutReady) {
                        $recovered = $this->attemptStaleOfferRecovery($agency, $criteria, $offer);
                        if ($recovered !== null) {
                            $selectedOfferId = (string) ($recovered['offer']['id'] ?? $selectedOfferId);
                            $searchId = (string) $recovered['search_id'];
                            $this->bookingDraft->merge([
                                'flight_id' => $selectedOfferId,
                                'offer_id' => $selectedOfferId,
                                'search_id' => $searchId,
                            ]);
                            $offer = $recovered['offer'];
                            $validation = $this->offerValidationService->validateSelectedOffer($agency, $offer, $criteria + [
                                'source_channel' => 'public_guest',
                            ]);
                            if ($validation->is_valid && $validation->validated_offer !== null) {
                                $normalizedValidated = $validation->validated_offer->toArray();
                                $pricing = $validation->meta['pricing_snapshot'] ?? [];
                                $offer = $this->presentValidatedOffer($normalizedValidated, $pricing);
                                $normalizedFare = is_array($normalizedValidated['fare_breakdown'] ?? null) ? $normalizedValidated['fare_breakdown'] : [];
                                $passengerPricing = is_array($normalizedFare['passenger_pricing'] ?? null) ? $normalizedFare['passenger_pricing'] : null;
                                $passengerPricingAvailable = (bool) ($normalizedFare['passenger_pricing_available'] ?? (is_array($passengerPricing) && $passengerPricing !== []));
                                $protection = $this->buildCheckoutProtectionState($offer, $validation, $selectedOfferId);
                                $holdSessionId = (int) ($this->bookingDraft->current()['hold_session_id'] ?? 0);
                                session()->flash('validation_alert', 'Fare was refreshed with the latest airline availability.');
                                $postCheckoutReady = true;
                            } else {
                                return redirect()->route('flights.results', $resultsQuery)
                                    ->withErrors(['flight_id' => __('That fare is no longer available. Please select another updated option.')]);
                            }
                        } else {
                            return redirect()->route('flights.results', $resultsQuery)
                                ->withErrors(['flight_id' => __('That fare is no longer available. Please select another updated option.')]);
                        }
                    }
                } elseif ((string) $validation->status === 'provider_error') {
                    return redirect()->route('flights.results', $resultsQuery)
                        ->withErrors(['flight_id' => __('Fare validation is temporarily unavailable. Please try again.')]);
                }

                if (! $postCheckoutReady) {
                    return redirect()->route('flights.results', $resultsQuery)
                        ->withErrors(['flight_id' => __('Selected fare is no longer available. Please choose another option.')]);
                }
            }

            if ($normalizedValidated === null) {
                $normalizedValidated = $validation->validated_offer->toArray();
                $pricing = $validation->meta['pricing_snapshot'] ?? [];
                $normalizedValidated = $this->prepareSabreOfferForCheckoutHandoff($normalizedValidated);
                $offer = $this->presentValidatedOffer($normalizedValidated, $pricing);
                $normalizedFare = is_array($normalizedValidated['fare_breakdown'] ?? null) ? $normalizedValidated['fare_breakdown'] : [];
                $passengerPricing = is_array($normalizedFare['passenger_pricing'] ?? null) ? $normalizedFare['passenger_pricing'] : null;
                $passengerPricingAvailable = (bool) ($normalizedFare['passenger_pricing_available'] ?? (is_array($passengerPricing) && $passengerPricing !== []));
                $protection = $this->buildCheckoutProtectionState($offer, $validation, $selectedOfferId);
                $holdSessionId = (int) ($this->bookingDraft->current()['hold_session_id'] ?? 0);
            } else {
                $normalizedValidated = $this->prepareSabreOfferForCheckoutHandoff($normalizedValidated);
                $offer = $this->presentValidatedOffer($normalizedValidated, $pricing);
            }

            $sabreBookingContext = $this->sabreBookingContextFromOffer($normalizedValidated);

            try {
                $checkoutSupplier = CheckoutSupplierIdentity::fromNormalizedValidatedOffer($normalizedValidated);
            } catch (InvalidArgumentException $e) {
                Log::warning('booking.checkout.supplier_identity_missing', [
                    'reason' => $e->getMessage(),
                ]);

                return redirect()->route('flights.results', $resultsQuery)
                    ->withErrors(['flight_id' => __('Selected fare is missing supplier information. Please search again and select a flight.')]);
            }

            $blockedMsg = $this->bookingProviderRouter->checkoutBlockedMessage($checkoutSupplier['supplier_provider']);
            if ($blockedMsg !== null) {
                $reason = $checkoutSupplier['supplier_provider'] === SupplierProvider::Sabre->value
                    ? 'sabre_booking_not_implemented'
                    : 'unsupported_supplier_checkout';
                Log::notice('provider_routing_blocked', [
                    'reason' => $reason,
                    'provider' => $checkoutSupplier['supplier_provider'],
                    'stage' => 'passengers_post',
                ]);

                return redirect()->route('flights.results', $resultsQuery)
                    ->withErrors(['flight_id' => $blockedMsg]);
            }

            $routeStr = FlightOfferDisplayPresenter::formatCriteriaRouteLabel($criteria);
            if ($routeStr === '') {
                $routeStr = $criteria['origin'].' → '.$criteria['destination'];
            }
            $offer = FlightOfferDisplayPresenter::enrichOfferSnapshotForBooking($offer, $criteria);
            $complexItinerary = in_array((string) ($criteria['trip_type'] ?? 'one_way'), ['round_trip', 'multi_city'], true);
            $isSabreCheckout = $checkoutSupplier['supplier_provider'] === SupplierProvider::Sabre->value;
            $deferComplexSabrePnr = $isSabreCheckout && $complexItinerary;
            $airlineStr = ($offer['airline_name'] ?? '').' ('.($offer['airline_code'] ?? ($offer['carrier_code'] ?? '')).')';
            $travelDate = Carbon::parse($offer['depart_at'] ?? $criteria['depart_date'])->toDateString();
            if ($sabreBookingContext === [] && ($checkoutSupplier['supplier_provider'] ?? '') === SupplierProvider::Sabre->value) {
                Log::notice('booking.passenger_details.sabre_context_missing', [
                    'has_offer_id' => trim($selectedOfferId) !== '',
                    'has_search_id' => trim((string) ($validated['search_id'] ?? '')) !== '',
                    'provider' => (string) ($checkoutSupplier['supplier_provider'] ?? ''),
                ]);
            }
            $distributionChannel = $this->distributionChannelForBookingMeta($normalizedValidated, $offer);
            if ($isSabreCheckout && $distributionChannel === null) {
                Log::info('booking.sabre_channel_unknown', [
                    'reason_code' => 'sabre_channel_unknown',
                    'offer_id' => $selectedOfferId,
                ]);
            }
            $protection = $this->finalizeCheckoutProtection(
                $request,
                $protection,
                $searchId,
                $selectedOfferId,
                trim((string) ($validated['fare_option_key'] ?? '')),
            );
            $this->bookingDraft->merge(['checkout_protection' => $protection]);

            $booking = DB::transaction(function () use ($agency, $validated, $offer, $pricing, $criteria, $routeStr, $airlineStr, $travelDate, $validation, $normalizedValidated, $request, $holdSessionId, $protection, $selectedOfferId, $passengerPricing, $passengerPricingAvailable, $checkoutSupplier, $complexItinerary, $deferComplexSabrePnr, $sabreBookingContext, $distributionChannel, $searchId): Booking {
                $leadIdx = (int) ($validated['lead_passenger_index'] ?? 0);
                $passengersInput = (array) ($validated['passengers'] ?? []);
                $leadPassenger = $passengersInput[$leadIdx] ?? ($passengersInput[0] ?? []);
                $leadFirstName = trim((string) ($leadPassenger['first_name'] ?? ''));
                $leadLastName = trim((string) ($leadPassenger['last_name'] ?? ''));
                if (! Auth::check() && ($validated['create_account'] ?? false)) {
                    $accountName = trim((string) ($validated['contact_name'] ?? '')) !== ''
                        ? trim((string) $validated['contact_name'])
                        : trim($leadFirstName.' '.$leadLastName);
                    $user = User::query()->create([
                        'name' => $accountName,
                        'email' => $validated['email'],
                        'password' => Hash::make((string) $validated['password']),
                        'account_type' => AccountType::Customer,
                        'status' => UserAccountStatus::Active,
                        'current_agency_id' => $agency->id,
                        'meta' => [
                            'first_name' => $leadFirstName,
                            'last_name' => $leadLastName,
                            'phone' => $validated['phone'],
                            'registered_via' => 'checkout_inline',
                        ],
                    ]);
                    event(new Registered($user));
                    Auth::login($user);
                    $request->session()->regenerate();
                }

                $actor = Auth::user();
                $customer = ($actor instanceof User && $actor->isCustomer()) ? $actor : null;

                $booking = $this->bookingService->createDraftBooking($agency, $customer);
                $booking->forceFill([
                    'supplier' => $checkoutSupplier['supplier_provider'],
                    'route' => $routeStr,
                    'airline' => $airlineStr,
                    'travel_date' => $travelDate,
                    'payment_status' => 'unpaid',
                    'source_channel' => 'public_guest',
                    'hold_session_id' => $holdSessionId > 0 ? $holdSessionId : null,
                    'supplier_hold_status' => (string) ($protection['hold_status'] ?? 'not_started'),
                    'price_guarantee_expires_at' => $protection['price_guarantee_expires_at'] ?? null,
                    'payment_required_by' => $protection['payment_required_by'] ?? null,
                    'meta' => [
                        'flight_offer_snapshot' => SensitiveDataRedactor::redact($offer),
                        'normalized_offer_snapshot' => SensitiveDataRedactor::redact($offer),
                        ...($distributionChannel !== null ? ['distribution_channel' => $distributionChannel] : []),
                        'supplier_provider' => $checkoutSupplier['supplier_provider'],
                        'supplier_connection_id' => $checkoutSupplier['supplier_connection_id'],
                        'search_criteria' => $criteria,
                        'pricing_snapshot' => SensitiveDataRedactor::redact($pricing),
                        'applied_rules' => SensitiveDataRedactor::redact($pricing['applied_rules'] ?? []),
                        'offer_validation_status' => $validation->status,
                        'defer_supplier_booking_to_manual_review' => (bool) ($protection['provider_unstable_test_mode'] ?? false) || $deferComplexSabrePnr,
                        'supplier_pnr_deferred_reason' => $deferComplexSabrePnr ? ComplexItineraryPolicy::DEFER_REASON : null,
                        'provider_unstable_test_mode' => (bool) ($protection['provider_unstable_test_mode'] ?? false),
                        'validated_at' => now()->toIso8601String(),
                        'original_offer_id' => $selectedOfferId,
                        'supplier_total' => (float) ($protection['supplier_total'] ?? 0),
                        'supplier_currency' => (string) ($protection['supplier_currency'] ?? 'PKR'),
                        'price_changed' => (bool) ($protection['price_changed'] ?? false),
                        'offer_validated_at' => (string) ($protection['offer_validated_at'] ?? now()->toIso8601String()),
                        'payment_requirements' => $protection['payment_requirements'] ?? [],
                        'protection_mode' => (string) ($protection['protection_mode'] ?? 'instant_payment_required'),
                        'requires_instant_payment' => (bool) ($protection['requires_instant_payment'] ?? true),
                        'hold_supported' => (bool) ($protection['hold_supported'] ?? false),
                        'price_guaranteed' => (bool) ($protection['price_guaranteed'] ?? false),
                        'offer_expires_at' => $protection['offer_expires_at'] ?? null,
                        'price_guarantee_expires_at' => $protection['price_guarantee_expires_at'] ?? null,
                        'checkout_lock_started_at' => (string) ($protection['checkout_lock_started_at'] ?? now()->toIso8601String()),
                        'checkout_lock_expires_at' => (string) ($protection['checkout_lock_expires_at'] ?? $this->freshCheckoutLockTimestamps()['checkout_lock_expires_at']),
                        'validated_offer_snapshot' => SensitiveDataRedactor::redact(
                            FlightOfferDisplayPresenter::enrichOfferSnapshotForBooking($normalizedValidated, $criteria)
                        ),
                        'validation_warnings' => SensitiveDataRedactor::redact($validation->warnings),
                        'passenger_pricing' => $passengerPricing,
                        'passenger_pricing_available' => $passengerPricingAvailable,
                        'pricing_breakdown_available' => $passengerPricingAvailable,
                        'passenger_counts' => [
                            'adults' => (int) ($validated['adults'] ?? 1),
                            'children' => (int) ($validated['children'] ?? 0),
                            'infants' => (int) ($validated['infants'] ?? 0),
                            'total' => (int) (($validated['adults'] ?? 1) + ($validated['children'] ?? 0) + ($validated['infants'] ?? 0)),
                        ],
                        'lead_passenger_sequence' => $leadIdx + 1,
                        'checkout_search_id' => trim((string) ($validated['search_id'] ?? '')),
                        'checkout_offer_id' => $selectedOfferId,
                        'complex_itinerary_requires_staff_confirmation' => $complexItinerary,
                        'fare_option_key' => trim((string) ($validated['fare_option_key'] ?? '')),
                        'selected_fare_family_option' => $this->selectedFareFamilyOptionForMeta(),
                        'sabre_booking_context' => $sabreBookingContext,
                        ...$this->sabreOfferFreshnessMetaPatchForBooking($offer, $searchId),
                    ],
                ])->save();

                $mappedPassengers = collect($passengersInput)->values()->map(
                    function (array $passenger, int $idx) use ($leadIdx): array {
                        return [
                            'passenger_index' => $idx + 1,
                            'passenger_type' => (string) ($passenger['passenger_type'] ?? 'adult'),
                            'is_lead_passenger' => $idx === $leadIdx,
                            'title' => $passenger['title'] ?? null,
                            'first_name' => (string) ($passenger['first_name'] ?? ''),
                            'last_name' => (string) ($passenger['last_name'] ?? ''),
                            'date_of_birth' => $passenger['date_of_birth'] ?? null,
                            'nationality' => isset($passenger['nationality']) ? strtoupper((string) $passenger['nationality']) : null,
                            'gender' => $passenger['gender'] ?? null,
                            'passport_number' => isset($passenger['passport_number']) && trim((string) $passenger['passport_number']) !== ''
                                ? trim((string) $passenger['passport_number'])
                                : null,
                            'passport_issuing_country' => isset($passenger['passport_issuing_country']) && trim((string) $passenger['passport_issuing_country']) !== ''
                                ? strtoupper((string) $passenger['passport_issuing_country'])
                                : null,
                            'passport_expiry_date' => $passenger['passport_expiry_date'] ?? null,
                            'passport_issue_date' => $passenger['passport_issue_date'] ?? null,
                            'document_type' => $passenger['document_type'] ?? 'passport',
                            'national_id_number' => isset($passenger['national_id_number']) && trim((string) $passenger['national_id_number']) !== ''
                                ? trim((string) $passenger['national_id_number'])
                                : null,
                            'country_of_residence' => $passenger['country_of_residence'] ?? null,
                            'place_of_birth' => $passenger['place_of_birth'] ?? null,
                            'meta' => [
                                'traveler_type' => (string) ($passenger['passenger_type'] ?? 'adult'),
                            ],
                        ];
                    }
                )->all();
                $this->bookingService->attachPassengers($booking, $mappedPassengers);

                $this->bookingService->attachContact($booking, [
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                    'country' => $validated['country'] ?? null,
                    'address_line' => null,
                    'meta' => [
                        'contact_name' => trim((string) ($validated['contact_name'] ?? '')) !== ''
                            ? trim((string) $validated['contact_name'])
                            : trim($leadFirstName.' '.$leadLastName),
                    ],
                ]);

                $this->bookingService->attachFareBreakdown($booking, [
                    'base_fare' => $pricing['base_fare'],
                    'taxes' => $pricing['taxes'],
                    'fees' => $pricing['service_fee'],
                    'markup' => $pricing['admin_markup'] + $pricing['route_markup'] + $pricing['airline_markup'] + $pricing['agent_markup_or_commission'],
                    'discount' => 0,
                    'total' => $pricing['final_total'],
                    'currency' => $offer['currency'] ?? 'PKR',
                    'breakdown' => [
                        ['label' => 'Base fare', 'amount' => $pricing['base_fare']],
                        ['label' => 'Taxes & surcharges', 'amount' => $pricing['taxes']],
                        ['label' => 'Admin markup', 'amount' => $pricing['admin_markup']],
                        ['label' => 'Route markup', 'amount' => $pricing['route_markup']],
                        ['label' => 'Airline markup', 'amount' => $pricing['airline_markup']],
                        ['label' => 'Channel/agent markup', 'amount' => $pricing['agent_markup_or_commission']],
                        ['label' => 'Service fee', 'amount' => $pricing['service_fee']],
                        [
                            'passenger_pricing' => $passengerPricing,
                            'passenger_pricing_available' => $passengerPricingAvailable,
                            'passenger_counts' => [
                                'adults' => (int) ($validated['adults'] ?? 1),
                                'children' => (int) ($validated['children'] ?? 0),
                                'infants' => (int) ($validated['infants'] ?? 0),
                            ],
                        ],
                    ],
                ]);

                return $booking->fresh();
            });

            $activeHoldSession = null;
            if ($holdSessionId > 0) {
                BookingHoldSession::query()->whereKey($holdSessionId)->update([
                    'booking_id' => $booking->id,
                    'passenger_counts' => [
                        'adults' => (int) ($validated['adults'] ?? 1),
                        'children' => (int) ($validated['children'] ?? 0),
                        'infants' => (int) ($validated['infants'] ?? 0),
                        'total' => (int) (($validated['adults'] ?? 1) + ($validated['children'] ?? 0) + ($validated['infants'] ?? 0)),
                    ],
                    'hold_status' => in_array((string) ($protection['protection_mode'] ?? ''), ['hold_price_guaranteed', 'hold_no_price_guarantee'], true)
                        ? 'pending'
                        : 'not_supported',
                    'updated_at' => now(),
                ]);
                $activeHoldSession = BookingHoldSession::query()->find($holdSessionId);
            }

            $request->session()->put(PublicBooking::SESSION_BOOKING_ID, $booking->id);
            $this->bookingDraft->clear();

            $actor = Auth::user();
            if (! $actor instanceof User) {
                $actor = User::query()
                    ->where('current_agency_id', $booking->agency_id)
                    ->whereIn('account_type', [AccountType::Staff, AccountType::PlatformAdmin])
                    ->orderBy('id')
                    ->first();
            }
            if (in_array((string) (($booking->meta['protection_mode'] ?? '')), ['hold_price_guaranteed', 'hold_no_price_guarantee'], true)) {
                $holdAction = $this->fareHoldService->createHoldIfSupported(
                    $booking,
                    $actor instanceof User ? $actor : null,
                    fn (Booking $b, User $a) => $this->bookingProviderRouter->createSupplierBooking($b, $a, true)
                );
                $holdResult = $holdAction['result'];
                $meta = is_array($booking->meta) ? $booking->meta : [];
                $meta['supplier_hold_attempted_at'] = now()->toIso8601String();
                $meta['supplier_hold_action_status'] = (string) ($holdAction['status'] ?? 'not_supported');
                $meta['supplier_hold_success'] = (bool) ($holdResult?->success ?? false);
                $meta['supplier_hold_status'] = (string) ($holdResult?->status ?? ($holdAction['status'] ?? 'not_supported'));
                $meta['supplier_hold_reference'] = $holdResult?->supplier_reference;
                $meta['supplier_hold_pnr'] = $holdResult?->pnr;
                $meta['supplier_hold_warnings'] = $holdResult?->warnings ?? [];
                $booking->forceFill([
                    'meta' => $meta,
                    'supplier_hold_status' => $holdResult?->success ? 'held' : (($holdAction['status'] ?? '') === 'hold_pending_passenger_details' ? 'pending' : 'failed'),
                ])->save();
                if ($activeHoldSession !== null) {
                    BookingHoldSession::query()->whereKey($activeHoldSession->id)->update([
                        'supplier_order_id' => $holdResult?->supplier_reference,
                        'supplier_order_reference' => $holdResult?->pnr,
                        'hold_status' => $holdResult?->success ? 'held' : (($holdAction['status'] ?? '') === 'hold_pending_passenger_details' ? 'pending' : 'failed'),
                        'hold_order_snapshot' => [
                            'provider' => $holdResult?->provider,
                            'status' => $holdResult?->status,
                            'warnings' => $holdResult?->warnings ?? [],
                            'safe_summary' => $holdResult?->safe_summary ?? [],
                        ],
                        'last_error_safe' => $holdResult?->success ? null : ($holdResult?->error_message ?? 'Supplier hold not confirmed.'),
                        'updated_at' => now(),
                    ]);
                    if ($holdResult?->success) {
                        $this->fareHoldService->markHoldCompleted($booking, $activeHoldSession, $actor instanceof User ? $actor : null);
                    } else {
                        $this->fareHoldService->markHoldFailed($booking, $activeHoldSession, (string) ($holdResult?->error_message ?? 'Supplier hold not confirmed.'), $actor instanceof User ? $actor : null);
                    }
                }
            }

            return redirect()->route('booking.review');
        }

        $flightId = $request->string('flight_id')->toString();
        $offerId = $request->string('offer_id')->toString();
        $searchId = $request->string('search_id')->toString();
        if ($flightId !== '' || $offerId !== '' || $searchId !== '') {
            $draftMerge = [
                'flight_id' => $flightId !== '' ? $flightId : $offerId,
                'offer_id' => $offerId !== '' ? $offerId : $flightId,
                'search_id' => $searchId,
                'search_from' => $request->string('from')->toString(),
                'search_to' => $request->string('to')->toString(),
                'search_depart' => $request->string('depart')->toString(),
                'trip_type' => $request->string('trip_type', 'one_way')->toString(),
                'return_date' => $request->string('return_date')->toString(),
                'cabin' => $request->string('cabin', 'economy')->toString(),
                'adults' => max(1, (int) $request->input('adults', 1)),
                'children' => max(0, (int) $request->input('children', 0)),
                'infants' => max(0, (int) $request->input('infants', 0)),
            ];
            if ($request->filled('fare_option_key')) {
                $draftMerge['fare_option_key'] = $request->string('fare_option_key')->toString();
            }
            $this->bookingDraft->merge($draftMerge);
        }

        $draft = $this->bookingDraft->current();
        $effectiveFlightId = $flightId !== '' ? $flightId : (($draft['offer_id'] ?? '') !== '' ? $draft['offer_id'] : ($draft['flight_id'] ?? ''));

        $criteria = $this->resolveCheckoutSearchCriteria($draft, (string) ($draft['search_id'] ?? ''));

        $resultsQuery = $this->buildFlightsResultsQuery($criteria);

        $offer = null;
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->first();
        if ($effectiveFlightId !== '') {
            $offer = null;
            if (is_string($draft['search_id'] ?? null) && ($draft['search_id'] ?? '') !== '') {
                $offer = $this->searchStore->findOffer((string) $draft['search_id'], $effectiveFlightId);
            }
            if ($offer === null) {
                $offers = $agency !== null && $criteria['origin'] !== '' && $criteria['destination'] !== '' && $criteria['depart_date'] !== ''
                    ? $this->flightSearch->search($criteria, $agency, 'public_guest')
                    : [];
                $offer = collect($offers)->firstWhere('id', $effectiveFlightId);
            }
        }

        if ($effectiveFlightId !== '' && $offer === null) {
            $request->session()->forget(self::SESSION_BOOKING_AFTER_STALE_RECOVERY);

            return redirect()->route('flights.search')
                ->withErrors(['flight_id' => __('Selected flight is no longer available.')]);
        }

        $fareOptionKey = trim((string) ($draft['fare_option_key'] ?? ''));
        if ($fareOptionKey !== '') {
            $fareOptionRedirect = $this->applyValidatedFareOptionSelection(
                $fareOptionKey,
                (string) ($draft['search_id'] ?? ''),
                $effectiveFlightId,
                is_array($offer) ? $offer : null,
                $criteria,
                $resultsQuery,
                $agency,
            );
            if ($fareOptionRedirect !== null) {
                return $fareOptionRedirect;
            }
        }

        if ($offer !== null && $agency !== null) {
            $providerEarly = strtolower(trim((string) ($offer['supplier_provider'] ?? '')));
            if (($earlyBlock = $this->bookingProviderRouter->checkoutBlockedMessage($providerEarly)) !== null) {
                $reason = $providerEarly === SupplierProvider::Sabre->value
                    ? 'sabre_booking_not_implemented'
                    : 'unsupported_supplier_checkout';
                Log::notice('provider_routing_blocked', [
                    'reason' => $reason,
                    'provider' => $providerEarly !== '' ? $providerEarly : 'empty',
                    'stage' => 'passengers_get',
                ]);

                return redirect()->route('flights.results', $resultsQuery)
                    ->withErrors(['flight_id' => $earlyBlock]);
            }

            $freshnessRedirect = $this->guardSabreOfferFreshnessAtCheckout(
                $agency,
                $offer,
                $criteria,
                (string) ($draft['search_id'] ?? ''),
                $resultsQuery,
            );
            if ($freshnessRedirect !== null) {
                return $freshnessRedirect;
            }

            $holdPreparation = $this->fareHoldService->prepareCheckoutHold(
                searchId: (string) ($draft['search_id'] ?? ''),
                offerId: $effectiveFlightId,
                agency: $agency,
                user: auth()->user(),
                offer: $offer,
                criteria: $criteria + ['source_channel' => 'public_guest'],
                presentOffer: fn (array $normalized, array $pricing): array => $this->presentValidatedOffer($normalized, $pricing),
            );
            $validation = $holdPreparation['validation'];
            $checkoutReady = $validation->is_valid && $validation->validated_offer !== null;
            $mergedHoldFromUnstableTestMode = false;

            if (! $checkoutReady) {
                if (in_array((string) $validation->status, ['unavailable', 'expired'], true)) {
                    if ($request->session()->get(self::SESSION_BOOKING_AFTER_STALE_RECOVERY)) {
                        $request->session()->forget(self::SESSION_BOOKING_AFTER_STALE_RECOVERY);

                        return redirect()->route('flights.search')
                            ->withErrors(['flight_id' => __('That fare is no longer available. Please select another updated option.')]);
                    }
                    $unstablePack = $this->tryCheckoutProviderUnstableTestMode(
                        $agency,
                        $criteria,
                        $offer,
                        $effectiveFlightId,
                        (string) ($draft['search_id'] ?? ''),
                        (string) $validation->status,
                    );
                    if ($unstablePack !== null) {
                        $offer = $unstablePack['offer'];
                        $protection = $this->finalizeCheckoutProtection(
                            $request,
                            $unstablePack['protection'],
                            (string) ($draft['search_id'] ?? ''),
                            $effectiveFlightId,
                            $fareOptionKey,
                        );
                        $this->bookingDraft->merge([
                            'checkout_protection' => $protection,
                            'hold_session_id' => $unstablePack['hold_session']->id,
                        ]);
                        $checkoutReady = true;
                        $mergedHoldFromUnstableTestMode = true;
                    }
                }

                if (! $checkoutReady && in_array((string) $validation->status, ['unavailable', 'expired'], true)) {
                    $recovered = $this->attemptStaleOfferRecovery($agency, $criteria, $offer);
                    if ($recovered !== null) {
                        $effectiveFlightId = (string) ($recovered['offer']['id'] ?? $effectiveFlightId);
                        $this->bookingDraft->merge([
                            'flight_id' => $effectiveFlightId,
                            'offer_id' => $effectiveFlightId,
                            'search_id' => (string) $recovered['search_id'],
                        ]);

                        $request->session()->put(self::SESSION_BOOKING_AFTER_STALE_RECOVERY, true);

                        return $this->redirectToBookingPassengers([
                            'flight_id' => $effectiveFlightId,
                            'offer_id' => $effectiveFlightId,
                            'search_id' => (string) $recovered['search_id'],
                            'from' => $criteria['origin'],
                            'to' => $criteria['destination'],
                            'depart' => $criteria['depart_date'],
                            'trip_type' => (string) ($criteria['trip_type'] ?? 'one_way'),
                            'return_date' => (string) ($criteria['return_date'] ?? ''),
                            'cabin' => (string) ($criteria['cabin'] ?? 'economy'),
                            'adults' => (int) ($criteria['adults'] ?? 1),
                            'children' => (int) ($criteria['children'] ?? 0),
                            'infants' => (int) ($criteria['infants'] ?? 0),
                            'recovery_done' => '1',
                        ], 'Fare was refreshed with the latest airline availability.');
                    }

                    $request->session()->forget(self::SESSION_BOOKING_AFTER_STALE_RECOVERY);

                    return redirect()->route('flights.search')
                        ->withErrors(['flight_id' => __('That fare is no longer available. Please select another updated option.')]);
                }
                if (! $checkoutReady && (string) $validation->status === 'provider_error') {
                    $request->session()->forget(self::SESSION_BOOKING_AFTER_STALE_RECOVERY);

                    return redirect()->route('flights.search')
                        ->withErrors(['flight_id' => __('Fare validation is temporarily unavailable. Please try again.')]);
                }

                if (! $checkoutReady) {
                    $request->session()->forget(self::SESSION_BOOKING_AFTER_STALE_RECOVERY);

                    return redirect()->route('flights.search')
                        ->withErrors(['flight_id' => __('Selected flight is no longer available.')]);
                }
            }

            if ($checkoutReady && ! $mergedHoldFromUnstableTestMode) {
                $pricing = $validation->meta['pricing_snapshot'] ?? [];
                $normalizedValidated = $validation->validated_offer->toArray();
                $offer = $holdPreparation['presented_offer'];
                $protection = $this->finalizeCheckoutProtection(
                    $request,
                    $this->buildCheckoutProtectionState($offer, $validation, $effectiveFlightId),
                    (string) ($draft['search_id'] ?? ''),
                    $effectiveFlightId,
                    $fareOptionKey,
                );
                $holdSession = $holdPreparation['hold_session'];
                $this->bookingDraft->merge([
                    'checkout_protection' => $protection,
                    'hold_session_id' => $holdSession?->id,
                ]);
            }
        }

        if ($offer !== null) {
            if (! $this->departurePolicy->offerMeetsLeadTimeForBooking($offer, $criteria)) {
                $request->session()->forget(self::SESSION_BOOKING_AFTER_STALE_RECOVERY);

                return redirect()->route('flights.search')
                    ->withErrors(['flight_id' => __(FlightDeparturePolicy::SAME_DAY_LEAD_MESSAGE)]);
            }
        }

        $adults = (int) ($draft['adults'] ?? $criteria['adults'] ?? 1);
        $children = (int) ($draft['children'] ?? $criteria['children'] ?? 0);
        $infants = (int) ($draft['infants'] ?? $criteria['infants'] ?? 0);
        $expectedPassengers = [];
        $idx = 0;
        for ($i = 0; $i < $adults; $i++) {
            $expectedPassengers[] = ['index' => $idx++, 'type' => 'adult', 'label' => 'Adult'];
        }
        for ($i = 0; $i < $children; $i++) {
            $expectedPassengers[] = ['index' => $idx++, 'type' => 'child', 'label' => 'Child'];
        }
        for ($i = 0; $i < $infants; $i++) {
            $expectedPassengers[] = ['index' => $idx++, 'type' => 'infant', 'label' => 'Infant'];
        }

        $draft = $this->bookingDraft->current();

        $checkoutPresentation = null;
        $complexItineraryNotice = false;
        if ($offer !== null) {
            $displayOffer = FlightOfferDisplayPresenter::enrichOfferSnapshotForBooking($offer, $criteria);
            $checkoutPresentation = FlightOfferDisplayPresenter::buildPresentation(
                $displayOffer,
                $criteria,
                FlightOfferDisplayPresenter::airportCityMap(FlightOfferDisplayPresenter::collectIataCodes($displayOffer)),
            );
            $complexItineraryNotice = in_array((string) ($criteria['trip_type'] ?? 'one_way'), ['round_trip', 'multi_city'], true);
        }

        $request->session()->forget(self::SESSION_BOOKING_AFTER_STALE_RECOVERY);

        $viewData = [
            'draft' => $draft,
            'flightId' => $effectiveFlightId,
            'offer' => $offer,
            'criteria' => $criteria,
            'checkoutPresentation' => $checkoutPresentation,
            'complexItineraryNotice' => $complexItineraryNotice,
            'client' => config('ota-client', []),
            'validationResult' => session('validation_result'),
            'validationAlert' => session('validation_alert'),
            'airlineLogo' => $offer !== null
                ? $this->airlineBranding->getLogoForCode((string) ($offer['airline_code'] ?? ($offer['carrier_code'] ?? '')))
                : null,
            'hideInlineAccount' => Auth::check(),
            'isInternationalRoute' => app(InternationalRouteDetector::class)
                ->isInternational((string) ($draft['search_from'] ?? ''), (string) ($draft['search_to'] ?? '')),
            'pkDomesticTravelDocuments' => app(InternationalRouteDetector::class)
                ->nationalIdTravelDocumentsAllowedForOffer(
                    is_array($offer) ? $offer : null,
                    (string) ($draft['search_from'] ?? ''),
                    (string) ($draft['search_to'] ?? ''),
                ),
            'expectedPassengers' => $expectedPassengers,
            'passengerCountSummary' => [
                'adults' => $adults,
                'children' => $children,
                'infants' => $infants,
                'total' => $adults + $children + $infants,
            ],
            'checkoutProtection' => $draft['checkout_protection'] ?? null,
            'checkoutCountries' => CountryList::forSelect(),
            'checkoutPhoneDialCodes' => $this->checkoutPhoneDialCodes(),
            'checkoutContactPhone' => $this->checkoutContactPhoneParts($request, $draft),
            'checkoutContactPrefill' => $this->checkoutContactPrefillForUser(Auth::user()),
            'resultsBackUrl' => route('flights.results', $resultsQuery),
            'refreshSearchUrl' => $this->resolveRefreshSearchUrl($criteria),
        ];

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.bookings.passengers', $viewData);
        }

        return view('frontend.booking.passenger-details', $viewData);
    }

    public function review(Request $request): View|RedirectResponse
    {
        $this->logBookingRouteEntry($request);

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'booking_method' => ['required', 'string', 'in:pay_later,bank_transfer,office,pay_later_booking_request,offline_bank_transfer,office_confirmation,online_card'],
                'confirm_updated_fare' => ['nullable', 'boolean'],
            ]);

            $bookingId = $request->session()->get(PublicBooking::SESSION_BOOKING_ID);
            if ($bookingId === null) {
                return redirect()->route('flights.search');
            }

            $booking = Booking::query()->find($bookingId);
            if ($booking === null) {
                $request->session()->forget(PublicBooking::SESSION_BOOKING_ID);

                return redirect()->route('flights.search');
            }

            if ($booking->status !== BookingStatus::Draft || $booking->submitted_at !== null) {
                Log::info('checkout.duplicate_submit_blocked', [
                    'booking_id' => $booking->id,
                    'reason_code' => 'already_submitted',
                ]);

                return redirect()->route('booking.confirmation');
            }

            $meta = is_array($booking->meta) ? $booking->meta : [];

            $supplierEarly = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
            if ($supplierEarly === SupplierProvider::Sabre->value) {
                $freshnessSubmitRedirect = $this->guardSabreOfferFreshnessAtBookingSubmit($booking);
                if ($freshnessSubmitRedirect !== null) {
                    return $freshnessSubmitRedirect;
                }
                $booking->refresh();
                $meta = is_array($booking->meta) ? $booking->meta : [];
            }

            $revalidated = $this->revalidateCheckoutBeforeConfirmation($booking);
            $booking->refresh();
            $meta = is_array($booking->meta) ? $booking->meta : [];

            if (($revalidated['status'] ?? 'ok') === 'hold_expired') {
                Log::info('checkout.hold_expired', [
                    'booking_id' => $booking->id,
                    'reason_code' => 'hold_expired',
                ]);

                return redirect()->route('booking.review')
                    ->withErrors(['flight_id' => 'Your airline hold has expired. Please recheck the fare before continuing.'])
                    ->with('recheck_required', true);
            }
            if (($revalidated['status'] ?? 'ok') !== 'ok') {
                return $this->redirectToBookingPassengers([
                    'flight_id' => (string) ($meta['original_offer_id'] ?? ''),
                    'offer_id' => (string) ($meta['original_offer_id'] ?? ''),
                    'search_id' => (string) ($meta['checkout_search_id'] ?? ''),
                    'from' => (string) data_get($meta, 'search_criteria.origin', ''),
                    'to' => (string) data_get($meta, 'search_criteria.destination', ''),
                    'depart' => (string) data_get($meta, 'search_criteria.depart_date', ''),
                    'trip_type' => (string) data_get($meta, 'search_criteria.trip_type', 'one_way'),
                    'return_date' => (string) data_get($meta, 'search_criteria.return_date', ''),
                    'cabin' => (string) data_get($meta, 'search_criteria.cabin', 'economy'),
                    'adults' => (int) data_get($meta, 'search_criteria.adults', 1),
                    'children' => (int) data_get($meta, 'search_criteria.children', 0),
                    'infants' => (int) data_get($meta, 'search_criteria.infants', 0),
                ])->withErrors(['flight_id' => 'This fare is no longer available. Please choose another flight.']);
            }

            if (($revalidated['fare_changed'] ?? false)) {
                session()->flash('validation_alert', sprintf(
                    'Fare updated: old Rs %s → new Rs %s. Confirm below to continue with the new total, or return to search.',
                    number_format((float) ($revalidated['old_total'] ?? 0), 0),
                    number_format((float) ($revalidated['new_total'] ?? 0), 0)
                ));
            }

            if (($meta['requires_price_change_confirmation'] ?? false) && ! (bool) ($validated['confirm_updated_fare'] ?? false)) {
                return redirect()->route('booking.review')
                    ->withErrors(['confirm_updated_fare' => (string) __('The fare has changed. Please confirm below to continue with the new total, or return to search to pick another flight.')]);
            }

            $method = (string) $validated['booking_method'];
            $canonical = match ($method) {
                'pay_later', 'pay_later_booking_request' => 'pay_later_booking_request',
                'bank_transfer', 'offline_bank_transfer' => 'offline_bank_transfer',
                'office', 'office_confirmation' => 'office_confirmation',
                'online_card' => 'online_card',
                default => $method,
            };
            $offlineManual = in_array($canonical, ['offline_bank_transfer', 'pay_later_booking_request', 'office_confirmation'], true);

            $fareAccepted = (bool) ($validated['confirm_updated_fare'] ?? false);

            $meta['booking_method'] = $canonical;
            $meta['confirmation_method'] = $canonical;
            $meta['lifecycle_phase'] = $offlineManual ? 'awaiting_payment' : ($canonical === 'online_card' ? 'pending_online_payment' : 'submitted');
            if ($offlineManual) {
                $meta['ticketing_phase'] = 'ticketing_pending';
            }

            $bookingPatch = [
                'meta' => $meta,
                'confirmation_method' => $canonical,
            ];
            if ($fareAccepted) {
                $bookingPatch['fare_change_accepted_at'] = now();
            }
            if ($offlineManual) {
                $bookingPatch['ticketing_status'] = 'pending';
            }
            $booking->forceFill($bookingPatch)->save();
            $booking->refresh();
            $meta = is_array($booking->meta) ? $booking->meta : [];

            $supplierForConfirm = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
            $sabreCheckoutNotice = null;
            $sabreSubmitLock = null;

            if ($supplierForConfirm === SupplierProvider::Sabre->value) {
                $snapshot = is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : [];
                if ($snapshot !== [] && FlightOfferDisplayPresenter::selectedItineraryTimelineInvalid($snapshot)) {
                    return redirect()->route('booking.review')
                        ->withErrors(['booking' => (string) __('Selected itinerary timing could not be verified. Please choose another fare.')]);
                }
                if (! (bool) config('suppliers.sabre.booking_enabled', false)) {
                    return redirect()->route('booking.review')
                        ->withErrors(['booking' => (string) __('Sabre booking is not enabled yet.')]);
                }

                $sabreSubmitLock = Cache::lock('public-booking-review-submit:'.$booking->id, 120);
                if (! $sabreSubmitLock->get()) {
                    Log::info('checkout.duplicate_submit_blocked', [
                        'booking_id' => $booking->id,
                        'reason_code' => 'review_submit_lock_busy',
                    ]);

                    return redirect()->route('booking.review')
                        ->withErrors(['booking' => $this->duplicatePublicSabreBookingSubmitMessage()]);
                }
            }

            try {
                if ($supplierForConfirm === SupplierProvider::Sabre->value) {
                    $booking->refresh();

                    $guardRedirect = $this->maybeAbortDuplicatePublicSabreBookingSubmit($booking);
                    if ($guardRedirect !== null) {
                        return $guardRedirect;
                    }

                    $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);

                    if (SabreOfferRefreshAcceptance::requiresAcceptance($booking)) {
                        return redirect()->route('booking.review')
                            ->with('show_offer_refresh_modal', true);
                    }

                    $sabreRefreshOutcome = $this->applySabreOfferRefreshBeforePublicPnr($booking);
                    $booking->refresh();
                    if (($sabreRefreshOutcome['status'] ?? '') === 'fare_change_pending') {
                        $this->bookingCommunicationService->notifyFareUpdateRequiresAcceptance($booking);

                        return redirect()->route('booking.review')
                            ->with('show_offer_refresh_modal', true);
                    }
                    if (($sabreRefreshOutcome['status'] ?? '') === 'unavailable') {
                        return $this->redirectToFlightResultsFromBooking($booking)
                            ->with('status', (string) __('Please choose another available fare.'))
                            ->withErrors(['flight_id' => (string) __('This fare is no longer available. Please choose another flight.')]);
                    }

                    $outcome = $this->sabreBookingService->runPublicReviewDryRun($booking);
                    $statusOut = (string) ($outcome['status'] ?? '');

                    if (! ($outcome['success'] ?? false)) {
                        $code = (string) ($outcome['error_code'] ?? '');
                        if ($code === 'sabre_booking_application_error' && $statusOut === 'needs_review') {
                            $codes = is_array($outcome['response_error_codes'] ?? null) ? $outcome['response_error_codes'] : [];
                            $codesNorm = array_map(static fn ($c) => strtoupper((string) $c), $codes);
                            $sabreCheckoutNotice = in_array('MANDATORY_DATA_MISSING', $codesNorm, true)
                                ? (string) __('Booking request saved. Sabre reported mandatory booking data was missing; staff must complete the record. No PNR or ticket has been issued.')
                                : (string) __('Booking request saved. Sabre returned a response requiring staff review. No ticket has been issued.');
                        } elseif ($code === ComplexItineraryPolicy::ERROR_CODE && $statusOut === 'needs_review') {
                            $sabreCheckoutNotice = ComplexItineraryPolicy::publicCheckoutNotice();
                        } elseif (in_array($code, [SabreCertifiedRouteSelector::ERROR_CODE_PENDING, SabreCertifiedRouteSelector::ERROR_CODE_NOT_CERTIFIED], true)
                            && $statusOut === 'needs_review') {
                            $selection = is_array($outcome['certified_route_selection'] ?? null)
                                ? $outcome['certified_route_selection']
                                : [];
                            $sabreCheckoutNotice = app(SabreCertifiedRouteSelector::class)->publicCheckoutNoticeForSelection($selection);
                        } elseif ($code === 'sabre_passenger_records_itinerary_guard' && $statusOut === 'needs_review') {
                            $sabreCheckoutNotice = (string) __('Booking request saved. Passenger Records live create was not attempted for this itinerary; staff must complete supplier booking manually. No PNR or ticket has been issued.');
                        } elseif ($code === 'sabre_passenger_records_stale_shop_segment' && $statusOut === 'needs_review') {
                            $sabreCheckoutNotice = (string) __('This flight is no longer available at the selected schedule/class. Please search again or contact staff.');
                        } elseif ($code === SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE) {
                            return redirect()->route('booking.review')
                                ->with('show_offer_refresh_modal', true);
                        } else {
                            $default = (string) ($outcome['message'] ?? __('Sabre booking failed.'));
                            $msg = match (true) {
                                $statusOut === 'disabled' => (string) __('Sabre booking is not enabled yet.'),
                                $statusOut === 'validation_failed' => (string) ($outcome['message'] ?? __('This Sabre fare could not be validated for submission.')),
                                $code === 'sabre_booking_forbidden' => (string) __('Sabre booking endpoint is forbidden for this credential/path. Try configured booking path or contact Sabre/provider.'),
                                $code === 'sabre_booking_validation_failed' => $default,
                                default => $default,
                            };

                            return redirect()->route('booking.review')
                                ->withErrors(['booking' => $msg]);
                        }
                    } elseif (($outcome['success'] ?? false)) {
                        $skipParts = [];
                        if (($outcome['prebooking_revalidation_skipped_reason'] ?? '') === 'pnr_only_ticketing_disabled') {
                            $skipParts[] = (string) __(
                                'PNR created using the fare shown from search. That fare is subject to confirmation by staff before ticketing or final payment.'
                            );
                        } elseif (($outcome['revalidation_skipped_by_config'] ?? false) && ($outcome['live_call_attempted'] ?? false)) {
                            if (($outcome['revalidation_bypass_enabled'] ?? false) === true) {
                                $skipParts[] = (string) __('Sabre booking was attempted without completed fare revalidation because revalidation bypass is enabled for this test. Ticketing remains disabled.');
                            } else {
                                $skipParts[] = (string) __('Sabre booking was attempted without completed fare revalidation because pre-booking revalidation is disabled in configuration. Ticketing remains disabled.');
                            }
                        }

                        if ($statusOut === 'dry_run') {
                            $dry = $this->sabreBookingService->effectiveSabreBookingSchema() === 'trip_orders_create_booking'
                                ? (string) __('Sabre Trip Orders booking dry-run prepared. No live PNR attempted.')
                                : (string) __('Sabre booking dry-run prepared.');
                            $skipParts[] = $dry;
                        } elseif ($statusOut === 'pending_payment_or_ticketing') {
                            $pnrOut = trim((string) ($outcome['pnr'] ?? ''));
                            $skipParts[] = $pnrOut !== ''
                                ? (string) __('PNR created. Ticketing remains pending/manual.')
                                : (string) __('Sabre booking reference received. No PNR/locator yet. Ticketing is pending/manual.');
                        } elseif ($statusOut === 'needs_review') {
                            $skipParts[] = (string) __('Booking request submitted for staff review. No ticket has been issued.');
                        }

                        $sabreCheckoutNotice = $skipParts !== [] ? implode(' ', $skipParts) : null;
                    }
                }

                $this->bookingService->submitBookingRequest($booking->fresh());

                $redirect = redirect()->route('booking.confirmation');

                if ($sabreCheckoutNotice !== null) {
                    return $redirect->with('sabre_checkout_notice', $sabreCheckoutNotice);
                }

                return $redirect;
            } finally {
                $sabreSubmitLock?->release();
            }
        }

        $bookingId = $request->session()->get(PublicBooking::SESSION_BOOKING_ID);
        if ($bookingId === null) {
            return redirect()->route('flights.search');
        }

        $booking = Booking::query()
            ->with(['passengers', 'contact', 'fareBreakdown'])
            ->find($bookingId);

        if ($booking === null) {
            $request->session()->forget(PublicBooking::SESSION_BOOKING_ID);

            return redirect()->route('flights.search');
        }

        $meta = $booking->meta ?? [];
        $offer = $meta['flight_offer_snapshot'] ?? null;
        $criteria = $meta['search_criteria'] ?? [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $booking->travel_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
        ];

        if ($offer === null) {
            return redirect()->route('flights.search');
        }

        $leadPassenger = $booking->passengers->firstWhere('is_lead_passenger', true) ?? $booking->passengers->first();
        $contact = $booking->contact;
        $draft = [
            'flight_id' => $offer['id'] ?? '',
            'title' => $leadPassenger?->title,
            'first_name' => $leadPassenger?->first_name,
            'last_name' => $leadPassenger?->last_name,
            'dob' => $leadPassenger?->date_of_birth?->format('Y-m-d'),
            'gender' => $leadPassenger?->gender,
            'nationality' => $leadPassenger?->nationality,
            'email' => $contact?->email,
            'phone' => $contact?->phone,
            'country' => $contact?->country,
            'search_from' => $criteria['origin'] ?? '',
            'search_to' => $criteria['destination'] ?? '',
            'search_depart' => $criteria['depart_date'] ?? '',
        ];

        $supplierForReview = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        $sabreBookingEnabled = (bool) config('suppliers.sabre.booking_enabled', false);
        $sabreLiveCallsEnabled = (bool) config('suppliers.sabre.booking_live_call_enabled', false);
        $sabreCheckoutSubmitDisabled = $supplierForReview === SupplierProvider::Sabre->value && ! $sabreBookingEnabled;
        $sabreCheckoutDryRunInfo = $supplierForReview === SupplierProvider::Sabre->value && $sabreBookingEnabled && ! $sabreLiveCallsEnabled;
        $sabreCheckoutPendingApiInfo = $supplierForReview === SupplierProvider::Sabre->value && $sabreBookingEnabled && $sabreLiveCallsEnabled;
        $sabreTripOrdersDryRunReview = $supplierForReview === SupplierProvider::Sabre->value
            && $sabreCheckoutDryRunInfo
            && $this->sabreBookingService->effectiveSabreBookingSchema() === 'trip_orders_create_booking';

        $sabreTripOrdersFareBasisWarning = false;
        if ($supplierForReview === SupplierProvider::Sabre->value
            && $this->sabreBookingService->effectiveSabreBookingSchema() === 'trip_orders_create_booking') {
            $segs = [];
            foreach (['normalized_offer_snapshot', 'validated_offer_snapshot', 'flight_offer_snapshot'] as $sk) {
                if (is_array($meta[$sk]['segments'] ?? null)) {
                    $segs = $meta[$sk]['segments'];
                    break;
                }
            }
            if ($segs !== []) {
                foreach ($segs as $sr) {
                    if (! is_array($sr)) {
                        continue;
                    }
                    if (trim((string) ($sr['fare_basis_code'] ?? '')) === '') {
                        $sabreTripOrdersFareBasisWarning = true;
                        break;
                    }
                }
            }
        }

        $displayOffer = FlightOfferDisplayPresenter::enrichOfferSnapshotForBooking($offer, $criteria);
        $timelineSnapshotInvalid = FlightOfferDisplayPresenter::selectedItineraryTimelineInvalid($displayOffer);
        $reviewCityMap = FlightOfferDisplayPresenter::airportCityMap(
            FlightOfferDisplayPresenter::collectIataCodes($displayOffer)
        );
        $reviewPresentation = FlightOfferDisplayPresenter::buildPresentation($displayOffer, $criteria, $reviewCityMap);
        $complexItineraryNotice = (bool) ($meta['complex_itinerary_requires_staff_confirmation'] ?? false)
            || in_array((string) ($criteria['trip_type'] ?? 'one_way'), ['round_trip', 'multi_city'], true);

        if ($supplierForReview === SupplierProvider::Sabre->value && $timelineSnapshotInvalid) {
            $sabreCheckoutSubmitDisabled = true;
        }

        $offerRefreshPending = SabreOfferRefreshAcceptance::requiresAcceptance($booking)
            || (bool) session('show_offer_refresh_modal', false);
        $offerRefreshDisplay = SabreOfferRefreshAcceptance::customerDisplayFromBooking($booking);
        if ($offerRefreshPending && $offerRefreshDisplay === null) {
            $booking->loadMissing('fareBreakdown');
            SabreOfferRefreshAcceptance::writeCustomerDisplayMeta(
                $booking,
                $this->offerValidationService,
                (float) ($booking->fareBreakdown?->total ?? 0),
            );
            $booking->refresh();
            $offerRefreshDisplay = SabreOfferRefreshAcceptance::customerDisplayFromBooking($booking);
        }
        if ($offerRefreshPending) {
            $sabreCheckoutSubmitDisabled = true;
        }

        $viewData = [
            'draft' => $draft,
            'leadPassenger' => $leadPassenger,
            'offer' => $offer,
            'criteria' => $criteria,
            'booking' => $booking,
            'airlineLogo' => $this->airlineBranding->getLogoForCode((string) ($offer['airline_code'] ?? ($offer['carrier_code'] ?? ''))),
            'recheckRequired' => (bool) session('recheck_required', false),
            'offerRefreshPending' => $offerRefreshPending,
            'offerRefreshDisplay' => $offerRefreshDisplay,
            'sabreCheckoutSubmitDisabled' => $sabreCheckoutSubmitDisabled,
            'sabreCheckoutDryRunInfo' => $sabreCheckoutDryRunInfo,
            'sabreCheckoutPendingApiInfo' => $sabreCheckoutPendingApiInfo,
            'sabreTripOrdersDryRunReview' => $sabreTripOrdersDryRunReview,
            'sabreTripOrdersFareBasisWarning' => $sabreTripOrdersFareBasisWarning,
            'reviewPresentation' => $reviewPresentation,
            'timelineSnapshotInvalid' => $timelineSnapshotInvalid,
            'complexItineraryNotice' => $complexItineraryNotice,
            'refreshSearchUrl' => $this->resolveRefreshSearchUrl($criteria),
            'fareSessionExpiresAt' => (string) ($meta['checkout_lock_expires_at'] ?? ''),
        ];

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.bookings.review', $viewData);
        }

        return view('frontend.booking.review', $viewData);
    }

    public function confirmation(Request $request): View|RedirectResponse
    {
        $bookingId = $request->session()->get(PublicBooking::SESSION_BOOKING_ID);
        if ($bookingId === null) {
            return redirect()->route('flights.search');
        }

        $booking = Booking::query()
            ->with(['passengers', 'contact', 'fareBreakdown'])
            ->find($bookingId);

        if ($booking === null) {
            $request->session()->forget(PublicBooking::SESSION_BOOKING_ID);

            return redirect()->route('flights.search');
        }

        $meta = $booking->meta ?? [];
        $offer = $meta['flight_offer_snapshot'] ?? null;
        $criteria = $meta['search_criteria'] ?? [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $booking->travel_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
        ];

        $leadPassenger = $booking->passengers->firstWhere('is_lead_passenger', true) ?? $booking->passengers->first();
        $contact = $booking->contact;
        $draft = [
            'flight_id' => $offer['id'] ?? '',
            'booking_reference' => $booking->booking_reference,
            'booking_method' => $meta['booking_method'] ?? 'pay_later',
            'title' => $leadPassenger?->title,
            'first_name' => $leadPassenger?->first_name,
            'last_name' => $leadPassenger?->last_name,
            'email' => $contact?->email,
            'phone' => $contact?->phone,
            'country' => $contact?->country,
            'search_from' => $criteria['origin'] ?? '',
            'search_to' => $criteria['destination'] ?? '',
            'search_depart' => $criteria['depart_date'] ?? '',
        ];

        $viewData = [
            'draft' => $draft,
            'offer' => $offer,
            'criteria' => $criteria,
            'booking' => $booking,
            'airlineLogo' => $offer !== null
                ? $this->airlineBranding->getLogoForCode((string) ($offer['airline_code'] ?? ($offer['carrier_code'] ?? '')))
                : null,
        ];

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.bookings.confirmation', $viewData);
        }

        return view('frontend.booking.confirmation', $viewData);
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    protected function resolveCheckoutSearchCriteria(array $draft, string $searchId = ''): array
    {
        $fromForm = [
            'origin' => trim((string) ($draft['search_from'] ?? $draft['from'] ?? '')),
            'destination' => trim((string) ($draft['search_to'] ?? $draft['to'] ?? '')),
            'depart_date' => trim((string) ($draft['search_depart'] ?? $draft['depart'] ?? '')),
            'trip_type' => (string) ($draft['trip_type'] ?? 'one_way'),
            'return_date' => ($draft['return_date'] ?? null) !== '' && ($draft['return_date'] ?? null) !== null
                ? (string) $draft['return_date']
                : null,
            'cabin' => (string) ($draft['cabin'] ?? 'economy'),
            'adults' => (int) ($draft['adults'] ?? 1),
            'children' => (int) ($draft['children'] ?? 0),
            'infants' => (int) ($draft['infants'] ?? 0),
            'segments' => is_array($draft['segments'] ?? null) ? $draft['segments'] : null,
            'source_channel' => (string) ($draft['source_channel'] ?? 'public_guest'),
        ];

        $stored = null;
        if ($searchId !== '') {
            $payload = $this->searchStore->get($searchId);
            if (is_array($payload['criteria'] ?? null)) {
                $stored = $payload['criteria'];
            }
        }

        return FlightOfferDisplayPresenter::mergeStoredSearchCriteria($fromForm, $stored);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $pricing
     * @return array<string, mixed>
     */
    protected function presentValidatedOffer(array $offer, array $pricing): array
    {
        $segments = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];
        $stopsFromSegments = count($segments) > 1 ? count($segments) - 1 : 0;
        $stops = max((int) ($offer['stops'] ?? 0), $stopsFromSegments);

        $durationMinutes = (int) ($offer['duration_minutes'] ?? 0);
        $timeline = FlightOfferDisplayPresenter::journeyTimelineMinutesFromOffer($offer);
        if ($timeline > 0 && ($durationMinutes <= 0 || abs($durationMinutes - $timeline) > 15)) {
            $durationMinutes = $timeline;
        }
        $fare = $offer['fare_breakdown'] ?? [];
        $baggageSummary = is_array($offer['baggage'] ?? null)
            ? (string) (($offer['baggage']['summary'] ?? '') ?: ($offer['baggage']['checked'] ?? ''))
            : (string) ($offer['baggage'] ?? '');

        return array_merge($offer, [
            'id' => $offer['offer_id'] ?? null,
            'depart_at' => $offer['departure_at'] ?? null,
            'arrive_at' => $offer['arrival_at'] ?? null,
            'carrier_code' => $offer['airline_code'] ?? 'XX',
            'stops' => $stops,
            'duration_minutes' => $durationMinutes,
            'duration_h' => intdiv($durationMinutes, 60),
            'duration_m' => $durationMinutes % 60,
            'baggage' => $baggageSummary,
            'base_fare' => (float) ($fare['base_fare'] ?? 0),
            'currency' => (string) ($fare['currency'] ?? 'PKR'),
            'taxes' => (float) ($pricing['taxes'] ?? 0),
            'markup' => (float) ($pricing['admin_markup'] ?? 0)
                + (float) ($pricing['route_markup'] ?? 0)
                + (float) ($pricing['airline_markup'] ?? 0)
                + (float) ($pricing['agent_markup_or_commission'] ?? 0),
            'service_fee' => (float) ($pricing['service_fee'] ?? 0),
            'total' => (float) ($pricing['final_total'] ?? 0),
            'pricing_components' => $pricing,
        ]);
    }

    /**
     * @param  array<string, mixed>  ...$snapshots
     */
    protected function distributionChannelForBookingMeta(array ...$snapshots): ?string
    {
        foreach ($snapshots as $snapshot) {
            if (! isset($snapshot['distribution_channel']) || ! is_string($snapshot['distribution_channel'])) {
                continue;
            }
            $channel = trim($snapshot['distribution_channel']);
            if ($channel !== '') {
                return $channel;
            }
        }

        return null;
    }

    protected function checkoutLockMinutes(): int
    {
        return max(1, (int) config('ota.checkout_lock_minutes', 7));
    }

    protected function buildCheckoutLockKey(string $searchId, string $offerId, string $fareOptionKey = ''): string
    {
        return implode('|', [trim($searchId), trim($offerId), trim($fareOptionKey)]);
    }

    /**
     * @return array{checkout_lock_started_at: string, checkout_lock_expires_at: string}
     */
    protected function freshCheckoutLockTimestamps(): array
    {
        $started = now();

        return [
            'checkout_lock_started_at' => $started->toIso8601String(),
            'checkout_lock_expires_at' => $started->copy()->addMinutes($this->checkoutLockMinutes())->toIso8601String(),
        ];
    }

    protected function checkoutLockIsExpired(string $expiresAtIso): bool
    {
        try {
            return now()->greaterThanOrEqualTo(Carbon::parse($expiresAtIso));
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @param  array<string, mixed>  $protection
     * @param  array<string, mixed>|null  $existingProtection
     * @return array<string, mixed>
     */
    protected function applyCheckoutLockToProtection(
        array $protection,
        ?array $existingProtection,
        string $lockKey,
        bool $issueFreshLock,
    ): array {
        if (
            ! $issueFreshLock
            && is_array($existingProtection)
            && (string) ($existingProtection['checkout_lock_key'] ?? '') === $lockKey
            && trim((string) ($existingProtection['checkout_lock_expires_at'] ?? '')) !== ''
        ) {
            $protection['checkout_lock_key'] = $lockKey;
            $protection['checkout_lock_started_at'] = (string) ($existingProtection['checkout_lock_started_at'] ?? '');
            $protection['checkout_lock_expires_at'] = (string) $existingProtection['checkout_lock_expires_at'];

            return $protection;
        }

        $protection = array_merge($protection, $this->freshCheckoutLockTimestamps());
        $protection['checkout_lock_key'] = $lockKey;

        return $protection;
    }

    /**
     * @param  array<string, mixed>|null  $existingProtection
     */
    protected function shouldIssueFreshCheckoutLock(Request $request, ?array $existingProtection, string $lockKey): bool
    {
        if (! is_array($existingProtection) || trim((string) ($existingProtection['checkout_lock_key'] ?? '')) === '') {
            return true;
        }

        if ((string) ($existingProtection['checkout_lock_key'] ?? '') !== $lockKey) {
            return true;
        }

        $expiresAt = (string) ($existingProtection['checkout_lock_expires_at'] ?? '');
        if ($expiresAt === '' || $this->checkoutLockIsExpired($expiresAt)) {
            $referer = (string) $request->headers->get('referer', '');
            if (str_contains($referer, '/flights/results')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $protection
     * @return array<string, mixed>
     */
    protected function finalizeCheckoutProtection(
        Request $request,
        array $protection,
        string $searchId,
        string $offerId,
        string $fareOptionKey = '',
    ): array {
        $draft = $this->bookingDraft->current();
        $existingProtection = is_array($draft['checkout_protection'] ?? null) ? $draft['checkout_protection'] : null;
        $lockKey = $this->buildCheckoutLockKey($searchId, $offerId, $fareOptionKey);
        $issueFreshLock = $this->shouldIssueFreshCheckoutLock($request, $existingProtection, $lockKey);

        return $this->applyCheckoutLockToProtection($protection, $existingProtection, $lockKey, $issueFreshLock);
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function buildCheckoutProtectionState(array $offer, $validation, string $selectedOfferId, bool $providerUnstableTestMode = false): array
    {
        $rawPayload = is_array($offer['raw_payload'] ?? null) ? $offer['raw_payload'] : [];
        $paymentRequirements = is_array(data_get($rawPayload, 'payment_requirements'))
            ? data_get($rawPayload, 'payment_requirements')
            : [];
        $requiresInstantPayment = (bool) ($paymentRequirements['requires_instant_payment'] ?? true);
        $priceGuarantee = data_get($rawPayload, 'conditions.price_guarantee');
        $priceGuaranteed = is_array($priceGuarantee)
            ? (bool) ($priceGuarantee['enabled'] ?? $priceGuarantee['is_guaranteed'] ?? false)
            : false;
        $holdSupported = ! $requiresInstantPayment;
        $mode = $requiresInstantPayment
            ? 'instant_payment_required'
            : ($priceGuaranteed ? 'hold_price_guaranteed' : 'hold_no_price_guarantee');
        $offerExpiresAt = (string) ($offer['expires_at'] ?? '');
        $paymentRequiredBy = data_get($rawPayload, 'payment_requirements.payment_required_by');
        $holdStatus = $holdSupported ? 'pending' : 'not_supported';

        if ($providerUnstableTestMode) {
            $requiresInstantPayment = true;
            $holdSupported = false;
            $priceGuaranteed = false;
            $mode = 'instant_payment_required';
            $holdStatus = 'not_supported';
        }

        return [
            'original_offer_id' => $selectedOfferId,
            'validated_offer_snapshot' => SensitiveDataRedactor::redact($offer),
            'supplier_total' => (float) ($offer['total'] ?? 0),
            'supplier_currency' => (string) ($offer['currency'] ?? 'PKR'),
            'price_changed' => (bool) ($validation->price_changed ?? false),
            'offer_validated_at' => now()->toIso8601String(),
            'payment_requirements' => SensitiveDataRedactor::redact($paymentRequirements),
            'requires_instant_payment' => $requiresInstantPayment,
            'hold_supported' => $holdSupported,
            'price_guaranteed' => $priceGuaranteed,
            'protection_mode' => $mode,
            'offer_expires_at' => $offerExpiresAt !== '' ? $offerExpiresAt : null,
            'price_guarantee_expires_at' => is_array($priceGuarantee) ? ($priceGuarantee['expires_at'] ?? null) : null,
            'payment_required_by' => $paymentRequiredBy ?: null,
            'hold_status' => $holdStatus,
            'provider_unstable_test_mode' => $providerUnstableTestMode,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $searchPayload
     */
    protected function searchPayloadIsFreshForUnstableTestMode(?array $searchPayload): bool
    {
        if (! is_array($searchPayload)) {
            return false;
        }
        $createdAt = $searchPayload['created_at'] ?? null;
        if (! is_string($createdAt) || trim($createdAt) === '') {
            return false;
        }
        try {
            $created = Carbon::parse($createdAt);
        } catch (\Throwable) {
            return false;
        }
        $window = (int) config('ota.provider_unstable_test_mode_window_seconds', 120);
        $window = max(1, $window);

        return ! now()->subSeconds($window)->greaterThan($created);
    }

    /**
     * Testing: allow provider-unstable fallback. Local: only when OTA_ALLOW_PROVIDER_UNSTABLE_LOCAL=true.
     * Staging/production: never.
     */
    protected function providerUnstableTestModeIsAllowed(): bool
    {
        return ProviderUnstableTestMode::isCheckoutFallbackAllowed();
    }

    /**
     * local/testing only: allow checkout using cached search pricing when single-offer validation fails.
     *
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $cachedOffer
     * @return array{
     *     validation: OfferValidationResultData,
     *     normalizedValidated: array<string, mixed>,
     *     pricing: array<string, mixed>,
     *     offer: array<string, mixed>,
     *     protection: array<string, mixed>,
     *     hold_session: BookingHoldSession
     * }|null
     */
    protected function tryCheckoutProviderUnstableTestMode(
        Agency $agency,
        array $criteria,
        array $cachedOffer,
        string $effectiveFlightId,
        string $searchId,
        string $underlyingValidationStatus,
    ): ?array {
        if (! $this->providerUnstableTestModeIsAllowed()) {
            return null;
        }
        if ($searchId === '' || ! in_array($underlyingValidationStatus, ['unavailable', 'expired'], true)) {
            return null;
        }
        $payload = $this->searchStore->get($searchId);
        if (! $this->searchPayloadIsFreshForUnstableTestMode($payload)) {
            return null;
        }

        $validatedDto = NormalizedFlightOfferData::fromArray($cachedOffer);
        $normalizedValidated = $validatedDto->toArray();
        $pricing = $this->offerValidationService->pricingSnapshotForCachedOffer(
            $agency,
            $cachedOffer,
            $criteria + ['source_channel' => 'public_guest']
        );
        $offer = $this->presentValidatedOffer($normalizedValidated, $pricing);
        $validation = new OfferValidationResultData(
            is_valid: true,
            status: 'provider_unstable_test_mode',
            original_offer_id: $effectiveFlightId,
            validated_offer: $validatedDto,
            warnings: [
                'Duffel did not confirm this offer via single-offer retrieval; checkout uses cached search pricing in this environment.',
            ],
            meta: [
                'underlying_validation_status' => $underlyingValidationStatus,
                'reason_code' => 'provider_unstable_test_mode',
            ],
        );
        $protection = $this->buildCheckoutProtectionState($offer, $validation, $effectiveFlightId, true);
        $holdSession = $this->fareHoldService->refreshHoldSession(
            agency: $agency,
            booking: null,
            searchId: $searchId,
            offerId: $effectiveFlightId,
            normalizedOffer: $offer,
            user: auth()->user(),
            holdStatus: 'not_supported',
            safeError: null,
            existing: null,
            metaOverrides: [
                'reason_code' => 'provider_unstable_test_mode',
                'provider_unstable_test_mode' => true,
            ],
        );

        Log::info('booking.checkout.provider_unstable_test_mode', [
            'reason_code' => 'provider_unstable_test_mode',
            'offer_id' => $effectiveFlightId,
            'search_id' => $searchId,
            'underlying_validation_status' => $underlyingValidationStatus,
        ]);

        return [
            'validation' => $validation,
            'normalizedValidated' => $normalizedValidated,
            'pricing' => $pricing,
            'offer' => $offer,
            'protection' => $protection,
            'hold_session' => $holdSession,
        ];
    }

    protected function upsertCheckoutHoldSession(Request $request, Agency $agency, array $criteria, string $offerId, array $protection): ?BookingHoldSession
    {
        if ($offerId === '') {
            return null;
        }

        $draft = $this->bookingDraft->current();
        $existingId = (int) ($draft['hold_session_id'] ?? 0);
        $session = $existingId > 0 ? BookingHoldSession::query()->find($existingId) : null;
        if ($session === null) {
            $session = new BookingHoldSession;
        }

        $session->fill([
            'agency_id' => $agency->id,
            'search_id' => (string) ($draft['search_id'] ?? $request->query('search_id', '')),
            'offer_id' => $offerId,
            'supplier_provider' => (string) ($protection['validated_offer_snapshot']['supplier_provider'] ?? ''),
            'supplier_connection_id' => $protection['validated_offer_snapshot']['supplier_connection_id'] ?? null,
            'supplier_offer_id' => (string) ($protection['validated_offer_snapshot']['raw_reference'] ?? $offerId),
            'hold_status' => (string) ($protection['hold_status'] ?? 'not_started'),
            'requires_instant_payment' => (bool) ($protection['requires_instant_payment'] ?? true),
            'price_guarantee_expires_at' => $protection['price_guarantee_expires_at'] ?? null,
            'payment_required_by' => $protection['payment_required_by'] ?? null,
            'local_checkout_expires_at' => $protection['checkout_lock_expires_at'] ?? null,
            'hold_expires_at' => $protection['offer_expires_at'] ?? null,
            'validated_total_amount' => (float) ($protection['supplier_total'] ?? 0),
            'validated_total_currency' => (string) ($protection['supplier_currency'] ?? 'PKR'),
            'converted_total_pkr' => (float) ($protection['supplier_total'] ?? 0),
            'markup_snapshot' => is_array($protection['validated_offer_snapshot']['pricing_components'] ?? null)
                ? $protection['validated_offer_snapshot']['pricing_components']
                : [],
            'passenger_counts' => [
                'adults' => (int) ($criteria['adults'] ?? 1),
                'children' => (int) ($criteria['children'] ?? 0),
                'infants' => (int) ($criteria['infants'] ?? 0),
                'total' => (int) (($criteria['adults'] ?? 1) + ($criteria['children'] ?? 0) + ($criteria['infants'] ?? 0)),
            ],
            'passenger_pricing' => is_array(data_get($protection, 'validated_offer_snapshot.fare_breakdown.passenger_pricing'))
                ? data_get($protection, 'validated_offer_snapshot.fare_breakdown.passenger_pricing')
                : null,
            'passenger_pricing_available' => (bool) data_get($protection, 'validated_offer_snapshot.fare_breakdown.passenger_pricing_available', false),
            'validated_offer_snapshot' => $protection['validated_offer_snapshot'] ?? [],
            'safe_error' => null,
            'expires_at' => $protection['checkout_lock_expires_at'] ?? $this->freshCheckoutLockTimestamps()['checkout_lock_expires_at'],
            'created_by_user_id' => auth()->id(),
        ]);
        $session->save();

        return $session->fresh();
    }

    /**
     * @return array{status:string, old_total?:float, new_total?:float}
     */
    protected function revalidateCheckoutBeforeConfirmation(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $holdExpiry = $meta['payment_required_by'] ?? $meta['price_guarantee_expires_at'] ?? $meta['offer_expires_at'] ?? null;
        if (is_string($holdExpiry) && trim($holdExpiry) !== '') {
            try {
                if (now()->greaterThan(Carbon::parse($holdExpiry))) {
                    return ['status' => 'hold_expired'];
                }
            } catch (\Throwable) {
                // ignore parse issues and continue with revalidation
            }
        }
        $mode = (string) ($meta['protection_mode'] ?? 'instant_payment_required');
        if (! $this->fareHoldService->requiresFinalRevalidation($booking)) {
            return ['status' => 'ok'];
        }

        $agency = Agency::query()->find($booking->agency_id);
        if ($agency === null) {
            return ['status' => 'unavailable'];
        }

        $validation = $this->fareHoldService->revalidateBeforeConfirmation($booking, $agency);
        if (! $validation->is_valid || $validation->validated_offer === null) {
            if (
                $this->providerUnstableTestModeIsAllowed()
                && ($meta['provider_unstable_test_mode'] ?? false) === true
            ) {
                Log::info('booking.checkout.revalidation_skipped_provider_unstable_test_mode', [
                    'booking_id' => $booking->id,
                    'reason_code' => 'provider_unstable_test_mode',
                ]);

                return ['status' => 'ok'];
            }

            return ['status' => 'unavailable'];
        }

        $oldTotal = (float) ($meta['supplier_total'] ?? 0);
        $normalizedValidated = $validation->validated_offer->toArray();
        $pricing = $validation->meta['pricing_snapshot'] ?? [];
        $presented = $this->presentValidatedOffer($normalizedValidated, $pricing);
        $newTotal = (float) ($presented['total'] ?? 0);
        $priceChanged = (bool) ($validation->price_changed ?? false) || ($oldTotal > 0 && abs($newTotal - $oldTotal) > 0.009);
        $meta['validated_offer_snapshot'] = SensitiveDataRedactor::redact($normalizedValidated);
        $revalidateCriteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        $presented = FlightOfferDisplayPresenter::enrichOfferSnapshotForBooking($presented, $revalidateCriteria);
        $meta['flight_offer_snapshot'] = SensitiveDataRedactor::redact($presented);
        $revalidatedChannel = $this->distributionChannelForBookingMeta($normalizedValidated, $presented, $meta);
        if ($revalidatedChannel !== null) {
            $meta['distribution_channel'] = $revalidatedChannel;
        }
        $meta['supplier_total'] = (float) ($presented['total'] ?? 0);
        $meta['supplier_currency'] = (string) ($presented['currency'] ?? 'PKR');
        $meta['price_changed'] = (bool) ($validation->price_changed ?? false);
        $meta['offer_validated_at'] = now()->toIso8601String();
        $normalizedFare = is_array($normalizedValidated['fare_breakdown'] ?? null) ? $normalizedValidated['fare_breakdown'] : [];
        $passengerPricing = is_array($normalizedFare['passenger_pricing'] ?? null) ? $normalizedFare['passenger_pricing'] : null;
        $passengerPricingAvailable = (bool) ($normalizedFare['passenger_pricing_available'] ?? (is_array($passengerPricing) && $passengerPricing !== []));
        $meta['passenger_pricing'] = $passengerPricing;
        $meta['passenger_pricing_available'] = $passengerPricingAvailable;
        $meta['pricing_breakdown_available'] = $passengerPricingAvailable;
        $meta['fare_rechecked_at'] = now()->toIso8601String();
        $meta['requires_price_change_confirmation'] = $priceChanged;
        if ($priceChanged) {
            $meta['price_change_old_total'] = $oldTotal;
            $meta['price_change_new_total'] = $newTotal;
        } else {
            unset($meta['price_change_old_total'], $meta['price_change_new_total']);
        }
        $booking->forceFill([
            'meta' => $meta,
            'fare_revalidated_at' => now(),
            'selected_fare_total' => $oldTotal > 0 ? $oldTotal : null,
            'revalidated_fare_total' => $newTotal > 0 ? $newTotal : null,
        ])->save();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->bookingService->attachFareBreakdown($booking, [
            'base_fare' => (float) ($pricing['base_fare'] ?? 0),
            'taxes' => (float) ($pricing['taxes'] ?? 0),
            'fees' => (float) ($pricing['service_fee'] ?? 0),
            'markup' => (float) (($pricing['admin_markup'] ?? 0) + ($pricing['route_markup'] ?? 0) + ($pricing['airline_markup'] ?? 0) + ($pricing['agent_markup_or_commission'] ?? 0)),
            'discount' => 0,
            'total' => (float) ($pricing['final_total'] ?? 0),
            'currency' => (string) ($presented['currency'] ?? 'PKR'),
            'breakdown' => [
                ['label' => 'Base fare', 'amount' => (float) ($pricing['base_fare'] ?? 0)],
                ['label' => 'Taxes & surcharges', 'amount' => (float) ($pricing['taxes'] ?? 0)],
                ['label' => 'Admin markup', 'amount' => (float) ($pricing['admin_markup'] ?? 0)],
                ['label' => 'Route markup', 'amount' => (float) ($pricing['route_markup'] ?? 0)],
                ['label' => 'Airline markup', 'amount' => (float) ($pricing['airline_markup'] ?? 0)],
                ['label' => 'Channel/agent markup', 'amount' => (float) ($pricing['agent_markup_or_commission'] ?? 0)],
                ['label' => 'Service fee', 'amount' => (float) ($pricing['service_fee'] ?? 0)],
                [
                    'passenger_pricing' => $passengerPricing,
                    'passenger_pricing_available' => $passengerPricingAvailable,
                    'passenger_counts' => is_array($meta['passenger_counts'] ?? null) ? $meta['passenger_counts'] : [],
                ],
            ],
        ]);

        if ($priceChanged) {
            return [
                'status' => 'ok',
                'fare_changed' => true,
                'old_total' => $oldTotal,
                'new_total' => $newTotal,
            ];
        }

        return ['status' => 'ok'];
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $selectedOffer
     * @return array{search_id: string, offer: array<string,mixed>}|null
     */
    protected function attemptStaleOfferRecovery(Agency $agency, array $criteria, array $selectedOffer): ?array
    {
        $freshResult = $this->flightSearch->searchWithMeta($criteria, $agency, 'public_guest');
        $selectedProvider = strtolower(trim((string) ($selectedOffer['supplier_provider'] ?? '')));
        $selectedConnectionId = (int) ($selectedOffer['supplier_connection_id'] ?? 0);
        $freshOffers = array_values(array_filter(
            is_array($freshResult['offers'] ?? null) ? $freshResult['offers'] : [],
            function (array $offer) use ($selectedProvider, $selectedConnectionId): bool {
                if ($selectedProvider === '') {
                    return false;
                }
                if (strtolower((string) ($offer['supplier_provider'] ?? '')) !== $selectedProvider) {
                    return false;
                }
                if ($selectedConnectionId > 0) {
                    return (int) ($offer['supplier_connection_id'] ?? 0) === $selectedConnectionId;
                }

                return true;
            }
        ));
        if ($freshOffers === []) {
            Log::info('booking.stale_offer.recovery_failed', ['reason_code' => 'stale_offer_recovery_failed']);

            return null;
        }

        $selectedAirline = strtolower((string) ($selectedOffer['airline_code'] ?? ''));
        $selectedFlightNumber = strtolower((string) ($selectedOffer['flight_number'] ?? ''));
        $selectedCabin = strtolower((string) ($selectedOffer['cabin'] ?? ''));
        $selectedFareFamily = strtolower((string) ($selectedOffer['fare_family'] ?? ''));
        $selectedTotal = (float) ($selectedOffer['total'] ?? $selectedOffer['final_customer_price'] ?? 0);

        $best = null;
        $bestScore = -INF;
        foreach ($freshOffers as $candidate) {
            $score = 0.0;
            if (strtolower((string) ($candidate['airline_code'] ?? '')) === $selectedAirline) {
                $score += 100;
            }
            if ($selectedFlightNumber !== '' && strtolower((string) ($candidate['flight_number'] ?? '')) === $selectedFlightNumber) {
                $score += 120;
            }
            if ($selectedCabin !== '' && strtolower((string) ($candidate['cabin'] ?? '')) === $selectedCabin) {
                $score += 50;
            }
            if ($selectedFareFamily !== '' && strtolower((string) ($candidate['fare_family'] ?? '')) === $selectedFareFamily) {
                $score += 25;
            }
            $candidateTotal = (float) ($candidate['total'] ?? $candidate['final_customer_price'] ?? 0);
            if ($selectedTotal > 0) {
                $deltaPct = abs($candidateTotal - $selectedTotal) / $selectedTotal;
                $score -= ($deltaPct * 100);
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }
        if (! is_array($best)) {
            Log::info('booking.stale_offer.recovery_failed', ['reason_code' => 'stale_offer_recovery_failed']);

            return null;
        }

        $newSearchId = $this->searchStore->store(
            $criteria,
            $freshOffers,
            is_array($freshResult['warnings'] ?? null) ? $freshResult['warnings'] : []
        );
        Log::info('booking.stale_offer.recovered', [
            'reason_code' => 'stale_offer_recovered',
            'old_offer_id' => (string) ($selectedOffer['id'] ?? $selectedOffer['offer_id'] ?? ''),
            'new_offer_id' => (string) ($best['id'] ?? $best['offer_id'] ?? ''),
            'search_id' => $newSearchId,
        ]);
        Log::info('checkout.stale_offer_recovered', [
            'reason_code' => 'stale_offer_recovered',
            'old_offer_id' => (string) ($selectedOffer['id'] ?? $selectedOffer['offer_id'] ?? ''),
            'new_offer_id' => (string) ($best['id'] ?? $best['offer_id'] ?? ''),
            'search_id' => $newSearchId,
        ]);

        return [
            'search_id' => $newSearchId,
            'offer' => $best,
        ];
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    protected function buildFlightsResultsQuery(array $criteria): array
    {
        $resultsQuery = [
            'from' => $criteria['origin'],
            'to' => $criteria['destination'],
            'depart' => $criteria['depart_date'],
            'trip_type' => $criteria['trip_type'] ?? 'one_way',
            'cabin' => $criteria['cabin'] ?? 'economy',
            'adults' => $criteria['adults'] ?? 1,
            'children' => $criteria['children'] ?? 0,
            'infants' => $criteria['infants'] ?? 0,
        ];
        if (($resultsQuery['trip_type'] ?? '') === 'round_trip') {
            $rd = trim((string) ($criteria['return_date'] ?? ''));
            if ($rd !== '') {
                $resultsQuery['return_date'] = $rd;
            }
        }

        return $resultsQuery;
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    protected function resolveRefreshSearchUrl(array $criteria): string
    {
        $origin = trim((string) ($criteria['origin'] ?? ''));
        $destination = trim((string) ($criteria['destination'] ?? ''));
        $departDate = trim((string) ($criteria['depart_date'] ?? ''));

        if ($origin === '' || $destination === '' || $departDate === '') {
            Log::notice('checkout_refresh_search_url_fallback', [
                'reason' => 'missing_search_criteria',
                'origin' => $origin,
                'destination' => $destination,
                'depart_date' => $departDate,
            ]);

            return url('/');
        }

        return route('flights.results', $this->buildFlightsResultsQuery($criteria));
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $resultsQuery
     * @param  array<string, mixed>|null  $offer
     */
    protected function applyValidatedFareOptionSelection(
        string $fareOptionKey,
        string $searchId,
        string $offerId,
        ?array $offer,
        array $criteria,
        array $resultsQuery,
        ?Agency $agency,
    ): ?RedirectResponse {
        if ($fareOptionKey === '') {
            return null;
        }

        if ($offer === null) {
            $offer = $this->resolveOfferForFareOptionValidation($searchId, $offerId, $criteria, $agency);
        }

        if ($offer === null) {
            return redirect()->route('flights.results', $resultsQuery)
                ->withErrors(['fare_option_key' => __('This fare search has expired. Please search again and select a fare option.')]);
        }

        $resolved = FlightOfferDisplayPresenter::findFareFamilyOptionByKey($offer, $fareOptionKey);
        if ($resolved === null) {
            return redirect()->route('flights.results', $resultsQuery)
                ->withErrors(['fare_option_key' => __('The selected fare option is no longer available. Please choose again.')]);
        }

        $this->bookingDraft->merge([
            'fare_option_key' => $fareOptionKey,
            'selected_fare_family_option' => $resolved,
        ]);

        return null;
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>|null
     */
    protected function resolveOfferForFareOptionValidation(
        string $searchId,
        string $offerId,
        array $criteria,
        ?Agency $agency,
    ): ?array {
        if ($searchId !== '' && $offerId !== '') {
            $cached = $this->searchStore->findOffer($searchId, $offerId);
            if (is_array($cached)) {
                return $cached;
            }
        }

        if ($agency === null || $offerId === '') {
            return null;
        }

        if ($criteria['origin'] === '' || $criteria['destination'] === '' || $criteria['depart_date'] === '') {
            return null;
        }

        $offers = $this->flightSearch->search($criteria, $agency, 'public_guest');

        $found = collect($offers)->firstWhere('id', $offerId);

        return is_array($found) ? $found : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function selectedFareFamilyOptionForMeta(): ?array
    {
        $selected = $this->bookingDraft->current()['selected_fare_family_option'] ?? null;

        return is_array($selected) ? SensitiveDataRedactor::redact($selected) : null;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    protected function prepareSabreOfferForCheckoutHandoff(array $offer): array
    {
        if (strcasecmp((string) ($offer['supplier_provider'] ?? ''), 'sabre') !== 0) {
            return $offer;
        }

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $selected = $this->bookingDraft->current()['selected_fare_family_option'] ?? null;
        if (is_array($selected) && $selected !== []) {
            $offer = $normalizer->applyBrandedFareOptionToOfferSnapshot($offer, $selected);
        }

        $offer = $normalizer->ensureSabreBookingContextOnCachedOffer($offer);

        return $normalizer->mergeSabrePricingLinkageHandoff($offer);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    protected function sabreBookingContextFromOffer(array $offer): array
    {
        $ctx = data_get($offer, 'raw_payload.sabre_booking_context');
        if (! is_array($ctx)) {
            $ctx = is_array($offer['sabre_booking_context'] ?? null) ? $offer['sabre_booking_context'] : [];
        }

        return is_array($ctx) ? SensitiveDataRedactor::redact($ctx) : [];
    }

    protected function logBookingRouteEntry(Request $request): void
    {
        Log::info('booking_route_entry', [
            'method' => $request->method(),
            'route' => optional($request->route())->getName(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'search_id_present' => $request->filled('search_id'),
            'offer_id_present' => $request->filled('offer_id'),
            'flight_id_present' => $request->filled('flight_id'),
            'draft_id_present' => $request->filled('draft_id'),
            'hold_session_id_present' => $request->filled('hold_session_id'),
            'recovery_done_present' => $request->filled('recovery_done'),
            'user_authenticated' => Auth::check(),
            'intended_url' => $request->session()->get('url.intended'),
        ]);
    }

    public function acceptUpdatedFare(Request $request, Booking $booking): RedirectResponse
    {
        $resolved = $this->resolvePublicSessionBooking($request, $booking);
        if ($resolved === null) {
            return redirect()->route('flights.search');
        }

        $booking = $resolved;
        $meta = is_array($booking->meta) ? $booking->meta : [];
        if (strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? ''))) !== SupplierProvider::Sabre->value) {
            return redirect()->route('booking.review');
        }

        if (! SabreOfferRefreshAcceptance::requiresAcceptance($booking) && ! SabreOfferRefreshAcceptance::isAccepted($booking)) {
            return redirect()->route('booking.review')
                ->withErrors(['booking' => (string) __('No fare update is waiting for your confirmation.')]);
        }

        $acceptResult = SabreOfferRefreshAcceptance::accept($booking, 'customer');
        if (($acceptResult['success'] ?? false) !== true) {
            return redirect()->route('booking.review')
                ->withErrors(['booking' => (string) __('This fare update could not be confirmed. Please contact support.')]);
        }

        $pricingResult = SabreOfferRefreshAcceptance::applyAcceptedCustomerPricing(
            $booking,
            $this->offerValidationService,
            $this->bookingService,
            fn (array $normalized, array $pricing): array => $this->presentValidatedOffer($normalized, $pricing),
        );

        if (($pricingResult['success'] ?? false) !== true) {
            Log::warning('booking.offer_refresh.pricing_update_failed', [
                'booking_id' => $booking->id,
                'error' => (string) ($pricingResult['error'] ?? 'unknown'),
            ]);

            return redirect()->route('booking.review')
                ->withErrors(['booking' => (string) __('Your fare was accepted but the payable total could not be updated automatically. Our team must confirm before your booking can continue.')]);
        }

        $this->bookingCommunicationService->notifyUpdatedFareAccepted($booking->fresh());

        return redirect()->route('booking.review')
            ->with('offer_refresh_accepted', true)
            ->with('status', (string) __('Updated fare accepted. Continue booking.'));
    }

    public function declineUpdatedFare(Request $request, Booking $booking): RedirectResponse
    {
        $resolved = $this->resolvePublicSessionBooking($request, $booking);
        if ($resolved === null) {
            return redirect()->route('flights.search');
        }

        return $this->redirectToFlightResultsFromBooking($resolved)
            ->with('status', (string) __('Please choose another available fare.'));
    }

    /**
     * @return array{status: string}
     */
    protected function applySabreOfferRefreshBeforePublicPnr(Booking $booking): array
    {
        if (! (bool) config('suppliers.sabre.refresh_offer_before_public_pnr', true)) {
            return ['status' => 'ok'];
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        if (strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? ''))) !== SupplierProvider::Sabre->value) {
            return ['status' => 'not_sabre'];
        }

        $booking->loadMissing('fareBreakdown');
        $oldCustomerTotal = (float) ($booking->fareBreakdown?->total ?? 0);
        try {
            $refresh = $this->sabreOfferRefreshService->refresh($booking, true);
        } catch (\Throwable $e) {
            Log::warning('booking.offer_refresh.before_pnr_failed', [
                'booking_id' => $booking->id,
                'message' => $e->getMessage(),
            ]);

            return ['status' => 'ok'];
        }
        $booking->refresh();

        $refreshError = trim((string) ($refresh['error'] ?? ''));
        if ($refreshError !== '') {
            $skipRefreshErrors = [
                'missing_stored_segments',
                'missing_search_criteria',
                'missing_offer_snapshot',
                'not_sabre_booking',
            ];
            if (in_array($refreshError, $skipRefreshErrors, true)) {
                return ['status' => 'ok'];
            }

            return ['status' => 'unavailable'];
        }

        if (($refresh['match_found'] ?? false) !== true) {
            return ['status' => 'unavailable'];
        }

        if (($refresh['price_changed'] ?? false) === true || SabreOfferRefreshAcceptance::requiresAcceptance($booking)) {
            SabreOfferRefreshAcceptance::writeCustomerDisplayMeta(
                $booking,
                $this->offerValidationService,
                $oldCustomerTotal,
            );

            return ['status' => 'fare_change_pending'];
        }

        return ['status' => 'ok'];
    }

    protected function redirectToFlightResultsFromBooking(Booking $booking): RedirectResponse
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        $params = array_filter([
            'from' => (string) ($criteria['origin'] ?? ''),
            'to' => (string) ($criteria['destination'] ?? ''),
            'depart' => (string) ($criteria['depart_date'] ?? ''),
            'return_date' => (string) ($criteria['return_date'] ?? ''),
            'trip_type' => (string) ($criteria['trip_type'] ?? 'one_way'),
            'cabin' => (string) ($criteria['cabin'] ?? 'economy'),
            'adults' => (int) ($criteria['adults'] ?? 1),
            'children' => (int) ($criteria['children'] ?? 0),
            'infants' => (int) ($criteria['infants'] ?? 0),
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        $searchId = trim((string) ($meta['checkout_search_id'] ?? ''));
        if ($searchId !== '') {
            $params['search_id'] = $searchId;
        }

        return redirect()->route('flights.results', $params);
    }

    protected function resolvePublicSessionBooking(Request $request, Booking $booking): ?Booking
    {
        $bookingId = $request->session()->get(PublicBooking::SESSION_BOOKING_ID);
        if ($bookingId === null || (int) $bookingId !== (int) $booking->id) {
            return null;
        }

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function redirectToBookingPassengers(array $params, ?string $validationAlert = null): RedirectResponse
    {
        $routeParams = array_filter(
            $params,
            static fn (mixed $v): bool => $v !== null && $v !== ''
        );
        $targetUrl = route('booking.passengers', $routeParams, true);
        $current = request()->fullUrl();
        if (request()->isMethod('get') && $this->normalizedUrlSignature($current) === $this->normalizedUrlSignature($targetUrl)) {
            Log::warning('booking.passengers.self_redirect_blocked', [
                'url' => $current,
            ]);
            request()->session()->forget(self::SESSION_BOOKING_AFTER_STALE_RECOVERY);

            return redirect()->route('flights.search')
                ->withErrors(['flight_id' => __('Checkout could not continue with this fare. Please search again.')]);
        }

        $redirect = redirect()->route('booking.passengers', $routeParams);
        if ($validationAlert !== null) {
            $redirect->with('validation_alert', $validationAlert);
        }

        return $redirect;
    }

    protected function duplicatePublicSabreBookingSubmitMessage(): string
    {
        return (string) __('This booking request is already being processed. Please wait before trying again.');
    }

    protected function maybeAbortDuplicatePublicSabreBookingSubmit(Booking $booking): ?RedirectResponse
    {
        if ($booking->status !== BookingStatus::Draft || $booking->submitted_at !== null) {
            return redirect()->route('booking.confirmation');
        }

        $hasSupplierIdentity = trim((string) ($booking->pnr ?? '')) !== ''
            || trim((string) ($booking->supplier_reference ?? '')) !== ''
            || trim((string) ($booking->supplier_api_booking_id ?? '')) !== '';
        if ($hasSupplierIdentity) {
            return redirect()->route('booking.review')
                ->withErrors(['booking' => (string) __('This booking already has a supplier or airline record. Please refresh this page or contact support if you need changes.')]);
        }

        $latestPublicAttempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->where('action', 'create_pnr')
            ->orderByDesc('id')
            ->first();

        if ($latestPublicAttempt === null) {
            return null;
        }

        $summary = is_array($latestPublicAttempt->safe_summary) ? $latestPublicAttempt->safe_summary : [];
        if (($summary['source'] ?? '') !== 'sabre_public_checkout') {
            return null;
        }

        if ($latestPublicAttempt->status === 'success') {
            return redirect()->route('booking.confirmation');
        }

        if ($latestPublicAttempt->status === 'processing' || $latestPublicAttempt->completed_at === null) {
            return redirect()->route('booking.review')
                ->withErrors(['booking' => $this->duplicatePublicSabreBookingSubmitMessage()]);
        }

        // B81: stale shop / segment — user must start a new search; block repeat live create on this checkout.
        if ($latestPublicAttempt->error_code === 'sabre_passenger_records_stale_shop_segment') {
            return redirect()->route('booking.review')
                ->withErrors(['booking' => (string) __('This flight is no longer available at the selected schedule or class. Please start a new search.')]);
        }

        // B81: Sabre application-level booking error — no immediate customer retry while staff reviews.
        if ($latestPublicAttempt->status === 'needs_review'
            && $latestPublicAttempt->error_code === 'sabre_booking_application_error') {
            if ($this->publicSabreCheckoutAttemptCompletedWithinMinutes($latestPublicAttempt, 60)) {
                return redirect()->route('booking.review')
                    ->withErrors(['booking' => (string) __('Sabre could not complete this booking automatically. Our team will review it; please do not submit again yet.')]);
            }

            return null;
        }

        if ($this->publicSabreCreateShouldThrottleCooldown($latestPublicAttempt)) {
            return redirect()->route('booking.review')
                ->withErrors(['booking' => $this->duplicatePublicSabreBookingSubmitMessage()]);
        }

        return null;
    }

    /**
     * B81: True when a public-checkout Sabre attempt finished within the last {@code $minutes} (inclusive).
     */
    protected function publicSabreCheckoutAttemptCompletedWithinMinutes(SupplierBookingAttempt $attempt, int $minutes): bool
    {
        $completedAt = $attempt->completed_at ?? $attempt->attempted_at;
        if ($completedAt === null) {
            return true;
        }

        return $completedAt->greaterThan(now()->subMinutes($minutes));
    }

    /**
     * B81: Short cooldown after ambiguous transport/rate-limit outcomes so repeat clicks do not stack supplier calls.
     */
    protected function publicSabreCreateShouldThrottleCooldown(SupplierBookingAttempt $attempt): bool
    {
        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        if (! ($summary['live_call_attempted'] ?? false)) {
            return false;
        }

        $completedAt = $attempt->completed_at ?? $attempt->attempted_at;
        if ($completedAt === null) {
            return true;
        }

        if ($completedAt->lessThanOrEqualTo(now()->subMinutes(5))) {
            return false;
        }

        $http = (int) ($summary['http_status'] ?? 0);
        $err = strtolower((string) ($attempt->error_code ?? ''));

        $isTransportCooldownCode = in_array($err, [
            'transport_timeout',
            'sabre_booking_connection_error',
        ], true);

        return $http === 429 || $isTransportCooldownCode;
    }

    protected function normalizedUrlSignature(string $url): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '';
        $query = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        ksort($query);

        return $path.'?'.http_build_query($query);
    }

    /**
     * @return array<string, string>
     */
    protected function checkoutPhoneDialCodes(): array
    {
        return [
            '+92' => 'Pakistan (+92)',
            '+61' => 'Australia (+61)',
            '+971' => 'UAE (+971)',
            '+966' => 'Saudi Arabia (+966)',
            '+44' => 'United Kingdom (+44)',
            '+1' => 'United States / Canada (+1)',
            '+90' => 'Turkey (+90)',
            '+974' => 'Qatar (+974)',
            '+965' => 'Kuwait (+965)',
            '+60' => 'Malaysia (+60)',
            '+65' => 'Singapore (+65)',
            '+94' => 'Sri Lanka (+94)',
            '+86' => 'China (+86)',
            '+66' => 'Thailand (+66)',
            '+62' => 'Indonesia (+62)',
            '+49' => 'Germany (+49)',
            '+33' => 'France (+33)',
            '+39' => 'Italy (+39)',
            '+34' => 'Spain (+34)',
            '+31' => 'Netherlands (+31)',
            '+64' => 'New Zealand (+64)',
            '+81' => 'Japan (+81)',
            '+82' => 'South Korea (+82)',
            '+91' => 'India (+91)',
            '+880' => 'Bangladesh (+880)',
            '+968' => 'Oman (+968)',
            '+973' => 'Bahrain (+973)',
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array{code: string, number: string}
     */
    protected function checkoutContactPhoneParts(Request $request, array $draft): array
    {
        if ($request->old('phone_country_code') !== null || $request->old('phone_number') !== null) {
            return [
                'code' => $this->normalizeCheckoutDialCode((string) $request->old('phone_country_code', '+92')),
                'number' => preg_replace('/\D+/', '', (string) $request->old('phone_number', '')) ?? '',
            ];
        }

        $phone = trim((string) $request->old('phone', (string) ($draft['phone'] ?? '')));
        if ($phone === '') {
            return ['code' => '+92', 'number' => ''];
        }

        $dialCodes = array_keys($this->checkoutPhoneDialCodes());
        usort($dialCodes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($dialCodes as $dial) {
            if (str_starts_with($phone, $dial)) {
                return [
                    'code' => $dial,
                    'number' => preg_replace('/\D+/', '', substr($phone, strlen($dial))) ?? '',
                ];
            }
        }

        if (preg_match('/^\+(\d{1,4})(\d{5,})$/', $phone, $matches) === 1) {
            return [
                'code' => '+'.$matches[1],
                'number' => $matches[2],
            ];
        }

        return [
            'code' => '+92',
            'number' => preg_replace('/\D+/', '', $phone) ?? '',
        ];
    }

    protected function normalizeCheckoutDialCode(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return '+92';
        }

        return str_starts_with($code, '+') ? $code : '+'.$code;
    }

    /**
     * Lead contact prefill from the authenticated user's universal profile (empty when guest).
     *
     * @return array{name: string, email: string, country: string, phone_code: string, phone_number: string}
     */
    protected function checkoutContactPrefillForUser(?User $user): array
    {
        $empty = ['name' => '', 'email' => '', 'country' => '', 'phone_code' => '+92', 'phone_number' => ''];

        if ($user === null) {
            return $empty;
        }

        $user->loadMissing('profile');
        $profile = $user->profile;
        $phoneRaw = trim((string) ($profile?->phone ?? $profile?->whatsapp ?? ''));
        $phoneParts = $phoneRaw !== ''
            ? $this->parseCheckoutPhoneString($phoneRaw)
            : ['code' => '+92', 'number' => ''];

        return [
            'name' => trim((string) $user->name),
            'email' => trim((string) $user->email),
            'country' => strtoupper(trim((string) ($profile?->country_code ?? ''))),
            'phone_code' => $phoneParts['code'],
            'phone_number' => $phoneParts['number'],
        ];
    }

    /**
     * @return array{code: string, number: string}
     */
    protected function parseCheckoutPhoneString(string $phone): array
    {
        $phone = trim($phone);
        if ($phone === '') {
            return ['code' => '+92', 'number' => ''];
        }

        $dialCodes = array_keys($this->checkoutPhoneDialCodes());
        usort($dialCodes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($dialCodes as $dial) {
            if (str_starts_with($phone, $dial)) {
                return [
                    'code' => $dial,
                    'number' => preg_replace('/\D+/', '', substr($phone, strlen($dial))) ?? '',
                ];
            }
        }

        if (preg_match('/^\+(\d{1,4})(\d{5,})$/', $phone, $matches) === 1) {
            return [
                'code' => '+'.$matches[1],
                'number' => $matches[2],
            ];
        }

        return [
            'code' => '+92',
            'number' => preg_replace('/\D+/', '', $phone) ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $criteria
     * @param  array<string, string|int>  $resultsQuery
     */
    protected function guardSabreOfferFreshnessAtCheckout(
        Agency $agency,
        array $offer,
        array $criteria,
        string $searchId,
        array $resultsQuery,
    ): ?RedirectResponse {
        if (strcasecmp((string) ($offer['supplier_provider'] ?? ''), SupplierProvider::Sabre->value) !== 0) {
            return null;
        }

        $searchPayload = $searchId !== '' ? $this->searchStore->get($searchId) : null;
        $gate = $this->sabreSelectedOfferRevalidationGate->evaluateCheckoutTransition(
            $agency,
            $offer,
            $criteria,
            $searchId,
            $searchPayload,
        );

        if ($gate['allowed'] ?? false) {
            $this->bookingDraft->merge([
                'offer_freshness' => $gate['freshness_meta'] ?? [],
            ]);

            return null;
        }

        $message = (string) ($gate['message'] ?? $this->sabreOfferFreshness->customerSafeMessage('offer_stale_before_checkout'));
        $code = (string) ($gate['block_code'] ?? 'offer_stale_before_checkout');

        Log::info('booking.checkout.freshness_blocked', [
            'reason_code' => $code,
            'diagnostic' => (string) ($gate['diagnostic'] ?? ''),
            'search_id' => $searchId,
            'offer_id' => (string) ($offer['id'] ?? $offer['offer_id'] ?? ''),
        ]);

        return redirect()->route('flights.results', $resultsQuery)
            ->withErrors(['flight_id' => $message])
            ->with('offer_freshness_refresh_required', true)
            ->with('offer_freshness_selected_offer_id', (string) ($offer['id'] ?? $offer['offer_id'] ?? ''))
            ->with('offer_freshness_block_code', $code);
    }

    protected function guardSabreOfferFreshnessAtBookingSubmit(Booking $booking): ?RedirectResponse
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        if (strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? ''))) !== SupplierProvider::Sabre->value) {
            return null;
        }

        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null)
            ? $meta['normalized_offer_snapshot']
            : (is_array($meta['validated_offer_snapshot'] ?? null)
                ? $meta['validated_offer_snapshot']
                : (is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : []));

        if ($snapshot === []) {
            return null;
        }

        $searchId = trim((string) ($meta['checkout_search_id'] ?? ''));
        $searchPayload = $searchId !== '' ? $this->searchStore->get($searchId) : null;
        $meta = app(SabreHostRejectionFingerprintMatcher::class)
            ->applyMatchToBookingMeta($snapshot, (int) $booking->agency_id, $meta);
        $block = $this->sabreOfferFreshness->blocksBookingSubmit($snapshot, $meta, $searchPayload);

        if ($block === null) {
            return null;
        }

        $patch = $meta;
        $patch['offer_freshness'] = $this->sabreOfferFreshness->buildOfferFreshnessMeta($snapshot, $searchPayload, $meta);
        $patch['sabre_checkout_freshness_block'] = [
            'classification' => (string) ($block['diagnostic'] ?? ''),
            'code' => (string) ($block['code'] ?? ''),
            'at' => now()->toIso8601String(),
        ];
        $booking->forceFill(['meta' => $patch])->save();

        Log::info('booking.submit.freshness_blocked', [
            'booking_id' => $booking->id,
            'reason_code' => (string) ($block['code'] ?? ''),
            'diagnostic' => (string) ($block['diagnostic'] ?? ''),
        ]);

        return redirect()->route('booking.review')
            ->withErrors(['booking' => (string) ($block['message'] ?? '')])
            ->with('offer_freshness_refresh_required', true);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    protected function sabreOfferFreshnessMetaPatchForBooking(array $offer, string $searchId): array
    {
        if (strcasecmp((string) ($offer['supplier_provider'] ?? ''), SupplierProvider::Sabre->value) !== 0) {
            return [];
        }

        $searchPayload = $searchId !== '' ? $this->searchStore->get($searchId) : null;
        $draft = $this->bookingDraft->current();
        $draftFreshness = is_array($draft['offer_freshness'] ?? null) ? $draft['offer_freshness'] : [];

        $freshness = $draftFreshness !== []
            ? $draftFreshness
            : $this->sabreOfferFreshness->buildOfferFreshnessMeta($offer, $searchPayload);

        return [
            'offer_freshness' => $freshness,
            'search_created_at' => $freshness['search_created_at'] ?? null,
            'selected_offer_created_at' => $freshness['selected_offer_created_at'] ?? null,
            'selected_offer_last_revalidated_at' => $freshness['last_revalidated_at'] ?? null,
            'selected_offer_revalidation_status' => $freshness['revalidation_status'] ?? null,
        ];
    }
}
