<?php

namespace App\Services\Suppliers\Sabre\Booking;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Booking\InternationalRouteDetector;
use App\Support\Suppliers\SabrePassengerRecordsMultiSegmentSellVerifier;
use App\Support\Suppliers\SabreTraditionalCpnrIatiWireStructureDiagnostic;
use Illuminate\Support\Carbon;

/**
 * Builds internal Sabre PNR/booking drafts and outbound JSON envelopes for {@see SabreBookingClient}:
 * CreatePassengerNameRecordRQ-style (legacy path) or Trip Orders {@code createBooking} (B10/B12: `trip_orders_reservation_action`
 * for non-ticketing finalize, optional `shop_context` from BFM snapshot ids). **B22:** configurable `flightOffer` / `flightDetails`
 * under {@code createBooking}. **B23:** alternate styles lift {@code flightOffer}/{@code flightDetails}/{@code products} to the HTTP JSON root
 * (Sabre Trip Orders validators read the wire root). **B25:** Trip Orders {@code travelers[].gender} uses Sabre {@code GenderEnum} strings
 * ({@code MALE}/{@code FEMALE}/infant/undisclosed); internal/CPNR gender codes remain single-letter where applicable. **B26:** Trip Orders wire
 * {@code remarks} omitted unless {@code suppliers.sabre.createbooking_send_remarks} is true (plain {@code string[]} breaks {@code BookRemark} deserialization);
 * when enabled, remarks are sent as {@code [{type,text}]} rows. **B27:** Trip Orders traveler/document wire normalization (name regex, passport type
 * mapping via {@code suppliers.sabre.document_type_passport_value}), pre-POST {@code wire_traveler_required_fields_valid} / per-traveler {@code traveler_*}
 * diagnostics (no PII), and HTTP 400 safe digest {@code response_error_paths}. **B28:** final-wire null scan ({@code wire_null_paths}, {@code wire_payload_null_free}),
 * optional-null stripping, safe defaults (payment mode, ticketing.enabled, commit.receivedFrom, passport ISO2 fill), and
 * {@code trip_orders_flight_offer_root_v1} contract ({@code wire_contract_valid}); **B30:** {@code trip_orders_flight_details_camel_v1}
 * segment contract matches {@code flightDetails} wire keys ({@code departure_datetime}, {@code marketing_airline}, …) plus
 * {@code wire_segment_field_style} / {@code wire_segment_required_fields_valid} diagnostics; optional {@code trip_orders_flight_details_full_camel_v1}.
 * **B31:** {@code trip_orders_flight_details_sabre_v1} uses {@code travelers[].passengerCode} (Sabre Trip Orders) instead of {@code passengerTypeCode}, with
 * {@code wire_has_passengerCode} / {@code wire_traveler_field_style}=sabreTripOrders diagnostics. **B32:** same style uses root {@code contactInfo} (email/phone)
 * instead of {@code contact}; wire diagnostics add {@code wire_has_contactInfo}, {@code wire_contact_field_style}, {@code wire_has_contact_email} / {@code wire_has_contact_phone} (booleans only).
 * **B33:** {@code trip_orders_flight_details_sabre_agency_v1} mirrors {@code trip_orders_flight_details_sabre_v1}; both support root {@code agencyContactInfo} (office phone) with {@code wire_has_agency_phone}, {@code wire_agency_phone_field_style}, {@code wire_agency_phone_redacted}, {@code wire_has_customer_contact_phone}, {@code wire_agency_phone_ok}.
 * **B34:** Additional compare styles place agency/office phone under alternate roots ({@code agencyInfo}, {@code agencyContactInfo.phoneNumber}, {@code agencyContactInfo.phones[]}, root {@code agencyPhone}, root {@code phoneNumbers[]}) with {@code wire_agency_phone_paths} (safe dot paths only) + per-style required path validation.
 * **B35:** Compare-only PNR-style phone variants: root {@code phones[]}/{@code phoneNumbers[]} with {@code phoneNumber}+{@code phoneUseType} (or {@code number}+{@code type}), {@code contactInfo.phones[]}, {@code agencyContactInfo.phones[]} with {@code phoneUseType}; preview adds {@code wire_phone_use_type_values_sanitized} (no phone digits).
 * **B36 (compare / POS–agency):** {@code trip_orders_flight_details_sabre_pos_source_phone_v1}, {@code …_pos_phone_v1}, {@code …_agency_root_camel_v1}, {@code …_travelAgency_v1}, {@code …_customerInfo_phone_v1} embed agency phone under {@code POS}/{@code pos}/{@code agency}/{@code travelAgency}/{@code customerInfo}; {@code wire_has_POS}/{@code wire_has_pos}, {@code wire_pcc_present} (from connection PCC availability, no value), config presence flags.
 * **B37 (compare / PNR phone-line):** {@code trip_orders_flight_details_sabre_phoneLine_v1}, {@code …_phoneLines_v1}, {@code …_contactNumbers_v1}, {@code …_pnrContact_v1}, {@code …_reservationContact_v1}, {@code …_contactInfo_phoneLine_v1}, {@code …_travelers_phone_v1} — traditional {@code Number}/{@code Type}/{@code LocationCode} (or {@code PhoneUseType}) shapes; {@code wire_phone_location_values_sanitized}.
 * **B38:** {@code AGENCY_PHONE_BODY_VARIANT_COMPARE_STYLES} (B34–B37 phone-placement experiments); {@code traditional_pnr_create_passenger_name_record_v1} CPNR-root wire for legacy REST paths + redacted preview; {@code buildTraditionalPnrCreatePassengerNameRecordV1Wire}, {@code stripOtaInternalKeysFromBookingWire}.
 * **B39:** {@code sabre:inspect-booking-payload --wire-preview-json --style=traditional_pnr_create_passenger_name_record_v1} uses {@code previewTripOrdersWireJsonForInspectCommand()} traditional branch (not Trip Orders style fallback); {@code summarizeTraditionalPnrWirePostBody}, {@code redactTraditionalPnrWireJsonForPreview}, CPNR wire embeds {@code TravelItineraryAddInfo.CustomerInfo.PersonName}; {@code wire_traditional_pnr_contract_valid} gates {@code sabre:compare-booking-endpoints --send} for traditional style. **B58:** {@code CustomerInfo.Email[]} rows include {@code Type=TO}. **B59:** root {@code AirPrice} {@code OptionalQualifiers.PricingQualifiers.PassengerType}.
 * **B43:** Traditional CPNR adds {@code haltOnAirPriceError}, root {@code AirPrice} as an array of rows with {@code PriceRequestInformation.Retain} (**B47/B50:** not under {@code AirBook}), {@code targetCity} when PCC resolves; wire summary adds {@code wire_has_target_city}, {@code wire_has_air_price} / {@code wire_has_root_air_price}, {@code wire_root_air_price_*}, {@code wire_has_halt_on_air_price_error}, {@code wire_has_email}.
 * **B44:** CPNR {@code AirBook} adds {@code HaltOnStatus} host-hold codes, segment {@code Status}={@code NN}, {@code PostProcessing.EndTransaction} (minimal {@code Source.ReceivedFrom}) + {@code PostProcessing.RedisplayReservation.waitInterval} (**B52**, not {@code EndTransactionRQ}); {@see SabreBookingClient::digestBookingResponseJsonForProbe} merges Passenger Records {@code ApplicationResults} safe digests.
 * **B48:** Passenger Records {@code AirBook.OriginDestinationInformation.FlightSegment[]} sell schema rejects {@code CabinCode}, {@code ClassOfService}, {@code FareBasisCode}, and {@code Number}; wire keeps booking class only as {@code ResBookDesigCode} (plus allowed sell fields). {@code summarizeTraditionalPnrWirePostBody} adds {@code wire_flight_segment_has_*} diagnostics; {@code traditionalPnrV1AugmentCpnrBlock} strips forbidden segment keys defensively.
 * **B49:** Passenger Records schema expects {@code FlightSegment.NumberInParty} as a **string** primitive (e.g. {@code "1"}); {@code buildSabreApiEnvelope} / {@code traditionalPnrV1AugmentCpnrBlock} emit string values. {@code summarizeTraditionalPnrWirePostBody} adds {@code wire_flight_segment_number_in_party_type} / {@code wire_flight_segment_number_in_party_valid}.
 * **B46:** {@code haltOnAirBookError} removed from {@code CreatePassengerNameRecordRQ} (Sabre REST schema rejects it); wire summary adds {@code wire_has_halt_on_air_book_error} (expect {@code false}).
 * **B47:** Passenger Records REST rejects {@code AirBook.AirPrice}, {@code AirBook.OTAFareBreakdownSummary}, {@code AirBook.PriceQuoteInformation}; {@code buildSabreApiEnvelope} keeps sell-only {@code AirBook} and lifts pricing to {@code CreatePassengerNameRecordRQ} root. {@code summarizeTraditionalPnrWirePostBody} adds {@code wire_airbook_has_air_price}, {@code wire_airbook_has_price_quote_information}, {@code wire_airbook_has_fare_breakdown_summary}, {@code wire_has_root_air_price} (mirrors retain presence), {@code wire_root_air_price_*}.
 * **B50:** Passenger Records expects root {@code CreatePassengerNameRecordRQ.AirPrice} as an **array** (not a JSON object); wire emits {@code [{ PriceRequestInformation: { Retain: true } }]}; {@code summarizeTraditionalPnrWirePostBody} adds {@code wire_root_air_price_type}, {@code wire_root_air_price_count}, {@code wire_root_air_price_retain_present}.
 * **B53:** Passenger Records {@code SpecialReqDetails.AddRemark.RemarkInfo.Remark[].Type} uses Sabre enum casing (e.g. {@code General}, not {@code GENERAL}); {@code summarizeTraditionalPnrWirePostBody} adds {@code wire_remarks_count}, {@code wire_remark_type_values_sanitized}, {@code wire_remark_type_enum_valid}, {@code wire_has_general_remark}.
 * **B54:** Passenger Records {@code SpecialReqDetails.SpecialService} must not include a {@code Service} child (Sabre REST schema rejects it); TTL/manual hints use {@code AddRemark} only. {@code summarizeTraditionalPnrWirePostBody} adds {@code wire_special_service_present}, {@code wire_special_service_has_service}, {@code wire_special_service_omitted}, {@code wire_add_remark_present}; {@code traditionalPnrV1AugmentCpnrBlock} strips any {@code SpecialService} subtree defensively.
 * **B55:** Passenger Records {@code TravelItineraryAddInfo.AgencyInfo} must not include {@code Telephone} (schema rejects it); agency office phone stays off CPNR {@code AgencyInfo}. {@code summarizeTraditionalPnrWirePostBody} adds {@code wire_agency_info_present}, {@code wire_agency_info_has_telephone}, {@code wire_customer_info_has_contact_numbers}, {@code wire_customer_info_has_email}; augment strips {@code AgencyInfo.Telephone}.
 * **B56:** Passenger Records {@code TravelItineraryAddInfo.CustomerInfo.PersonName} must be a JSON **array** of rows (not one associative object). {@code traditionalPnrV1AugmentCpnrBlock} emits {@code PersonName} as {@code array_values} list and {@code traditionalPnrNormalizeCustomerInfoPersonNameToArray} wraps legacy single-row objects. {@code summarizeTraditionalPnrWirePostBody} adds {@code wire_customer_person_name_type}, {@code wire_customer_person_name_count}, {@code wire_customer_person_name_array_valid}.
 * **B58:** Traditional CPNR {@code CustomerInfo.Email} rows include {@code Type=TO} (IATI GDS parity); {@code traditionalPnrNormalizeCustomerInfoEmailForTraditionalPnr} coerces object→list and backfills missing {@code Type}. {@code summarizeTraditionalPnrWirePostBody} adds {@code wire_customer_email_*}; {@see SabreBookingService::traditionalPnrWireInspectPreviewMatchesContract} requires {@code wire_customer_email_type_valid=true} when email is present.
 * **B79:** Compare-only {@code traditional_pnr_create_passenger_name_record_v1_airprice_validating_carrier_compare_v1} adds root {@code AirPrice...PricingQualifiers.ValidatingCarrier.Code}
 * when draft {@code validating_carrier} sanitizes to a 2–3 char carrier token (never live default wire).
 * **D2C:** Gated {@code suppliers.sabre.traditional_cpnr_airprice_validating_carrier} adds the same {@code ValidatingCarrier} qualifier on live
 * {@code traditional_pnr_create_passenger_name_record_v1} wire via {@see self::traditionalPnrApplyRootAirPriceValidatingCarrierCompareQualifier} (default off).
 * **F9I:** {@see self::buildIatiLikeCpnrV24GdsWire} merges AirPrice validating carrier when draft {@code validating_carrier} sanitizes; enriches {@code sabre_booking_context} and maps per-segment {@code CommandPricing} fare basis for mixed-carrier preflight.
 * **F9K:** IATI-like v2.4 wire places VC at {@code OptionalQualifiers.FlightQualifiers.VendorPrefs.Airline.Code} (Sabre schema); strips forbidden
 * {@code PricingQualifiers.ValidatingCarrier} on that lane.
 * **B60:** {@code summarizeTraditionalPnrWirePostBody} optional booking-meta arg adds **inspect-only** {@code wire_segment_sell_context_*}, {@code wire_offer_snapshot_*}, {@code wire_offer_has_raw_sabre_identifiers}, {@code wire_offer_has_brand_candidates}, {@code wire_brand_candidate_keys_sanitized}; no outbound JSON changes.
 * **B75:** {@see self::traditionalPnrAirBookSegmentSellDiagnostics} — safe {@code AirBook} segment sell rows + connection gaps + route continuity for Passenger Records **0411 / FLIGHT NOOP** triage (fare basis from snapshot only; no PII).
 * **BF7-A/BF7-D/F:** {@see self::summarizeAirPriceBrandQualifierForInspect()} + {@see self::candidateAirPriceBrandShapesForCompare()} — local inspect-only AirPrice {@code Brand} shape audit (gate {@code suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled}); **BF7-F** default wire {@code Brand:[{content}]} via {@see self::DEFAULT_AIRPRICE_BRAND_SHAPE_SELECTOR}; BF7-D adds 10 compare variants via {@see self::AIRPRICE_BRAND_SHAPE_COMPARE_VARIANTS} + {@see self::resolveAirPriceBrandNodeForWire()}.
 * **B61:** Gated {@code suppliers.sabre.traditional_cpnr_airbook_retry_redisplay} adds {@code AirBook.RetryRebook} (required boolean {@code Option}, **B61B**) + **integer** {@code NumAttempts}/{@code WaitInterval} (**B61A**) and {@code AirBook.RedisplayReservation} integers; {@code summarizeTraditionalPnrWirePostBody} adds {@code wire_airbook_retry_rebook_has_option}, {@code wire_airbook_retry_rebook_option_type}, {@code wire_airbook_retry_rebook_contract_valid}, plus numeric flags; {@code PostProcessing.RedisplayReservation} unchanged.
 * Excludes payment card data;
 * callers must not log full payloads. Keys prefixed with {@code _ota} are diagnostics-only and stripped before HTTP.
 */
final class SabreBookingPayloadBuilder
{
    /**
     * B53: Sabre Passenger Records {@code AddRemark.Remark.Type} enum (case-sensitive; excerpt from REST validation).
     *
     * @var list<string>
     */
    private const TRADITIONAL_CPNR_ADD_REMARK_TYPE_ENUM = [
        'Alpha-Coded', 'Client Address', 'Corporate', 'Delivery Address', 'General',
        'Group Name', 'Hidden', 'Historical', 'Invoice', 'Itinerary',
    ];

    /** @var list<string> */
    private const TRADITIONAL_CPNR_HALT_ON_STATUS_BASE = ['HL', 'LL', 'NN', 'UC', 'US', 'NO', 'WN'];

    /** @var list<string> */
    private const IATI_LIKE_CPNR_HALT_ON_STATUS_EXTRA = ['KK', 'UN', 'UU'];

    /** @var list<string> IATI GDS reference parity — omits NN/WN halt (CERT diagnostic / IATI template). */
    private const IATI_LIKE_CPNR_HALT_ON_STATUS_WITHOUT_NN_WN = ['HL', 'LL', 'UC', 'US', 'NO', 'KK', 'UN', 'UU'];

    public function __construct(
        protected InternationalRouteDetector $internationalRouteDetector,
    ) {}

    /**
     * B36: Resolve pseudo-city / PCC string for Trip Orders POS wire (from draft override or {@see SupplierConnection} credentials; never logged by callers).
     */
    public function resolveSabrePseudoCityCodeForTripOrdersWire(array $internalDraft): string
    {
        $explicit = trim((string) ($internalDraft['_sabre_pseudo_city_code'] ?? ''));
        if ($explicit !== '') {
            return strtoupper(substr($explicit, 0, 16));
        }
        $cid = (int) ($internalDraft['supplier_connection_id'] ?? 0);
        if ($cid <= 0) {
            return '';
        }
        $conn = SupplierConnection::query()->find($cid);
        if ($conn === null) {
            return '';
        }
        $cred = is_array($conn->credentials) ? $conn->credentials : [];
        $settings = is_array($conn->settings) ? $conn->settings : [];
        foreach (['pcc', 'PCC', 'pseudo_city_code', 'pseudoCityCode'] as $key) {
            $v = trim((string) ($cred[$key] ?? ''));
            if ($v !== '') {
                return strtoupper(substr($v, 0, 16));
            }
            $v = trim((string) data_get($settings, $key));
            if ($v !== '') {
                return strtoupper(substr($v, 0, 16));
            }
        }

        return '';
    }

    /**
     * Internal draft after offer validation (same shape historically produced by {@see SabreBookingService::prepareBookingPayload}).
     *
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $passengerData  keys: passengers[], contact[]
     * @return array<string, mixed>
     */
    public function buildInternalDraft(array $offer, array $passengerData): array
    {
        $normalized = $this->normalizeOfferForPayload($offer);
        $passengersIn = is_array($passengerData['passengers'] ?? null) ? $passengerData['passengers'] : [];
        $contact = is_array($passengerData['contact'] ?? null) ? $passengerData['contact'] : [];

        $firstOrig = '';
        $lastDest = '';
        $segs = is_array($normalized['_segments_out'] ?? null) ? $normalized['_segments_out'] : [];
        if ($segs !== []) {
            $firstOrig = strtoupper(trim((string) ($segs[0]['origin'] ?? '')));
            $last = $segs[array_key_last($segs)];
            $lastDest = strtoupper(trim((string) ($last['destination'] ?? '')));
        }
        $requiresPassportDoc = $this->internationalRouteDetector->requiresPassportOnlyTravelDocuments(
            $offer,
            $firstOrig !== '' ? $firstOrig : null,
            $lastDest !== '' ? $lastDest : null,
        );

        $passengersOut = [];
        foreach ($passengersIn as $row) {
            if (! is_array($row)) {
                continue;
            }
            $pax = [
                'type' => $this->passengerTypeToSabreCode((string) ($row['passenger_type'] ?? $row['type'] ?? 'adult')),
                'first_name' => trim((string) ($row['first_name'] ?? '')),
                'last_name' => trim((string) ($row['last_name'] ?? '')),
            ];
            $genderRaw = isset($row['gender']) ? trim((string) $row['gender']) : '';
            if ($genderRaw !== '') {
                $pax['gender'] = $this->mapToSabreTripOrdersGenderEnum($genderRaw);
            }
            if (isset($row['date_of_birth']) && $row['date_of_birth'] !== null && $row['date_of_birth'] !== '') {
                $dobRaw = $row['date_of_birth'];
                if ($dobRaw instanceof \DateTimeInterface) {
                    $pax['date_of_birth'] = $dobRaw->format('Y-m-d');
                } elseif (is_string($dobRaw) && trim($dobRaw) !== '') {
                    $pax['date_of_birth'] = trim($dobRaw);
                }
            }
            if ($requiresPassportDoc) {
                foreach ([
                    'passport_number' => 'passport_number',
                    'passport_issuing_country' => 'passport_issuing_country',
                    'passport_expiry_date' => 'passport_expiry_date',
                    'nationality' => 'nationality',
                    'document_type' => 'document_type',
                ] as $from => $to) {
                    if (isset($row[$from]) && is_string($row[$from]) && trim($row[$from]) !== '') {
                        $pax[$to] = trim($row[$from]);
                    }
                }
            } else {
                foreach (['national_id_number' => 'national_id_number', 'document_type' => 'document_type'] as $from => $to) {
                    if (isset($row[$from]) && is_string($row[$from]) && trim($row[$from]) !== '') {
                        $pax[$to] = trim($row[$from]);
                    }
                }
            }
            $passengersOut[] = $pax;
        }

        $girArchive = is_array($normalized['_sabre_bfm_gir_archive'] ?? null)
            ? $normalized['_sabre_bfm_gir_archive']
            : (is_array(data_get($offer, 'raw_payload.sabre_bfm_gir_archive'))
                ? data_get($offer, 'raw_payload.sabre_bfm_gir_archive')
                : []);
        if ($girArchive !== []) {
            $girArchive = $this->sanitizeGirArchiveSegmentSellRows($girArchive);
        }

        return [
            '_valid' => true,
            'provider' => SupplierProvider::Sabre->value,
            'selected_offer_id' => (string) ($normalized['offer_id'] ?? $normalized['id'] ?? ''),
            'supplier_connection_id' => (int) ($normalized['supplier_connection_id'] ?? 0),
            'supplier_offer_id' => (string) ($normalized['supplier_offer_id'] ?? ''),
            '_sabre_shop_identifiers' => is_array($normalized['_sabre_shop_identifiers'] ?? null) ? $normalized['_sabre_shop_identifiers'] : [],
            '_sabre_shop_context' => is_array($normalized['_sabre_shop_context'] ?? null) ? $normalized['_sabre_shop_context'] : [],
            '_sabre_bfm_gir_archive' => $girArchive,
            'validating_carrier' => strtoupper(trim((string) ($normalized['validating_carrier'] ?? ''))),
            'fare_family' => isset($normalized['fare_family']) && is_string($normalized['fare_family'])
                ? trim($normalized['fare_family'])
                : null,
            'fare' => [
                'amount' => (float) ($normalized['_fare_amount'] ?? 0),
                'currency' => (string) ($normalized['_fare_currency'] ?? ''),
                'base_fare' => (float) ($normalized['_fare_base'] ?? 0),
                'taxes' => (float) ($normalized['_fare_taxes'] ?? 0),
            ],
            'baggage_summary' => (string) ($normalized['_baggage_summary'] ?? ''),
            'segments' => $normalized['_segments_out'],
            '_b65_multi_segment_prep' => is_array($normalized['_b65_multi_segment_prep'] ?? null)
                ? $normalized['_b65_multi_segment_prep']
                : [],
            'passengers' => $passengersOut,
            'contact' => [
                'email' => trim((string) ($contact['email'] ?? '')),
                'phone' => trim((string) ($contact['phone'] ?? '')),
            ],
            '_requires_passport_doc' => $requiresPassportDoc,
            '_sabre_booking_context' => is_array($normalized['_sabre_booking_context'] ?? null)
                ? $normalized['_sabre_booking_context']
                : [],
            'checkout_payment_mode' => trim((string) ($normalized['checkout_payment_mode'] ?? '')) !== ''
                ? trim((string) $normalized['checkout_payment_mode'])
                : null,
        ];
    }

    /**
     * CreatePassengerNameRecordRQ-style JSON for Sabre passenger-record REST paths. Field names follow common Sabre XML/JSON hybrids;
     * tune against Sabre cert responses. Never include payment data.
     *
     * @param  array<string, mixed>  $internalDraft  Valid draft (\_valid === true)
     * @param  array<string, mixed>  $ticketingHints  Optional: time_limit_iso, remarks from offer meta — never payment
     * @return array<string, mixed>
     */
    public function buildSabreApiEnvelope(array $internalDraft, array $ticketingHints = []): array
    {
        $mode = (string) config('suppliers.sabre.booking_mode', 'pnr_only');
        $ticketingEnabled = (bool) config('suppliers.sabre.ticketing_enabled', false);
        $segments = is_array($internalDraft['segments'] ?? null) ? $internalDraft['segments'] : [];
        $passengers = is_array($internalDraft['passengers'] ?? null) ? $internalDraft['passengers'] : [];
        $fare = is_array($internalDraft['fare'] ?? null) ? $internalDraft['fare'] : [];
        $contact = is_array($internalDraft['contact'] ?? null) ? $internalDraft['contact'] : [];

        $flightSegments = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $depAt = (string) ($seg['departure_at'] ?? '');
            $arrAt = (string) ($seg['arrival_at'] ?? '');
            $op = strtoupper(trim((string) ($seg['operating_airline_code'] ?? '')));
            $flightNo = trim((string) ($seg['flight_number'] ?? $seg['flight_no'] ?? ''));
            $row = [
                'DepartureDateTime' => $depAt !== '' ? $depAt : null,
                'ArrivalDateTime' => $arrAt !== '' ? $arrAt : null,
                'FlightNumber' => $flightNo,
                'Status' => 'NN',
                'NumberInParty' => (string) max(1, count($passengers)),
                'ResBookDesigCode' => trim((string) ($seg['booking_class'] ?? '')) !== '' ? trim((string) $seg['booking_class']) : null,
                'MarketingAirline' => array_filter([
                    'Code' => (string) ($seg['carrier'] ?? $seg['airline_code'] ?? ''),
                    'FlightNumber' => $flightNo,
                ]),
                'OriginLocation' => ['LocationCode' => (string) ($seg['origin'] ?? '')],
                'DestinationLocation' => ['LocationCode' => (string) ($seg['destination'] ?? '')],
                'MarriageGrp' => 'O',
            ];
            if ($op !== '') {
                $row['OperatingAirline'] = ['Code' => $op];
            }
            $flightSegments[] = array_filter($row, fn ($v) => $v !== null && $v !== '' && $v !== []);
        }

        $travelers = [];
        foreach ($passengers as $p) {
            if (! is_array($p)) {
                continue;
            }
            $travelers[] = array_filter([
                'PassengerTypeCode' => (string) ($p['type'] ?? 'ADT'),
                'GivenName' => (string) ($p['first_name'] ?? ''),
                'Surname' => (string) ($p['last_name'] ?? ''),
                'Gender' => $this->tripOrdersGenderEnumToCpnrGenderCode(isset($p['gender']) ? (string) $p['gender'] : null),
                'BirthDate' => isset($p['date_of_birth']) ? (string) $p['date_of_birth'] : null,
                'Document' => $this->travelerDocumentNode($p),
            ], fn ($v) => $v !== null && $v !== [] && $v !== '');
        }

        $timeLimit = null;
        if (isset($ticketingHints['time_limit_iso']) && is_string($ticketingHints['time_limit_iso']) && trim($ticketingHints['time_limit_iso']) !== '') {
            $timeLimit = trim($ticketingHints['time_limit_iso']);
        }

        $contactNumbers = [];
        $phone = trim((string) ($contact['phone'] ?? ''));
        if ($phone !== '') {
            $contactNumbers[] = ['Phone' => $phone, 'PhoneUseType' => 'H'];
        }

        $email = trim((string) ($contact['email'] ?? ''));

        $remarks = [];
        if (isset($ticketingHints['remarks']) && is_string($ticketingHints['remarks']) && trim($ticketingHints['remarks']) !== '') {
            $remarks[] = ['Type' => 'General', 'Text' => substr(trim($ticketingHints['remarks']), 0, 200)];
        }
        if ($timeLimit !== null) {
            $remarks[] = ['Type' => 'General', 'Text' => 'TTL '.substr($timeLimit, 0, 180)];
        }
        if (! $ticketingEnabled) {
            $remarks[] = ['Type' => 'General', 'Text' => 'TICKETING PENDING MANUAL'];
        }
        $baggageSummary = trim((string) ($internalDraft['baggage_summary'] ?? ''));
        if ($baggageSummary !== '') {
            $remarks[] = ['Type' => 'General', 'Text' => 'BAGGAGE: '.substr($baggageSummary, 0, 160)];
        }

        // B52: minimal PostProcessing.EndTransaction is always required for Passenger Records CPNR (independent of SABRE_TICKETING_ENABLED).
        $includeEndTransaction = true;

        $cpnr = array_filter([
            'version' => '2.5.0',
            'haltOnAirPriceError' => true,
            'AirPrice' => [
                [
                    'PriceRequestInformation' => [
                        'Retain' => true,
                    ],
                ],
            ],
            'TravelItineraryAddInfo' => array_filter([
                'CustomerInfo' => array_filter([
                    'ContactNumbers' => $contactNumbers !== [] ? ['ContactNumber' => $contactNumbers] : null,
                    'Email' => $email !== '' ? [['Address' => $email]] : null,
                    'PersonName' => [],
                ]),
                'AgencyInfo' => [
                    'Ticketing' => [
                        'TicketType' => $ticketingEnabled ? '7TAW' : '7TAW',
                    ],
                ],
            ]),
            'AirBook' => [
                'HaltOnStatus' => $this->buildTraditionalCpnrHaltOnStatusWireRows(
                    $this->resolveTraditionalCpnrHaltOnStatusCodes(
                        iatiLike: false,
                        omitNnWn: $this->traditionalCpnrDraftOmitsNnWnFromHaltOnStatus($internalDraft),
                    )
                ),
                'OriginDestinationInformation' => [
                    'FlightSegment' => $flightSegments,
                ],
            ],
            'TravelItineraryRead' => null,
        ]);

        $specialReq = [];
        if ($remarks !== []) {
            $specialReq['AddRemark'] = [
                'RemarkInfo' => ['Remark' => $remarks],
            ];
        }
        if ($specialReq !== []) {
            $cpnr['SpecialReqDetails'] = $specialReq;
        }

        $envelope = [
            'ota_schema' => 'sabre_create_passenger_name_record_v1',
            'ota_booking_mode' => $mode,
            'context' => [
                'supplier' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => (int) ($internalDraft['supplier_connection_id'] ?? 0),
                'selected_offer_id' => (string) ($internalDraft['selected_offer_id'] ?? ''),
                'supplier_offer_id' => (string) ($internalDraft['supplier_offer_id'] ?? ''),
            ],
            'CreatePassengerNameRecordRQ' => $cpnr,
            'travelers' => $travelers,
            'pricing' => [
                'total' => (float) ($fare['amount'] ?? 0),
                'currency' => (string) ($fare['currency'] ?? ''),
                'validating_carrier' => (string) ($internalDraft['validating_carrier'] ?? ''),
                'fare_family' => $internalDraft['fare_family'] ?? null,
            ],
            'itinerary' => [
                'segments' => $segments,
            ],
            'contact' => [
                'email' => $email,
                'phone' => $phone,
            ],
            'ticketing' => array_filter([
                'time_limit_hint' => $timeLimit,
                'remarks' => isset($ticketingHints['remarks']) && is_string($ticketingHints['remarks'])
                    ? substr($ticketingHints['remarks'], 0, 240)
                    : null,
                'ticketing_enabled' => $ticketingEnabled,
            ]),
        ];

        if ($includeEndTransaction) {
            // B51: EndTransactionRQ is not allowed on Passenger Records. B52: EndTransaction object is required (minimal, non-ticketing).
            $envelope['PostProcessing'] = [
                'EndTransaction' => [
                    'Source' => [
                        'ReceivedFrom' => 'OTA_WEB',
                    ],
                ],
                'RedisplayReservation' => [
                    'waitInterval' => 2000,
                ],
            ];
        }

        return $envelope;
    }

    /**
     * B38: REST wire body with {@code CreatePassengerNameRecordRQ} at the JSON root for legacy passenger-record endpoints
     * (compare/inspect only unless explicitly sent with {@code sabre:compare-booking-endpoints --send}). **B55:** no {@code AgencyInfo.Telephone} on Passenger Records. **B56:** {@code CustomerInfo.PersonName} is a JSON array of name rows (never a lone object). AirBook segments use sell-allowed keys only (B48: no {@code ClassOfService} / pricing mirrors).
     * Ticketing remains disabled via existing CPNR markers (no auto ticket).
     *
     * @param  array<string, mixed>  $internalDraft  Valid draft (\_valid === true)
     * @param  array<string, mixed>  $ticketingHints  Optional TTL/remarks hints — never payment
     * @return array<string, mixed>
     */
    public function buildTraditionalPnrCreatePassengerNameRecordV1Wire(array $internalDraft, array $ticketingHints = []): array
    {
        $base = $this->buildSabreApiEnvelope($internalDraft, $ticketingHints);
        $cpnr = is_array($base['CreatePassengerNameRecordRQ'] ?? null) ? $base['CreatePassengerNameRecordRQ'] : [];
        $cpnr = $this->traditionalPnrV1AugmentCpnrBlock($cpnr, $internalDraft);
        if ((bool) config('suppliers.sabre.traditional_cpnr_airprice_validating_carrier', false)) {
            $cpnr = $this->traditionalPnrApplyRootAirPriceValidatingCarrierCompareQualifier($cpnr, $internalDraft);
        }
        if (isset($base['PostProcessing']) && is_array($base['PostProcessing'])) {
            $cpnr['PostProcessing'] = $base['PostProcessing'];
        }
        if (isset($cpnr['PostProcessing']) && is_array($cpnr['PostProcessing'])) {
            unset($cpnr['PostProcessing']['EndTransactionRQ']);
        }
        $cpnr = $this->traditionalPnrNormalizeCpnrAddRemarkTypes($cpnr);

        return [
            'CreatePassengerNameRecordRQ' => $cpnr,
            '_ota_payload_schema' => self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
            '_ota_ticketing_disabled_marker' => true,
        ];
    }

    /**
     * B79: Compare/inspect-only Passenger Records wire — {@see self::buildTraditionalPnrCreatePassengerNameRecordV1Wire} plus optional root
     * {@code AirPrice...PricingQualifiers.ValidatingCarrier} when draft validating carrier token is safe (not used for live checkout default).
     *
     * @param  array<string, mixed>  $internalDraft  Valid draft (\_valid === true)
     * @param  array<string, mixed>  $ticketingHints  Optional TTL/remarks hints — never payment
     * @return array<string, mixed>
     */
    public function buildTraditionalPnrCreatePassengerNameRecordV1AirpriceValidatingCarrierCompareWire(array $internalDraft, array $ticketingHints = []): array
    {
        $base = $this->buildTraditionalPnrCreatePassengerNameRecordV1Wire($internalDraft, $ticketingHints);
        $cpnr = is_array($base['CreatePassengerNameRecordRQ'] ?? null) ? $base['CreatePassengerNameRecordRQ'] : [];
        $cpnr = $this->traditionalPnrApplyRootAirPriceValidatingCarrierCompareQualifier($cpnr, $internalDraft);

        return [
            'CreatePassengerNameRecordRQ' => $cpnr,
            '_ota_payload_schema' => self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_VALIDATING_CARRIER_COMPARE_V1,
            '_ota_ticketing_disabled_marker' => true,
        ];
    }

    /**
     * P4: Compare/inspect-only — root {@code AirPrice...PricingQualifiers.CommandPricing} with per-segment {@code FareBasis}
     * from draft segments (mixed/interline experiment; not live checkout default).
     *
     * @param  array<string, mixed>  $internalDraft
     * @param  array<string, mixed>  $ticketingHints
     * @return array<string, mixed>
     */
    public function buildTraditionalPnrCreatePassengerNameRecordV1AirpricePerSegmentFareBasisCompareWire(array $internalDraft, array $ticketingHints = []): array
    {
        $base = $this->buildTraditionalPnrCreatePassengerNameRecordV1Wire($internalDraft, $ticketingHints);
        $cpnr = is_array($base['CreatePassengerNameRecordRQ'] ?? null) ? $base['CreatePassengerNameRecordRQ'] : [];
        $cpnr = $this->traditionalPnrApplyRootAirPricePerSegmentFareBasisCompareQualifier($cpnr, $internalDraft);

        return [
            'CreatePassengerNameRecordRQ' => $cpnr,
            '_ota_payload_schema' => self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_PER_SEGMENT_FARE_BASIS_COMPARE_V1,
            '_ota_ticketing_disabled_marker' => true,
        ];
    }

    /**
     * P4: Compare/inspect-only — {@see self::buildTraditionalPnrCreatePassengerNameRecordV1Wire} with AirBook
     * {@code RetryRebook} + {@code RedisplayReservation} (IATI helper parity; not gated on production config).
     *
     * @param  array<string, mixed>  $internalDraft
     * @param  array<string, mixed>  $ticketingHints
     * @return array<string, mixed>
     */
    public function buildTraditionalPnrCreatePassengerNameRecordV1AirbookRetryRebookRedisplayCompareWire(array $internalDraft, array $ticketingHints = []): array
    {
        $base = $this->buildTraditionalPnrCreatePassengerNameRecordV1Wire($internalDraft, $ticketingHints);
        $cpnr = is_array($base['CreatePassengerNameRecordRQ'] ?? null) ? $base['CreatePassengerNameRecordRQ'] : [];
        if (isset($cpnr['AirBook']) && is_array($cpnr['AirBook'])) {
            $ab = $cpnr['AirBook'];
            $ab['RetryRebook'] = [
                'Option' => true,
                'NumAttempts' => 3,
                'WaitInterval' => 1000,
            ];
            $ab['RedisplayReservation'] = [
                'NumAttempts' => 3,
                'WaitInterval' => 1000,
            ];
            $cpnr['AirBook'] = $ab;
        }

        return [
            'CreatePassengerNameRecordRQ' => $cpnr,
            '_ota_payload_schema' => self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRBOOK_RETRY_REBOOK_REDISPLAY_COMPARE_V1,
            '_ota_ticketing_disabled_marker' => true,
        ];
    }

    /**
     * Sprint 2A: IATI GDS-style CPNR v2.4.0 wire (side-by-side; not live default). No ticket issue; time-limit via {@code 7TAW}.
     *
     * @param  array<string, mixed>  $internalDraft  Valid draft (\_valid === true)
     * @param  array<string, mixed>  $ticketingHints  Optional TTL/remarks hints — never payment
     * @return array<string, mixed>
     */
    public function buildIatiLikeCpnrV24GdsWire(array $internalDraft, array $ticketingHints = []): array
    {
        $draft = $this->enrichInternalDraftFromSabreBookingContext($internalDraft);
        $base = $this->buildTraditionalPnrCreatePassengerNameRecordV1Wire($draft, $ticketingHints);
        $cpnr = is_array($base['CreatePassengerNameRecordRQ'] ?? null) ? $base['CreatePassengerNameRecordRQ'] : [];
        $cpnr['version'] = '2.4.0';
        $cpnr = $this->traditionalPnrIatiLikeAugmentCpnrBlock($cpnr, $draft);
        $cpnr = $this->traditionalPnrIatiLikeNormalizeKnownGoodStructuralWire($cpnr);
        $ctx = is_array($draft['_sabre_booking_context'] ?? null) ? $draft['_sabre_booking_context'] : [];
        $cpnr = $this->traditionalPnrApplyIatiRootAirPricePerSegmentCommandPricingFromContext($cpnr, $draft, $ctx);
        $brand = $this->traditionalPnrResolveBrandCodeFromDraft($draft);
        if ($brand !== null) {
            $cpnr = $this->traditionalPnrApplyIatiV24RootAirPriceBrandQualifier($cpnr, $brand);
        }
        $cpnr = $this->traditionalPnrApplyRootAirPriceFlightQualifiersVendorPrefsValidatingCarrier($cpnr, $draft);
        $pp = is_array($cpnr['PostProcessing'] ?? null) ? $cpnr['PostProcessing'] : [];
        $et = is_array($pp['EndTransaction'] ?? null) ? $pp['EndTransaction'] : [];
        $src = is_array($et['Source'] ?? null) ? $et['Source'] : [];
        $src['ReceivedFrom'] = $this->resolveTraditionalPnrReceivedFromLabel();
        $et['Source'] = $src;
        $pp['EndTransaction'] = $et;
        unset($pp['EndTransactionRQ']);
        $cpnr['PostProcessing'] = $pp;

        return [
            'CreatePassengerNameRecordRQ' => $cpnr,
            '_ota_payload_schema' => self::IATI_LIKE_CPNR_V2_4_GDS,
            '_ota_passenger_records_api_version' => '2.4.0',
            '_ota_ticketing_disabled_marker' => true,
        ];
    }

    /**
     * Sprint v2.5 GDS: certified Passenger Records wire with authoritative {@code sabre_booking_context} mapped into
     * AirPrice qualifiers, PNR-only {@code 7TAW} manual ticketing marker (no ticket issuance / AirTicketRQ).
     *
     * @param  array<string, mixed>  $internalDraft  Valid draft (\_valid === true)
     * @param  array<string, mixed>  $ticketingHints  Optional TTL/remarks hints — never payment
     * @return array<string, mixed>
     */
    public function buildPassengerRecordsV25GdsWire(array $internalDraft, array $ticketingHints = []): array
    {
        $draft = $this->enrichInternalDraftFromSabreBookingContext($internalDraft);
        $base = $this->buildTraditionalPnrCreatePassengerNameRecordV1Wire($draft, $ticketingHints);
        $cpnr = is_array($base['CreatePassengerNameRecordRQ'] ?? null) ? $base['CreatePassengerNameRecordRQ'] : [];
        $ctx = is_array($draft['_sabre_booking_context'] ?? null) ? $draft['_sabre_booking_context'] : [];
        $cpnr['version'] = '2.5.0';
        $cpnr = $this->traditionalPnrApplyGdsV25SabreBookingContextQualifiers($cpnr, $draft, $ctx);
        $cpnr = $this->traditionalPnrHardenGdsV25AirPriceOptionalQualifiers($cpnr);
        $cpnr = $this->traditionalPnrApplyPnrOnlyManualTicketingTimeLimitMarker($cpnr);
        $pp = is_array($cpnr['PostProcessing'] ?? null) ? $cpnr['PostProcessing'] : [];
        $et = is_array($pp['EndTransaction'] ?? null) ? $pp['EndTransaction'] : [];
        $src = is_array($et['Source'] ?? null) ? $et['Source'] : [];
        $src['ReceivedFrom'] = $this->resolveTraditionalPnrReceivedFromLabel();
        $et['Source'] = $src;
        $pp['EndTransaction'] = $et;
        unset($pp['EndTransactionRQ']);
        $cpnr['PostProcessing'] = $pp;

        return [
            'CreatePassengerNameRecordRQ' => $cpnr,
            '_ota_payload_schema' => self::PASSENGER_RECORDS_V2_5_GDS,
            '_ota_passenger_records_api_version' => '2.5.0',
            '_ota_ticketing_disabled_marker' => true,
            '_ota_pnr_only_manual_ticketing_marker' => true,
        ];
    }

    /**
     * Build Passenger Records CPNR wire for a named style (traditional baseline, compare variants, IATI-like v2.4).
     *
     * @param  array<string, mixed>  $internalDraft
     * @param  array<string, mixed>  $ticketingHints
     * @return array<string, mixed>
     */
    public function buildPassengerRecordsCpnrWireForStyle(array $internalDraft, array $ticketingHints, string $style): array
    {
        return match ($style) {
            self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_VALIDATING_CARRIER_COMPARE_V1 => $this->buildTraditionalPnrCreatePassengerNameRecordV1AirpriceValidatingCarrierCompareWire($internalDraft, $ticketingHints),
            self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_PER_SEGMENT_FARE_BASIS_COMPARE_V1 => $this->buildTraditionalPnrCreatePassengerNameRecordV1AirpricePerSegmentFareBasisCompareWire($internalDraft, $ticketingHints),
            self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRBOOK_RETRY_REBOOK_REDISPLAY_COMPARE_V1 => $this->buildTraditionalPnrCreatePassengerNameRecordV1AirbookRetryRebookRedisplayCompareWire($internalDraft, $ticketingHints),
            self::IATI_LIKE_CPNR_V2_4_GDS => $this->buildIatiLikeCpnrV24GdsWire($internalDraft, $ticketingHints),
            self::PASSENGER_RECORDS_V2_5_GDS => $this->buildPassengerRecordsV25GdsWire($internalDraft, $ticketingHints),
            self::MINIMAL_AIRBOOK_AIRPRICE_ENDTRANSACTION_GDS => $this->buildTraditionalPnrCreatePassengerNameRecordV1Wire($internalDraft, $ticketingHints),
            default => $this->buildTraditionalPnrCreatePassengerNameRecordV1Wire($internalDraft, $ticketingHints),
        };
    }

    /**
     * Safe ReceivedFrom for CPNR EndTransaction (no raw user input).
     */
    public function resolveTraditionalPnrReceivedFromLabel(): string
    {
        $name = trim((string) config('suppliers.sabre.agency_name', ''));
        if ($name === '') {
            return 'OTA_WEB';
        }
        $sanitized = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $name);
        $label = trim((string) ($sanitized ?? ''));
        if ($label === '') {
            return 'OTA_WEB';
        }

        return substr($label, 0, 32);
    }

    /**
     * Sprint 2A: IATI-like CPNR augment — HaltOnStatus parity, optional Ticketing.ShortText, SpecialServiceInfo (no Service child).
     *
     * @param  array<string, mixed>  $cpnr
     * @param  array<string, mixed>  $internalDraft
     * @return array<string, mixed>
     */
    protected function traditionalPnrIatiLikeAugmentCpnrBlock(array $cpnr, array $internalDraft): array
    {
        if (isset($cpnr['AirBook']) && is_array($cpnr['AirBook'])) {
            $ab = $cpnr['AirBook'];
            $haltCodes = $this->resolveTraditionalCpnrHaltOnStatusCodes(
                iatiLike: true,
                omitNnWn: $this->traditionalCpnrDraftOmitsNnWnFromHaltOnStatus($internalDraft),
            );
            $ab['HaltOnStatus'] = $this->buildTraditionalCpnrHaltOnStatusWireRows($haltCodes);
            $airbookRetryRedisplay = (bool) config('suppliers.sabre.traditional_cpnr_airbook_retry_redisplay', false);
            if ($airbookRetryRedisplay) {
                $ab['RetryRebook'] = [
                    'Option' => true,
                    'NumAttempts' => 3,
                    'WaitInterval' => 1000,
                ];
                $ab['RedisplayReservation'] = [
                    'NumAttempts' => 3,
                    'WaitInterval' => 1000,
                ];
            }
            $cpnr['AirBook'] = $ab;
        }

        $tia = is_array($cpnr['TravelItineraryAddInfo'] ?? null) ? $cpnr['TravelItineraryAddInfo'] : [];
        $agencyInfo = is_array($tia['AgencyInfo'] ?? null) ? $tia['AgencyInfo'] : [];
        $ticketing = is_array($agencyInfo['Ticketing'] ?? null) ? $agencyInfo['Ticketing'] : [];
        $ticketing['TicketType'] = '7TAW';
        $shortText = trim((string) config('suppliers.sabre.cpnr_iati_ticketing_short_text', ''));
        if ($shortText !== '') {
            $ticketing['ShortText'] = substr($shortText, 0, 16);
        } else {
            unset($ticketing['ShortText']);
        }
        $agencyInfo['Ticketing'] = $ticketing;
        unset($agencyInfo['Telephone']);
        $tia['AgencyInfo'] = $agencyInfo;
        $cpnr['TravelItineraryAddInfo'] = $tia;

        return $this->traditionalPnrIatiLikeApplySpecialServiceInfo($cpnr, $internalDraft);
    }

    /**
     * Align IATI-like v2.4 GDS AirBook sell rows with known-good structural contract (#79/#95/#138).
     *
     * @param  array<string, mixed>  $cpnr
     * @return array<string, mixed>
     */
    protected function traditionalPnrIatiLikeNormalizeKnownGoodStructuralWire(array $cpnr): array
    {
        $tia = is_array($cpnr['TravelItineraryAddInfo'] ?? null) ? $cpnr['TravelItineraryAddInfo'] : [];
        $ci = is_array($tia['CustomerInfo'] ?? null) ? $tia['CustomerInfo'] : [];
        $pn = $ci['PersonName'] ?? null;
        if (is_array($pn)) {
            $rows = array_is_list($pn) ? $pn : [$pn];
            $next = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (! array_key_exists('Infant', $row)) {
                    $row['Infant'] = false;
                }
                $next[] = $row;
            }
            if ($next !== []) {
                $ci['PersonName'] = array_is_list($pn) ? $next : ($next[0] ?? []);
            }
        }
        $tia['CustomerInfo'] = $ci;
        $cpnr['TravelItineraryAddInfo'] = $tia;

        $air = is_array($cpnr['AirBook'] ?? null) ? $cpnr['AirBook'] : [];
        $odi = $air['OriginDestinationInformation'] ?? null;
        if (is_array($odi)) {
            $groups = array_is_list($odi) ? $odi : [$odi];
            $nextGroups = [];
            foreach ($groups as $group) {
                if (! is_array($group)) {
                    continue;
                }
                $fs = $group['FlightSegment'] ?? null;
                if (! is_array($fs)) {
                    $nextGroups[] = $group;
                    continue;
                }
                $wasList = array_is_list($fs);
                $list = $wasList ? $fs : [$fs];
                $nextSegs = [];
                foreach ($list as $seg) {
                    if (! is_array($seg)) {
                        continue;
                    }
                    $nextSegs[] = $this->traditionalPnrIatiLikeNormalizeFlightSegmentRow($seg);
                }
                $group['FlightSegment'] = $wasList ? $nextSegs : ($nextSegs[0] ?? []);
                $nextGroups[] = $group;
            }
            $air['OriginDestinationInformation'] = array_is_list($odi) ? $nextGroups : ($nextGroups[0] ?? []);
            if (isset($air['HaltOnStatus']) && is_array($air['HaltOnStatus'])) {
                $air['HaltOnStatus'] = array_values(array_filter(
                    $air['HaltOnStatus'],
                    static fn ($row): bool => is_array($row)
                        && ! in_array(strtoupper(trim((string) ($row['Code'] ?? ''))), ['NN', 'WN'], true),
                ));
            }
            unset($air['IgnoreAfter']);
            $cpnr['AirBook'] = $air;
        }

        $pp = is_array($cpnr['PostProcessing'] ?? null) ? $cpnr['PostProcessing'] : [];
        if (isset($pp['RedisplayReservation']) && is_array($pp['RedisplayReservation'])) {
            $rd = $pp['RedisplayReservation'];
            if (array_key_exists('WaitInterval', $rd) && ! array_key_exists('waitInterval', $rd)) {
                $rd['waitInterval'] = $rd['WaitInterval'];
                unset($rd['WaitInterval']);
            }
            $pp['RedisplayReservation'] = $rd;
        }
        unset($pp['IgnoreAfter']);
        if ($pp !== []) {
            $cpnr['PostProcessing'] = $pp;
        }

        return $cpnr;
    }

    /**
     * @param  array<string, mixed>  $seg
     * @return array<string, mixed>
     */
    protected function traditionalPnrIatiLikeNormalizeFlightSegmentRow(array $seg): array
    {
        unset($seg['MarriageGrp'], $seg['OperatingAirline'], $seg['ActionCode']);

        $fn = trim((string) ($seg['FlightNumber'] ?? ''));
        if ($fn !== '') {
            $seg['FlightNumber'] = $this->traditionalPnrIatiLikePadFlightNumber($fn);
        }

        $mkt = is_array($seg['MarketingAirline'] ?? null) ? $seg['MarketingAirline'] : [];
        $mktCode = strtoupper(trim((string) ($mkt['Code'] ?? '')));
        $mktFn = trim((string) ($mkt['FlightNumber'] ?? $fn));
        if ($mktCode !== '') {
            $seg['MarketingAirline'] = array_filter([
                'Code' => $mktCode,
                'FlightNumber' => $mktFn !== '' ? $this->traditionalPnrIatiLikePadFlightNumber($mktFn) : null,
            ], static fn ($v) => $v !== null && $v !== '');
        }

        foreach (['DepartureDateTime', 'ArrivalDateTime'] as $key) {
            $raw = trim((string) ($seg[$key] ?? ''));
            if ($raw !== '') {
                $seg[$key] = $this->traditionalPnrIatiLikeSabreDateTime($raw);
            }
        }

        if (array_key_exists('NumberInParty', $seg) && ! is_string($seg['NumberInParty'])) {
            $seg['NumberInParty'] = is_scalar($seg['NumberInParty']) ? (string) $seg['NumberInParty'] : '1';
        }

        $status = strtoupper(trim((string) ($seg['Status'] ?? '')));
        if ($status === '') {
            $seg['Status'] = 'NN';
        }

        return $seg;
    }

    protected function traditionalPnrIatiLikeSabreDateTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        try {
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d\TH:i:s');
        } catch (\Throwable) {
            return $value;
        }
    }

    protected function traditionalPnrIatiLikePadFlightNumber(string $flightNumber): string
    {
        $flightNumber = trim($flightNumber);
        if ($flightNumber === '') {
            return '';
        }
        $digits = preg_replace('/\D+/', '', $flightNumber) ?? '';
        if ($digits === '') {
            return substr($flightNumber, 0, 8);
        }

        return str_pad($digits, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $internalDraft
     */
    public function resolveBrandCodeFromInternalDraftForInspect(array $internalDraft): ?string
    {
        return $this->traditionalPnrResolveBrandCodeFromDraft($internalDraft);
    }

    /**
     * BF7-B: Whitelist-sanitize selected branded fare intent for Sabre booking context (no PII).
     *
     * @param  array<string, mixed>|null  $intent
     * @return array<string, mixed>
     */
    public function sanitizeSelectedFareFamilyForSabreContext(?array $intent, ?string $fareOptionKeyFromMeta = null): array
    {
        if (! is_array($intent)) {
            return [];
        }

        $fareOptionKey = trim((string) ($fareOptionKeyFromMeta ?? ''));
        if ($fareOptionKey === '') {
            $fareOptionKey = trim((string) ($intent['fare_option_key'] ?? $intent['option_key'] ?? ''));
        }

        $brandCode = strtoupper(trim((string) ($intent['brand_code'] ?? $intent['code'] ?? '')));
        if ($brandCode !== '' && preg_match('/^[A-Z0-9]{2,16}$/', $brandCode) !== 1) {
            $brandCode = '';
        }

        $baggage = trim((string) ($intent['baggage'] ?? $intent['baggage_summary'] ?? ''));

        $segmentSliceCount = (int) ($intent['segment_slice_count'] ?? 0);
        $bookingBySeg = $this->normalizeSabreSegmentStringList(
            is_array($intent['booking_classes_by_segment'] ?? null) ? $intent['booking_classes_by_segment'] : [],
        );
        if ($bookingBySeg === [] && $segmentSliceCount <= 1) {
            $singleBc = trim((string) ($intent['booking_class'] ?? ''));
            if ($singleBc !== '') {
                $bookingBySeg = $this->normalizeSabreSegmentStringList([$singleBc]);
            }
        }
        $fareBasisBySeg = $this->normalizeSabreSegmentStringList(
            is_array($intent['fare_basis_codes_by_segment'] ?? null) ? $intent['fare_basis_codes_by_segment'] : (
                is_array($intent['fare_basis_codes'] ?? null) ? $intent['fare_basis_codes'] : []
            ),
        );
        if ($fareBasisBySeg === [] && $segmentSliceCount <= 1) {
            $singleFb = trim((string) ($intent['fare_basis'] ?? ''));
            if ($singleFb !== '') {
                $fareBasisBySeg = $this->normalizeSabreSegmentStringList([$singleFb]);
            }
        }
        if ($segmentSliceCount > 1) {
            if (count($bookingBySeg) === 1) {
                $bookingBySeg = [];
            }
            if (count($fareBasisBySeg) === 1) {
                $fareBasisBySeg = [];
            }
        }
        $cabinBySeg = $this->normalizeSabreSegmentStringList(
            is_array($intent['cabin_by_segment'] ?? null) ? $intent['cabin_by_segment'] : [],
            lowercase: true,
        );

        $pricingIndex = isset($intent['pricing_information_index']) && is_numeric($intent['pricing_information_index'])
            ? (int) $intent['pricing_information_index']
            : null;

        $out = array_filter([
            'brand_name' => trim((string) ($intent['brand_name'] ?? $intent['name'] ?? '')),
            'brand_code' => $brandCode !== '' ? $brandCode : null,
            'fare_option_key' => $fareOptionKey !== '' ? $fareOptionKey : null,
            'baggage' => $baggage !== '' ? $baggage : null,
            'cabin' => trim((string) ($intent['cabin'] ?? '')),
            'booking_class' => $bookingBySeg[0] ?? trim((string) ($intent['booking_class'] ?? '')),
            'fare_basis' => $fareBasisBySeg[0] ?? trim((string) ($intent['fare_basis'] ?? '')),
            'booking_classes_by_segment' => $bookingBySeg !== [] ? $bookingBySeg : null,
            'fare_basis_codes_by_segment' => $fareBasisBySeg !== [] ? $fareBasisBySeg : null,
            'cabin_by_segment' => $cabinBySeg !== [] ? $cabinBySeg : null,
            'pricing_information_index' => $pricingIndex,
            'price_display' => trim((string) ($intent['price_display'] ?? '')),
            'selected_price_total' => isset($intent['displayed_price']) && is_numeric($intent['displayed_price'])
                ? (float) $intent['displayed_price']
                : (isset($intent['price_total']) && is_numeric($intent['price_total']) ? (float) $intent['price_total'] : null),
            'segment_slice_count' => $segmentSliceCount > 0 ? $segmentSliceCount : null,
        ], static fn (mixed $v): bool => $v !== null && $v !== '' && $v !== []);

        return $out;
    }

    /**
     * BF7-B: Merge sanitized selected fare family into Sabre booking context handoff.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $sanitized
     * @return array<string, mixed>
     */
    public function mergeSelectedFareFamilyIntoSabreBookingContext(array $context, array $sanitized): array
    {
        if ($sanitized === []) {
            return $context;
        }

        $context['selected_fare_family_option'] = $sanitized;

        $brandCode = strtoupper(trim((string) ($sanitized['brand_code'] ?? '')));
        if ($brandCode !== '' && preg_match('/^[A-Z0-9]{2,16}$/', $brandCode) === 1) {
            $context['selected_brand_code'] = $brandCode;
            $context['brand_code'] = $brandCode;
        }

        foreach ([
            'booking_classes_by_segment',
            'fare_basis_codes_by_segment',
            'cabin_by_segment',
        ] as $segmentKey) {
            $list = is_array($sanitized[$segmentKey] ?? null) ? $sanitized[$segmentKey] : [];
            if ($list !== []) {
                $context[$segmentKey] = array_values($list);
            }
        }

        if (isset($sanitized['pricing_information_index']) && is_numeric($sanitized['pricing_information_index'])) {
            $context['pricing_information_index'] = (int) $sanitized['pricing_information_index'];
        }

        $baggage = trim((string) ($sanitized['baggage'] ?? ''));
        if ($baggage !== '') {
            $context['baggage'] = $baggage;
        }

        if (isset($sanitized['selected_price_total']) && is_numeric($sanitized['selected_price_total'])) {
            $context['selected_price_total'] = (float) $sanitized['selected_price_total'];
        }

        $segmentCount = max(
            count($context['booking_classes_by_segment'] ?? []),
            count($context['fare_basis_codes_by_segment'] ?? []),
        );
        if ($segmentCount > 0) {
            $context['segment_slice_count'] = $segmentCount;
        }

        return $context;
    }

    /**
     * @return list<string>
     */
    protected function normalizeSabreSegmentStringList(mixed $value, bool $lowercase = false): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            $s = trim((string) $item);
            if ($s === '') {
                continue;
            }
            $out[] = $lowercase ? strtolower($s) : strtoupper($s);
        }

        return array_values($out);
    }

    /**
     * Drop blank GIR segment_sell_rows; never use rows missing origin/destination/carrier/flight number.
     *
     * @param  array<string, mixed>  $archive
     * @return array<string, mixed>
     */
    public function sanitizeGirArchiveSegmentSellRows(array $archive): array
    {
        $rows = is_array($archive['segment_sell_rows'] ?? null) ? $archive['segment_sell_rows'] : [];
        if ($rows === []) {
            return $archive;
        }

        $filtered = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $origin = strtoupper(trim((string) ($row['origin'] ?? '')));
            $destination = strtoupper(trim((string) ($row['destination'] ?? '')));
            $carrier = strtoupper(trim((string) ($row['carrier'] ?? $row['marketing_carrier'] ?? '')));
            $flightNo = trim((string) ($row['flight_number'] ?? $row['flight_no'] ?? ''));
            if ($origin === '' || $destination === '' || $carrier === '' || $flightNo === '') {
                continue;
            }
            $filtered[] = $row;
        }

        $archive['segment_sell_rows'] = $filtered;

        return $archive;
    }

    /** BF7-F: Production default AirPrice Brand wire shape selector (gate off). */
    public const DEFAULT_AIRPRICE_BRAND_SHAPE_SELECTOR = 'object_content';

    /** V25-CPNR: Brand omitted on Passenger Records v2.5 GDS wire (Sabre rejects object-at-0 and string-at-0). */
    public const DEFAULT_AIRPRICE_BRAND_SHAPE_SELECTOR_V25_GDS = 'omit_brand';

    /** V25-CPNR: Safe digest reason when Brand qualifier is withheld from v2.5 GDS wire. */
    public const V25_GDS_BRAND_QUALIFIER_OMITTED_REASON = 'sabre_v25_schema_rejects_brand_qualifier';

    /** V25-CPNR: Host classification when Sabre HTTP 400 indicates Brand is required or shape is unknown. */
    public const V25_BRAND_QUALIFIER_REQUIRED_OR_SHAPE_UNKNOWN = 'sabre_v25_brand_qualifier_required_or_shape_unknown';

    /** V25-CPNR: Safe digest reason when ItineraryOptions/SegmentSelect is withheld from v2.5 GDS wire. */
    public const V25_GDS_SEGMENT_SELECT_OMITTED_REASON = 'sabre_v25_schema_rejects_itinerary_options_segment_select';

    /** IATI v2.4 GDS: Safe digest reason when Brand is omitted because SegmentSelect requires RPH-aligned Brand. */
    public const IATI_V24_GDS_BRAND_OMITTED_FOR_SEGMENTSELECT_REASON = 'sabre_v24_brand_omitted_incompatible_with_segmentselect';

    /** V25-CPNR: Host classification for AirPrice OptionalQualifiers HTTP 400 schema failures. */
    public const V25_AIRPRICE_OPTIONAL_QUALIFIER_SCHEMA_ERROR = 'sabre_v25_airprice_optional_qualifier_schema_error';

    /** V25-CPNR: Generic public customer message when v2.5 optional qualifier schema blocks auto PNR. */
    public const V25_GDS_OPTIONAL_QUALIFIER_CUSTOMER_MESSAGE = 'Your booking request could not be completed automatically. Our team will review it.';

    public const AIRPRICE_SEGMENT_SELECT_REJECTED_POINTER = '/CreatePassengerNameRecordRQ/AirPrice/0/PriceRequestInformation/OptionalQualifiers/PricingQualifiers/ItineraryOptions/SegmentSelect';

    public const AIRPRICE_BRAND_RPH_REJECTED_POINTER = '/CreatePassengerNameRecordRQ/AirPrice/0/PriceRequestInformation/OptionalQualifiers/PricingQualifiers/Brand/0/RPH';

    /** Sabre Passenger Records v2.4 JSON schema: Brand.RPH is integer (not string). */
    public const IATI_V24_GDS_BRAND_RPH_SCHEMA_EXPECTED_TYPE = 'integer';

    /** @var list<string> Schema-safe PricingQualifiers keys for passenger_records_v2_5_gds production wire. */
    public const V25_GDS_ALLOWED_PRICING_QUALIFIER_KEYS = [
        'CommandPricing',
        'PassengerType',
    ];

    /** @var list<string> PricingQualifiers keys always stripped on v2.5 GDS wire. */
    public const V25_GDS_FORBIDDEN_PRICING_QUALIFIER_KEYS = [
        'Brand',
        'ItineraryOptions',
        'ValidatingCarrier',
        'CurrencyCode',
        'SpecificPenalty',
        'AlternateCurrency',
    ];

    /**
     * BF7-B/BF7-D/F: Resolved AirPrice Brand wire shape selector (production default or compare gate).
     */
    public function resolveActiveAirPriceBrandShapeSelector(): string
    {
        if (! (bool) config('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled', false)) {
            return self::DEFAULT_AIRPRICE_BRAND_SHAPE_SELECTOR;
        }

        $variant = strtolower(trim((string) config(
            'suppliers.sabre.branded_fares_airprice_brand_shape_compare_variant',
            self::DEFAULT_AIRPRICE_BRAND_SHAPE_SELECTOR
        )));

        return $this->isSupportedAirPriceBrandShapeCompareVariant($variant)
            ? $variant
            : self::DEFAULT_AIRPRICE_BRAND_SHAPE_SELECTOR;
    }

    /**
     * BF7-B/BF7-D: AirPrice Brand node for CPNR wire (gate-controlled shape compare).
     *
     * @return array<int|string, mixed>|null null = omit Brand node entirely (omit_brand variant)
     */
    protected function resolveAirPriceBrandNodeForWire(string $brandCode): mixed
    {
        $selector = $this->resolveActiveAirPriceBrandShapeSelector();

        return match ($selector) {
            'string_array' => [$brandCode],
            'empty_object_array' => [[]],
            'object_value' => [['value' => $brandCode]],
            'object_content' => [['content' => $brandCode]],
            'object_text' => [['text' => $brandCode]],
            'single_object_code' => ['Code' => $brandCode],
            'single_object_value' => ['value' => $brandCode],
            'single_object_content' => ['content' => $brandCode],
            'omit_brand' => null,
            'current_object_code' => [['Code' => $brandCode]],
            default => [['content' => $brandCode]],
        };
    }

    /**
     * BF7-A: Sanitized selected branded-fare brand code from booking meta (no PII).
     *
     * @param  array<string, mixed>|null  $bookingMeta
     */
    public function selectedFareFamilyBrandCodeFromBookingMetaForInspect(?array $bookingMeta): ?string
    {
        if (! is_array($bookingMeta)) {
            return null;
        }
        $intent = is_array($bookingMeta['selected_fare_family_option'] ?? null)
            ? $bookingMeta['selected_fare_family_option']
            : [];
        foreach ([
            $intent['brand_code'] ?? null,
            $intent['code'] ?? null,
        ] as $candidate) {
            $code = strtoupper(trim((string) ($candidate ?? '')));
            if ($code !== '' && preg_match('/^[A-Z0-9]{2,16}$/', $code) === 1) {
                return $code;
            }
        }

        return null;
    }

    public const AIRPRICE_BRAND_REJECTED_POINTER = '/CreatePassengerNameRecordRQ/AirPrice/0/PriceRequestInformation/OptionalQualifiers/PricingQualifiers/Brand/0';

    /** @var list<string> BF7-D: Controlled CERT Brand wire shape compare variants (gate ON only). */
    public const AIRPRICE_BRAND_SHAPE_COMPARE_VARIANTS = [
        'current_object_code',
        'string_array',
        'empty_object_array',
        'object_value',
        'object_content',
        'object_text',
        'single_object_code',
        'single_object_value',
        'single_object_content',
        'omit_brand',
    ];

    /**
     * @return list<string>
     */
    public function supportedAirPriceBrandShapeCompareVariants(): array
    {
        return self::AIRPRICE_BRAND_SHAPE_COMPARE_VARIANTS;
    }

    public function isSupportedAirPriceBrandShapeCompareVariant(string $variant): bool
    {
        return in_array(strtolower(trim($variant)), self::AIRPRICE_BRAND_SHAPE_COMPARE_VARIANTS, true);
    }

    /**
     * BF7-A: Sanitized AirPrice Brand qualifier summary for local inspect/compare (no PII, no credentials, no HTTP).
     *
     * @param  array<string, mixed>  $internalDraft
     * @param  array<string, mixed>  $wire  Stripped CPNR envelope or {@code CreatePassengerNameRecordRQ} block
     * @param  array<string, mixed>|null  $bookingMeta
     * @return array<string, mixed>
     */
    public function summarizeAirPriceBrandQualifierForInspect(
        array $internalDraft,
        array $wire,
        ?string $payloadStyle = null,
        ?array $bookingMeta = null,
    ): array {
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null)
            ? $wire['CreatePassengerNameRecordRQ']
            : $wire;
        $brandNode = data_get(
            $cpnr,
            'AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand'
        );
        $shape = $this->classifyAirPriceBrandNodeShape($brandNode);
        $resolved = $this->traditionalPnrResolveBrandCodeFromDraft($internalDraft);
        $metaBrand = $this->selectedFareFamilyBrandCodeFromBookingMetaForInspect($bookingMeta);
        $style = trim((string) ($payloadStyle ?? ''));
        $endpointVersion = is_scalar($cpnr['version'] ?? null) ? (string) $cpnr['version'] : null;
        if ($endpointVersion === null && self::isIatiLikeCpnrV24GdsWireStyle($style)) {
            $endpointVersion = '2.4.0';
        }
        if ($endpointVersion === null && self::isTraditionalPnrPassengerRecordsWireStyle($style)) {
            $endpointVersion = '2.5.0';
        }

        $ctx = is_array($internalDraft['_sabre_booking_context'] ?? null)
            ? $internalDraft['_sabre_booking_context']
            : [];
        $mergedIntent = is_array($ctx['selected_fare_family_option'] ?? null)
            ? $ctx['selected_fare_family_option']
            : [];
        $compareGateEnabled = (bool) config('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled', false);
        $compareVariant = strtolower(trim((string) config(
            'suppliers.sabre.branded_fares_airprice_brand_shape_compare_variant',
            'current_object_code'
        )));
        $activeSelector = $this->resolveActiveAirPriceBrandShapeSelector();
        $candidateNode = $resolved !== null
            ? $this->resolveAirPriceBrandNodeForWire($resolved)
            : null;
        $candidateShapeClassified = $candidateNode !== null
            ? $this->classifyAirPriceBrandNodeShape($candidateNode)
            : 'absent';

        $summary = [
            'selected_fare_family_brand_code' => $metaBrand,
            'resolved_brand_code_for_wire' => $resolved,
            'default_brand_node_shape' => 'array_of_content_objects',
            'default_brand_shape_selector' => self::DEFAULT_AIRPRICE_BRAND_SHAPE_SELECTOR,
            'payload_style' => $style !== '' ? $style : null,
            'endpoint_version' => $endpointVersion,
            'current_brand_node_shape' => $shape,
            'current_brand_node_json_preview' => $this->airPriceBrandNodeJsonPreview($brandNode, $shape),
            'brand_present_on_wire' => $shape !== 'absent',
            'rejected_pointer_expected' => self::AIRPRICE_BRAND_REJECTED_POINTER,
            'rejected_shape_is_array_of_code_objects' => $shape === 'array_of_code_objects',
            'candidate_shape_keys' => self::AIRPRICE_BRAND_SHAPE_COMPARE_VARIANTS,
            'compare_gate_enabled' => $compareGateEnabled,
            'compare_variant' => $compareVariant !== '' ? $compareVariant : 'current_object_code',
            'active_brand_shape_selector' => $activeSelector,
            'selected_fare_family_option_merged' => $mergedIntent !== [],
            'merged_context_brand_code' => is_string($ctx['brand_code'] ?? null) && trim($ctx['brand_code']) !== ''
                ? strtoupper(trim($ctx['brand_code']))
                : null,
            'live_call_attempted' => false,
        ];

        if ($compareGateEnabled) {
            $code = $resolved ?? $metaBrand;
            $summary['candidate_shapes'] = $this->candidateAirPriceBrandShapesForCompare($code);
            $summary['candidate_brand_node_shape'] = $candidateShapeClassified;
            $summary['candidate_brand_node_json_preview'] = $this->airPriceBrandNodeJsonPreview(
                $candidateNode,
                $candidateShapeClassified
            );
        }

        return $summary;
    }

    /**
     * BF7-A/BF7-D: Compare-only alternate Brand shapes (local/test gate; not live checkout default).
     *
     * @return array<string, mixed>
     */
    public function candidateAirPriceBrandShapesForCompare(?string $brandCode): array
    {
        $code = strtoupper(trim((string) ($brandCode ?? '')));
        if ($code === '' || preg_match('/^[A-Z0-9]{2,16}$/', $code) !== 1) {
            $empty = array_fill_keys(self::AIRPRICE_BRAND_SHAPE_COMPARE_VARIANTS, null);
            $empty['omit_brand'] = true;

            return $empty;
        }

        return [
            'current_object_code' => [['Code' => $code]],
            'string_array' => [$code],
            'empty_object_array' => [[]],
            'object_value' => [['value' => $code]],
            'object_content' => [['content' => $code]],
            'object_text' => [['text' => $code]],
            'single_object_code' => ['Code' => $code],
            'single_object_value' => ['value' => $code],
            'single_object_content' => ['content' => $code],
            'omit_brand' => null,
        ];
    }

    /**
     * @return 'array_of_code_objects'|'array_of_strings'|'array_of_empty_objects'|'array_of_value_objects'|'array_of_content_objects'|'array_of_text_objects'|'scalar_string'|'single_code_object'|'single_value_object'|'single_content_object'|'single_text_object'|'absent'|'unknown'
     */
    public function classifyAirPriceBrandNodeShape(mixed $brandNode): string
    {
        if ($brandNode === null || $brandNode === false) {
            return 'absent';
        }
        if (is_string($brandNode)) {
            return 'scalar_string';
        }
        if (! is_array($brandNode)) {
            return 'unknown';
        }
        if (! array_is_list($brandNode)) {
            if (isset($brandNode['Code'])) {
                return 'single_code_object';
            }
            if (isset($brandNode['value'])) {
                return 'single_value_object';
            }
            if (isset($brandNode['content'])) {
                return 'single_content_object';
            }
            if (isset($brandNode['text'])) {
                return 'single_text_object';
            }

            return 'unknown';
        }
        if ($brandNode === []) {
            return 'absent';
        }
        $first = $brandNode[0] ?? null;
        if (is_string($first)) {
            return 'array_of_strings';
        }
        if (is_array($first)) {
            if ($first === []) {
                return 'array_of_empty_objects';
            }
            if (array_key_exists('Code', $first)) {
                return 'array_of_code_objects';
            }
            if (array_key_exists('value', $first)) {
                return 'array_of_value_objects';
            }
            if (array_key_exists('content', $first)) {
                return 'array_of_content_objects';
            }
            if (array_key_exists('text', $first)) {
                return 'array_of_text_objects';
            }
        }

        return 'unknown';
    }

    /**
     * @return array<int, mixed>|string|null
     */
    protected function airPriceBrandNodeJsonPreview(mixed $brandNode, string $shape): array|string|null
    {
        if ($shape === 'absent') {
            return null;
        }
        if ($shape === 'scalar_string' && is_string($brandNode)) {
            return '[scalar]';
        }
        if ($shape === 'array_of_strings' && is_array($brandNode)) {
            return array_map(static fn (): string => '[scalar]', $brandNode);
        }
        if ($shape === 'array_of_code_objects' && is_array($brandNode)) {
            return array_map(
                static fn (): array => ['Code' => '[scalar]'],
                $brandNode
            );
        }
        if ($shape === 'array_of_empty_objects' && is_array($brandNode)) {
            return array_map(static fn (): array => [], $brandNode);
        }
        if ($shape === 'array_of_value_objects' && is_array($brandNode)) {
            return array_map(static fn (): array => ['value' => '[scalar]'], $brandNode);
        }
        if ($shape === 'array_of_content_objects' && is_array($brandNode)) {
            return array_map(static fn (): array => ['content' => '[scalar]'], $brandNode);
        }
        if ($shape === 'array_of_text_objects' && is_array($brandNode)) {
            return array_map(static fn (): array => ['text' => '[scalar]'], $brandNode);
        }
        if ($shape === 'single_code_object' && is_array($brandNode)) {
            return ['Code' => '[scalar]'];
        }
        if ($shape === 'single_value_object' && is_array($brandNode)) {
            return ['value' => '[scalar]'];
        }
        if ($shape === 'single_content_object' && is_array($brandNode)) {
            return ['content' => '[scalar]'];
        }
        if ($shape === 'single_text_object' && is_array($brandNode)) {
            return ['text' => '[scalar]'];
        }

        return '[]';
    }

    protected function traditionalPnrResolveBrandCodeFromDraft(array $internalDraft): ?string
    {
        $ctx = is_array($internalDraft['_sabre_booking_context'] ?? null)
            ? $internalDraft['_sabre_booking_context']
            : [];
        foreach ([
            $ctx['brand_code'] ?? null,
            $ctx['selected_brand_code'] ?? null,
            data_get($ctx, 'selected_fare_family_option.brand_code'),
            data_get($ctx, 'selected_fare_family_option.code'),
            $internalDraft['fare_family']['brand_code'] ?? null,
            $internalDraft['fare_family']['code'] ?? null,
        ] as $candidate) {
            $code = strtoupper(trim((string) ($candidate ?? '')));
            if ($code !== '' && preg_match('/^[A-Z0-9]{2,16}$/', $code) === 1) {
                return $code;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $cpnr
     */
    protected function traditionalPnrApplyRootAirPriceBrandQualifier(array $cpnr, string $brandCode): array
    {
        if (! isset($cpnr['AirPrice']) || ! is_array($cpnr['AirPrice']) || ! array_is_list($cpnr['AirPrice']) || $cpnr['AirPrice'] === []) {
            return $cpnr;
        }
        $ap = $cpnr['AirPrice'];
        $first = is_array($ap[0] ?? null) ? $ap[0] : [];
        $pri = is_array($first['PriceRequestInformation'] ?? null) ? $first['PriceRequestInformation'] : [];
        $oq = is_array($pri['OptionalQualifiers'] ?? null) ? $pri['OptionalQualifiers'] : [];
        $pq = is_array($oq['PricingQualifiers'] ?? null) ? $oq['PricingQualifiers'] : [];
        $brandNode = $this->resolveAirPriceBrandNodeForWire($brandCode);
        if ($brandNode === null) {
            unset($pq['Brand']);
        } else {
            $pq['Brand'] = $brandNode;
        }
        $oq['PricingQualifiers'] = $pq;
        $pri['OptionalQualifiers'] = $oq;
        $first['PriceRequestInformation'] = $pri;
        $ap[0] = $first;
        $cpnr['AirPrice'] = array_values($ap);

        return $cpnr;
    }

    /**
     * IATI v2.4 GDS: Brand qualifier aligned to {@code ItineraryOptions.SegmentSelect@RPH} when SegmentSelect is present.
     *
     * @param  array<string, mixed>  $cpnr
     * @return array<string, mixed>
     */
    protected function traditionalPnrApplyIatiV24RootAirPriceBrandQualifier(array $cpnr, string $brandCode): array
    {
        if (! isset($cpnr['AirPrice']) || ! is_array($cpnr['AirPrice']) || ! array_is_list($cpnr['AirPrice']) || $cpnr['AirPrice'] === []) {
            return $cpnr;
        }

        $wire = ['CreatePassengerNameRecordRQ' => $cpnr];
        $segmentSelectRphs = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['rph'] ?? '')),
            $this->extractTraditionalPnrSegmentSelectRows($wire),
        ), static fn (string $v): bool => $v !== '')));
        sort($segmentSelectRphs, SORT_STRING);

        if ($segmentSelectRphs !== []) {
            $brandRows = [];
            foreach ($segmentSelectRphs as $rph) {
                $rphInt = $this->normalizeIatiV24BrandRphToSchemaInteger($rph);
                if ($rphInt === null) {
                    continue;
                }
                $brandRows[] = [
                    'RPH' => $rphInt,
                    'content' => $brandCode,
                ];
            }
            $brandNode = count($brandRows) === 1 ? $brandRows[0] : array_values($brandRows);
        } else {
            $brandNode = $this->resolveAirPriceBrandNodeForWire($brandCode);
        }

        $ap = $cpnr['AirPrice'];
        $first = is_array($ap[0] ?? null) ? $ap[0] : [];
        $pri = is_array($first['PriceRequestInformation'] ?? null) ? $first['PriceRequestInformation'] : [];
        $oq = is_array($pri['OptionalQualifiers'] ?? null) ? $pri['OptionalQualifiers'] : [];
        $pq = is_array($oq['PricingQualifiers'] ?? null) ? $oq['PricingQualifiers'] : [];
        if ($brandNode === null) {
            unset($pq['Brand']);
        } else {
            $pq['Brand'] = $brandNode;
        }
        $oq['PricingQualifiers'] = $pq;
        $pri['OptionalQualifiers'] = $oq;
        $first['PriceRequestInformation'] = $pri;
        $ap[0] = $first;
        $cpnr['AirPrice'] = array_values($ap);

        return $cpnr;
    }

    /**
     * SpecialServiceInfo only (AdvancePassenger / SecureFlight). CTCE/CTCM SSR rows need Service child (B54 forbidden).
     *
     * @param  array<string, mixed>  $cpnr
     * @param  array<string, mixed>  $internalDraft
     * @return array<string, mixed>
     */
    protected function traditionalPnrIatiLikeApplySpecialServiceInfo(array $cpnr, array $internalDraft): array
    {
        $passengers = is_array($internalDraft['passengers'] ?? null) ? array_values($internalDraft['passengers']) : [];
        $requiresPassport = ($internalDraft['_requires_passport_doc'] ?? false) === true;
        $advanceRows = [];
        $secureRows = [];
        foreach ($passengers as $idx => $p) {
            if (! is_array($p)) {
                continue;
            }
            $nameNumber = ($idx + 1).'.1';
            $given = trim((string) ($p['first_name'] ?? ''));
            $surname = trim((string) ($p['last_name'] ?? ''));
            if ($given === '' && $surname === '') {
                continue;
            }
            $gender = $this->tripOrdersGenderEnumToCpnrGenderCode(isset($p['gender']) ? (string) $p['gender'] : null);
            $dob = isset($p['date_of_birth']) ? trim((string) $p['date_of_birth']) : '';
            $personNameBase = array_filter([
                'GivenName' => $given,
                'Surname' => $surname,
                'DateOfBirth' => $dob !== '' ? $dob : null,
                'Gender' => $gender,
                'NameNumber' => $nameNumber,
            ], static fn ($v) => $v !== null && $v !== '');
            if ($dob !== '' && $gender !== null) {
                $secureRows[] = [
                    'PersonName' => $personNameBase,
                    'SegmentNumber' => 'A',
                ];
            }
            $passportNum = trim((string) ($p['passport_number'] ?? ''));
            if ($requiresPassport && $passportNum !== '') {
                $advancePersonName = $personNameBase;
                $advancePersonName['DocumentHolder'] = true;
                $advanceRows[] = [
                    'Document' => array_filter([
                        'Type' => 'P',
                        'Number' => $passportNum,
                        'IssueCountry' => strtoupper(trim((string) ($p['passport_issuing_country'] ?? ''))),
                        'NationalityCountry' => strtoupper(trim((string) ($p['nationality'] ?? ''))),
                        'ExpirationDate' => (string) ($p['passport_expiry_date'] ?? ''),
                    ], static fn ($v) => $v !== null && $v !== ''),
                    'PersonName' => $advancePersonName,
                    'VendorPrefs' => ['Airline' => ['Hosted' => false]],
                    'SegmentNumber' => 'A',
                ];
            }
        }
        $ssi = [];
        if ($advanceRows !== []) {
            $ssi['AdvancePassenger'] = $advanceRows;
        }
        if ($secureRows !== []) {
            $ssi['SecureFlight'] = $secureRows;
        }
        if ($ssi === []) {
            return $cpnr;
        }
        $sr = is_array($cpnr['SpecialReqDetails'] ?? null) ? $cpnr['SpecialReqDetails'] : [];
        $sr['SpecialService'] = ['SpecialServiceInfo' => $ssi];
        unset($sr['SpecialService']['Service']);
        $cpnr['SpecialReqDetails'] = $sr;

        return $cpnr;
    }

    /**
     * Safe Sprint 2A SSR / IATI-style flags for diagnostics (no PII values).
     *
     * @param  array<string, mixed>  $cpnr
     * @return array<string, mixed>
     */
    public function traditionalPnrIatiLikeSsrDiagnosticFlags(array $cpnr): array
    {
        $sr = is_array($cpnr['SpecialReqDetails'] ?? null) ? $cpnr['SpecialReqDetails'] : [];
        $ss = is_array($sr['SpecialService'] ?? null) ? $sr['SpecialService'] : [];
        $ssi = is_array($ss['SpecialServiceInfo'] ?? null) ? $ss['SpecialServiceInfo'] : [];
        $docsPresent = isset($ssi['AdvancePassenger']) && is_array($ssi['AdvancePassenger']) && $ssi['AdvancePassenger'] !== [];
        $securePresent = isset($ssi['SecureFlight']) && is_array($ssi['SecureFlight']) && $ssi['SecureFlight'] !== [];
        $hasServiceChild = array_key_exists('Service', $ss);
        $brandPresent = false;
        $apRaw = $cpnr['AirPrice'] ?? null;
        if (is_array($apRaw) && array_is_list($apRaw) && isset($apRaw[0]) && is_array($apRaw[0])) {
            $pri = is_array($apRaw[0]['PriceRequestInformation'] ?? null) ? $apRaw[0]['PriceRequestInformation'] : [];
            $pq = data_get($pri, 'OptionalQualifiers.PricingQualifiers', []);
            $brandPresent = is_array($pq['Brand'] ?? null) && $pq['Brand'] !== [];
        }
        $ptPresent = false;
        if (is_array($apRaw) && array_is_list($apRaw) && isset($apRaw[0]) && is_array($apRaw[0])) {
            $pri = is_array($apRaw[0]['PriceRequestInformation'] ?? null) ? $apRaw[0]['PriceRequestInformation'] : [];
            $pt = data_get($pri, 'OptionalQualifiers.PricingQualifiers.PassengerType');
            $ptPresent = is_array($pt) && $pt !== [];
        }

        return [
            'is_iati_like_cpnr_style' => true,
            'endpoint_version' => '2.4.0',
            'target_city_present' => trim((string) ($cpnr['targetCity'] ?? '')) !== '',
            'airbook_present' => isset($cpnr['AirBook']) && is_array($cpnr['AirBook']) && $cpnr['AirBook'] !== [],
            'airprice_present' => is_array($apRaw) && $apRaw !== [],
            'end_transaction_present' => is_array($cpnr['PostProcessing']['EndTransaction'] ?? null)
                && $cpnr['PostProcessing']['EndTransaction'] !== [],
            'received_from_present' => trim((string) data_get($cpnr, 'PostProcessing.EndTransaction.Source.ReceivedFrom', '')) !== '',
            'ticketing_present' => is_array(data_get($cpnr, 'TravelItineraryAddInfo.AgencyInfo.Ticketing'))
                && data_get($cpnr, 'TravelItineraryAddInfo.AgencyInfo.Ticketing') !== [],
            'docs_block_present' => $docsPresent,
            'ctce_block_present' => false,
            'ctcm_block_present' => false,
            'secure_flight_present' => $securePresent,
            'brand_code_present' => $brandPresent,
            'passenger_type_pricing_present' => $ptPresent,
            'wire_iati_ssr_ctce_ctcm_schema_note' => $hasServiceChild
                ? null
                : 'CTCE/CTCM require SpecialService.Service.SSR_Code; omitted on Passenger Records wire (B54 forbids Service child). Next: cert host exception or alternate contact path.',
        ];
    }

    /**
     * B38: Strip {@code _ota*} keys before an authenticated booking HTTP probe (same contract as {@see SabreBookingClient}).
     *
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    /**
     * @return list<string>
     */
    public function resolveTraditionalCpnrHaltOnStatusCodes(bool $iatiLike, bool $omitNnWn = false): array
    {
        if ($omitNnWn) {
            $base = $iatiLike
                ? array_values(array_unique(array_merge(
                    self::TRADITIONAL_CPNR_HALT_ON_STATUS_BASE,
                    self::IATI_LIKE_CPNR_HALT_ON_STATUS_EXTRA,
                )))
                : self::TRADITIONAL_CPNR_HALT_ON_STATUS_BASE;

            return array_values(array_filter(
                $base,
                static fn (string $code): bool => ! in_array($code, ['NN', 'WN'], true),
            ));
        }

        if ($iatiLike) {
            return array_values(array_unique(array_merge(
                self::TRADITIONAL_CPNR_HALT_ON_STATUS_BASE,
                self::IATI_LIKE_CPNR_HALT_ON_STATUS_EXTRA,
            )));
        }

        return self::TRADITIONAL_CPNR_HALT_ON_STATUS_BASE;
    }

    /**
     * @param  list<string>  $codes
     * @return list<array{Code: string}>
     */
    public function buildTraditionalCpnrHaltOnStatusWireRows(array $codes): array
    {
        return array_map(
            static fn (string $code): array => ['Code' => $code],
            $codes,
        );
    }

    /**
     * @param  array<string, mixed>  $internalDraft
     */
    protected function traditionalCpnrDraftOmitsNnWnFromHaltOnStatus(array $internalDraft): bool
    {
        if ((bool) config('suppliers.sabre.cpnr_include_nn_in_halt_on_status', false)) {
            return false;
        }

        if ((bool) config('suppliers.sabre.cpnr_omit_nn_from_halt_on_status', true)) {
            return true;
        }

        return ($internalDraft['_ota_cert_allow_nn_diagnostic'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $cpnr  {@code CreatePassengerNameRecordRQ} block
     * @return list<string>
     */
    public function extractHaltOnStatusCodesFromCpnr(array $cpnr): array
    {
        $air = is_array($cpnr['AirBook'] ?? null) ? $cpnr['AirBook'] : [];
        $halt = $air['HaltOnStatus'] ?? null;
        if (! is_array($halt)) {
            return [];
        }

        $codes = [];
        foreach ($halt as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = strtoupper(trim((string) ($row['Code'] ?? '')));
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    public function stripOtaInternalKeysFromBookingWire(array $envelope): array
    {
        $out = [];
        foreach ($envelope as $k => $v) {
            if (is_string($k) && str_starts_with($k, '_ota')) {
                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }

    /**
     * Phase 3E-B: Safe fingerprint of the Passenger Records POST body after {@see stripOtaInternalKeysFromBookingWire}
     * (same top-level {@code _ota*} strip as {@see SabreBookingClient::resolvePassengerRecordsWireEnvelopeForPost} for CPNR).
     * No raw JSON, traveler PII, or credentials.
     *
     * @param  array<string, mixed>  $apiEnvelope  CPNR envelope from {@see buildPassengerRecordsCpnrWireForStyle} (may include {@code _ota*})
     * @return array<string, mixed>
     */
    public function fingerprintPassengerRecordsFinalPostBody(array $apiEnvelope): array
    {
        $stripped = $this->stripOtaInternalKeysFromBookingWire($apiEnvelope);
        $cpnr = is_array($stripped['CreatePassengerNameRecordRQ'] ?? null)
            ? $stripped['CreatePassengerNameRecordRQ']
            : [];
        $air = is_array($cpnr['AirBook'] ?? null) ? $cpnr['AirBook'] : [];
        $haltCodes = $this->extractHaltOnStatusCodesFromCpnr($cpnr);
        $haltRaw = $air['HaltOnStatus'] ?? null;
        $haltNodeCount = 0;
        if (is_array($haltRaw)) {
            foreach ($haltRaw as $row) {
                if (is_array($row)) {
                    $haltNodeCount++;
                }
            }
        }

        $odi = is_array($air['OriginDestinationInformation'] ?? null) ? $air['OriginDestinationInformation'] : [];
        $fs = $odi['FlightSegment'] ?? null;
        $segs = [];
        if (is_array($fs)) {
            $segs = array_is_list($fs) ? $fs : [$fs];
        }
        $sellCtx = $this->traditionalPnrSummarizeSegmentSellContext($segs);
        $segmentStatuses = array_values(
            (array) ($sellCtx['wire_segment_sell_context_status_values_sanitized'] ?? [])
        );

        $pp = is_array($cpnr['PostProcessing'] ?? null) ? $cpnr['PostProcessing'] : [];
        $postProcRedisplay = is_array($pp['RedisplayReservation'] ?? null) && $pp['RedisplayReservation'] !== [];
        $airBookRetryRebook = is_array($air['RetryRebook'] ?? null) && $air['RetryRebook'] !== [];
        $airBookRedisplay = is_array($air['RedisplayReservation'] ?? null) && $air['RedisplayReservation'] !== [];

        return [
            'final_wire_halt_on_status_codes' => $haltCodes,
            'final_wire_contains_nn_halt' => in_array('NN', $haltCodes, true),
            'final_wire_contains_wn_halt' => in_array('WN', $haltCodes, true),
            'final_wire_halt_on_status_node_count' => $haltNodeCount,
            'final_wire_flight_segment_statuses' => $segmentStatuses,
            'final_wire_retry_rebook_present' => $airBookRetryRebook,
            'final_wire_airbook_redisplay_present' => $airBookRedisplay,
            'final_wire_post_processing_redisplay_present' => $postProcRedisplay,
            'final_wire_sell_action_status_note' => 'FlightSegment.Status=NN is sell/request action, not HaltOnStatus',
        ];
    }

    /**
     * B38: Structural preview of {@code CreatePassengerNameRecordRQ} wire (no traveler names, phones, emails, passport numbers, DOB).
     *
     * @param  array<string, mixed>  $wire  Output of {@see self::buildTraditionalPnrCreatePassengerNameRecordV1Wire()} (may include {@code _ota*})
     * @return array<string, mixed>
     */
    public function previewRedactedTraditionalPnrCreatePassengerNameRecordV1Wire(array $wire, ?string $traditionalPayloadStyle = null): array
    {
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];
        $style = $traditionalPayloadStyle ?? self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1;

        return [
            'payload_style' => $style,
            'CreatePassengerNameRecordRQ' => $this->redactedCpnrStructureForPreview($cpnr),
        ];
    }

    /**
     * @param  array<string, mixed>  $cpnr
     * @return array<string, mixed>
     */
    protected function traditionalPnrV1AugmentCpnrBlock(array $cpnr, array $internalDraft): array
    {
        $pcc = $this->resolveSabrePseudoCityCodeForTripOrdersWire($internalDraft);
        if ($pcc !== '') {
            $cpnr['targetCity'] = $pcc;
        }

        $airBook = is_array($cpnr['AirBook'] ?? null) ? $cpnr['AirBook'] : [];
        $odi = is_array($airBook['OriginDestinationInformation'] ?? null) ? $airBook['OriginDestinationInformation'] : [];
        $segs = $odi['FlightSegment'] ?? null;
        if (is_array($segs)) {
            $wasList = array_is_list($segs);
            $list = $wasList ? $segs : [$segs];
            $forbiddenFlightSegmentKeys = ['CabinCode', 'ClassOfService', 'FareBasisCode', 'Number'];
            $next = [];
            foreach ($list as $row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach ($forbiddenFlightSegmentKeys as $fk) {
                    unset($row[$fk]);
                }
                if (array_key_exists('NumberInParty', $row) && ! is_string($row['NumberInParty'])) {
                    $nip = $row['NumberInParty'];
                    $row['NumberInParty'] = is_scalar($nip) ? (string) $nip : '1';
                }
                $next[] = $row;
            }
            $odi['FlightSegment'] = $wasList ? $next : ($next[0] ?? []);
            $airBook['OriginDestinationInformation'] = $odi;
            $cpnr['AirBook'] = $airBook;
        }

        $tia = is_array($cpnr['TravelItineraryAddInfo'] ?? null) ? $cpnr['TravelItineraryAddInfo'] : [];
        $ci = is_array($tia['CustomerInfo'] ?? null) ? $tia['CustomerInfo'] : [];
        $passengers = is_array($internalDraft['passengers'] ?? null) ? $internalDraft['passengers'] : [];
        $personBlocks = [];
        foreach ($passengers as $idx => $p) {
            if (! is_array($p)) {
                continue;
            }
            $given = trim((string) ($p['first_name'] ?? ''));
            $surname = trim((string) ($p['last_name'] ?? ''));
            if ($given === '' && $surname === '') {
                continue;
            }
            $ptc = strtoupper(trim((string) ($p['type'] ?? 'ADT')));
            if ($ptc === '') {
                $ptc = 'ADT';
            }
            $personBlocks[] = array_filter([
                'GivenName' => $given,
                'Surname' => $surname,
                'PassengerType' => $ptc,
                'NameNumber' => ($idx + 1).'.1',
            ], static fn ($v) => $v !== null && $v !== '');
        }
        if ($personBlocks !== []) {
            $ci['PersonName'] = array_values($personBlocks);
        }
        $ci = $this->traditionalPnrNormalizeCustomerInfoPersonNameToArray($ci);
        $ci = $this->traditionalPnrNormalizeCustomerInfoEmailForTraditionalPnr($ci);
        $tia['CustomerInfo'] = $ci;
        // B55: Passenger Records schema rejects AgencyInfo.Telephone; keep Ticketing (and other allowed keys) only.
        $agencyInfo = is_array($tia['AgencyInfo'] ?? null) ? $tia['AgencyInfo'] : [];
        unset($agencyInfo['Telephone']);
        if ($agencyInfo === []) {
            unset($tia['AgencyInfo']);
        } else {
            $tia['AgencyInfo'] = $agencyInfo;
        }
        $cpnr['TravelItineraryAddInfo'] = $tia;

        if (isset($cpnr['AirBook']) && is_array($cpnr['AirBook'])) {
            $ab = $cpnr['AirBook'];
            foreach (['AirPrice', 'OTAFareBreakdownSummary', 'PriceQuoteInformation'] as $forbiddenAirBookKey) {
                unset($ab[$forbiddenAirBookKey]);
            }
            $airbookRetryRedisplay = (bool) config('suppliers.sabre.traditional_cpnr_airbook_retry_redisplay', false);
            if ($airbookRetryRedisplay) {
                $ab['RetryRebook'] = [
                    'Option' => true,
                    'NumAttempts' => 3,
                    'WaitInterval' => 1000,
                ];
                $ab['RedisplayReservation'] = [
                    'NumAttempts' => 3,
                    'WaitInterval' => 1000,
                ];
            } else {
                unset($ab['RetryRebook'], $ab['RedisplayReservation']);
            }
            $cpnr['AirBook'] = $ab;
        }

        // B54: Passenger Records schema does not allow SpecialService.Service; drop entire SpecialService subtree if present.
        if (isset($cpnr['SpecialReqDetails']) && is_array($cpnr['SpecialReqDetails'])) {
            unset($cpnr['SpecialReqDetails']['SpecialService']);
        }

        $cpnr = $this->traditionalPnrNormalizeRootAirPricePassengerTypeQualifiers($cpnr, $internalDraft);
        $cpnr = $this->traditionalPnrStripForbiddenRootAirPriceRowKeys($cpnr);

        return $cpnr;
    }

    /**
     * BF6-FIX6: Sabre REST schema rejects stray {@code AirPrice[0].message} (object/scalar diagnostic shape).
     *
     * @param  array<string, mixed>  $cpnr
     * @return array<string, mixed>
     */
    protected function traditionalPnrStripForbiddenRootAirPriceRowKeys(array $cpnr): array
    {
        if (! isset($cpnr['AirPrice']) || ! is_array($cpnr['AirPrice']) || ! array_is_list($cpnr['AirPrice']) || $cpnr['AirPrice'] === []) {
            return $cpnr;
        }
        $ap = $cpnr['AirPrice'];
        $first = is_array($ap[0] ?? null) ? $ap[0] : [];
        unset($first['message'], $first['Message']);
        $ap[0] = $first;
        $cpnr['AirPrice'] = array_values($ap);

        return $cpnr;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|string
     */
    protected function redactedCpnrStructureForPreview(mixed $node): mixed
    {
        if (! is_array($node)) {
            return is_scalar($node) ? '[scalar]' : '[]';
        }
        $out = [];
        foreach ($node as $k => $v) {
            if (! is_string($k)) {
                continue;
            }
            $lk = strtolower($k);
            if (in_array($lk, [
                'givenname', 'surname', 'email', 'birthdate', 'document', 'number', 'phone', 'phonenumber',
                'address', 'text', 'remark', 'targetcity',
            ], true) || str_contains($lk, 'passport') || str_contains($lk, 'password')) {
                $out[$k] = '[redacted]';

                continue;
            }
            if (is_array($v)) {
                $out[$k] = $this->redactedCpnrStructureForPreview($v);

                continue;
            }
            if (is_scalar($v)) {
                $out[$k] = '[scalar]';

                continue;
            }
            $out[$k] = '[]';
        }

        return $out;
    }

    /**
     * Trip Orders {@code POST /v1/trip/orders/createBooking} envelope (B10). Schema is explicit/inspectable; exact Sabre
     * contract may differ — tune against tenant responses. No ticket issuance or payment capture nodes.
     * Top-level {@code _ota*} keys are stripped before HTTP (see {@see SabreBookingClient}).
     *
     * @param  array<string, mixed>  $internalDraft  Valid draft (\_valid === true)
     * @param  array<string, mixed>  $ticketingHints  Optional TTL/remarks hints — never payment
     * @param  non-empty-string|null  $payloadStyleOverride  Local/testing: force {@see self::CREATEBOOKING_PAYLOAD_STYLES} entry
     * @return array<string, mixed>
     */
    public function buildTripOrdersCreateBookingEnvelope(array $internalDraft, array $ticketingHints = [], ?string $payloadStyleOverride = null): array
    {
        $mode = (string) config('suppliers.sabre.booking_mode', 'pnr_only');
        $ticketingEnabled = (bool) config('suppliers.sabre.ticketing_enabled', false);
        $segments = is_array($internalDraft['segments'] ?? null) ? $internalDraft['segments'] : [];
        usort($segments, static function (array $a, array $b): int {
            return strcmp((string) ($a['departure_at'] ?? $a['depart_at'] ?? ''), (string) ($b['departure_at'] ?? $b['depart_at'] ?? ''));
        });
        $passengers = is_array($internalDraft['passengers'] ?? null) ? $internalDraft['passengers'] : [];
        $fare = is_array($internalDraft['fare'] ?? null) ? $internalDraft['fare'] : [];
        $contact = is_array($internalDraft['contact'] ?? null) ? $internalDraft['contact'] : [];
        $email = trim((string) ($contact['email'] ?? ''));
        $phone = trim((string) ($contact['phone'] ?? ''));
        $amount = (float) ($fare['amount'] ?? 0);
        $currency = trim((string) ($fare['currency'] ?? ''));
        $baggageSummary = trim((string) ($internalDraft['baggage_summary'] ?? ''));

        $hasPassportDoc = false;
        foreach ($passengers as $p) {
            if (! is_array($p)) {
                continue;
            }
            if (trim((string) ($p['passport_number'] ?? '')) !== '') {
                $hasPassportDoc = true;
                break;
            }
        }

        $ptcCounts = ['ADT' => 0, 'CHD' => 0, 'INF' => 0];
        foreach ($passengers as $p) {
            if (! is_array($p)) {
                continue;
            }
            $code = $this->passengerTypeToSabreCode((string) ($p['type'] ?? 'adult'));
            if (isset($ptcCounts[$code])) {
                $ptcCounts[$code]++;
            } else {
                $ptcCounts['ADT']++;
            }
        }
        if ($ptcCounts === ['ADT' => 0, 'CHD' => 0, 'INF' => 0]) {
            $ptcCounts['ADT'] = max(1, count($passengers));
        }

        $tripSegments = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $mkt = strtoupper(trim((string) ($seg['carrier'] ?? $seg['airline_code'] ?? '')));
            $op = strtoupper(trim((string) ($seg['operating_airline_code'] ?? '')));
            $row = array_filter([
                'origin' => strtoupper(trim((string) ($seg['origin'] ?? ''))),
                'destination' => strtoupper(trim((string) ($seg['destination'] ?? ''))),
                'departure_at' => (string) ($seg['departure_at'] ?? $seg['depart_at'] ?? ''),
                'arrival_at' => (string) ($seg['arrival_at'] ?? $seg['arrive_at'] ?? ''),
                'marketing_carrier' => $mkt !== '' ? $mkt : null,
                'operating_carrier' => $op !== '' ? $op : null,
                'flight_number' => trim((string) ($seg['flight_number'] ?? $seg['flight_no'] ?? '')),
                'class_of_service' => isset($seg['booking_class']) ? strtoupper(trim((string) $seg['booking_class'])) : null,
                'cabin' => isset($seg['segment_cabin_code']) ? strtoupper(trim((string) $seg['segment_cabin_code'])) : null,
                'fare_basis_code' => isset($seg['fare_basis_code']) ? strtoupper(trim((string) $seg['fare_basis_code'])) : null,
            ], fn ($v) => $v !== null && $v !== '');
            $tripSegments[] = $row;
        }

        $travelers = [];
        $requiresPassportRoute = (bool) ($internalDraft['_requires_passport_doc'] ?? false);
        foreach ($passengers as $p) {
            if (! is_array($p)) {
                continue;
            }
            $given = $this->normalizeTripOrdersPersonName((string) ($p['first_name'] ?? ''));
            $surname = $this->normalizeTripOrdersPersonName((string) ($p['last_name'] ?? ''));
            $passportNode = $this->tripOrdersTravelerPassportNode($p, $requiresPassportRoute);
            $travelers[] = array_filter([
                'passenger_type_code' => (string) ($p['type'] ?? 'ADT'),
                'given_name' => $given,
                'surname' => $surname,
                'gender' => $this->mapToSabreTripOrdersGenderEnum($p['gender'] ?? null),
                'birth_date' => isset($p['date_of_birth']) ? trim((string) $p['date_of_birth']) : null,
                'passport' => $passportNode,
            ], fn ($v) => $v !== null && $v !== [] && $v !== '');
        }

        $style = $payloadStyleOverride !== null && trim((string) $payloadStyleOverride) !== ''
            ? $this->normalizeCreatebookingPayloadStyle(trim((string) $payloadStyleOverride))
            : $this->resolveCreatebookingPayloadStyle();
        if ($this->tripOrdersStyleUsesSabreTripOrdersPassengerCode($style)) {
            $travelers = $this->mapTripOrdersTravelersWireToSabreTripOrdersCamelCase($travelers);
        } elseif ($this->tripOrdersStyleUsesCamelTravelerKeys($style)) {
            $travelers = $this->mapTripOrdersTravelersWireToCamelCase($travelers);
        }

        $timeLimit = null;
        if (isset($ticketingHints['time_limit_iso']) && is_string($ticketingHints['time_limit_iso']) && trim($ticketingHints['time_limit_iso']) !== '') {
            $timeLimit = trim($ticketingHints['time_limit_iso']);
        }

        $paymentMode = match ($mode) {
            'hold' => 'hold',
            default => 'pnr_only',
        };
        if ($mode === '' || $mode === 'default') {
            $paymentMode = 'pnr_only';
        }
        $checkoutMode = trim((string) ($internalDraft['checkout_payment_mode'] ?? ''));
        if ($checkoutMode !== '') {
            $paymentMode = match ($checkoutMode) {
                'pay_later_booking_request', 'offline_bank_transfer', 'office_confirmation', 'online_card' => 'pay_later',
                'hold_price_guaranteed', 'hold_no_price_guarantee' => 'hold',
                default => $paymentMode,
            };
        }

        $shopIds = is_array($internalDraft['_sabre_shop_context'] ?? null) && $internalDraft['_sabre_shop_context'] !== []
            ? $internalDraft['_sabre_shop_context']
            : (is_array($internalDraft['_sabre_shop_identifiers'] ?? null) ? $internalDraft['_sabre_shop_identifiers'] : []);
        $shopContext = $this->sanitizeShopContext($shopIds);

        $linkage = is_array($internalDraft['_fare_linkage'] ?? null) ? $internalDraft['_fare_linkage'] : [];
        $fareLinkageBlock = $this->normalizeFareLinkageBlock($linkage);
        $tripSegments = $this->mergeRevalidatedFareBasisIntoSegments($tripSegments, $linkage);
        $revalidatedTotal = is_numeric($linkage['revalidated_total'] ?? null) ? (float) $linkage['revalidated_total'] : null;
        $revalidatedCurrency = isset($linkage['revalidated_currency']) && is_string($linkage['revalidated_currency'])
            ? strtoupper(trim($linkage['revalidated_currency']))
            : '';
        $revalidatingCarrierFromRev = isset($linkage['validating_carrier']) && is_string($linkage['validating_carrier'])
            ? strtoupper(trim($linkage['validating_carrier']))
            : '';
        $effectiveValidatingCarrier = strtoupper(trim((string) ($internalDraft['validating_carrier'] ?? '')));
        if ($effectiveValidatingCarrier === '' && $revalidatingCarrierFromRev !== '') {
            $effectiveValidatingCarrier = $revalidatingCarrierFromRev;
        }

        $createBooking = [
            'supplier_context' => [
                'provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => (int) ($internalDraft['supplier_connection_id'] ?? 0),
                'selected_offer_id' => (string) ($internalDraft['selected_offer_id'] ?? ''),
                'supplier_offer_id' => (string) ($internalDraft['supplier_offer_id'] ?? ''),
            ],
            'shop_context' => $shopContext !== [] ? $shopContext : null,
            'fare_linkage' => $fareLinkageBlock !== [] ? $fareLinkageBlock : null,
            'trip_orders_reservation_action' => [
                'endTransaction' => true,
                'commitWithoutTicketing' => true,
                'receivedFrom' => 'OTA_WEB',
            ],
            'validating_carrier' => $effectiveValidatingCarrier,
            'itinerary' => array_filter([
                'segments' => $tripSegments,
                'baggage_summary' => $baggageSummary !== '' ? substr($baggageSummary, 0, 160) : null,
            ], fn ($v) => $v !== null && $v !== '' && $v !== []),
            'passenger_type_counts' => array_filter($ptcCounts, fn (int $n): bool => $n > 0),
            'travelers' => $travelers,
            'pricing' => array_filter([
                'total' => $amount > 0 ? $amount : null,
                'currency' => $currency !== '' ? $currency : null,
                'revalidated_total' => $revalidatedTotal !== null && $revalidatedTotal > 0 ? $revalidatedTotal : null,
                'revalidated_currency' => $revalidatedCurrency !== '' ? $revalidatedCurrency : null,
            ], fn ($v) => $v !== null && $v !== ''),
            'payment' => [
                'mode' => $paymentMode,
                'capture' => false,
            ],
            'ticketing' => array_filter([
                'automated_issue' => false,
                'manual_ticketing_only' => true,
                'ticketing_enabled_config' => $ticketingEnabled,
                'time_limit_hint' => $timeLimit,
            ], fn ($v) => $v !== null),
        ];
        $contactBlock = array_filter([
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
        ], fn ($v) => $v !== null && $v !== '');
        if ($contactBlock !== []) {
            $createBooking['contact'] = $contactBlock;
        }

        if ($this->tripOrdersWireRemarksEnabled()) {
            $remarksWire = $this->buildTripOrdersWireRemarks($ticketingEnabled, $baggageSummary);
            if ($remarksWire !== []) {
                $createBooking['remarks'] = $remarksWire;
            }
        }

        if (in_array($style, ['trip_orders_flight_offer_v1', 'trip_orders_flight_offer_root_v1', 'trip_orders_flight_offer_camel_v1'], true)) {
            $createBooking['flightOffer'] = $this->buildTripOrdersFlightOfferNode(
                $internalDraft,
                $tripSegments,
                $ptcCounts,
                $shopContext,
                $fareLinkageBlock,
                $amount,
                $currency,
                $effectiveValidatingCarrier,
                $baggageSummary,
                $revalidatedTotal,
                $revalidatedCurrency,
            );
        }
        if (in_array($style, [
            'trip_orders_flight_details_v1', 'trip_orders_flight_details_root_v1', 'trip_orders_flight_details_camel_v1', 'trip_orders_flight_details_full_camel_v1',
            'trip_orders_create_booking_root_flight_details_v2',
            'trip_orders_root_flight_details_v2_agency_phone_flat',
            'trip_orders_root_flight_details_v2_agency_phone_nested',
            'trip_orders_root_flight_details_v2_agency_contact_as_contactInfo',
            'trip_orders_root_flight_details_v2_no_agency_contact',
            'trip_orders_flight_details_sabre_v1', 'trip_orders_flight_details_sabre_agency_v1',
            'trip_orders_flight_details_sabre_agencyInfo_v1', 'trip_orders_flight_details_sabre_agencyPhoneNumber_v1', 'trip_orders_flight_details_sabre_agencyPhonesArray_v1',
            'trip_orders_flight_details_sabre_rootAgencyPhone_v1', 'trip_orders_flight_details_sabre_phoneNumbers_v1',
            'trip_orders_flight_details_sabre_rootPhones_v1', 'trip_orders_flight_details_sabre_rootPhoneNumbers_v1',
            'trip_orders_flight_details_sabre_contactInfoPhones_v1', 'trip_orders_flight_details_sabre_agencyPhoneUseType_v1',
            'trip_orders_flight_details_sabre_phone_use_business_v1', 'trip_orders_flight_details_sabre_phone_use_agency_v1',
            'trip_orders_flight_details_sabre_pos_source_phone_v1', 'trip_orders_flight_details_sabre_pos_phone_v1',
            'trip_orders_flight_details_sabre_agency_root_camel_v1', 'trip_orders_flight_details_sabre_travelAgency_v1',
            'trip_orders_flight_details_sabre_customerInfo_phone_v1',
            'trip_orders_flight_details_sabre_phoneLine_v1',
            'trip_orders_flight_details_sabre_phoneLines_v1',
            'trip_orders_flight_details_sabre_contactNumbers_v1',
            'trip_orders_flight_details_sabre_pnrContact_v1',
            'trip_orders_flight_details_sabre_reservationContact_v1',
            'trip_orders_flight_details_sabre_contactInfo_phoneLine_v1',
            'trip_orders_flight_details_sabre_travelers_phone_v1',
            'trip_orders_product_array_v1',
        ], true)) {
            $fdWireKind = $style === 'trip_orders_flight_details_full_camel_v1'
                || $this->tripOrdersStyleUsesCreateBookingRootFlightDetailsV2($style)
                ? 'full_camel'
                : 'legacy';
            $createBooking['flightDetails'] = $this->buildTripOrdersFlightDetailsNode(
                $tripSegments,
                $ptcCounts,
                $amount,
                $currency,
                $effectiveValidatingCarrier,
                $baggageSummary,
                $revalidatedTotal,
                $revalidatedCurrency,
                $fdWireKind,
            );
        }

        $pccForPos = $this->resolveSabrePseudoCityCodeForTripOrdersWire($internalDraft);
        $otaMeta = [
            '_ota_payload_schema' => 'trip_orders_create_booking_v1',
            '_ota_createbooking_payload_style' => $style,
            '_ota_booking_mode' => $mode,
            '_ota_ticketing_enabled' => $ticketingEnabled,
            '_ota_has_passport_doc' => $hasPassportDoc,
            '_ota_requires_passport_doc' => (bool) ($internalDraft['_requires_passport_doc'] ?? false),
            '_ota_has_contact_email' => $email !== '',
            '_ota_has_contact_phone' => $phone !== '',
            '_ota_pcc_available_for_pos' => $pccForPos !== '',
            '_ota_agency_phone_config_present' => trim((string) config('suppliers.sabre.agency_phone', '')) !== '',
            '_ota_agency_country_config_present' => trim((string) config('suppliers.sabre.agency_country', '')) !== ''
                || trim((string) config('suppliers.sabre.agency_phone_country_code', '')) !== '',
        ];

        if ($this->tripOrdersStyleUsesRootWireBody($style)) {
            return array_merge($otaMeta, $this->buildTripOrdersRootWireBody($createBooking, $style, $ticketingEnabled, $pccForPos));
        }

        return array_merge($otaMeta, [
            'createBooking' => $createBooking,
        ]);
    }

    /**
     * Trip Orders styles whose HTTP POST body is a flat JSON object (no {@code createBooking} wrapper).
     */
    public function tripOrdersStyleUsesRootWireBody(string $style): bool
    {
        return in_array(trim($style), [
            'trip_orders_flight_offer_root_v1',
            'trip_orders_flight_offer_camel_v1',
            'trip_orders_flight_details_root_v1',
            'trip_orders_flight_details_camel_v1',
            'trip_orders_flight_details_full_camel_v1',
            'trip_orders_flight_details_sabre_v1',
            'trip_orders_flight_details_sabre_agency_v1',
            'trip_orders_flight_details_sabre_agencyInfo_v1',
            'trip_orders_flight_details_sabre_agencyPhoneNumber_v1',
            'trip_orders_flight_details_sabre_agencyPhonesArray_v1',
            'trip_orders_flight_details_sabre_rootAgencyPhone_v1',
            'trip_orders_flight_details_sabre_phoneNumbers_v1',
            'trip_orders_flight_details_sabre_rootPhones_v1',
            'trip_orders_flight_details_sabre_rootPhoneNumbers_v1',
            'trip_orders_flight_details_sabre_contactInfoPhones_v1',
            'trip_orders_flight_details_sabre_agencyPhoneUseType_v1',
            'trip_orders_flight_details_sabre_phone_use_business_v1',
            'trip_orders_flight_details_sabre_phone_use_agency_v1',
            'trip_orders_flight_details_sabre_pos_source_phone_v1',
            'trip_orders_flight_details_sabre_pos_phone_v1',
            'trip_orders_flight_details_sabre_agency_root_camel_v1',
            'trip_orders_flight_details_sabre_travelAgency_v1',
            'trip_orders_flight_details_sabre_customerInfo_phone_v1',
            'trip_orders_flight_details_sabre_phoneLine_v1',
            'trip_orders_flight_details_sabre_phoneLines_v1',
            'trip_orders_flight_details_sabre_contactNumbers_v1',
            'trip_orders_flight_details_sabre_pnrContact_v1',
            'trip_orders_flight_details_sabre_reservationContact_v1',
            'trip_orders_flight_details_sabre_contactInfo_phoneLine_v1',
            'trip_orders_flight_details_sabre_travelers_phone_v1',
            'trip_orders_create_booking_root_flight_details_v2',
            'trip_orders_root_flight_details_v2_agency_phone_flat',
            'trip_orders_root_flight_details_v2_agency_phone_nested',
            'trip_orders_root_flight_details_v2_agency_contact_as_contactInfo',
            'trip_orders_root_flight_details_v2_no_agency_contact',
            'trip_orders_product_array_v1',
        ], true);
    }

    /**
     * P5/Q3: v2 root {@code flightDetails} certification phone-shape variants (same full-camel wire as {@code trip_orders_create_booking_root_flight_details_v2}).
     *
     * @var list<string>
     */
    public const TRIP_ORDERS_V2_AGENCY_PHONE_CERTIFICATION_STYLES = [
        'trip_orders_root_flight_details_v2_agency_phone_flat',
        'trip_orders_root_flight_details_v2_agency_phone_nested',
        'trip_orders_root_flight_details_v2_agency_contact_as_contactInfo',
        'trip_orders_root_flight_details_v2_no_agency_contact',
    ];

    public function sabreTripOrdersCreateBookingRootFlightDetailsV2Styles(): array
    {
        return array_merge(
            ['trip_orders_create_booking_root_flight_details_v2'],
            self::TRIP_ORDERS_V2_AGENCY_PHONE_CERTIFICATION_STYLES,
        );
    }

    public function tripOrdersStyleUsesCreateBookingRootFlightDetailsV2(string $style): bool
    {
        return in_array(trim($style), $this->sabreTripOrdersCreateBookingRootFlightDetailsV2Styles(), true);
    }

    public function tripOrdersStyleSkipsSabreAgencyPhoneOnWire(string $style): bool
    {
        return trim($style) === 'trip_orders_root_flight_details_v2_no_agency_contact';
    }

    /**
     * B33/B34: Trip Orders traditional Sabre flight-details wire styles (passengerCode + contactInfo + agency phone contract).
     *
     * @return list<string>
     */
    public function sabreTripOrdersTraditionalFlightDetailsStyles(): array
    {
        return [
            'trip_orders_flight_details_sabre_v1',
            'trip_orders_flight_details_sabre_agency_v1',
            'trip_orders_flight_details_sabre_agencyInfo_v1',
            'trip_orders_flight_details_sabre_agencyPhoneNumber_v1',
            'trip_orders_flight_details_sabre_agencyPhonesArray_v1',
            'trip_orders_flight_details_sabre_rootAgencyPhone_v1',
            'trip_orders_flight_details_sabre_phoneNumbers_v1',
            'trip_orders_flight_details_sabre_rootPhones_v1',
            'trip_orders_flight_details_sabre_rootPhoneNumbers_v1',
            'trip_orders_flight_details_sabre_contactInfoPhones_v1',
            'trip_orders_flight_details_sabre_agencyPhoneUseType_v1',
            'trip_orders_flight_details_sabre_phone_use_business_v1',
            'trip_orders_flight_details_sabre_phone_use_agency_v1',
            'trip_orders_flight_details_sabre_pos_source_phone_v1',
            'trip_orders_flight_details_sabre_pos_phone_v1',
            'trip_orders_flight_details_sabre_agency_root_camel_v1',
            'trip_orders_flight_details_sabre_travelAgency_v1',
            'trip_orders_flight_details_sabre_customerInfo_phone_v1',
            'trip_orders_flight_details_sabre_phoneLine_v1',
            'trip_orders_flight_details_sabre_phoneLines_v1',
            'trip_orders_flight_details_sabre_contactNumbers_v1',
            'trip_orders_flight_details_sabre_pnrContact_v1',
            'trip_orders_flight_details_sabre_reservationContact_v1',
            'trip_orders_flight_details_sabre_contactInfo_phoneLine_v1',
            'trip_orders_flight_details_sabre_travelers_phone_v1',
        ];
    }

    /**
     * B34: Required non-empty scalar dot path(s) on the Trip Orders wire root for agency phone (per compare style).
     *
     * @return list<string>
     */
    public function expectedSabreAgencyPhoneDotPathsForStyle(string $style): array
    {
        return match (trim($style)) {
            'trip_orders_flight_details_sabre_agencyInfo_v1' => ['agencyInfo.phone'],
            'trip_orders_flight_details_sabre_agencyPhoneNumber_v1' => ['agencyContactInfo.phoneNumber'],
            'trip_orders_flight_details_sabre_agencyPhonesArray_v1' => ['agencyContactInfo.phones.0.number'],
            'trip_orders_flight_details_sabre_rootAgencyPhone_v1' => ['agencyPhone'],
            'trip_orders_flight_details_sabre_phoneNumbers_v1' => ['phoneNumbers.0.number'],
            'trip_orders_flight_details_sabre_rootPhones_v1' => ['phones.0.phoneNumber'],
            'trip_orders_flight_details_sabre_rootPhoneNumbers_v1' => ['phoneNumbers.0.phoneNumber'],
            'trip_orders_flight_details_sabre_contactInfoPhones_v1' => ['contactInfo.phones.0.phoneNumber'],
            'trip_orders_flight_details_sabre_agencyPhoneUseType_v1' => ['agencyContactInfo.phones.0.phoneNumber'],
            'trip_orders_flight_details_sabre_phone_use_business_v1' => ['phones.0.number'],
            'trip_orders_flight_details_sabre_phone_use_agency_v1' => ['phones.0.number'],
            'trip_orders_flight_details_sabre_pos_source_phone_v1' => ['POS.Source.0.AgencyPhone.PhoneNumber'],
            'trip_orders_flight_details_sabre_pos_phone_v1' => ['pos.source.agencyPhone'],
            'trip_orders_flight_details_sabre_agency_root_camel_v1' => ['agency.phoneNumber'],
            'trip_orders_flight_details_sabre_travelAgency_v1' => ['travelAgency.phoneNumber'],
            'trip_orders_flight_details_sabre_customerInfo_phone_v1' => ['customerInfo.agencyPhone'],
            'trip_orders_flight_details_sabre_phoneLine_v1' => ['phoneLine.Number'],
            'trip_orders_flight_details_sabre_phoneLines_v1' => ['phoneLines.0.Number'],
            'trip_orders_flight_details_sabre_contactNumbers_v1' => ['contactNumbers.0.Number'],
            'trip_orders_flight_details_sabre_pnrContact_v1' => ['pnrContact.phone.Number'],
            'trip_orders_flight_details_sabre_reservationContact_v1' => ['reservationContact.phones.0.Number'],
            'trip_orders_flight_details_sabre_contactInfo_phoneLine_v1' => ['contactInfo.agencyPhone.Number'],
            'trip_orders_flight_details_sabre_travelers_phone_v1' => ['travelers.0.phone.Number'],
            'trip_orders_root_flight_details_v2_agency_phone_flat' => ['agencyContactInfo.phoneNumber'],
            'trip_orders_root_flight_details_v2_agency_phone_nested' => ['agencyContactInfo.phones.0.number'],
            'trip_orders_root_flight_details_v2_agency_contact_as_contactInfo' => ['agencyContactInfo.phone'],
            'trip_orders_create_booking_root_flight_details_v2' => ['agencyContactInfo.phone'],
            default => ['agencyContactInfo.phone'],
        };
    }

    /**
     * B33: Traditional Trip Orders booking requires root agency/office phone (separate from customer {@code contactInfo}).
     */
    public function tripOrdersStyleRequiresSabreAgencyPhone(string $style): bool
    {
        if ($this->tripOrdersStyleSkipsSabreAgencyPhoneOnWire($style)) {
            return false;
        }

        return in_array(trim($style), $this->sabreTripOrdersTraditionalFlightDetailsStyles(), true)
            || $this->tripOrdersStyleUsesCreateBookingRootFlightDetailsV2($style);
    }

    /**
     * B29: Trip Orders wire uses Sabre-style camelCase keys on {@code travelers[]} / {@code passport}.
     */
    public function tripOrdersStyleUsesCamelTravelerKeys(string $style): bool
    {
        return in_array(trim($style), [
            'trip_orders_flight_offer_camel_v1',
            'trip_orders_flight_details_camel_v1',
            'trip_orders_flight_details_full_camel_v1',
        ], true);
    }

    /**
     * B31: Trip Orders wire travelers use Sabre {@code passengerCode} (ADT/CHD/…) instead of {@code passengerTypeCode}.
     */
    public function tripOrdersStyleUsesSabreTripOrdersPassengerCode(string $style): bool
    {
        return in_array(trim($style), $this->sabreTripOrdersTraditionalFlightDetailsStyles(), true)
            || $this->tripOrdersStyleUsesCreateBookingRootFlightDetailsV2($style);
    }

    /**
     * B32: Trip Orders wire root uses Sabre {@code contactInfo} (not {@code contact}) for traditional Sabre flight-details styles.
     */
    public function tripOrdersStyleUsesSabreTripOrdersContactInfo(string $style): bool
    {
        return in_array(trim($style), $this->sabreTripOrdersTraditionalFlightDetailsStyles(), true)
            || $this->tripOrdersStyleUsesCreateBookingRootFlightDetailsV2($style);
    }

    /**
     * @param  list<array<string, mixed>>  $travelers  Snake_case Trip Orders traveler rows (pre-wire)
     * @return list<array<string, mixed>>
     */
    public function mapTripOrdersTravelersWireToSabreTripOrdersCamelCase(array $travelers): array
    {
        $out = [];
        foreach ($travelers as $t) {
            if (! is_array($t)) {
                continue;
            }
            $pIn = isset($t['passport']) && is_array($t['passport']) ? $t['passport'] : [];
            $passportOut = [];
            if ($pIn !== []) {
                if (isset($pIn['document_type']) && trim((string) $pIn['document_type']) !== '') {
                    $passportOut['documentType'] = $pIn['document_type'];
                }
                if (isset($pIn['issuing_country']) && trim((string) $pIn['issuing_country']) !== '') {
                    $passportOut['issuingCountry'] = $pIn['issuing_country'];
                }
                if (isset($pIn['nationality']) && trim((string) $pIn['nationality']) !== '') {
                    $passportOut['nationality'] = $pIn['nationality'];
                }
                if (isset($pIn['expiry_date']) && trim((string) $pIn['expiry_date']) !== '') {
                    $passportOut['expiryDate'] = $pIn['expiry_date'];
                }
                if (isset($pIn['number']) && trim((string) $pIn['number']) !== '') {
                    $passportOut['number'] = $pIn['number'];
                }
            }
            $row = array_filter([
                'passengerCode' => trim((string) ($t['passenger_type_code'] ?? 'ADT')) !== '' ? trim((string) ($t['passenger_type_code'] ?? 'ADT')) : null,
                'givenName' => trim((string) ($t['given_name'] ?? '')) !== '' ? trim((string) ($t['given_name'] ?? '')) : null,
                'surname' => trim((string) ($t['surname'] ?? '')) !== '' ? trim((string) ($t['surname'] ?? '')) : null,
                'gender' => isset($t['gender']) && trim((string) $t['gender']) !== '' ? trim((string) $t['gender']) : null,
                'birthDate' => isset($t['birth_date']) && trim((string) $t['birth_date']) !== '' ? trim((string) $t['birth_date']) : null,
                'passport' => $passportOut !== [] ? $passportOut : null,
            ], static fn ($v) => $v !== null && $v !== [] && $v !== '');

            if ($row !== []) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $travelers  Snake_case Trip Orders traveler rows (pre-wire)
     * @return list<array<string, mixed>>
     */
    public function mapTripOrdersTravelersWireToCamelCase(array $travelers): array
    {
        $out = [];
        foreach ($travelers as $t) {
            if (! is_array($t)) {
                continue;
            }
            $pIn = isset($t['passport']) && is_array($t['passport']) ? $t['passport'] : [];
            $passportOut = [];
            if ($pIn !== []) {
                if (isset($pIn['document_type']) && trim((string) $pIn['document_type']) !== '') {
                    $passportOut['documentType'] = $pIn['document_type'];
                }
                if (isset($pIn['issuing_country']) && trim((string) $pIn['issuing_country']) !== '') {
                    $passportOut['issuingCountry'] = $pIn['issuing_country'];
                }
                if (isset($pIn['nationality']) && trim((string) $pIn['nationality']) !== '') {
                    $passportOut['nationality'] = $pIn['nationality'];
                }
                if (isset($pIn['expiry_date']) && trim((string) $pIn['expiry_date']) !== '') {
                    $passportOut['expiryDate'] = $pIn['expiry_date'];
                }
                if (isset($pIn['number']) && trim((string) $pIn['number']) !== '') {
                    $passportOut['number'] = $pIn['number'];
                }
            }
            $row = array_filter([
                'passengerTypeCode' => trim((string) ($t['passenger_type_code'] ?? 'ADT')) !== '' ? trim((string) ($t['passenger_type_code'] ?? 'ADT')) : null,
                'givenName' => trim((string) ($t['given_name'] ?? '')) !== '' ? trim((string) ($t['given_name'] ?? '')) : null,
                'surname' => trim((string) ($t['surname'] ?? '')) !== '' ? trim((string) ($t['surname'] ?? '')) : null,
                'gender' => isset($t['gender']) && trim((string) $t['gender']) !== '' ? trim((string) $t['gender']) : null,
                'birthDate' => isset($t['birth_date']) && trim((string) $t['birth_date']) !== '' ? trim((string) $t['birth_date']) : null,
                'passport' => $passportOut !== [] ? $passportOut : null,
            ], static fn ($v) => $v !== null && $v !== [] && $v !== '');

            if ($row !== []) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * Exact JSON body keys sent after {@code _ota*} stripping (Trip Orders createBooking).
     *
     * @param  array<string, mixed>  $envelope  Output of {@see buildTripOrdersCreateBookingEnvelope()}
     * @return array<string, mixed>
     */
    public function tripOrdersWirePostBodyFromEnvelope(array $envelope): array
    {
        $out = [];
        foreach ($envelope as $k => $v) {
            if (is_string($k) && str_starts_with($k, '_ota')) {
                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }

    /**
     * Trip Orders wire JSON as sent on HTTP POST: safe defaults, optional null keys removed (B28).
     *
     * @param  array<string, mixed>  $envelope  Output of {@see buildTripOrdersCreateBookingEnvelope()}
     * @return array<string, mixed>
     */
    public function tripOrdersFinalWirePostBodyFromEnvelope(array $envelope): array
    {
        $wire = $this->tripOrdersWirePostBodyFromEnvelope($envelope);
        if (($envelope['_ota_payload_schema'] ?? '') !== 'trip_orders_create_booking_v1' || ! is_array($wire)) {
            return is_array($wire) ? $wire : [];
        }
        $cloned = unserialize(serialize($wire));
        if (! is_array($cloned)) {
            return $wire;
        }
        if (! isset($cloned['createBooking']) || ! is_array($cloned['createBooking'])) {
            $this->applyTripOrdersWireSafeDefaults($cloned);
        }

        return $this->deepRemoveNullValuesFromArray($cloned);
    }

    /**
     * Safe structural digest of the wire POST body (no PII values).
     *
     * @param  array<string, mixed>  $envelope  Full Trip Orders envelope (may include {@code _ota*})
     * @return array<string, mixed>
     */
    public function summarizeTripOrdersWirePostBodyForEnvelope(array $envelope): array
    {
        $style = (string) ($envelope['_ota_createbooking_payload_style'] ?? $this->resolveCreatebookingPayloadStyle());
        $requiresPassportDoc = (bool) ($envelope['_ota_requires_passport_doc'] ?? false);
        $rawWire = $this->tripOrdersWirePostBodyFromEnvelope($envelope);
        $nullBefore = (($envelope['_ota_payload_schema'] ?? '') === 'trip_orders_create_booking_v1')
            ? $this->collectWireJsonNullDotPaths($rawWire)
            : [];
        $wire = $this->tripOrdersFinalWirePostBodyFromEnvelope($envelope);
        $base = $this->summarizeTripOrdersWirePostBody($wire, $requiresPassportDoc, $style);
        $nullAfter = (($envelope['_ota_payload_schema'] ?? '') === 'trip_orders_create_booking_v1')
            ? $this->collectWireJsonNullDotPaths($wire)
            : [];
        $contract = match (true) {
            $this->tripOrdersStyleUsesCreateBookingRootFlightDetailsV2($style) => $this->validateTripOrdersFlightDetailsRootSabreWireContract($wire, $requiresPassportDoc, 'full_camel'),
            $style === 'trip_orders_flight_offer_root_v1' => $this->validateTripOrdersFlightOfferRootWireContract($wire, $requiresPassportDoc),
            $style === 'trip_orders_flight_offer_camel_v1' => $this->validateTripOrdersFlightOfferRootCamelWireContract($wire, $requiresPassportDoc),
            $style === 'trip_orders_flight_details_camel_v1' => $this->validateTripOrdersFlightDetailsRootCamelWireContract($wire, $requiresPassportDoc),
            $style === 'trip_orders_flight_details_full_camel_v1' => $this->validateTripOrdersFlightDetailsRootFullCamelWireContract($wire, $requiresPassportDoc),
            $this->tripOrdersStyleUsesSabreTripOrdersPassengerCode($style) => $this->validateTripOrdersFlightDetailsRootSabreWireContract($wire, $requiresPassportDoc),
            default => ['wire_contract_valid' => true, 'wire_invalid_contract_keys' => []],
        };
        if ($this->tripOrdersStyleRequiresSabreAgencyPhone($style)) {
            $expected = $this->expectedSabreAgencyPhoneDotPathsForStyle($style);
            $missing = [];
            foreach ($expected as $dotPath) {
                if (! $this->wireHasNonEmptyScalarAtDotPath($wire, $dotPath)) {
                    $missing[] = $dotPath;
                }
            }
            if ($missing !== []) {
                $invalid = isset($contract['wire_invalid_contract_keys']) && is_array($contract['wire_invalid_contract_keys'])
                    ? $contract['wire_invalid_contract_keys']
                    : [];
                foreach ($missing as $m) {
                    $invalid[] = $m;
                }
                $contract['wire_contract_valid'] = false;
                $contract['wire_invalid_contract_keys'] = array_values(array_unique(array_map('strval', $invalid)));
            }
        }
        $requiredNullPaths = [];
        foreach ($nullAfter as $p) {
            if (is_string($p) && $p !== '' && ! $this->isWireOptionalNullDotPath($p)) {
                $requiredNullPaths[] = $p;
            }
        }
        $requiredNullPaths = array_values(array_slice(array_unique($requiredNullPaths), 0, 48));

        $otaWirePresence = [
            'wire_pcc_present' => (bool) ($envelope['_ota_pcc_available_for_pos'] ?? false),
            'wire_agency_config_phone_present' => (bool) ($envelope['_ota_agency_phone_config_present'] ?? false),
            'wire_agency_country_config_present' => (bool) ($envelope['_ota_agency_country_config_present'] ?? false),
        ];

        return array_merge([
            'payload_style' => $style,
            'wire_null_path_count' => count($nullAfter),
            'wire_null_paths' => array_slice($nullAfter, 0, 96),
            'wire_required_null_paths' => $requiredNullPaths,
            'wire_has_any_nulls' => $nullBefore !== [],
            'wire_nulls_safe_to_omit' => $nullBefore === [] || $this->wireNullPathsAreSafeToOmit($nullBefore),
            'wire_payload_null_free' => $nullAfter === [],
        ], $base, $contract, $otaWirePresence);
    }

    /**
     * @param  array<string, mixed>  $wire  Body after {@see tripOrdersWirePostBodyFromEnvelope()}
     * @return array<string, mixed>
     */
    public function summarizeTripOrdersWirePostBody(array $wire, bool $requiresPassportDoc = false, ?string $payloadStyle = null): array
    {
        $rootKeys = [];
        foreach (array_keys($wire) as $k) {
            if (is_string($k)) {
                $rootKeys[] = $k;
            }
        }
        sort($rootKeys);
        $cb = is_array($wire['createBooking'] ?? null) ? $wire['createBooking'] : [];
        $hasFoRoot = isset($wire['flightOffer']) && is_array($wire['flightOffer']) && $wire['flightOffer'] !== [];
        $hasFdRoot = isset($wire['flightDetails']) && is_array($wire['flightDetails']) && $wire['flightDetails'] !== [];
        $hasHotelRoot = array_key_exists('hotel', $wire) && $wire['hotel'] !== null && $wire['hotel'] !== [];
        $hasCarRoot = array_key_exists('car', $wire) && $wire['car'] !== null && $wire['car'] !== [];
        $hasFoCb = isset($cb['flightOffer']) && is_array($cb['flightOffer']) && $cb['flightOffer'] !== [];
        $hasFdCb = isset($cb['flightDetails']) && is_array($cb['flightDetails']) && $cb['flightDetails'] !== [];
        $products = is_array($wire['products'] ?? null) ? $wire['products'] : [];
        $hasFlightProductInProducts = $this->wireProductsContainFlight($products);
        $wireHasRequiredProductAtRoot = $hasFoRoot || $hasFdRoot || $hasHotelRoot || $hasCarRoot || $hasFlightProductInProducts;
        $wireHasRequiredBookingProductNested = $hasFoCb || $hasFdCb;

        $foPath = 'none';
        if ($hasFoRoot) {
            $foPath = 'flightOffer';
        } elseif ($hasFoCb) {
            $foPath = 'createBooking.flightOffer';
        }
        $fdPath = 'none';
        if ($hasFdRoot) {
            $fdPath = 'flightDetails';
        } elseif ($hasFdCb) {
            $fdPath = 'createBooking.flightDetails';
        } elseif ($hasFlightProductInProducts) {
            $fdPath = 'products[].flightDetails';
        }

        $segCount = $this->wireSegmentCount($wire, $cb);
        $travelers = is_array($wire['travelers'] ?? null) ? $wire['travelers'] : (is_array($cb['travelers'] ?? null) ? $cb['travelers'] : []);
        $contactBlock = is_array($wire['contact'] ?? null) ? $wire['contact'] : (is_array($cb['contact'] ?? null) ? $cb['contact'] : []);
        $contactInfoBlock = is_array($wire['contactInfo'] ?? null) ? $wire['contactInfo'] : (is_array($cb['contactInfo'] ?? null) ? $cb['contactInfo'] : []);
        $sabreContactInfoStyle = $payloadStyle !== null && $this->tripOrdersStyleUsesSabreTripOrdersContactInfo($payloadStyle);
        $wireHasContactLegacy = (trim((string) ($contactBlock['email'] ?? '')) !== '')
            || (trim((string) ($contactBlock['phone'] ?? '')) !== '');
        $wireHasContactInfo = (trim((string) ($contactInfoBlock['email'] ?? '')) !== '')
            || (trim((string) ($contactInfoBlock['phone'] ?? '')) !== '');
        $effectiveContactBlock = $sabreContactInfoStyle ? $contactInfoBlock : $contactBlock;
        $wireContactFieldStyle = $sabreContactInfoStyle
            ? 'contactInfo'
            : ($wireHasContactLegacy ? 'contact' : ($wireHasContactInfo ? 'contactInfo' : 'none'));
        $payment = is_array($wire['payment'] ?? null) ? $wire['payment'] : (is_array($cb['payment'] ?? null) ? $cb['payment'] : []);
        $wireAgencyPhonePaths = $this->collectWireAgencyPhonePathsPresent($wire);
        $requiresSabreAgencyPhone = $payloadStyle !== null && $this->tripOrdersStyleRequiresSabreAgencyPhone($payloadStyle);
        if ($requiresSabreAgencyPhone && $payloadStyle !== null) {
            $expected = $this->expectedSabreAgencyPhoneDotPathsForStyle($payloadStyle);
            $missing = [];
            foreach ($expected as $dotPath) {
                if (! $this->wireHasNonEmptyScalarAtDotPath($wire, $dotPath)) {
                    $missing[] = $dotPath;
                }
            }
            $wireHasAgencyPhone = $missing === [];
            $wireAgencyPhoneFieldStyle = $wireHasAgencyPhone ? ($expected[0] ?? 'none') : 'none';
        } else {
            $wireHasAgencyPhone = $wireAgencyPhonePaths !== [];
            $wireAgencyPhoneFieldStyle = $wireAgencyPhonePaths[0] ?? 'none';
        }
        $fo = is_array($wire['flightOffer'] ?? null) ? $wire['flightOffer'] : (is_array($cb['flightOffer'] ?? null) ? $cb['flightOffer'] : []);
        $fd = is_array($wire['flightDetails'] ?? null) ? $wire['flightDetails'] : (is_array($cb['flightDetails'] ?? null) ? $cb['flightDetails'] : []);
        $foSeg = is_array($fo['segments'] ?? null) ? $fo['segments'] : [];
        $fdSeg = is_array($fd['segments'] ?? null) ? $fd['segments'] : [];
        if ($fdSeg === [] && is_array($wire['products'] ?? null)) {
            foreach (array_slice($wire['products'], 0, 12) as $p) {
                if (! is_array($p)) {
                    continue;
                }
                $fdp = is_array($p['flightDetails'] ?? null) ? $p['flightDetails'] : [];
                $sdp = is_array($fdp['segments'] ?? null) ? $fdp['segments'] : [];
                if ($sdp !== []) {
                    $fdSeg = array_merge($fdSeg, $sdp);
                }
            }
        }
        $wireTravelerCount = count($travelers);
        $wireFlightOfferSegmentCount = count($foSeg);
        $wireFlightDetailsSegmentCount = count($fdSeg);
        $fbcList = is_array($fo['fare_basis_codes'] ?? null) ? $fo['fare_basis_codes'] : [];
        $wireFareBasisCount = count($fbcList);
        if ($wireFareBasisCount === 0) {
            foreach (array_merge($foSeg, $fdSeg) as $s) {
                if (is_array($s) && trim((string) ($s['fare_basis_code'] ?? $s['fareBasisCode'] ?? '')) !== '') {
                    $wireFareBasisCount++;
                }
            }
        }
        $wireBookingClassCount = 0;
        foreach (array_merge($foSeg, $fdSeg) as $s) {
            if (! is_array($s)) {
                continue;
            }
            $bc = trim((string) ($s['booking_class'] ?? $s['class_of_service'] ?? $s['classOfService'] ?? ''));
            if ($bc !== '') {
                $wireBookingClassCount++;
            }
        }
        $vcRoot = strtoupper(trim((string) ($wire['validating_carrier'] ?? '')));
        $vcFo = strtoupper(trim((string) ($fo['validating_carrier'] ?? '')));
        $vcFd = strtoupper(trim((string) ($fd['validating_carrier'] ?? '')));
        $vcProduct = '';
        if (is_array($wire['products'] ?? null)) {
            foreach (array_slice($wire['products'], 0, 8) as $p) {
                if (! is_array($p)) {
                    continue;
                }
                $fdp = $p['flightDetails'] ?? null;
                if (is_array($fdp) && is_string($fdp['validating_carrier'] ?? null) && trim($fdp['validating_carrier']) !== '') {
                    $vcProduct = strtoupper(trim($fdp['validating_carrier']));
                    break;
                }
            }
        }
        $wireHasValidatingCarrier = $vcRoot !== '' || $vcFo !== '' || $vcFd !== '' || $vcProduct !== '';
        $pricingPaths = [
            $wire['pricing'] ?? null,
            $fo['pricing'] ?? null,
            $fd['pricing'] ?? null,
        ];
        if (is_array($wire['products'] ?? null)) {
            foreach (array_slice($wire['products'], 0, 8) as $p) {
                if (! is_array($p)) {
                    continue;
                }
                $fdp = $p['flightDetails'] ?? null;
                if (is_array($fdp) && isset($fdp['pricing'])) {
                    $pricingPaths[] = $fdp['pricing'];
                }
            }
        }
        $wireHasAmount = false;
        $wireHasCurrency = false;
        foreach ($pricingPaths as $pr) {
            if (! is_array($pr)) {
                continue;
            }
            $tot = $pr['total'] ?? null;
            if (is_numeric($tot) && (float) $tot > 0) {
                $wireHasAmount = true;
            }
            $cur = trim((string) ($pr['currency'] ?? ''));
            if ($cur !== '') {
                $wireHasCurrency = true;
            }
        }

        $remarksRoot = $wire['remarks'] ?? null;
        $remarksCb = $cb['remarks'] ?? null;
        $remarksList = is_array($remarksRoot) ? $remarksRoot : (is_array($remarksCb) ? $remarksCb : []);
        $wireRemarksCount = count($remarksList);
        $wireHasRemarks = $remarksList !== [];

        $acceptedGenders = self::sabreTripOrdersGenderEnumAccepted();
        $wireGenderValuesSanitized = [];
        $wireGenderEnumValid = true;
        foreach ($travelers as $tv) {
            if (! is_array($tv)) {
                continue;
            }
            $gv = isset($tv['gender']) ? strtoupper(trim((string) $tv['gender'])) : '';
            if ($gv === '' || ! in_array($gv, $acceptedGenders, true)) {
                $wireGenderEnumValid = false;
                $wireGenderValuesSanitized[] = $gv === '' ? '_missing_' : 'INVALID';
            } else {
                $wireGenderValuesSanitized[] = $gv;
            }
        }

        $sabrePtcStyle = $payloadStyle !== null && $this->tripOrdersStyleUsesSabreTripOrdersPassengerCode($payloadStyle);
        $camelWireForDiag = ($payloadStyle !== null && ($this->tripOrdersStyleUsesCamelTravelerKeys($payloadStyle) || $sabrePtcStyle))
            || $this->tripOrdersWireTravelersUseCamelCaseKeys($travelers);
        $travelerFieldDiag = $this->summarizeTripOrdersWireTravelerFieldDiagnostics(
            $travelers,
            $requiresPassportDoc,
            $camelWireForDiag,
            $sabrePtcStyle ? 'passengerCode' : null,
        );
        $camelStyle = ($payloadStyle !== null && $this->tripOrdersStyleUsesCamelTravelerKeys($payloadStyle))
            || (! $sabrePtcStyle && $this->tripOrdersWireTravelersUseCamelCaseKeys($travelers));
        $segDiag = $this->tripOrdersWireSegmentRequirementDiagnostics($wire, $cb, $payloadStyle, $foSeg, $fdSeg);

        $hasPOS = isset($wire['POS']) && is_array($wire['POS']) && $wire['POS'] !== [];
        $hasPosCamel = isset($wire['pos']) && is_array($wire['pos']) && $wire['pos'] !== [];
        $hasAgencyBlock = isset($wire['agency']) && is_array($wire['agency']) && $wire['agency'] !== [];
        $hasTravelAgency = isset($wire['travelAgency']) && is_array($wire['travelAgency']) && $wire['travelAgency'] !== [];
        $hasCustomerInfo = isset($wire['customerInfo']) && is_array($wire['customerInfo']) && $wire['customerInfo'] !== [];

        return array_merge([
            'wire_root_keys' => array_slice($rootKeys, 0, 48),
            'wire_has_flight_offer_at_root' => $hasFoRoot,
            'wire_has_flight_details_at_root' => $hasFdRoot,
            'wire_has_hotel_at_root' => $hasHotelRoot,
            'wire_has_car_at_root' => $hasCarRoot,
            'wire_has_required_product_at_root' => $wireHasRequiredProductAtRoot,
            'wire_flight_offer_path' => $foPath,
            'wire_flight_details_path' => $fdPath,
            'wire_segment_count' => $segCount,
            'wire_flight_offer_segment_count' => $wireFlightOfferSegmentCount,
            'wire_flight_details_segment_count' => $wireFlightDetailsSegmentCount,
            'wire_traveler_count' => $wireTravelerCount,
            'wire_gender_values_sanitized' => $wireGenderValuesSanitized,
            'wire_gender_enum_valid' => $wireGenderEnumValid,
            'wire_has_remarks' => $wireHasRemarks,
            'wire_remarks_count' => $wireRemarksCount,
            'wire_fare_basis_count' => $wireFareBasisCount,
            'wire_booking_class_count' => $wireBookingClassCount,
            'wire_has_validating_carrier' => $wireHasValidatingCarrier,
            'wire_has_amount' => $wireHasAmount,
            'wire_has_currency' => $wireHasCurrency,
            'wire_has_passengers' => $travelers !== [],
            'wire_has_contact' => $wireHasContactLegacy,
            'wire_has_contactInfo' => $wireHasContactInfo,
            'wire_contact_field_style' => $wireContactFieldStyle,
            'wire_has_contact_email' => trim((string) ($effectiveContactBlock['email'] ?? '')) !== '',
            'wire_has_contact_phone' => trim((string) ($effectiveContactBlock['phone'] ?? '')) !== '',
            'wire_has_customer_contact_phone' => trim((string) ($effectiveContactBlock['phone'] ?? '')) !== '',
            'wire_has_agency_phone' => $wireHasAgencyPhone,
            'wire_agency_phone_field_style' => $wireAgencyPhoneFieldStyle,
            'wire_agency_phone_paths' => $wireAgencyPhonePaths,
            'wire_agency_phone_redacted' => $wireHasAgencyPhone,
            'wire_agency_phone_ok' => ! $requiresSabreAgencyPhone || $wireHasAgencyPhone,
            'wire_has_POS' => $hasPOS,
            'wire_has_pos' => $hasPosCamel,
            'wire_has_agency_block' => $hasAgencyBlock,
            'wire_has_travelAgency' => $hasTravelAgency,
            'wire_has_customerInfo' => $hasCustomerInfo,
            'wire_phone_use_type_values_sanitized' => $this->collectWirePhoneUseTypeLikeValuesSanitized($wire),
            'wire_phone_location_values_sanitized' => $this->collectWirePhoneLocationValuesSanitized($wire),
            'wire_has_payment_or_hold_mode' => isset($payment['mode']) && is_string($payment['mode']) && trim($payment['mode']) !== '',
            'wire_ticketing_enabled' => (bool) data_get($wire, 'ticketing.enabled', data_get($cb, 'ticketing.ticketing_enabled_config', false)),
            'wire_has_required_booking_product_nested' => $wireHasRequiredBookingProductNested,
            'wire_traveler_field_style' => $sabrePtcStyle ? 'sabreTripOrders' : ($camelStyle ? 'camelCase' : 'snake_case'),
            'wire_has_passengerCode' => $this->wireTravelersHasNonEmptyKey($travelers, 'passengerCode'),
            'wire_has_passengerTypeCode' => $this->wireTravelersHasNonEmptyKey($travelers, 'passengerTypeCode'),
            'wire_has_givenName' => $this->wireTravelersHasNonEmptyKey($travelers, 'givenName'),
            'wire_has_given_name' => $this->wireTravelersHasNonEmptyKey($travelers, 'given_name'),
        ], $travelerFieldDiag, $segDiag);
    }

    /**
     * @param  list<array<string, mixed>>  $travelers
     * @param  list<string>  $invalid
     */
    protected function appendTripOrdersRootWireTravelerContractViolations(
        array $travelers,
        bool $requiresPassportDoc,
        bool $camelTravelerKeys,
        array &$invalid,
        string $camelTravelerTopPtcWireKey = 'passengerTypeCode',
    ): void {
        if (count($travelers) < 1) {
            $invalid[] = 'travelers';

            return;
        }
        foreach ($travelers as $i => $t) {
            if (! is_array($t)) {
                $invalid[] = 'travelers.'.$i;

                continue;
            }
            if ($camelTravelerKeys) {
                $ptcKey = in_array($camelTravelerTopPtcWireKey, ['passengerCode', 'passengerTypeCode'], true)
                    ? $camelTravelerTopPtcWireKey
                    : 'passengerTypeCode';
                foreach (['givenName', 'surname', 'gender', 'birthDate', $ptcKey] as $k) {
                    if (trim((string) ($t[$k] ?? '')) === '') {
                        $invalid[] = 'travelers.'.$i.'.'.$k;
                    }
                }
                if ($requiresPassportDoc) {
                    $pp = is_array($t['passport'] ?? null) ? $t['passport'] : [];
                    foreach (['documentType', 'issuingCountry', 'nationality', 'expiryDate', 'number'] as $dk) {
                        if (trim((string) ($pp[$dk] ?? '')) === '') {
                            $invalid[] = 'travelers.'.$i.'.passport.'.$dk;
                        }
                    }
                }
            } else {
                foreach (['given_name', 'surname', 'gender', 'birth_date'] as $k) {
                    if (trim((string) ($t[$k] ?? '')) === '') {
                        $invalid[] = 'travelers.'.$i.'.'.$k;
                    }
                }
                if ($requiresPassportDoc) {
                    $pp = is_array($t['passport'] ?? null) ? $t['passport'] : [];
                    foreach (['document_type', 'issuing_country', 'nationality', 'expiry_date', 'number'] as $dk) {
                        if (trim((string) ($pp[$dk] ?? '')) === '') {
                            $invalid[] = 'travelers.'.$i.'.passport.'.$dk;
                        }
                    }
                }
            }
        }
    }

    /**
     * B30: detect first-segment wire key family for diagnostics (no PII).
     *
     * @param  list<array<string, mixed>>  $foSeg
     * @param  list<array<string, mixed>>  $fdSeg
     */
    public function detectWireSegmentFieldStyle(array $foSeg, array $fdSeg): string
    {
        if ($fdSeg !== []) {
            $s0 = $fdSeg[0];
            if (is_array($s0)) {
                if (array_key_exists('departureDateTime', $s0)) {
                    return 'flightDetails_full_camel';
                }
                if (array_key_exists('departure_datetime', $s0)) {
                    return 'flightDetails_datetime_airline';
                }
                if (array_key_exists('departure_at', $s0)) {
                    return 'flightDetails_snake';
                }
            }

            return 'flightDetails_unknown';
        }
        if ($foSeg !== []) {
            return 'flightOffer_snake';
        }

        return 'none';
    }

    /**
     * B30: which flight product root + segment key profile {@see collectTripOrdersWireSegmentFieldViolationsForProduct()} applies for envelope style.
     *
     * @return array{0: string, 1: string}|null [productRootKey, segmentContractProfile]
     */
    protected function tripOrdersWireSegmentContractBindingForPayloadStyle(?string $payloadStyle): ?array
    {
        if ($payloadStyle === null || $payloadStyle === '') {
            return null;
        }
        $s = trim($payloadStyle);

        return match (true) {
            in_array($s, [
                'trip_orders_flight_details_v1', 'trip_orders_flight_details_root_v1', 'trip_orders_flight_details_camel_v1',
                'trip_orders_flight_details_sabre_v1', 'trip_orders_flight_details_sabre_agency_v1',
                'trip_orders_flight_details_sabre_agencyInfo_v1', 'trip_orders_flight_details_sabre_agencyPhoneNumber_v1',
                'trip_orders_flight_details_sabre_agencyPhonesArray_v1', 'trip_orders_flight_details_sabre_rootAgencyPhone_v1',
                'trip_orders_flight_details_sabre_phoneNumbers_v1',
                'trip_orders_flight_details_sabre_rootPhones_v1', 'trip_orders_flight_details_sabre_rootPhoneNumbers_v1',
                'trip_orders_flight_details_sabre_contactInfoPhones_v1', 'trip_orders_flight_details_sabre_agencyPhoneUseType_v1',
                'trip_orders_flight_details_sabre_phone_use_business_v1', 'trip_orders_flight_details_sabre_phone_use_agency_v1',
                'trip_orders_flight_details_sabre_pos_source_phone_v1', 'trip_orders_flight_details_sabre_pos_phone_v1',
                'trip_orders_flight_details_sabre_agency_root_camel_v1', 'trip_orders_flight_details_sabre_travelAgency_v1',
                'trip_orders_flight_details_sabre_customerInfo_phone_v1',
                'trip_orders_flight_details_sabre_phoneLine_v1',
                'trip_orders_flight_details_sabre_phoneLines_v1',
                'trip_orders_flight_details_sabre_contactNumbers_v1',
                'trip_orders_flight_details_sabre_pnrContact_v1',
                'trip_orders_flight_details_sabre_reservationContact_v1',
                'trip_orders_flight_details_sabre_contactInfo_phoneLine_v1',
                'trip_orders_flight_details_sabre_travelers_phone_v1',
                'trip_orders_product_array_v1',
            ], true) => ['flightDetails', 'flight_details_datetime_airline'],
            $s === 'trip_orders_flight_details_full_camel_v1' => ['flightDetails', 'full_camel'],
            in_array($s, ['trip_orders_flight_offer_v1', 'trip_orders_flight_offer_root_v1', 'trip_orders_flight_offer_camel_v1'], true) => ['flightOffer', 'snake'],
            default => null,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $segs
     * @return list<string>
     */
    protected function collectTripOrdersWireSegmentFieldViolationsForProduct(
        string $productRootKey,
        array $segs,
        string $segmentContractProfile,
    ): array {
        $violations = [];
        if (count($segs) < 1) {
            return [$productRootKey.'.segments'];
        }
        $requiredKeys = match ($segmentContractProfile) {
            'flight_details_datetime_airline' => ['origin', 'destination', 'departure_datetime', 'marketing_airline', 'flight_number', 'class_of_service'],
            'full_camel' => ['origin', 'destination', 'departureDateTime', 'marketingAirline', 'flightNumber', 'classOfService'],
            default => ['origin', 'destination', 'departure_at', 'marketing_carrier', 'flight_number', 'class_of_service'],
        };
        foreach ($segs as $i => $s) {
            if (! is_array($s)) {
                $violations[] = $productRootKey.'.segments.'.$i;

                continue;
            }
            foreach ($requiredKeys as $k) {
                if (trim((string) ($s[$k] ?? '')) === '') {
                    $violations[] = $productRootKey.'.segments.'.$i.'.'.$k;
                }
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $wire
     * @param  array<string, mixed>  $cbNested  createBooking branch when wire is nested
     * @return array{wire_segment_field_style: string, wire_segment_required_fields_valid: bool, wire_invalid_segment_field_keys: list<string>}
     */
    protected function tripOrdersWireSegmentRequirementDiagnostics(
        array $wire,
        array $cbNested,
        ?string $payloadStyle,
        array $foSeg,
        array $fdSeg,
    ): array {
        $styleTag = $this->detectWireSegmentFieldStyle($foSeg, $fdSeg);
        $binding = $this->tripOrdersWireSegmentContractBindingForPayloadStyle($payloadStyle);
        if ($binding === null) {
            return [
                'wire_segment_field_style' => $styleTag,
                'wire_segment_required_fields_valid' => true,
                'wire_invalid_segment_field_keys' => [],
            ];
        }
        [$root, $profile] = $binding;
        $prod = is_array($wire[$root] ?? null) ? $wire[$root] : (is_array($cbNested[$root] ?? null) ? $cbNested[$root] : []);
        $segs = is_array($prod['segments'] ?? null) ? $prod['segments'] : [];
        if ($root === 'flightDetails' && $segs === [] && is_array($wire['products'] ?? null)) {
            foreach (array_slice($wire['products'], 0, 12) as $p) {
                if (! is_array($p)) {
                    continue;
                }
                $fdp = is_array($p['flightDetails'] ?? null) ? $p['flightDetails'] : [];
                $sdp = is_array($fdp['segments'] ?? null) ? $fdp['segments'] : [];
                if ($sdp !== []) {
                    $segs = $sdp;
                    break;
                }
            }
        }
        $violations = $this->collectTripOrdersWireSegmentFieldViolationsForProduct($root, $segs, $profile);
        $violations = array_values(array_slice(array_unique(array_filter($violations, static fn ($v) => is_string($v) && $v !== '')), 0, 48));

        return [
            'wire_segment_field_style' => $styleTag,
            'wire_segment_required_fields_valid' => $violations === [],
            'wire_invalid_segment_field_keys' => $violations,
        ];
    }

    /**
     * @param  array<string, mixed>  $wire
     * @param  'contact'|'contactInfo'  $contactWireKey  Root contact object key Sabre expects on the wire (B32: {@code contactInfo} for {@code trip_orders_flight_details_sabre_v1}).
     * @return array{wire_contract_valid: bool, wire_invalid_contract_keys: list<string>}
     */
    protected function validateTripOrdersRootWireFlightProductContract(
        array $wire,
        bool $requiresPassportDoc,
        bool $camelTravelerKeys,
        string $productRootKey,
        string $segmentContractProfile = 'snake',
        string $camelTravelerTopPtcWireKey = 'passengerTypeCode',
        string $contactWireKey = 'contact',
    ): array {
        $invalid = [];
        $prod = is_array($wire[$productRootKey] ?? null) ? $wire[$productRootKey] : [];
        if ($prod === []) {
            $invalid[] = $productRootKey;
        }
        $segs = is_array($prod['segments'] ?? null) ? $prod['segments'] : [];
        if (count($segs) < 1) {
            $invalid[] = $productRootKey.'.segments';
        } else {
            foreach ($this->collectTripOrdersWireSegmentFieldViolationsForProduct($productRootKey, $segs, $segmentContractProfile) as $p) {
                $invalid[] = $p;
            }
        }
        $travelers = is_array($wire['travelers'] ?? null) ? $wire['travelers'] : [];
        $this->appendTripOrdersRootWireTravelerContractViolations($travelers, $requiresPassportDoc, $camelTravelerKeys, $invalid, $camelTravelerTopPtcWireKey);
        $ck = in_array($contactWireKey, ['contact', 'contactInfo'], true) ? $contactWireKey : 'contact';
        if ($ck === 'contactInfo') {
            if (! isset($wire['contactInfo']) || ! is_array($wire['contactInfo'])) {
                $invalid[] = 'contactInfo';
            } else {
                $cblock = $wire['contactInfo'];
                if (trim((string) ($cblock['email'] ?? '')) === '' && trim((string) ($cblock['phone'] ?? '')) === '') {
                    $invalid[] = 'contactInfo.email_or_phone';
                }
            }
        } else {
            $contact = is_array($wire['contact'] ?? null) ? $wire['contact'] : [];
            if (trim((string) ($contact['email'] ?? '')) === '' && trim((string) ($contact['phone'] ?? '')) === '') {
                $invalid[] = 'contact.email_or_phone';
            }
        }
        $pr = is_array($wire['pricing'] ?? null) ? $wire['pricing'] : [];
        $tot = $pr['total'] ?? null;
        if (! is_numeric($tot) || (float) $tot <= 0) {
            $invalid[] = 'pricing.total';
        }
        if (trim((string) ($pr['currency'] ?? '')) === '') {
            $invalid[] = 'pricing.currency';
        }
        $commit = is_array($wire['commit'] ?? null) ? $wire['commit'] : [];
        if (($commit['endTransaction'] ?? false) !== true) {
            $invalid[] = 'commit.endTransaction';
        }
        $tick = is_array($wire['ticketing'] ?? null) ? $wire['ticketing'] : [];
        if (! array_key_exists('enabled', $tick) || $tick['enabled'] !== false) {
            $invalid[] = 'ticketing.enabled';
        }
        $invalid = array_values(array_unique(array_filter($invalid, static fn ($v) => is_string($v) && $v !== '')));

        return [
            'wire_contract_valid' => $invalid === [],
            'wire_invalid_contract_keys' => array_slice($invalid, 0, 48),
        ];
    }

    /**
     * @param  array<string, mixed>  $wire  Final Trip Orders wire body
     * @return array{wire_contract_valid: bool, wire_invalid_contract_keys: list<string>}
     */
    protected function validateTripOrdersFlightOfferRootWireContract(array $wire, bool $requiresPassportDoc): array
    {
        return $this->validateTripOrdersRootWireFlightProductContract($wire, $requiresPassportDoc, false, 'flightOffer');
    }

    /**
     * B29: {@code trip_orders_flight_offer_camel_v1} — same root product contract with camelCase travelers.
     *
     * @param  array<string, mixed>  $wire
     * @return array{wire_contract_valid: bool, wire_invalid_contract_keys: list<string>}
     */
    protected function validateTripOrdersFlightOfferRootCamelWireContract(array $wire, bool $requiresPassportDoc): array
    {
        return $this->validateTripOrdersRootWireFlightProductContract($wire, $requiresPassportDoc, true, 'flightOffer');
    }

    /**
     * B29: {@code trip_orders_flight_details_camel_v1}.
     *
     * @param  array<string, mixed>  $wire
     * @return array{wire_contract_valid: bool, wire_invalid_contract_keys: list<string>}
     */
    protected function validateTripOrdersFlightDetailsRootCamelWireContract(array $wire, bool $requiresPassportDoc): array
    {
        return $this->validateTripOrdersRootWireFlightProductContract($wire, $requiresPassportDoc, true, 'flightDetails', 'flight_details_datetime_airline');
    }

    /**
     * B30: {@code trip_orders_flight_details_full_camel_v1} — Sabre-like segment scalars + camel travelers.
     *
     * @param  array<string, mixed>  $wire
     * @return array{wire_contract_valid: bool, wire_invalid_contract_keys: list<string>}
     */
    protected function validateTripOrdersFlightDetailsRootFullCamelWireContract(array $wire, bool $requiresPassportDoc): array
    {
        return $this->validateTripOrdersRootWireFlightProductContract($wire, $requiresPassportDoc, true, 'flightDetails', 'full_camel');
    }

    /**
     * B31: {@code trip_orders_flight_details_sabre_v1} — {@code passengerCode} on wire travelers (Sabre Trip Orders) and root {@code contactInfo}.
     *
     * @param  array<string, mixed>  $wire
     * @return array{wire_contract_valid: bool, wire_invalid_contract_keys: list<string>}
     */
    protected function validateTripOrdersFlightDetailsRootSabreWireContract(
        array $wire,
        bool $requiresPassportDoc,
        string $segmentContractProfile = 'flight_details_datetime_airline',
    ): array {
        return $this->validateTripOrdersRootWireFlightProductContract($wire, $requiresPassportDoc, true, 'flightDetails', $segmentContractProfile, 'passengerCode', 'contactInfo');
    }

    /**
     * Safe certification digest of the Trip Orders wire POST body (no PII).
     *
     * @param  array<string, mixed>  $envelope  Full Trip Orders envelope (may include {@code _ota*})
     * @return array<string, mixed>
     */
    public function summarizeTripOrdersCertificationPayloadSummary(array $envelope): array
    {
        $wire = $this->tripOrdersFinalWirePostBodyFromEnvelope($envelope);
        $cb = is_array($wire['createBooking'] ?? null) ? $wire['createBooking'] : [];
        $rootKeys = [];
        foreach (array_keys($wire) as $k) {
            if (is_string($k)) {
                $rootKeys[] = $k;
            }
        }
        sort($rootKeys);
        $hasFdHttp = isset($wire['flightDetails']) && is_array($wire['flightDetails']) && $wire['flightDetails'] !== [];
        $hasFoHttp = isset($wire['flightOffer']) && is_array($wire['flightOffer']) && $wire['flightOffer'] !== [];
        $hasFdCb = isset($cb['flightDetails']) && is_array($cb['flightDetails']) && $cb['flightDetails'] !== [];
        $hasFoCb = isset($cb['flightOffer']) && is_array($cb['flightOffer']) && $cb['flightOffer'] !== [];
        $fd = $hasFdHttp ? $wire['flightDetails'] : ($hasFdCb ? $cb['flightDetails'] : []);
        $fd = is_array($fd) ? $fd : [];
        $segCount = is_array($fd['segments'] ?? null) ? count($fd['segments']) : 0;
        if ($segCount < 1) {
            $segCount = $this->wireSegmentCount($wire, $cb);
        }
        $travelers = is_array($wire['travelers'] ?? null) ? $wire['travelers'] : (is_array($cb['travelers'] ?? null) ? $cb['travelers'] : []);
        $ciWire = is_array($wire['contactInfo'] ?? null) ? $wire['contactInfo'] : [];
        $ciCb = is_array($cb['contactInfo'] ?? null) ? $cb['contactInfo'] : [];
        $hasContactInfo = (trim((string) ($ciWire['email'] ?? '')) !== '' || trim((string) ($ciWire['phone'] ?? '')) !== '')
            || (trim((string) ($ciCb['email'] ?? '')) !== '' || trim((string) ($ciCb['phone'] ?? '')) !== '');
        $agencyPaths = $this->collectWireAgencyPhonePathsPresent($wire);
        $hasAgencyPhoneValue = $agencyPaths !== [];
        $phoneShapes = $this->describeTripOrdersPhoneShapesForCertification($wire, $cb);

        return array_merge([
            'root_keys' => array_slice($rootKeys, 0, 48),
            'has_flightDetails' => $hasFdHttp || $hasFdCb,
            'has_flightOffer' => $hasFoHttp || $hasFoCb,
            'segment_count' => $segCount,
            'traveler_count' => count($travelers),
            'has_agencyContactInfo' => isset($wire['agencyContactInfo']) && is_array($wire['agencyContactInfo']) && $wire['agencyContactInfo'] !== [],
            'has_contactInfo' => $hasContactInfo,
            'has_agency_phone_value' => $hasAgencyPhoneValue,
            'agency_phone_shape' => $phoneShapes['agency_phone_shape'],
            'has_contactInfo_phone' => $phoneShapes['has_contactInfo_phone'],
            'contact_phone_shape' => $phoneShapes['contact_phone_shape'],
            'agency_name_present' => trim((string) config('suppliers.sabre.agency_name', '')) !== '',
            'agency_country_present' => trim((string) config('suppliers.sabre.agency_country', '')) !== ''
                || trim((string) config('suppliers.sabre.agency_phone_country_code', '')) !== '',
            'agency_phone_type_present' => trim((string) config('suppliers.sabre.agency_phone_type', '')) !== '',
        ], $phoneShapes);
    }

    /**
     * Q3: Safe phone placement labels for certification (no phone digits).
     *
     * @param  array<string, mixed>  $wire
     * @param  array<string, mixed>  $createBookingNested
     * @return array{agency_phone_shape: string, has_contactInfo_phone: bool, contact_phone_shape: string}
     */
    protected function describeTripOrdersPhoneShapesForCertification(array $wire, array $createBookingNested = []): array
    {
        $agencyPaths = $this->collectWireAgencyPhonePathsPresent($wire);
        $agencyShape = 'none';
        if ($agencyPaths !== []) {
            $agencyShape = $agencyPaths[0];
            if (count($agencyPaths) > 1) {
                $agencyShape .= '+'.(count($agencyPaths) - 1).'_more';
            }
        }
        $ci = is_array($wire['contactInfo'] ?? null) ? $wire['contactInfo'] : [];
        if ($ci === [] && $createBookingNested !== []) {
            $ci = is_array($createBookingNested['contactInfo'] ?? null) ? $createBookingNested['contactInfo'] : [];
        }
        $contactShape = 'none';
        $hasContactPhone = false;
        if (trim((string) ($ci['phone'] ?? '')) !== '') {
            $hasContactPhone = true;
            $contactShape = 'contactInfo.phone';
        }
        $cphones = is_array($ci['phones'] ?? null) ? $ci['phones'] : [];
        if ($cphones !== []) {
            $hasContactPhone = true;
            $row0 = is_array($cphones[0] ?? null) ? $cphones[0] : [];
            if (isset($row0['phoneNumber'])) {
                $contactShape = 'contactInfo.phones[].phoneNumber';
            } elseif (isset($row0['number'])) {
                $contactShape = 'contactInfo.phones[].number';
            } else {
                $contactShape = 'contactInfo.phones[]';
            }
        }

        return [
            'agency_phone_shape' => $agencyShape,
            'has_contactInfo_phone' => $hasContactPhone,
            'contact_phone_shape' => $contactShape,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string> Dot paths where value is JSON null (no scalar values)
     */
    protected function collectWireJsonNullDotPaths(mixed $node, string $prefix = ''): array
    {
        if ($node === null) {
            return $prefix !== '' ? [$prefix] : [];
        }
        if (! is_array($node)) {
            return [];
        }
        $out = [];
        foreach ($node as $k => $v) {
            if (! is_string($k) && ! is_int($k)) {
                continue;
            }
            $seg = is_int($k) ? (string) $k : $k;
            $path = $prefix === '' ? $seg : $prefix.'.'.$seg;
            if ($v === null) {
                $out[] = $path;
            } else {
                foreach ($this->collectWireJsonNullDotPaths($v, $path) as $p) {
                    $out[] = $p;
                }
            }
        }
        sort($out);

        return array_values(array_unique($out));
    }

    /**
     * @param  list<string>  $nullPaths
     */
    protected function wireNullPathsAreSafeToOmit(array $nullPaths): bool
    {
        foreach ($nullPaths as $p) {
            if (! is_string($p) || $p === '') {
                continue;
            }
            if (! $this->isWireOptionalNullDotPath($p)) {
                return false;
            }
        }

        return true;
    }

    protected function isWireOptionalNullDotPath(string $path): bool
    {
        $p = $path;
        if ($p === 'remarks' || str_starts_with($p, 'remarks.')) {
            return true;
        }
        if (str_starts_with($p, 'shop_context')) {
            return true;
        }
        if (str_starts_with($p, 'supplier_context')) {
            return true;
        }
        if (str_starts_with($p, 'fare_linkage')) {
            return true;
        }
        if (str_starts_with($p, 'passenger_type_counts')) {
            return true;
        }
        if (str_starts_with($p, 'validating_carrier')) {
            return true;
        }
        if (str_contains($p, '.operating_carrier') || str_ends_with($p, 'operating_carrier')) {
            return true;
        }
        if (str_starts_with($p, 'flightOffer.raw_reference') || str_starts_with($p, 'flightOffer.itinerary_ref')) {
            return true;
        }
        if (str_starts_with($p, 'flightOffer.fare_component_references')) {
            return true;
        }
        if (str_contains($p, 'revalidated_')) {
            return true;
        }
        if (str_starts_with($p, 'flightOffer.pricing.revalidated')) {
            return true;
        }
        if (str_starts_with($p, 'ticketing.') && ! str_starts_with($p, 'ticketing.enabled')) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $wire  Trip Orders wire (mutated in place)
     */
    protected function applyTripOrdersWireSafeDefaults(array &$wire): void
    {
        if (isset($wire['travelers']) && is_array($wire['travelers'])) {
            foreach ($wire['travelers'] as $i => &$t) {
                if (! is_array($t)) {
                    continue;
                }
                if (isset($t['passport']) && is_array($t['passport'])) {
                    $camelPassport = array_key_exists('issuingCountry', $t['passport']) || array_key_exists('documentType', $t['passport']);
                    if ($camelPassport) {
                        $iss = isset($t['passport']['issuingCountry']) ? strtoupper(trim((string) $t['passport']['issuingCountry'])) : '';
                        $nat = isset($t['passport']['nationality']) ? strtoupper(trim((string) $t['passport']['nationality'])) : '';
                        if ($nat === '' && $iss !== '' && $this->iso3166Alpha2Valid($iss)) {
                            $t['passport']['nationality'] = $iss;
                        }
                        if ($iss === '' && $nat !== '' && $this->iso3166Alpha2Valid($nat)) {
                            $t['passport']['issuingCountry'] = $nat;
                        }
                    } else {
                        $iss = isset($t['passport']['issuing_country']) ? strtoupper(trim((string) $t['passport']['issuing_country'])) : '';
                        $nat = isset($t['passport']['nationality']) ? strtoupper(trim((string) $t['passport']['nationality'])) : '';
                        if ($nat === '' && $iss !== '' && $this->iso3166Alpha2Valid($iss)) {
                            $t['passport']['nationality'] = $iss;
                        }
                        if ($iss === '' && $nat !== '' && $this->iso3166Alpha2Valid($nat)) {
                            $t['passport']['issuing_country'] = $nat;
                        }
                    }
                }
            }
            unset($t);
        }
        if (isset($wire['payment']) && is_array($wire['payment'])) {
            $mode = isset($wire['payment']['mode']) ? trim((string) $wire['payment']['mode']) : '';
            if ($mode === '') {
                $wire['payment']['mode'] = 'pay_later';
            }
        } elseif (! isset($wire['payment'])) {
            $wire['payment'] = ['mode' => 'pay_later', 'capture' => false];
        }
        if (! isset($wire['ticketing']) || ! is_array($wire['ticketing'])) {
            $wire['ticketing'] = ['enabled' => false];
        } else {
            $wire['ticketing']['enabled'] = false;
        }
        if (isset($wire['commit']) && is_array($wire['commit'])) {
            if (! isset($wire['commit']['receivedFrom']) || trim((string) $wire['commit']['receivedFrom']) === '') {
                $wire['commit']['receivedFrom'] = 'OTA_WEB';
            }
        }
    }

    /**
     * Remove keys whose value is JSON null; recurse into arrays. List indices with null elements are compacted.
     *
     * @param  array<string|int, mixed>  $data
     * @return array<string|int, mixed>|mixed
     */
    protected function deepRemoveNullValuesFromArray(mixed $data): mixed
    {
        if ($data === null || ! is_array($data)) {
            return $data;
        }
        if ($data === []) {
            return [];
        }
        if (array_is_list($data)) {
            $out = [];
            foreach ($data as $item) {
                $v = $this->deepRemoveNullValuesFromArray($item);
                if ($v !== null) {
                    $out[] = $v;
                }
            }

            return $out;
        }
        $out = [];
        foreach ($data as $k => $v) {
            if ($v === null) {
                continue;
            }
            $out[$k] = $this->deepRemoveNullValuesFromArray($v);
        }

        return $out;
    }

    /**
     * Deep-redacted wire JSON for local file output (masks PII and sensitive shop tokens; caps scalars).
     *
     * @param  array<string, mixed>  $wire
     * @return array<string, mixed>
     */
    public function redactTripOrdersWireJsonForPreview(array $wire): array
    {
        return $this->redactWireValueForPreview($wire, 0);
    }

    /**
     * B39: Deep-redacted traditional CPNR wire (same masking rules as Trip Orders wire preview).
     *
     * @param  array<string, mixed>  $wire  Stripped wire (typically only {@code CreatePassengerNameRecordRQ})
     * @return array<string, mixed>
     */
    public function redactTraditionalPnrWireJsonForPreview(array $wire): array
    {
        $out = $this->redactWireValueForPreview($wire, 0);

        return is_array($out) ? $out : [];
    }

    /**
     * B39: Safe structural summary + contract validation for traditional CPNR wire (no values). **B50:** root {@code AirPrice} must be a JSON array; {@code wire_root_air_price_*} diagnostics. **B52:** {@code PostProcessing.EndTransaction} required (minimal); {@code EndTransactionRQ} forbidden; {@code wire_post_processing_has_*} booleans. **B53:** {@code AddRemark.Remark[].Type} Sabre enum casing + {@code wire_remark_*} diagnostics. **B54:** {@code SpecialReqDetails.SpecialService.Service} forbidden; {@code wire_special_service_*}, {@code wire_add_remark_present}. **B55:** {@code AgencyInfo.Telephone} forbidden; {@code wire_agency_info_*}, {@code wire_customer_info_has_contact_numbers}, {@code wire_customer_info_has_email}. **B56:** {@code CustomerInfo.PersonName} JSON array + {@code wire_customer_person_name_*}. **B57:** merges frozen IATI GDS template diff ({@code wire_iati_*}) + {@see SabreTraditionalCpnrIatiWireStructureDiagnostic::cpnrKeyNameInventory} highlights (no PII). **B58:** {@code CustomerInfo.Email} array + {@code Type=TO} per row: {@code wire_customer_email_*} + contract {@code wire_customer_email_type_valid}. **B59:** root {@code AirPrice} {@code OptionalQualifiers.PricingQualifiers.PassengerType} + {@code wire_air_price_*}, {@code wire_iati_airprice_passenger_type_delta_closed}, {@code wire_air_price_passenger_type_contract_valid}. **B60:** optional {@code $bookingMetaForDiagnostics} → {@code wire_segment_sell_context_*}, {@code wire_offer_*}, {@code wire_brand_candidate_keys_sanitized} (keys only). **B61/B61A/B61B:** {@code wire_airbook_retry_redisplay_enabled}, AirBook helper blocks, {@code wire_airbook_retry_rebook_has_option}, {@code wire_airbook_retry_rebook_option_type}, {@code wire_airbook_retry_rebook_contract_valid}, {@code wire_airbook_*_type} (integer/string/missing), {@code wire_airbook_retry_redisplay_numeric_contract_valid}.
     *
     * @param  array<string, mixed>  $wire  Post-{@see stripOtaInternalKeysFromBookingWire} body
     * @param  array<string, mixed>|null  $bookingMetaForDiagnostics  Booking {@code meta} slice for offer snapshot freshness (no payload mutation)
     * @return array<string, mixed>
     */
    public function summarizeTraditionalPnrWirePostBody(
        array $wire,
        ?array $bookingMetaForDiagnostics = null,
        ?string $traditionalComparePayloadStyle = null,
    ): array {
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];
        $ticketingCfg = (bool) config('suppliers.sabre.ticketing_enabled', false);
        $isGdsV25 = self::isPassengerRecordsV25GdsWireStyle((string) ($traditionalComparePayloadStyle ?? ''));
        $invalid = [];

        $wireRootKeys = array_values(array_filter(array_keys($wire), static fn ($k) => is_string($k) && $k !== ''));
        $hasCpnrRoot = array_key_exists('CreatePassengerNameRecordRQ', $wire) && is_array($wire['CreatePassengerNameRecordRQ']);
        if (! $hasCpnrRoot) {
            $invalid[] = 'missing_CreatePassengerNameRecordRQ_root';
        }

        $tia = is_array($cpnr['TravelItineraryAddInfo'] ?? null) ? $cpnr['TravelItineraryAddInfo'] : [];
        $hasTia = $tia !== [];
        $ci = is_array($tia['CustomerInfo'] ?? null) ? $tia['CustomerInfo'] : [];
        $hasCi = $ci !== [];

        $pn = $ci['PersonName'] ?? null;
        $paxCount = 0;
        $hasPersonName = false;
        $wireCustomerPersonNameType = 'missing';
        $wireCustomerPersonNameCount = 0;
        $wireCustomerPersonNameArrayValid = false;

        if ($pn === null) {
            $wireCustomerPersonNameType = 'missing';
        } elseif (is_array($pn)) {
            if (array_is_list($pn)) {
                $wireCustomerPersonNameType = 'array';
                $wireCustomerPersonNameCount = count($pn);
                foreach ($pn as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $paxCount++;
                    if (trim((string) ($row['GivenName'] ?? '')) !== '' || trim((string) ($row['Surname'] ?? '')) !== '') {
                        $hasPersonName = true;
                    }
                }
                $wireCustomerPersonNameArrayValid = $wireCustomerPersonNameCount >= 1;
            } else {
                $wireCustomerPersonNameType = 'object';
                $wireCustomerPersonNameCount = 1;
                $paxCount = 1;
                $hasPersonName = trim((string) ($pn['GivenName'] ?? '')) !== '' || trim((string) ($pn['Surname'] ?? '')) !== '';
                $wireCustomerPersonNameArrayValid = false;
            }
        }

        if ($wireCustomerPersonNameType === 'object') {
            $invalid[] = 'customer_info_PersonName_must_be_array';
        }
        if ($wireCustomerPersonNameType === 'array' && $wireCustomerPersonNameCount < 1) {
            $invalid[] = 'customer_info_PersonName_empty_array';
        }
        if (! $hasPersonName) {
            $invalid[] = 'missing_TravelItineraryAddInfo_CustomerInfo_PersonName';
        }

        $contactRows = $this->traditionalPnrExtractContactNumberRows($ci);
        $hasContactNumbers = $contactRows !== [];
        $hasPhoneInContact = false;
        foreach ($contactRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $ph = trim((string) ($row['Phone'] ?? $row['Number'] ?? ''));
            if ($ph !== '') {
                $hasPhoneInContact = true;
                break;
            }
        }
        if (! $hasPhoneInContact) {
            $invalid[] = 'missing_ContactNumbers_phone';
        }

        $air = is_array($cpnr['AirBook'] ?? null) ? $cpnr['AirBook'] : [];
        $hasAirBook = $air !== [];
        $segs = $this->traditionalPnrExtractFlightSegments($cpnr);
        $segCount = count($segs);
        if ($segCount <= 0) {
            $invalid[] = 'missing_AirBook_FlightSegment';
        }
        $hasFlightSeg = $segCount > 0;
        $wireFsHasCabinCode = false;
        $wireFsHasClassOfService = false;
        $wireFsHasFareBasisCode = false;
        $wireFsHasNumber = false;
        $wireFsHasResBookDesigCode = $segCount > 0;
        $forbiddenSegKeys = ['CabinCode', 'ClassOfService', 'FareBasisCode', 'Number'];
        $nipKinds = [];
        foreach ($segs as $i => $seg) {
            if (! is_array($seg)) {
                $invalid[] = 'flight_segment_'.$i.'_invalid';
                $wireFsHasResBookDesigCode = false;

                continue;
            }
            if (! array_key_exists('NumberInParty', $seg)) {
                $nipKinds[] = 'missing';
            } else {
                $nip = $seg['NumberInParty'];
                if (is_string($nip)) {
                    $nipKinds[] = 'string';
                } elseif (is_int($nip)) {
                    $nipKinds[] = 'integer';
                } else {
                    $nipKinds[] = 'mixed';
                }
            }
            foreach ($forbiddenSegKeys as $fk) {
                if (array_key_exists($fk, $seg)) {
                    $invalid[] = 'flight_segment_'.$i.'_forbidden_'.$fk;
                    if ($fk === 'CabinCode') {
                        $wireFsHasCabinCode = true;
                    } elseif ($fk === 'ClassOfService') {
                        $wireFsHasClassOfService = true;
                    } elseif ($fk === 'FareBasisCode') {
                        $wireFsHasFareBasisCode = true;
                    } elseif ($fk === 'Number') {
                        $wireFsHasNumber = true;
                    }
                }
            }
            $rbd = trim((string) ($seg['ResBookDesigCode'] ?? ''));
            if ($rbd === '') {
                $invalid[] = 'flight_segment_'.$i.'_missing_res_book_desig_code';
                $wireFsHasResBookDesigCode = false;
            }
            $dep = trim((string) ($seg['DepartureDateTime'] ?? ''));
            if ($dep === '') {
                $invalid[] = 'flight_segment_'.$i.'_missing_departure_datetime';
            }
            $mkt = is_array($seg['MarketingAirline'] ?? null) ? $seg['MarketingAirline'] : [];
            $ac = strtoupper(trim((string) ($mkt['Code'] ?? '')));
            $fn = trim((string) ($seg['FlightNumber'] ?? $mkt['FlightNumber'] ?? ''));
            if ($ac === '' || $fn === '') {
                $invalid[] = 'flight_segment_'.$i.'_missing_airline_or_flight_number';
            }
        }

        $wireFlightSegmentNipType = 'missing';
        $wireFlightSegmentNipValid = false;
        if ($segCount > 0 && $nipKinds !== []) {
            $uniqNip = array_values(array_unique($nipKinds));
            $wireFlightSegmentNipType = count($uniqNip) > 1 ? 'mixed' : $uniqNip[0];
            $wireFlightSegmentNipValid = $wireFlightSegmentNipType === 'string';
        }
        if ($segCount > 0 && ! $wireFlightSegmentNipValid) {
            $invalid[] = 'flight_segment_number_in_party_not_all_strings';
        }

        $pp = is_array($cpnr['PostProcessing'] ?? null) ? $cpnr['PostProcessing'] : [];
        $hasPostProcessing = $pp !== [];
        $wirePostProcessingHasEndTransactionRq = array_key_exists('EndTransactionRQ', $pp);
        $etBlock = is_array($pp['EndTransaction'] ?? null) ? $pp['EndTransaction'] : [];
        $wirePostProcessingHasEndTransaction = $etBlock !== [];
        $srcFromEt = is_array($etBlock['Source'] ?? null) ? $etBlock['Source'] : [];
        $hasReceivedFrom = trim((string) ($srcFromEt['ReceivedFrom'] ?? '')) !== '';
        if ($wirePostProcessingHasEndTransaction && ! $hasReceivedFrom) {
            $invalid[] = 'missing_PostProcessing_EndTransaction_Source_ReceivedFrom';
        }
        $rdRaw = $pp['RedisplayReservation'] ?? null;
        $wirePostProcessingHasRedisplayReservation = is_array($rdRaw) && $rdRaw !== [];
        if (! $hasPostProcessing) {
            $invalid[] = 'missing_PostProcessing';
        }
        if ($wirePostProcessingHasEndTransactionRq) {
            $invalid[] = 'forbidden_PostProcessing_EndTransactionRQ';
        }
        if (! $wirePostProcessingHasEndTransaction) {
            $invalid[] = 'missing_PostProcessing_EndTransaction';
        }
        if (! $wirePostProcessingHasRedisplayReservation) {
            $invalid[] = 'missing_PostProcessing_RedisplayReservation';
        }

        $hasEndTransaction = $wirePostProcessingHasEndTransaction;

        $hasHaltOnAirPriceError = ($cpnr['haltOnAirPriceError'] ?? null) === true;
        if (! $hasHaltOnAirPriceError) {
            $invalid[] = 'missing_haltOnAirPriceError';
        }

        $hasHaltOnAirBookError = ($cpnr['haltOnAirBookError'] ?? null) === true;

        $wireAirbookHasAirPrice = array_key_exists('AirPrice', $air);
        $wireAirbookHasPriceQuoteInformation = array_key_exists('PriceQuoteInformation', $air);
        $wireAirbookHasFareBreakdownSummary = array_key_exists('OTAFareBreakdownSummary', $air);
        if ($wireAirbookHasAirPrice) {
            $invalid[] = 'forbidden_AirBook_AirPrice';
        }
        if ($wireAirbookHasPriceQuoteInformation) {
            $invalid[] = 'forbidden_AirBook_PriceQuoteInformation';
        }
        if ($wireAirbookHasFareBreakdownSummary) {
            $invalid[] = 'forbidden_AirBook_OTAFareBreakdownSummary';
        }

        $wireAirbookRetryRedisplayEnabled = (bool) config('suppliers.sabre.traditional_cpnr_airbook_retry_redisplay', false)
            || self::isTraditionalPnrAirbookRetryRedisplayCompareStyle((string) ($traditionalComparePayloadStyle ?? ''));
        $rrBlock = is_array($air['RetryRebook'] ?? null) ? $air['RetryRebook'] : [];
        $airBookRedisplay = is_array($air['RedisplayReservation'] ?? null) ? $air['RedisplayReservation'] : [];
        $wireAirbookHasRetryRebook = $rrBlock !== [];
        $wireAirbookHasRedisplayReservation = $airBookRedisplay !== [];

        $rrOptKey = array_key_exists('Option', $rrBlock);
        $wireAirbookRetryRebookOptionType = $this->traditionalPnrAirbookRetryRebookOptionPrimitiveType(
            $rrOptKey,
            $rrOptKey ? ($rrBlock['Option'] ?? null) : null
        );
        $wireAirbookRetryRebookHasOption = $rrOptKey && is_bool($rrBlock['Option'] ?? null) && ($rrBlock['Option'] ?? null) === true;

        $rrNaKey = array_key_exists('NumAttempts', $rrBlock);
        $rrWiKey = array_key_exists('WaitInterval', $rrBlock);
        $rdNaKey = array_key_exists('NumAttempts', $airBookRedisplay);
        $rdWiKey = array_key_exists('WaitInterval', $airBookRedisplay);

        $wireAirbookRetryRebookNumAttemptsType = $this->traditionalPnrAirbookRetryRedisplayScalarPrimitiveType(
            $rrNaKey,
            $rrNaKey ? ($rrBlock['NumAttempts'] ?? null) : null
        );
        $wireAirbookRetryRebookWaitIntervalType = $this->traditionalPnrAirbookRetryRedisplayScalarPrimitiveType(
            $rrWiKey,
            $rrWiKey ? ($rrBlock['WaitInterval'] ?? null) : null
        );
        $wireAirbookRedisplayNumAttemptsType = $this->traditionalPnrAirbookRetryRedisplayScalarPrimitiveType(
            $rdNaKey,
            $rdNaKey ? ($airBookRedisplay['NumAttempts'] ?? null) : null
        );
        $wireAirbookRedisplayWaitIntervalType = $this->traditionalPnrAirbookRetryRedisplayScalarPrimitiveType(
            $rdWiKey,
            $rdWiKey ? ($airBookRedisplay['WaitInterval'] ?? null) : null
        );

        $wireAirbookRetryRebookNumAttemptsPresent = $rrNaKey && is_int($rrBlock['NumAttempts']);
        $wireAirbookRetryRebookWaitIntervalPresent = $rrWiKey && is_int($rrBlock['WaitInterval']);
        $wireAirbookRedisplayNumAttemptsPresent = $rdNaKey && is_int($airBookRedisplay['NumAttempts']);
        $wireAirbookRedisplayWaitIntervalPresent = $rdWiKey && is_int($airBookRedisplay['WaitInterval']);

        $wireAirbookRetryRedisplayNumericContractValid = ! $wireAirbookRetryRedisplayEnabled
            || (
                $wireAirbookHasRetryRebook
                && $wireAirbookHasRedisplayReservation
                && $wireAirbookRetryRebookNumAttemptsPresent
                && $wireAirbookRetryRebookWaitIntervalPresent
                && $wireAirbookRedisplayNumAttemptsPresent
                && $wireAirbookRedisplayWaitIntervalPresent
            );

        $wireAirbookRetryRebookContractValid = ! $wireAirbookRetryRedisplayEnabled
            || (
                $wireAirbookHasRetryRebook
                && $wireAirbookRetryRebookHasOption
                && $wireAirbookRetryRebookNumAttemptsPresent
                && $wireAirbookRetryRebookWaitIntervalPresent
            );

        if ($wireAirbookRetryRedisplayEnabled) {
            if (! $wireAirbookHasRetryRebook) {
                $invalid[] = 'missing_AirBook_RetryRebook';
            }
            if (! $wireAirbookHasRedisplayReservation) {
                $invalid[] = 'missing_AirBook_RedisplayReservation';
            }
            if (! $rrOptKey) {
                $invalid[] = 'missing_AirBook_RetryRebook_Option';
            } elseif (! is_bool($rrBlock['Option'] ?? null) || ($rrBlock['Option'] ?? null) !== true) {
                $invalid[] = 'AirBook_RetryRebook_Option_invalid';
            }
            if (! $rrNaKey) {
                $invalid[] = 'missing_AirBook_RetryRebook_NumAttempts';
            } elseif (! is_int($rrBlock['NumAttempts'])) {
                $invalid[] = 'AirBook_RetryRebook_NumAttempts_not_integer';
            }
            if (! $rrWiKey) {
                $invalid[] = 'missing_AirBook_RetryRebook_WaitInterval';
            } elseif (! is_int($rrBlock['WaitInterval'])) {
                $invalid[] = 'AirBook_RetryRebook_WaitInterval_not_integer';
            }
            if (! $rdNaKey) {
                $invalid[] = 'missing_AirBook_RedisplayReservation_NumAttempts';
            } elseif (! is_int($airBookRedisplay['NumAttempts'])) {
                $invalid[] = 'AirBook_RedisplayReservation_NumAttempts_not_integer';
            }
            if (! $rdWiKey) {
                $invalid[] = 'missing_AirBook_RedisplayReservation_WaitInterval';
            } elseif (! is_int($airBookRedisplay['WaitInterval'])) {
                $invalid[] = 'AirBook_RedisplayReservation_WaitInterval_not_integer';
            }
        } else {
            if ($wireAirbookHasRetryRebook) {
                $invalid[] = 'forbidden_AirBook_RetryRebook_when_retry_redisplay_disabled';
            }
            if ($wireAirbookHasRedisplayReservation) {
                $invalid[] = 'forbidden_AirBook_RedisplayReservation_when_retry_redisplay_disabled';
            }
        }

        $rootApRaw = $cpnr['AirPrice'] ?? null;
        $wireRootAirPriceType = 'missing';
        $wireRootAirPriceCount = 0;
        $hasRootAirPriceRetain = false;
        $wireAirPriceHasOptionalQualifiers = false;
        $wireAirPriceHasPricingQualifiers = false;
        $wireAirPricePassengerTypeCount = 0;
        $wireAirPricePassengerTypeCodesSanitized = [];
        $wireAirPricePassengerTypeQuantitiesAreStrings = false;
        $wireAirPricePassengerTypeContractValid = true;
        $wireIatiAirPricePassengerTypeDeltaClosed = false;
        $pqFirstForContract = [];
        $wireAirpriceHasValidatingCarrier = false;
        $wireAirpriceHasFareBasis = false;
        $wireAirpriceValidatingCarriersSanitized = [];
        $wireAirpriceValidatingCarrierInvalidPointer = null;
        $wireAirPriceHasForbiddenMessage = false;
        $wireAirpriceFlightQualifiersValidatingCarrierPresent = false;
        $wireAirpricePricingQualifiersForbiddenKeys = [];
        if (! array_key_exists('AirPrice', $cpnr) || $rootApRaw === null) {
            $wireRootAirPriceType = 'missing';
        } elseif (is_array($rootApRaw)) {
            if (array_is_list($rootApRaw)) {
                $wireRootAirPriceType = 'array';
                $wireRootAirPriceCount = count($rootApRaw);
                foreach ($rootApRaw as $apRow) {
                    if (! is_array($apRow)) {
                        continue;
                    }
                    $priRow = is_array($apRow['PriceRequestInformation'] ?? null) ? $apRow['PriceRequestInformation'] : [];
                    if (isset($priRow['Retain']) && $priRow['Retain'] === true) {
                        $hasRootAirPriceRetain = true;
                        break;
                    }
                }
            } else {
                $wireRootAirPriceType = 'object';
                $wireRootAirPriceCount = 1;
                $priRoot = is_array($rootApRaw['PriceRequestInformation'] ?? null) ? $rootApRaw['PriceRequestInformation'] : [];
                $hasRootAirPriceRetain = isset($priRoot['Retain']) && $priRoot['Retain'] === true;
            }
        } else {
            $wireRootAirPriceType = 'object';
        }

        if ($wireRootAirPriceType !== 'array') {
            $invalid[] = 'root_AirPrice_must_be_array';
        }
        if ($wireRootAirPriceType === 'array' && $wireRootAirPriceCount < 1) {
            $invalid[] = 'root_AirPrice_empty_array';
        }
        if (! $hasRootAirPriceRetain) {
            $invalid[] = 'missing_AirPrice_PriceRequestInformation_Retain';
        }

        $wireRootAirPriceRetainPresent = $hasRootAirPriceRetain;

        if ($wireRootAirPriceType === 'array' && $wireRootAirPriceCount >= 1 && is_array($rootApRaw)) {
            $apFirst = is_array($rootApRaw[0] ?? null) ? $rootApRaw[0] : [];
            $wireAirPriceHasForbiddenMessage = array_key_exists('message', $apFirst) || array_key_exists('Message', $apFirst);
            if ($wireAirPriceHasForbiddenMessage) {
                $invalid[] = 'forbidden_AirPrice_message';
            }
            $priFirst = is_array($apFirst['PriceRequestInformation'] ?? null) ? $apFirst['PriceRequestInformation'] : [];
            $oqFirst = is_array($priFirst['OptionalQualifiers'] ?? null) ? $priFirst['OptionalQualifiers'] : [];
            $wireAirPriceHasOptionalQualifiers = $oqFirst !== [];
            $pqFirstForContract = is_array($oqFirst['PricingQualifiers'] ?? null) ? $oqFirst['PricingQualifiers'] : [];
            $wireAirPriceHasPricingQualifiers = $pqFirstForContract !== [];
            $vcFromOq = $this->traditionalPnrExtractValidatingCarrierCodeFromAirPriceOptionalQualifiers($oqFirst);
            $wireAirpriceFlightQualifiersValidatingCarrierPresent = $vcFromOq !== null
                && data_get($oqFirst, 'FlightQualifiers.VendorPrefs.Airline.Code') !== null;
            $wireAirpriceValidatingCarriersSanitized = $vcFromOq !== null ? [$vcFromOq] : [];
            $wireAirpriceHasValidatingCarrier = $wireAirpriceValidatingCarriersSanitized !== [];
            $wireAirpriceHasFareBasis = $this->traditionalPnrAirPriceQualifiersHaveFareBasis($pqFirstForContract);
            if (array_key_exists('ValidatingCarrier', $pqFirstForContract) && $wireAirpriceValidatingCarriersSanitized === []) {
                $invalid[] = 'air_price_validating_carrier_wire_shape_invalid';
                $wireAirpriceValidatingCarrierInvalidPointer = 'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.ValidatingCarrier';
            }
            if (self::isIatiLikeCpnrV24GdsWireStyle((string) ($traditionalComparePayloadStyle ?? ''))) {
                $allowedPqKeys = [
                    'PassengerType', 'Brand', 'ItineraryOptions', 'CommandPricing',
                    'CurrencyCode', 'SpecificPenalty', 'AlternateCurrency',
                ];
                foreach (array_keys($pqFirstForContract) as $pqKey) {
                    if (is_string($pqKey) && ! in_array($pqKey, $allowedPqKeys, true)) {
                        $wireAirpricePricingQualifiersForbiddenKeys[] = $pqKey;
                    }
                }
                if ($wireAirpricePricingQualifiersForbiddenKeys !== []) {
                    $invalid[] = 'iati_airprice_pricing_qualifiers_forbidden_keys';
                }
            }
            $ptRaw = $pqFirstForContract['PassengerType'] ?? null;
            $ptRows = [];
            if (is_array($ptRaw)) {
                $ptRows = array_is_list($ptRaw) ? $ptRaw : [$ptRaw];
            }
            $typeTokPt = [];
            $qtyAllString = true;
            $codesAllowed = true;
            foreach ($ptRows as $ptr) {
                if (! is_array($ptr)) {
                    continue;
                }
                $codePt = strtoupper(trim((string) ($ptr['Code'] ?? '')));
                if ($codePt === '') {
                    continue;
                }
                $wireAirPricePassengerTypeCount++;
                $typeTokPt[$codePt] = true;
                $qRaw = $ptr['Quantity'] ?? null;
                if (! is_string($qRaw)) {
                    $qtyAllString = false;
                }
                if (! in_array($codePt, ['ADT', 'CNN', 'INF'], true)) {
                    $codesAllowed = false;
                }
            }
            $wireAirPricePassengerTypeCodesSanitized = array_slice(array_keys($typeTokPt), 0, 8);
            sort($wireAirPricePassengerTypeCodesSanitized);
            $wireAirPricePassengerTypeQuantitiesAreStrings = $wireAirPricePassengerTypeCount >= 1 && $qtyAllString;
            $needsPaxPt = $wireCustomerPersonNameCount >= 1;
            $ptStructOk = isset($pqFirstForContract['PassengerType'])
                && is_array($pqFirstForContract['PassengerType'])
                && $wireAirPricePassengerTypeCount >= 1;
            $wireAirPricePassengerTypeContractValid = ! $needsPaxPt || (
                $wireAirPriceHasOptionalQualifiers
                && $wireAirPriceHasPricingQualifiers
                && $ptStructOk
                && $wireAirPricePassengerTypeQuantitiesAreStrings
                && $codesAllowed
            );
            $wireIatiAirPricePassengerTypeDeltaClosed = $wireAirPricePassengerTypeContractValid;
            if ($needsPaxPt) {
                if (! $wireAirPriceHasOptionalQualifiers) {
                    $invalid[] = 'missing_air_price_optional_qualifiers';
                }
                if (! $wireAirPriceHasPricingQualifiers || ! isset($pqFirstForContract['PassengerType'])) {
                    $invalid[] = 'missing_air_price_pricing_qualifiers_passenger_type';
                }
                if ($wireAirPricePassengerTypeCount < 1) {
                    $invalid[] = 'missing_air_price_passenger_type_rows';
                }
                if ($wireAirPricePassengerTypeCount >= 1 && ! $wireAirPricePassengerTypeQuantitiesAreStrings) {
                    $invalid[] = 'air_price_passenger_type_quantity_not_string';
                }
                if ($wireAirPricePassengerTypeCount >= 1 && ! $codesAllowed) {
                    $invalid[] = 'air_price_passenger_type_invalid_code';
                }
            }
        }
        if ($wireCustomerPersonNameCount >= 1 && (
            $wireRootAirPriceType !== 'array' || $wireRootAirPriceCount < 1 || ! $hasRootAirPriceRetain
        )) {
            $wireAirPricePassengerTypeContractValid = false;
            $wireIatiAirPricePassengerTypeDeltaClosed = false;
        }

        if ($isGdsV25) {
            if (! $wireAirpriceHasFareBasis && ! $wireFsHasFareBasisCode) {
                $invalid[] = 'fare_basis_missing_from_payload';
            }
            if (! $wireAirpriceHasValidatingCarrier) {
                $invalid[] = 'validating_carrier_missing_from_payload';
            }
        }

        $hasEmailBlock = false;
        $emRaw = $ci['Email'] ?? null;
        $wireCustomerEmailType = 'missing';
        $wireCustomerEmailCount = 0;
        $wireCustomerEmailHasType = false;
        $wireCustomerEmailTypeValuesSanitized = [];
        $wireCustomerEmailTypeValid = false;
        if ($emRaw === null) {
            $wireCustomerEmailType = 'missing';
        } elseif (is_array($emRaw)) {
            $wireCustomerEmailType = array_is_list($emRaw) ? 'array' : 'object';
            $emailRows = array_is_list($emRaw) ? $emRaw : [$emRaw];
            $typeTok = [];
            $allAddressRowsHaveNonEmptyType = true;
            $allAddressRowsAreTypeTo = true;
            $anyAddress = false;
            foreach ($emailRows as $er) {
                if (! is_array($er)) {
                    continue;
                }
                $addr = trim((string) ($er['Address'] ?? ''));
                if ($addr === '') {
                    continue;
                }
                $anyAddress = true;
                $wireCustomerEmailCount++;
                $tp = trim((string) ($er['Type'] ?? ''));
                if ($tp === '') {
                    $allAddressRowsHaveNonEmptyType = false;
                    $allAddressRowsAreTypeTo = false;
                } else {
                    $typeTok[strtoupper($tp)] = true;
                    if ($tp !== 'TO') {
                        $allAddressRowsAreTypeTo = false;
                    }
                }
            }
            $hasEmailBlock = $anyAddress;
            $wireCustomerEmailTypeValuesSanitized = array_slice(array_keys($typeTok), 0, 8);
            sort($wireCustomerEmailTypeValuesSanitized);
            $wireCustomerEmailHasType = $anyAddress && $allAddressRowsHaveNonEmptyType;
            $wireCustomerEmailTypeValid = $hasEmailBlock
                && $wireCustomerEmailType === 'array'
                && $wireCustomerEmailCount >= 1
                && $allAddressRowsAreTypeTo;
        } else {
            $wireCustomerEmailType = 'invalid';
        }
        if (! $hasEmailBlock) {
            $invalid[] = 'missing_TravelItineraryAddInfo_CustomerInfo_Email';
        }
        if ($hasEmailBlock) {
            if ($wireCustomerEmailType !== 'array') {
                $invalid[] = 'customer_info_Email_must_be_array';
            }
            if (! $wireCustomerEmailTypeValid) {
                $invalid[] = 'customer_info_Email_Type_must_be_TO';
            }
        }

        $wireCustomerInfoHasContactNumbers = $hasContactNumbers;
        $wireCustomerInfoHasEmail = $hasEmailBlock;

        $agencyInfoBlock = is_array($tia['AgencyInfo'] ?? null) ? $tia['AgencyInfo'] : [];
        $wireAgencyInfoPresent = $agencyInfoBlock !== [];
        $wireAgencyInfoHasTelephone = array_key_exists('Telephone', $agencyInfoBlock);
        if ($wireAgencyInfoHasTelephone) {
            $invalid[] = 'forbidden_TravelItineraryAddInfo_AgencyInfo_Telephone';
        }

        $hasTargetCity = isset($cpnr['targetCity']) && trim((string) $cpnr['targetCity']) !== '';

        $bookingMode = (string) config('suppliers.sabre.booking_mode', 'pnr_only');
        $pnrOnlyWire = $bookingMode === 'pnr_only' || $isGdsV25;
        if ($ticketingCfg && ! $pnrOnlyWire) {
            $invalid[] = 'ticketing_enabled_in_config';
        }
        $manualMarker = $this->traditionalPnrWireHasManualTicketingMarker($cpnr);
        if (! $manualMarker) {
            $invalid[] = $pnrOnlyWire ? 'pnr_time_limit_marker_missing' : 'manual_ticketing_marker_missing';
        }

        $remarkRows = $this->traditionalPnrExtractAddRemarkRows($cpnr);
        $wireRemarksCount = count($remarkRows);
        $remarkTypeTokens = [];
        $wireHasGeneralRemark = false;
        $remarkMissingType = false;
        $remarkBadEnum = false;
        foreach ($remarkRows as $r) {
            if (! is_array($r)) {
                continue;
            }
            $tp = isset($r['Type']) ? trim((string) $r['Type']) : '';
            if ($tp === '') {
                $remarkMissingType = true;

                continue;
            }
            $remarkTypeTokens[$tp] = true;
            if ($tp === 'General') {
                $wireHasGeneralRemark = true;
            }
            if (! in_array($tp, self::TRADITIONAL_CPNR_ADD_REMARK_TYPE_ENUM, true)) {
                $remarkBadEnum = true;
            }
        }
        $wireRemarkTypeValuesSanitized = array_slice(array_keys($remarkTypeTokens), 0, 16);
        sort($wireRemarkTypeValuesSanitized);
        $wireRemarkTypeEnumValid = $wireRemarksCount === 0 || (! $remarkMissingType && ! $remarkBadEnum);
        if ($remarkMissingType) {
            $invalid[] = 'missing_remark_Type';
        }
        if ($remarkBadEnum) {
            $invalid[] = 'invalid_remark_Type_enum';
        }

        $srDetails = is_array($cpnr['SpecialReqDetails'] ?? null) ? $cpnr['SpecialReqDetails'] : [];
        $ssRaw = $srDetails['SpecialService'] ?? null;
        $wireSpecialServicePresent = is_array($ssRaw) && $ssRaw !== [];
        $wireSpecialServiceHasService = $wireSpecialServicePresent && array_key_exists('Service', $ssRaw);
        $addRm = $srDetails['AddRemark'] ?? null;
        $wireAddRemarkPresent = is_array($addRm) && $addRm !== [];
        $wireSpecialServiceOmitted = ! $wireSpecialServicePresent;
        if ($wireSpecialServiceHasService) {
            $invalid[] = 'forbidden_SpecialReqDetails_SpecialService_Service';
        }

        $contractValid = $invalid === [];

        $iatiStructural = SabreTraditionalCpnrIatiWireStructureDiagnostic::analyze($cpnr);
        $invOta = is_array($iatiStructural['cpnr_key_name_inventory']['ota'] ?? null)
            ? $iatiStructural['cpnr_key_name_inventory']['ota']
            : [];
        $invIati = is_array($iatiStructural['cpnr_key_name_inventory']['iati_template'] ?? null)
            ? $iatiStructural['cpnr_key_name_inventory']['iati_template']
            : [];
        $personOta = is_array($invOta['TravelItineraryAddInfo.CustomerInfo.PersonName'] ?? null) ? $invOta['TravelItineraryAddInfo.CustomerInfo.PersonName'] : [];
        $personIati = is_array($invIati['TravelItineraryAddInfo.CustomerInfo.PersonName'] ?? null) ? $invIati['TravelItineraryAddInfo.CustomerInfo.PersonName'] : [];
        $emailOta = is_array($invOta['TravelItineraryAddInfo.CustomerInfo.Email'] ?? null) ? $invOta['TravelItineraryAddInfo.CustomerInfo.Email'] : [];
        $emailIati = is_array($invIati['TravelItineraryAddInfo.CustomerInfo.Email'] ?? null) ? $invIati['TravelItineraryAddInfo.CustomerInfo.Email'] : [];
        $airPriceOta = is_array($invOta['AirPrice'] ?? null) ? $invOta['AirPrice'] : [];
        $airPriceIati = is_array($invIati['AirPrice'] ?? null) ? $invIati['AirPrice'] : [];

        $sellCtx = $this->traditionalPnrSummarizeSegmentSellContext($segs);
        $offerSnapshot = $this->traditionalPnrResolveOfferSnapshotFromBookingMeta($bookingMetaForDiagnostics);
        $offerDiag = $this->traditionalPnrSummarizeOfferFreshnessDiagnostics($offerSnapshot, $bookingMetaForDiagnostics);

        $styleIsIati = self::isIatiLikeCpnrV24GdsWireStyle((string) ($traditionalComparePayloadStyle ?? ''))
            || (is_scalar($cpnr['version'] ?? null) && (string) $cpnr['version'] === '2.4.0');
        $iatiDiag = $styleIsIati
            ? $this->traditionalPnrIatiLikeSsrDiagnosticFlags($cpnr)
            : [
                'is_iati_like_cpnr_style' => false,
                'endpoint_version' => is_scalar($cpnr['version'] ?? null) ? (string) $cpnr['version'] : null,
                'docs_block_present' => false,
                'ctce_block_present' => false,
                'ctcm_block_present' => false,
                'secure_flight_present' => false,
                'brand_code_present' => $wireAirPriceHasOptionalQualifiers && $wireAirPriceHasPricingQualifiers
                    && is_array(data_get($pqFirstForContract, 'Brand')) && data_get($pqFirstForContract, 'Brand') !== [],
                'passenger_type_pricing_present' => $wireAirPricePassengerTypeCount > 0,
            ];

        $wireHaltOnStatusCodes = $this->extractHaltOnStatusCodesFromCpnr($cpnr);
        $wireHaltOnStatusNnOmitted = $wireHaltOnStatusCodes !== []
            && ! in_array('NN', $wireHaltOnStatusCodes, true)
            && ! in_array('WN', $wireHaltOnStatusCodes, true);

        return array_merge([
            'wire_root_keys' => $wireRootKeys,
            'wire_has_create_passenger_name_record_rq' => $hasCpnrRoot,
            'wire_has_travel_itinerary_add_info' => $hasTia,
            'wire_has_customer_info' => $hasCi,
            'wire_has_person_name' => $hasPersonName,
            'wire_customer_person_name_type' => $wireCustomerPersonNameType,
            'wire_customer_person_name_count' => $wireCustomerPersonNameCount,
            'wire_customer_person_name_array_valid' => $wireCustomerPersonNameArrayValid,
            'wire_has_contact_numbers' => $hasContactNumbers,
            'wire_has_email' => $hasEmailBlock,
            'wire_customer_email_type' => $wireCustomerEmailType,
            'wire_customer_email_count' => $wireCustomerEmailCount,
            'wire_customer_email_has_type' => $wireCustomerEmailHasType,
            'wire_customer_email_type_values_sanitized' => $wireCustomerEmailTypeValuesSanitized,
            'wire_customer_email_type_valid' => $wireCustomerEmailTypeValid,
            'wire_customer_info_has_contact_numbers' => $wireCustomerInfoHasContactNumbers,
            'wire_customer_info_has_email' => $wireCustomerInfoHasEmail,
            'wire_agency_info_present' => $wireAgencyInfoPresent,
            'wire_agency_info_has_telephone' => $wireAgencyInfoHasTelephone,
            'wire_has_air_book' => $hasAirBook,
            'wire_has_flight_segment' => $hasFlightSeg,
            'wire_has_post_processing' => $hasPostProcessing,
            'wire_post_processing_has_end_transaction' => $wirePostProcessingHasEndTransaction,
            'wire_post_processing_has_end_transaction_rq' => $wirePostProcessingHasEndTransactionRq,
            'wire_post_processing_has_redisplay_reservation' => $wirePostProcessingHasRedisplayReservation,
            'wire_has_end_transaction' => $hasEndTransaction,
            'wire_has_received_from' => $hasReceivedFrom,
            'wire_has_halt_on_air_price_error' => $hasHaltOnAirPriceError,
            'wire_has_halt_on_air_book_error' => $hasHaltOnAirBookError,
            'wire_halt_on_status_codes_sanitized' => $wireHaltOnStatusCodes,
            'wire_halt_on_status_nn_omitted' => $wireHaltOnStatusNnOmitted,
            'wire_airbook_has_air_price' => $wireAirbookHasAirPrice,
            'wire_airbook_has_price_quote_information' => $wireAirbookHasPriceQuoteInformation,
            'wire_airbook_has_fare_breakdown_summary' => $wireAirbookHasFareBreakdownSummary,
            'wire_airbook_retry_redisplay_enabled' => $wireAirbookRetryRedisplayEnabled,
            'wire_airbook_has_retry_rebook' => $wireAirbookHasRetryRebook,
            'wire_airbook_has_redisplay_reservation' => $wireAirbookHasRedisplayReservation,
            'wire_airbook_retry_rebook_num_attempts_present' => $wireAirbookRetryRebookNumAttemptsPresent,
            'wire_airbook_retry_rebook_wait_interval_present' => $wireAirbookRetryRebookWaitIntervalPresent,
            'wire_airbook_redisplay_num_attempts_present' => $wireAirbookRedisplayNumAttemptsPresent,
            'wire_airbook_redisplay_wait_interval_present' => $wireAirbookRedisplayWaitIntervalPresent,
            'wire_airbook_retry_rebook_num_attempts_type' => $wireAirbookRetryRebookNumAttemptsType,
            'wire_airbook_retry_rebook_wait_interval_type' => $wireAirbookRetryRebookWaitIntervalType,
            'wire_airbook_redisplay_num_attempts_type' => $wireAirbookRedisplayNumAttemptsType,
            'wire_airbook_redisplay_wait_interval_type' => $wireAirbookRedisplayWaitIntervalType,
            'wire_airbook_retry_rebook_has_option' => $wireAirbookRetryRebookHasOption,
            'wire_airbook_retry_rebook_option_type' => $wireAirbookRetryRebookOptionType,
            'wire_airbook_retry_rebook_contract_valid' => $wireAirbookRetryRebookContractValid,
            'wire_airbook_retry_redisplay_numeric_contract_valid' => $wireAirbookRetryRedisplayNumericContractValid,
            'wire_has_air_price' => $hasRootAirPriceRetain,
            'wire_has_root_air_price' => $hasRootAirPriceRetain,
            'wire_root_air_price_type' => $wireRootAirPriceType,
            'wire_root_air_price_count' => $wireRootAirPriceCount,
            'wire_root_air_price_retain_present' => $wireRootAirPriceRetainPresent,
            'wire_air_price_has_optional_qualifiers' => $wireAirPriceHasOptionalQualifiers,
            'wire_air_price_has_pricing_qualifiers' => $wireAirPriceHasPricingQualifiers,
            'wire_air_price_passenger_type_count' => $wireAirPricePassengerTypeCount,
            'wire_air_price_passenger_type_codes_sanitized' => $wireAirPricePassengerTypeCodesSanitized,
            'wire_air_price_passenger_type_quantities_are_strings' => $wireAirPricePassengerTypeQuantitiesAreStrings,
            'wire_air_price_passenger_type_contract_valid' => $wireAirPricePassengerTypeContractValid,
            'wire_air_price_has_forbidden_message' => $wireAirPriceHasForbiddenMessage,
            'wire_iati_airprice_passenger_type_delta_closed' => $wireIatiAirPricePassengerTypeDeltaClosed,
            'wire_airprice_has_validating_carrier' => $wireAirpriceHasValidatingCarrier,
            'wire_airprice_has_fare_basis' => $wireAirpriceHasFareBasis,
            'wire_airprice_validating_carriers_sanitized' => $wireAirpriceValidatingCarriersSanitized,
            'wire_airprice_validating_carrier_invalid_pointer' => $wireAirpriceValidatingCarrierInvalidPointer,
            'wire_airprice_flight_qualifiers_validating_carrier_present' => $wireAirpriceFlightQualifiersValidatingCarrierPresent,
            'wire_airprice_pricing_qualifiers_forbidden_keys' => array_slice($wireAirpricePricingQualifiersForbiddenKeys, 0, 12),
            'wire_has_target_city' => $hasTargetCity,
            'wire_ticketing_enabled' => $ticketingCfg,
            'wire_traditional_manual_ticketing_marker_present' => $manualMarker,
            'wire_remarks_count' => $wireRemarksCount,
            'wire_remark_type_values_sanitized' => $wireRemarkTypeValuesSanitized,
            'wire_remark_type_enum_valid' => $wireRemarkTypeEnumValid,
            'wire_has_general_remark' => $wireHasGeneralRemark,
            'wire_special_service_present' => $wireSpecialServicePresent,
            'wire_special_service_has_service' => $wireSpecialServiceHasService,
            'wire_special_service_omitted' => $wireSpecialServiceOmitted,
            'wire_add_remark_present' => $wireAddRemarkPresent,
            'wire_passenger_count' => $paxCount,
            'wire_segment_count' => $segCount,
            'wire_flight_segment_has_cabin_code' => $wireFsHasCabinCode,
            'wire_flight_segment_has_class_of_service' => $wireFsHasClassOfService,
            'wire_flight_segment_has_fare_basis_code' => $wireFsHasFareBasisCode,
            'wire_flight_segment_has_number' => $wireFsHasNumber,
            'wire_flight_segment_has_res_book_desig_code' => $wireFsHasResBookDesigCode,
            'wire_flight_segment_number_in_party_type' => $wireFlightSegmentNipType,
            'wire_flight_segment_number_in_party_valid' => $wireFlightSegmentNipValid,
            'wire_traditional_pnr_contract_valid' => $contractValid,
            'wire_invalid_traditional_pnr_contract_keys' => array_values(array_unique($invalid)),
            'wire_iati_reference_source' => SabreTraditionalCpnrIatiWireStructureDiagnostic::IATI_REFERENCE_SOURCE,
            'wire_iati_paths_only_in_iati_template_count' => count($iatiStructural['key_paths_only_in_iati_template'] ?? []),
            'wire_iati_paths_only_in_iati_template_head' => array_slice($iatiStructural['key_paths_only_in_iati_template'] ?? [], 0, 24),
            'wire_iati_paths_only_in_ota_wire_count' => count($iatiStructural['key_paths_only_in_ota_wire'] ?? []),
            'wire_iati_paths_only_in_ota_wire_head' => array_slice($iatiStructural['key_paths_only_in_ota_wire'] ?? [], 0, 16),
            'wire_iati_cpnr_version_ota' => $iatiStructural['cpnr_version']['ota_wire_value'] ?? null,
            'wire_iati_cpnr_version_iati_template' => $iatiStructural['cpnr_version']['iati_operational_template'] ?? null,
            'wire_iati_person_name_row_key_union_ota' => $personOta['row_key_union_sorted'] ?? [],
            'wire_iati_person_name_row_key_union_iati' => $personIati['row_key_union_sorted'] ?? [],
            'wire_iati_email_row_key_union_ota' => $emailOta['row_key_union_sorted'] ?? [],
            'wire_iati_email_row_key_union_iati' => $emailIati['row_key_union_sorted'] ?? [],
            'wire_iati_airprice_pricing_qualifiers_keys_ota' => $airPriceOta['optional_qualifiers_pricing_qualifiers_keys'] ?? [],
            'wire_iati_airprice_pricing_qualifiers_keys_iati' => $airPriceIati['optional_qualifiers_pricing_qualifiers_keys'] ?? [],
            'payload_style' => $traditionalComparePayloadStyle ?? self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
            'manual_ticketing_marker_present' => $manualMarker,
            'ticketing_time_limit_present' => $manualMarker,
            'pnr_time_limit_marker_present' => $manualMarker,
            'fare_basis_present' => $wireAirpriceHasFareBasis || $wireFsHasFareBasisCode,
            'validating_carrier_present' => $wireAirpriceHasValidatingCarrier,
            'booking_class_present' => $wireFsHasResBookDesigCode,
            'ticket_issuance_attempted' => false,
            'airticket_attempted' => false,
            'ticketing_enabled_required_for_pnr' => false,
            'ticket_issuance_disabled_ok' => true,
        ], $sellCtx, $offerDiag, $iatiDiag);
    }

    /**
     * Map traditional CPNR contract invalid keys to customer-safe reason tokens (no config leakage).
     *
     * @param  list<string>  $invalidKeys
     */
    public function buildTraditionalPnrPayloadValidationCustomerSafeMessage(array $invalidKeys): string
    {
        $map = [
            'missing_manual_ticketing_marker' => 'manual_ticketing_marker_missing',
            'pnr_time_limit_marker_missing' => 'pnr_time_limit_marker_missing',
            'ticketing_enabled_in_config' => 'manual_ticketing_marker_missing',
        ];
        $tokens = [];
        foreach ($invalidKeys as $key) {
            $k = trim((string) $key);
            if ($k === '' || $k === 'ticketing_enabled_in_config') {
                continue;
            }
            $tokens[] = $map[$k] ?? $k;
        }
        $tokens = array_values(array_unique($tokens));
        if ($tokens === []) {
            return 'manual_ticketing_marker_missing';
        }

        return implode(', ', array_slice($tokens, 0, 8)).', ticket_issuance_disabled_ok=true';
    }

    /**
     * @param  array<string, mixed>  $pricingQualifiers
     */
    protected function traditionalPnrAirPriceQualifiersHaveFareBasis(array $pricingQualifiers): bool
    {
        $commandPricing = $pricingQualifiers['CommandPricing'] ?? null;
        $rows = [];
        if (is_array($commandPricing)) {
            $rows = array_is_list($commandPricing) ? $commandPricing : [$commandPricing];
        }
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $fb = $row['FareBasis'] ?? null;
            if (is_array($fb) && trim((string) ($fb['Code'] ?? '')) !== '') {
                return true;
            }
            if (is_string($fb) && trim($fb) !== '') {
                return true;
            }
        }

        $brand = $pricingQualifiers['Brand'] ?? null;
        if (is_array($brand) && trim((string) ($brand['Value'] ?? $brand['Code'] ?? '')) !== '') {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $customerInfo  {@code TravelItineraryAddInfo.CustomerInfo}
     * @return list<array<string, mixed>>
     */
    protected function traditionalPnrExtractContactNumberRows(array $customerInfo): array
    {
        $cn = $customerInfo['ContactNumbers'] ?? null;
        if (! is_array($cn)) {
            return [];
        }
        $raw = $cn['ContactNumber'] ?? $cn['contactNumber'] ?? null;
        if ($raw === null) {
            return [];
        }
        if (is_array($raw) && array_is_list($raw)) {
            $out = [];
            foreach ($raw as $row) {
                if (is_array($row)) {
                    $out[] = $row;
                }
            }

            return $out;
        }
        if (is_array($raw)) {
            return [$raw];
        }

        return [];
    }

    /**
     * B61A: Classify {@code AirBook.RetryRebook} / {@code AirBook.RedisplayReservation} scalar fields for wire diagnostics (types only).
     *
     * @return 'missing'|'integer'|'string'
     */
    protected function traditionalPnrAirbookRetryRedisplayScalarPrimitiveType(bool $keyExists, mixed $value): string
    {
        if (! $keyExists) {
            return 'missing';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_string($value)) {
            return 'string';
        }

        return 'string';
    }

    /**
     * B61B: Classify {@code AirBook.RetryRebook.Option} for wire diagnostics (types only).
     *
     * @return 'missing'|'boolean'|'string'|'integer'
     */
    protected function traditionalPnrAirbookRetryRebookOptionPrimitiveType(bool $keyExists, mixed $value): string
    {
        if (! $keyExists) {
            return 'missing';
        }
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_string($value)) {
            return 'string';
        }
        if (is_int($value)) {
            return 'integer';
        }

        return 'string';
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function traditionalPnrExtractFlightSegments(array $cpnr): array
    {
        $air = is_array($cpnr['AirBook'] ?? null) ? $cpnr['AirBook'] : [];
        $odi = is_array($air['OriginDestinationInformation'] ?? null) ? $air['OriginDestinationInformation'] : [];
        $fs = $odi['FlightSegment'] ?? null;
        if (! is_array($fs)) {
            return [];
        }
        if (array_is_list($fs)) {
            $out = [];
            foreach ($fs as $row) {
                if (is_array($row)) {
                    $out[] = $row;
                }
            }

            return $out;
        }

        return [$fs];
    }

    /**
     * B75: Safe Passenger Records AirBook sell rows for EnhancedAirBook 0411 / FLIGHT NOOP triage (no raw wire dump; itinerary fields only).
     *
     * @param  array<string, mixed>  $wire  Traditional CPNR wire (after OTA key strip)
     * @param  list<array<string, mixed>>  $snapshotSegmentRows  Segments from normalized offer (fare_basis_code snapshot only)
     * @return array{
     *   segment_count: int,
     *   segments: list<array<string, mixed>>,
     *   chronology_gaps: list<array<string, mixed>>,
     *   route_continuity: bool,
     *   route_continuity_break_after_index: ?int
     * }
     */
    public function traditionalPnrAirBookSegmentSellDiagnostics(array $wire, array $snapshotSegmentRows): array
    {
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];
        $extracted = $this->traditionalPnrExtractFlightSegments($cpnr);
        $rows = [];
        foreach ($extracted as $i => $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $fb = null;
            if (isset($snapshotSegmentRows[$i]) && is_array($snapshotSegmentRows[$i])) {
                $f = trim((string) ($snapshotSegmentRows[$i]['fare_basis_code'] ?? ''));
                $fb = $f !== '' ? $f : null;
            }
            $rows[] = $this->traditionalPnrMapAirBookSegmentSafeRow($seg, $i, $fb);
        }
        $n = count($rows);
        $gaps = [];
        for ($i = 0; $i < $n - 1; $i++) {
            $arr = (string) ($rows[$i]['arrival_datetime'] ?? '');
            $dep = (string) ($rows[$i + 1]['departure_datetime'] ?? '');
            $gapMin = null;
            if ($arr !== '' && $dep !== '') {
                try {
                    $ta = Carbon::parse($arr);
                    $td = Carbon::parse($dep);
                    $gapMin = (int) floor(($td->getTimestamp() - $ta->getTimestamp()) / 60);
                } catch (\Throwable) {
                    $gapMin = null;
                }
            }
            $gaps[] = [
                'after_index' => $i,
                'minutes_connection' => $gapMin,
                'prior_arrival' => $arr !== '' ? $arr : null,
                'next_departure' => $dep !== '' ? $dep : null,
            ];
        }
        $routeOk = true;
        $breakAfter = null;
        for ($i = 0; $i < $n - 1; $i++) {
            $dest = strtoupper(trim((string) ($rows[$i]['destination'] ?? '')));
            $nextO = strtoupper(trim((string) ($rows[$i + 1]['origin'] ?? '')));
            if ($dest !== '' && $nextO !== '' && $dest !== $nextO) {
                $routeOk = false;
                if ($breakAfter === null) {
                    $breakAfter = $i;
                }
            }
        }

        return [
            'segment_count' => $n,
            'segments' => $rows,
            'chronology_gaps' => $gaps,
            'route_continuity' => $routeOk,
            'route_continuity_break_after_index' => $breakAfter,
        ];
    }

    /**
     * E3: Safe Passenger Records create-payload summary for supplier booking attempts (no raw wire bodies or PII).
     *
     * @param  array<string, mixed>  $envelope
     * @param  list<array<string, mixed>>  $offerSnapshotSegments
     * @param  array<string, mixed>  $context  {@code create_endpoint_path}, {@code create_payload_style}, {@code create_segment_source}, optional B65 repair flags
     * @return array<string, mixed>
     */
    public function summarizeCreatePayloadForAttempt(
        array $envelope,
        array $offerSnapshotSegments,
        array $context = [],
    ): array {
        $wire = $this->stripOtaInternalKeysFromBookingWire($envelope);
        $diag = $this->summarizeEnvelopeForDiagnostics($envelope);
        $segSell = $this->traditionalPnrAirBookSegmentSellDiagnostics($wire, array_values($offerSnapshotSegments));
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];
        $wireSegs = $this->traditionalPnrExtractFlightSegments($cpnr);

        $segmentsSummary = [];
        foreach ($segSell['segments'] as $idx => $row) {
            if (! is_array($row)) {
                continue;
            }
            $wireSeg = is_array($wireSegs[$idx] ?? null) ? $wireSegs[$idx] : [];
            $segmentsSummary[] = $this->formatCreateSegmentSummaryRow($row, $wireSeg);
        }

        $pp = is_array($cpnr['PostProcessing'] ?? null) ? $cpnr['PostProcessing'] : [];
        $et = is_array($pp['EndTransaction'] ?? null) ? $pp['EndTransaction'] : [];
        $receivedFromPresent = trim((string) data_get($et, 'Source.ReceivedFrom', '')) !== '';
        $ticketing = is_array(data_get($cpnr, 'TravelItineraryAddInfo.AgencyInfo.Ticketing'))
            ? data_get($cpnr, 'TravelItineraryAddInfo.AgencyInfo.Ticketing')
            : [];
        $ticketingDisabled = (bool) ($envelope['_ota_ticketing_disabled_marker'] ?? true)
            || ! (bool) config('suppliers.sabre.ticketing_enabled', false);
        $postTicketingAction = null;
        if (is_array($ticketing)) {
            $ticketType = trim((string) ($ticketing['TicketType'] ?? ''));
            $shortText = trim((string) ($ticketing['ShortText'] ?? ''));
            if ($ticketType !== '' || $shortText !== '') {
                $postTicketingAction = trim($ticketType.($shortText !== '' ? ':'.$shortText : ''));
            }
        }
        $apRaw = $cpnr['AirPrice'] ?? null;
        $priceQuotePresent = is_array($apRaw) && $apRaw !== [];

        $payloadStyle = trim((string) ($context['create_payload_style'] ?? ''));
        if ($payloadStyle === '') {
            $payloadStyle = trim((string) ($diag['payload_schema'] ?? $diag['payload_style'] ?? ''));
        }

        return array_filter([
            'create_endpoint_path' => trim((string) ($context['create_endpoint_path'] ?? '')) ?: null,
            'create_payload_style' => $payloadStyle !== '' ? $payloadStyle : null,
            'create_segment_count' => (int) ($segSell['segment_count'] ?? 0),
            'create_segments_summary' => $segmentsSummary !== [] ? $segmentsSummary : null,
            'create_passenger_count' => (int) ($diag['wire_passenger_count'] ?? $diag['passenger_count'] ?? 0) ?: null,
            'create_contact_present' => (bool) (($diag['wire_customer_info_has_email'] ?? false) || ($diag['wire_customer_info_has_contact_numbers'] ?? false)),
            'create_received_from_present' => $receivedFromPresent,
            'create_ticketing_disabled' => $ticketingDisabled,
            'create_post_ticketing_action' => $postTicketingAction,
            'create_price_quote_present' => $priceQuotePresent,
            'create_host_command_style' => (string) ($diag['booking_transport'] ?? 'rest_json_passenger_records_cpnr'),
            'create_segment_source' => trim((string) ($context['create_segment_source'] ?? '')) ?: null,
            'create_route_continuity' => array_key_exists('route_continuity', $segSell) ? (bool) $segSell['route_continuity'] : null,
            'create_chronology_gaps' => is_array($segSell['chronology_gaps'] ?? null)
                ? array_slice($segSell['chronology_gaps'], 0, 4)
                : null,
            'create_snapshot_segment_count' => count($offerSnapshotSegments) > 0 ? count($offerSnapshotSegments) : null,
            'create_segment_order_repaired' => ($context['create_segment_order_repaired'] ?? null) === true ? true : null,
            'create_date_repair_applied' => ($context['create_date_repair_applied'] ?? null) === true ? true : null,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $sellRow
     * @param  array<string, mixed>  $wireSeg
     * @return array<string, mixed>
     */
    protected function formatCreateSegmentSummaryRow(array $sellRow, array $wireSeg): array
    {
        $depParts = $this->splitSabreDateTimeForCreateSummary((string) ($sellRow['departure_datetime'] ?? ''));
        $arrParts = $this->splitSabreDateTimeForCreateSummary((string) ($sellRow['arrival_datetime'] ?? ''));
        $marriage = trim((string) ($wireSeg['MarriageGrp'] ?? ''));
        $connectionInd = trim((string) ($wireSeg['ConnectionInd'] ?? ''));

        return array_filter([
            'carrier' => $sellRow['marketing_airline'] ?? null,
            'flight_number' => $sellRow['flight_number'] ?? null,
            'origin' => $sellRow['origin'] ?? null,
            'destination' => $sellRow['destination'] ?? null,
            'departure_date' => $depParts['date'],
            'departure_time' => $depParts['time'],
            'arrival_date' => $arrParts['date'],
            'arrival_time' => $arrParts['time'],
            'booking_class' => $sellRow['res_book_desig_code'] ?? null,
            'marriage_group' => $marriage !== '' ? $marriage : null,
            'connection_ind' => $connectionInd !== '' ? $connectionInd : null,
            'number_in_party' => $sellRow['number_in_party'] ?? null,
            'status' => $sellRow['status'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @return array{date: ?string, time: ?string}
     */
    protected function splitSabreDateTimeForCreateSummary(string $datetime): array
    {
        $datetime = trim($datetime);
        if ($datetime === '') {
            return ['date' => null, 'time' => null];
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2}(?::\d{2})?)/', $datetime, $m) === 1) {
            return ['date' => $m[1], 'time' => substr($m[2], 0, 5)];
        }

        return ['date' => null, 'time' => null];
    }

    /**
     * @return array<string, mixed>
     */
    protected function traditionalPnrMapAirBookSegmentSafeRow(array $seg, int $zeroBasedIndex, ?string $fareBasisSnapshot): array
    {
        $mkt = is_array($seg['MarketingAirline'] ?? null) ? $seg['MarketingAirline'] : [];
        $op = is_array($seg['OperatingAirline'] ?? null) ? $seg['OperatingAirline'] : [];
        $ol = is_array($seg['OriginLocation'] ?? null) ? $seg['OriginLocation'] : [];
        $dl = is_array($seg['DestinationLocation'] ?? null) ? $seg['DestinationLocation'] : [];
        $mktCode = strtoupper(trim((string) ($mkt['Code'] ?? '')));
        $fnTop = trim((string) ($seg['FlightNumber'] ?? ''));
        $fnMkt = trim((string) ($mkt['FlightNumber'] ?? ''));
        $fn = $fnTop !== '' ? $fnTop : $fnMkt;
        $opCode = strtoupper(trim((string) ($op['Code'] ?? '')));
        $rbd = strtoupper(trim((string) ($seg['ResBookDesigCode'] ?? '')));
        $st = trim((string) ($seg['Status'] ?? ''));
        $nipRaw = $seg['NumberInParty'] ?? null;
        $nip = null;
        if (is_string($nipRaw)) {
            $nip = $nipRaw;
        } elseif (is_int($nipRaw)) {
            $nip = (string) $nipRaw;
        }
        $dep = trim((string) ($seg['DepartureDateTime'] ?? ''));
        $arr = trim((string) ($seg['ArrivalDateTime'] ?? ''));

        return [
            'index' => $zeroBasedIndex,
            'origin' => strtoupper(trim((string) ($ol['LocationCode'] ?? ''))),
            'destination' => strtoupper(trim((string) ($dl['LocationCode'] ?? ''))),
            'marketing_airline' => $mktCode !== '' ? $mktCode : null,
            'operating_airline' => $opCode !== '' ? $opCode : null,
            'flight_number' => $fn !== '' ? $fn : null,
            'departure_datetime' => $dep !== '' ? $dep : null,
            'arrival_datetime' => $arr !== '' ? $arr : null,
            'res_book_desig_code' => $rbd !== '' ? $rbd : null,
            'number_in_party' => $nip,
            'status' => $st !== '' ? $st : null,
            'fare_basis_snapshot' => $fareBasisSnapshot,
        ];
    }

    /**
     * B60: Safe sell-context diagnostics from {@code AirBook.OriginDestinationInformation.FlightSegment[]} (no passenger PII).
     *
     * @param  list<array<string, mixed>>  $segs
     * @return array<string, mixed>
     */
    protected function traditionalPnrSummarizeSegmentSellContext(array $segs): array
    {
        $n = count($segs);
        if ($n === 0) {
            return [
                'wire_segment_sell_context_count' => 0,
                'wire_segment_sell_context_all_have_marketing_airline' => false,
                'wire_segment_sell_context_all_have_flight_number' => false,
                'wire_segment_sell_context_all_have_res_book_desig_code' => false,
                'wire_segment_sell_context_all_have_departure_datetime' => false,
                'wire_segment_sell_context_all_have_origin_destination' => false,
                'wire_segment_sell_context_marketing_airlines_sanitized' => [],
                'wire_segment_sell_context_rbd_values_sanitized' => [],
                'wire_segment_sell_context_status_values_sanitized' => [],
                'wire_segment_sell_context_number_in_party_values_sanitized' => [],
                'wire_segment_sell_context_all_required_present' => false,
            ];
        }
        $allMkt = true;
        $allFn = true;
        $allRbd = true;
        $allDep = true;
        $allOd = true;
        $mTok = [];
        $rbdTok = [];
        $stTok = [];
        $nipTok = [];
        foreach ($segs as $seg) {
            if (! is_array($seg)) {
                $allMkt = $allFn = $allRbd = $allDep = $allOd = false;

                continue;
            }
            $mkt = is_array($seg['MarketingAirline'] ?? null) ? $seg['MarketingAirline'] : [];
            $ac = strtoupper(trim((string) ($mkt['Code'] ?? '')));
            $fnTop = trim((string) ($seg['FlightNumber'] ?? ''));
            $fnMkt = trim((string) ($mkt['FlightNumber'] ?? ''));
            $fn = $fnTop !== '' ? $fnTop : $fnMkt;
            $rbd = strtoupper(trim((string) ($seg['ResBookDesigCode'] ?? '')));
            $dep = trim((string) ($seg['DepartureDateTime'] ?? ''));
            $ol = is_array($seg['OriginLocation'] ?? null) ? $seg['OriginLocation'] : [];
            $dl = is_array($seg['DestinationLocation'] ?? null) ? $seg['DestinationLocation'] : [];
            $oc = strtoupper(trim((string) ($ol['LocationCode'] ?? '')));
            $dc = strtoupper(trim((string) ($dl['LocationCode'] ?? '')));
            $odOk = $oc !== '' && $dc !== '';
            $allMkt = $allMkt && $ac !== '';
            $allFn = $allFn && $fn !== '';
            $allRbd = $allRbd && $rbd !== '';
            $allDep = $allDep && $dep !== '';
            $allOd = $allOd && $odOk;
            if ($ac !== '') {
                $mTok[strtoupper(substr($ac, 0, 3))] = true;
            }
            if ($rbd !== '') {
                $rbdTok[strtoupper(substr($rbd, 0, 2))] = true;
            }
            $st = strtoupper(trim((string) ($seg['Status'] ?? '')));
            if ($st !== '') {
                $stTok[substr($st, 0, 8)] = true;
            }
            $nipRaw = $seg['NumberInParty'] ?? null;
            $nipStr = is_string($nipRaw) ? preg_replace('/\D+/', '', $nipRaw) : (is_int($nipRaw) ? (string) $nipRaw : '');
            if ($nipStr !== '') {
                $nipTok[substr($nipStr, 0, 4)] = true;
            }
        }
        $mList = array_keys($mTok);
        $rList = array_keys($rbdTok);
        $sList = array_keys($stTok);
        $nList = array_keys($nipTok);
        sort($mList);
        sort($rList);
        sort($sList);
        sort($nList);
        $req = $allMkt && $allFn && $allRbd && $allDep && $allOd;

        return [
            'wire_segment_sell_context_count' => $n,
            'wire_segment_sell_context_all_have_marketing_airline' => $allMkt,
            'wire_segment_sell_context_all_have_flight_number' => $allFn,
            'wire_segment_sell_context_all_have_res_book_desig_code' => $allRbd,
            'wire_segment_sell_context_all_have_departure_datetime' => $allDep,
            'wire_segment_sell_context_all_have_origin_destination' => $allOd,
            'wire_segment_sell_context_marketing_airlines_sanitized' => array_slice($mList, 0, 16),
            'wire_segment_sell_context_rbd_values_sanitized' => array_slice($rList, 0, 16),
            'wire_segment_sell_context_status_values_sanitized' => array_slice($sList, 0, 16),
            'wire_segment_sell_context_number_in_party_values_sanitized' => array_slice($nList, 0, 16),
            'wire_segment_sell_context_all_required_present' => $req,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $meta
     * @return array<string, mixed>|null
     */
    protected function traditionalPnrResolveOfferSnapshotFromBookingMeta(?array $meta): ?array
    {
        if ($meta === null || $meta === []) {
            return null;
        }
        foreach (['normalized_offer_snapshot', 'validated_offer_snapshot', 'flight_offer_snapshot'] as $k) {
            $s = $meta[$k] ?? null;
            if (is_array($s) && $s !== []) {
                return $s;
            }
        }

        return null;
    }

    /**
     * B60: Collect string field names under {@code $node} whose names suggest brand/fare-brand context (keys only).
     *
     * @param  array<string, true>  $accRef
     */
    protected function traditionalPnrAccumulateBrandishFieldNames(mixed $node, array &$accRef, int $depth, int $maxDepth, int $maxKeys = 64): void
    {
        if ($depth > $maxDepth || count($accRef) >= $maxKeys) {
            return;
        }
        if (! is_array($node)) {
            return;
        }
        foreach ($node as $k => $v) {
            if (count($accRef) >= $maxKeys) {
                return;
            }
            if (is_string($k) && preg_match('/brand/i', $k) === 1) {
                $accRef[$k] = true;
            }
            if (is_array($v)) {
                $this->traditionalPnrAccumulateBrandishFieldNames($v, $accRef, $depth + 1, $maxDepth, $maxKeys);
            }
        }
    }

    /**
     * B60: Offer snapshot / freshness flags for traditional CPNR diagnostics (no identifier values).
     *
     * @param  array<string, mixed>|null  $snapshot
     * @param  array<string, mixed>|null  $bookingMeta
     * @return array<string, mixed>
     */
    protected function traditionalPnrSummarizeOfferFreshnessDiagnostics(?array $snapshot, ?array $bookingMeta): array
    {
        $present = is_array($snapshot) && $snapshot !== [];
        $defaults = [
            'wire_offer_snapshot_present' => $present,
            'wire_offer_snapshot_age_minutes' => null,
            'wire_offer_snapshot_age_bucket' => $present ? 'unknown_no_capture_timestamp' : 'no_snapshot',
            'wire_offer_has_raw_sabre_identifiers' => false,
            'wire_offer_has_brand_candidates' => false,
            'wire_brand_candidate_keys_sanitized' => [],
        ];
        if (! $present) {
            return $defaults;
        }
        $rawRef = isset($snapshot['raw_reference']) && is_string($snapshot['raw_reference']) && trim($snapshot['raw_reference']) !== '';
        $rp = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : null;
        $shop = is_array($rp) && is_array($rp['sabre_shop_identifiers'] ?? null) && $rp['sabre_shop_identifiers'] !== [];
        $defaults['wire_offer_has_raw_sabre_identifiers'] = $rawRef || $shop;

        $brandKeys = [];
        $this->traditionalPnrAccumulateBrandishFieldNames($snapshot, $brandKeys, 0, 7, 64);
        $bk = array_keys($brandKeys);
        sort($bk);
        $defaults['wire_brand_candidate_keys_sanitized'] = array_slice($bk, 0, 32);
        $defaults['wire_offer_has_brand_candidates'] = $bk !== [];

        $ageMinutes = null;
        $bucket = 'unknown_no_capture_timestamp';
        $captureIso = null;
        if ($bookingMeta !== null) {
            foreach (['ota_offer_context_captured_at', 'sabre_search_completed_at', 'last_flight_search_at'] as $mk) {
                $cand = $bookingMeta[$mk] ?? null;
                if (is_string($cand) && trim($cand) !== '') {
                    $captureIso = trim($cand);
                    break;
                }
            }
        }
        if ($captureIso !== null) {
            try {
                $t0 = Carbon::parse($captureIso);
                $ageMinutes = (int) max(0, $t0->diffInMinutes(Carbon::now()));
                $bucket = match (true) {
                    $ageMinutes < 30 => 'captured_under_30m',
                    $ageMinutes < 240 => 'captured_under_4h',
                    default => 'captured_ge_4h',
                };
            } catch (\Throwable) {
                $ageMinutes = null;
                $bucket = 'capture_timestamp_unparseable';
            }
        } else {
            $exp = $snapshot['expires_at'] ?? null;
            if (is_string($exp) && trim($exp) !== '') {
                try {
                    $e = Carbon::parse(trim($exp));
                    $now = Carbon::now();
                    if ($e->isPast()) {
                        $ageMinutes = (int) max(0, $e->diffInMinutes($now));
                        $bucket = match (true) {
                            $ageMinutes < 30 => 'expired_under_30m_ago',
                            $ageMinutes < 1440 => 'expired_under_24h_ago',
                            default => 'expired_ge_24h_ago',
                        };
                    } else {
                        $minsTo = (int) max(0, $now->diffInMinutes($e));
                        $ageMinutes = null;
                        $bucket = match (true) {
                            $minsTo < 30 => 'active_expires_under_30m',
                            $minsTo < 240 => 'active_expires_under_4h',
                            default => 'active_expires_ge_4h',
                        };
                    }
                } catch (\Throwable) {
                    $bucket = 'expires_at_unparseable';
                }
            } else {
                $bucket = 'no_expires_at_field';
            }
        }
        $defaults['wire_offer_snapshot_age_minutes'] = $ageMinutes;
        $defaults['wire_offer_snapshot_age_bucket'] = $bucket;

        return $defaults;
    }

    /**
     * B53: {@code SpecialReqDetails.AddRemark.RemarkInfo.Remark} rows (list or single object).
     *
     * @return list<array<string, mixed>>
     */
    protected function traditionalPnrExtractAddRemarkRows(array $cpnr): array
    {
        $sr = is_array($cpnr['SpecialReqDetails'] ?? null) ? $cpnr['SpecialReqDetails'] : [];
        $add = $sr['AddRemark']['RemarkInfo']['Remark'] ?? null;
        if (! is_array($add)) {
            return [];
        }
        if (array_is_list($add)) {
            $out = [];
            foreach ($add as $row) {
                if (is_array($row)) {
                    $out[] = $row;
                }
            }

            return $out;
        }

        return [$add];
    }

    /**
     * B53: Map legacy uppercase {@code GENERAL} to Sabre enum {@code General}; passthrough otherwise.
     */
    protected function mapTraditionalPnrRemarkTypeToSabreEnum(string $raw): string
    {
        $t = trim($raw);
        if (strcasecmp($t, 'GENERAL') === 0) {
            return 'General';
        }

        return $t;
    }

    /**
     * B53: Normalize {@code Remark[].Type} to Sabre enum casing before Passenger Records POST.
     *
     * @param  array<string, mixed>  $cpnr
     * @return array<string, mixed>
     */
    protected function traditionalPnrNormalizeCpnrAddRemarkTypes(array $cpnr): array
    {
        $rows = $this->traditionalPnrExtractAddRemarkRows($cpnr);
        if ($rows === []) {
            return $cpnr;
        }
        foreach ($rows as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            if (array_key_exists('Type', $row)) {
                $rows[$i]['Type'] = $this->mapTraditionalPnrRemarkTypeToSabreEnum((string) $row['Type']);
            }
        }
        if (! isset($cpnr['SpecialReqDetails']) || ! is_array($cpnr['SpecialReqDetails'])) {
            $cpnr['SpecialReqDetails'] = [];
        }
        if (! isset($cpnr['SpecialReqDetails']['AddRemark']) || ! is_array($cpnr['SpecialReqDetails']['AddRemark'])) {
            $cpnr['SpecialReqDetails']['AddRemark'] = [];
        }
        if (! isset($cpnr['SpecialReqDetails']['AddRemark']['RemarkInfo']) || ! is_array($cpnr['SpecialReqDetails']['AddRemark']['RemarkInfo'])) {
            $cpnr['SpecialReqDetails']['AddRemark']['RemarkInfo'] = [];
        }
        $cpnr['SpecialReqDetails']['AddRemark']['RemarkInfo']['Remark'] = array_values($rows);

        return $cpnr;
    }

    /**
     * B56: Passenger Records expects {@code TravelItineraryAddInfo.CustomerInfo.PersonName} as a JSON **array** of name rows.
     * If {@code PersonName} is a single associative object, wrap it as a one-element list.
     *
     * @param  array<string, mixed>  $customerInfo  {@code TravelItineraryAddInfo.CustomerInfo}
     * @return array<string, mixed>
     */
    protected function traditionalPnrNormalizeCustomerInfoPersonNameToArray(array $customerInfo): array
    {
        if (! array_key_exists('PersonName', $customerInfo)) {
            return $customerInfo;
        }
        $pn = $customerInfo['PersonName'];
        if ($pn === null || $pn === []) {
            $customerInfo['PersonName'] = [];

            return $customerInfo;
        }
        if (! is_array($pn)) {
            unset($customerInfo['PersonName']);

            return $customerInfo;
        }
        if (! array_is_list($pn)) {
            $customerInfo['PersonName'] = [$pn];
        } else {
            $customerInfo['PersonName'] = array_values($pn);
        }

        return $customerInfo;
    }

    /**
     * B59: Ensure root {@code CreatePassengerNameRecordRQ.AirPrice[0].PriceRequestInformation} retains {@code Retain=true}
     * and carries {@code OptionalQualifiers.PricingQualifiers.PassengerType[]} built from draft passengers (string
     * {@code Quantity}); strips {@code Brand}. No-op when {@code AirPrice} is missing or not a list.
     *
     * @param  array<string, mixed>  $cpnr
     * @param  array<string, mixed>  $internalDraft
     * @return array<string, mixed>
     */
    protected function traditionalPnrNormalizeRootAirPricePassengerTypeQualifiers(array $cpnr, array $internalDraft): array
    {
        if (! isset($cpnr['AirPrice']) || ! is_array($cpnr['AirPrice'])) {
            return $cpnr;
        }
        $ap = $cpnr['AirPrice'];
        if (! array_is_list($ap) || $ap === []) {
            return $cpnr;
        }
        $first = is_array($ap[0] ?? null) ? $ap[0] : [];
        $pri = is_array($first['PriceRequestInformation'] ?? null) ? $first['PriceRequestInformation'] : [];
        $pri['Retain'] = true;
        $passengers = is_array($internalDraft['passengers'] ?? null) ? $internalDraft['passengers'] : [];
        $counts = $this->traditionalPnrCountPassengerTypesForRootAirPrice($passengers);
        $oq = is_array($pri['OptionalQualifiers'] ?? null) ? $pri['OptionalQualifiers'] : [];
        $pq = is_array($oq['PricingQualifiers'] ?? null) ? $oq['PricingQualifiers'] : [];
        unset($pq['Brand']);
        $rows = [];
        foreach (['ADT', 'CNN', 'INF'] as $code) {
            $n = (int) ($counts[$code] ?? 0);
            if ($n > 0) {
                $rows[] = ['Code' => $code, 'Quantity' => (string) $n];
            }
        }
        if ($rows === []) {
            $rows = [['Code' => 'ADT', 'Quantity' => '1']];
        }
        $normRows = [];
        foreach ($rows as $r) {
            if (! is_array($r)) {
                continue;
            }
            $c = strtoupper(trim((string) ($r['Code'] ?? '')));
            $q = $r['Quantity'] ?? null;
            if ($c === '' || ! in_array($c, ['ADT', 'CNN', 'INF'], true)) {
                continue;
            }
            $normRows[] = ['Code' => $c, 'Quantity' => is_string($q) ? $q : (is_int($q) || is_float($q) ? (string) $q : '1')];
        }
        if ($normRows === []) {
            $normRows = [['Code' => 'ADT', 'Quantity' => '1']];
        }
        $pq['PassengerType'] = array_values($normRows);
        $oq['PricingQualifiers'] = $pq;
        $pri['OptionalQualifiers'] = $oq;
        $first['PriceRequestInformation'] = $pri;
        $ap[0] = $first;
        $cpnr['AirPrice'] = array_values($ap);

        return $cpnr;
    }

    /**
     * B79: Merge optional {@code ValidatingCarrier} under root {@code AirPrice...PricingQualifiers} for compare-only wire (default V1 unchanged).
     *
     * @param  array<string, mixed>  $cpnr
     * @param  array<string, mixed>  $internalDraft
     * @return array<string, mixed>
     */
    protected function traditionalPnrApplyRootAirPriceValidatingCarrierCompareQualifier(array $cpnr, array $internalDraft): array
    {
        if (! isset($cpnr['AirPrice']) || ! is_array($cpnr['AirPrice']) || ! array_is_list($cpnr['AirPrice']) || $cpnr['AirPrice'] === []) {
            return $cpnr;
        }
        $ap = $cpnr['AirPrice'];
        $first = is_array($ap[0] ?? null) ? $ap[0] : [];
        $pri = is_array($first['PriceRequestInformation'] ?? null) ? $first['PriceRequestInformation'] : [];
        $oq = is_array($pri['OptionalQualifiers'] ?? null) ? $pri['OptionalQualifiers'] : [];
        $pq = is_array($oq['PricingQualifiers'] ?? null) ? $oq['PricingQualifiers'] : [];
        unset($pq['ValidatingCarrier']);
        $vc = $this->traditionalPnrSanitizeCarrierTokenForAirPriceCompare($internalDraft['validating_carrier'] ?? null);
        if ($vc !== null) {
            $pq['ValidatingCarrier'] = ['Code' => $vc];
        }
        $oq['PricingQualifiers'] = $pq;
        $pri['OptionalQualifiers'] = $oq;
        $first['PriceRequestInformation'] = $pri;
        $ap[0] = $first;
        $cpnr['AirPrice'] = array_values($ap);

        return $cpnr;
    }

    /**
     * F9K: Merge validating carrier under {@code AirPrice...OptionalQualifiers.FlightQualifiers.VendorPrefs.Airline}
     * for IATI-like CPNR v2.4 (Sabre schema-approved path). Strips forbidden {@code PricingQualifiers.ValidatingCarrier}.
     *
     * @param  array<string, mixed>  $cpnr
     * @param  array<string, mixed>  $internalDraft
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $internalDraft
     * @return array<string, mixed>
     */
    protected function enrichInternalDraftFromSabreBookingContext(array $internalDraft): array
    {
        $ctx = is_array($internalDraft['_sabre_booking_context'] ?? null) ? $internalDraft['_sabre_booking_context'] : [];
        if ($ctx === []) {
            return $internalDraft;
        }

        $vc = strtoupper(trim((string) ($ctx['validating_carrier'] ?? '')));
        if ($vc !== '' && trim((string) ($internalDraft['validating_carrier'] ?? '')) === '') {
            $internalDraft['validating_carrier'] = $vc;
        }

        $fareBasisBySeg = $this->normalizeSabreSegmentStringList(
            $ctx['fare_basis_codes_by_segment'] ?? $ctx['fare_basis_codes'] ?? []
        );
        $bookingClassBySeg = $this->normalizeSabreSegmentStringList($ctx['booking_classes_by_segment'] ?? []);
        if ($fareBasisBySeg !== [] || $bookingClassBySeg !== []) {
            $segments = is_array($internalDraft['segments'] ?? null) ? array_values($internalDraft['segments']) : [];
            foreach ($segments as $i => $seg) {
                if (! is_array($seg)) {
                    continue;
                }
                if (isset($fareBasisBySeg[$i]) && trim((string) ($seg['fare_basis_code'] ?? '')) === '') {
                    $segments[$i]['fare_basis_code'] = $fareBasisBySeg[$i];
                }
                if (isset($bookingClassBySeg[$i]) && trim((string) ($seg['booking_class'] ?? '')) === '') {
                    $segments[$i]['booking_class'] = $bookingClassBySeg[$i];
                }
            }
            $internalDraft['segments'] = $segments;
        }

        if (isset($ctx['selected_price_total']) && is_numeric($ctx['selected_price_total'])) {
            $fare = is_array($internalDraft['fare'] ?? null) ? $internalDraft['fare'] : [];
            $fare['amount'] = (float) $ctx['selected_price_total'];
            $internalDraft['fare'] = $fare;
        }

        return $internalDraft;
    }

    /**
     * @param  array<string, mixed>  $cpnr
     * @param  array<string, mixed>  $internalDraft
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    protected function traditionalPnrApplyGdsV25SabreBookingContextQualifiers(array $cpnr, array $internalDraft, array $ctx): array
    {
        $vc = trim((string) ($ctx['validating_carrier'] ?? $internalDraft['validating_carrier'] ?? ''));
        if ($vc !== '') {
            $draftVc = $internalDraft;
            $draftVc['validating_carrier'] = strtoupper($vc);
            $cpnr = $this->traditionalPnrApplyRootAirPriceFlightQualifiersVendorPrefsValidatingCarrier($cpnr, $draftVc);
        }

        $brand = $this->traditionalPnrResolveBrandCodeFromDraft($internalDraft);
        $cpnr = $this->traditionalPnrApplyGdsV25RootAirPriceBrandQualifier($cpnr, $brand ?? '');

        return $this->traditionalPnrApplyGdsV25RootAirPriceFareBasisFromContext($cpnr, $internalDraft, $ctx);
    }

    /**
     * PNR-only manual ticketing / time-limit marker ({@code 7TAW}) — hold PNR without ticket issuance.
     *
     * @param  array<string, mixed>  $cpnr
     * @return array<string, mixed>
     */
    protected function traditionalPnrApplyPnrOnlyManualTicketingTimeLimitMarker(array $cpnr): array
    {
        $tia = is_array($cpnr['TravelItineraryAddInfo'] ?? null) ? $cpnr['TravelItineraryAddInfo'] : [];
        $agencyInfo = is_array($tia['AgencyInfo'] ?? null) ? $tia['AgencyInfo'] : [];
        unset($agencyInfo['Telephone']);
        $agencyInfo['Ticketing'] = ['TicketType' => '7TAW'];
        $tia['AgencyInfo'] = $agencyInfo;
        $cpnr['TravelItineraryAddInfo'] = $tia;

        return $cpnr;
    }

    /**
     * @param  array<string, mixed>  $cpnr
     * @param  array<string, mixed>  $internalDraft
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    protected function traditionalPnrApplyGdsV25RootAirPriceFareBasisFromContext(array $cpnr, array $internalDraft, array $ctx): array
    {
        if (! isset($cpnr['AirPrice']) || ! is_array($cpnr['AirPrice']) || ! array_is_list($cpnr['AirPrice']) || $cpnr['AirPrice'] === []) {
            return $cpnr;
        }

        $fareBasisBySeg = $this->normalizeSabreSegmentStringList(
            $ctx['fare_basis_codes_by_segment'] ?? $ctx['fare_basis_codes'] ?? []
        );
        $segments = is_array($internalDraft['segments'] ?? null) ? array_values($internalDraft['segments']) : [];
        $commandRows = [];
        $count = max(count($segments), count($fareBasisBySeg));
        for ($i = 0; $i < $count; $i++) {
            $fbRaw = $fareBasisBySeg[$i] ?? null;
            if (($fbRaw === null || trim((string) $fbRaw) === '') && isset($segments[$i]) && is_array($segments[$i])) {
                $fbRaw = $segments[$i]['fare_basis_code'] ?? null;
            }
            $fb = $this->traditionalPnrSanitizeFareBasisCodeForGdsAirPrice($fbRaw);
            if ($fb === null) {
                continue;
            }
            $rph = (string) ($i + 1);
            $commandRows[] = [
                'RPH' => $rph,
                'FareBasis' => ['Code' => $fb],
            ];
        }
        if ($commandRows === []) {
            return $cpnr;
        }

        $ap = $cpnr['AirPrice'];
        $first = is_array($ap[0] ?? null) ? $ap[0] : [];
        $pri = is_array($first['PriceRequestInformation'] ?? null) ? $first['PriceRequestInformation'] : [];
        $oq = is_array($pri['OptionalQualifiers'] ?? null) ? $pri['OptionalQualifiers'] : [];
        $pq = is_array($oq['PricingQualifiers'] ?? null) ? $oq['PricingQualifiers'] : [];
        unset($pq['ItineraryOptions']);
        $pq['CommandPricing'] = array_values($commandRows);
        $oq['PricingQualifiers'] = $pq;
        $pri['OptionalQualifiers'] = $oq;
        $first['PriceRequestInformation'] = $pri;
        $ap[0] = $first;
        $cpnr['AirPrice'] = array_values($ap);

        return $cpnr;
    }

    /**
     * IATI v2.4: per-segment CommandPricing with schema-valid fare basis only ({@code RPH} + {@code FareBasis.Code}),
     * paired with sibling {@code ItineraryOptions.SegmentSelect@RPH} (not nested under CommandPricing).
     * Carrier/RBD/segment association proof stays in AirBook sell rows + preflight diagnostics (not on CommandPricing).
     *
     * @param  array<string, mixed>  $cpnr
     * @param  array<string, mixed>  $internalDraft
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    protected function traditionalPnrApplyIatiRootAirPricePerSegmentCommandPricingFromContext(
        array $cpnr,
        array $internalDraft,
        array $ctx,
    ): array {
        if (! isset($cpnr['AirPrice']) || ! is_array($cpnr['AirPrice']) || ! array_is_list($cpnr['AirPrice']) || $cpnr['AirPrice'] === []) {
            return $cpnr;
        }

        $fareBasisBySeg = $this->normalizeSabreSegmentStringList(
            $ctx['fare_basis_codes_by_segment'] ?? $ctx['fare_basis_codes'] ?? []
        );
        $segments = is_array($internalDraft['segments'] ?? null) ? array_values($internalDraft['segments']) : [];
        $commandRows = [];
        $segmentSelectRows = [];
        $count = max(count($segments), count($fareBasisBySeg));
        for ($i = 0; $i < $count; $i++) {
            $seg = is_array($segments[$i] ?? null) ? $segments[$i] : [];
            $fbRaw = $fareBasisBySeg[$i] ?? null;
            if (($fbRaw === null || trim((string) $fbRaw) === '') && $seg !== []) {
                $fbRaw = $seg['fare_basis_code'] ?? null;
            }
            $fb = $this->traditionalPnrSanitizeFareBasisCodeForGdsAirPrice($fbRaw);
            if ($fb === null) {
                continue;
            }
            $rph = (string) ($i + 1);
            $segmentSelectRows[] = ['RPH' => $rph, 'Number' => $rph];
            $commandRows[] = [
                'RPH' => $rph,
                'FareBasis' => ['Code' => $fb],
            ];
        }
        if ($commandRows === []) {
            return $cpnr;
        }

        $ap = $cpnr['AirPrice'];
        $first = is_array($ap[0] ?? null) ? $ap[0] : [];
        $pri = is_array($first['PriceRequestInformation'] ?? null) ? $first['PriceRequestInformation'] : [];
        $oq = is_array($pri['OptionalQualifiers'] ?? null) ? $pri['OptionalQualifiers'] : [];
        $pq = is_array($oq['PricingQualifiers'] ?? null) ? $oq['PricingQualifiers'] : [];
        $pq['ItineraryOptions'] = [
            'SegmentSelect' => count($segmentSelectRows) === 1
                ? $segmentSelectRows[0]
                : array_values($segmentSelectRows),
        ];
        $pq['CommandPricing'] = count($commandRows) === 1
            ? $commandRows[0]
            : array_values($commandRows);
        $oq['PricingQualifiers'] = $pq;
        $pri['OptionalQualifiers'] = $oq;
        $first['PriceRequestInformation'] = $pri;
        $ap[0] = $first;
        $cpnr['AirPrice'] = array_values($ap);

        return $cpnr;
    }

    /**
     * @param  array<string, mixed>  $seg
     */
    protected function traditionalPnrSegmentMarketingCarrierCode(array $seg): ?string
    {
        if ($seg === []) {
            return null;
        }

        foreach ([
            'marketing_carrier',
            'carrier',
            'airline_code',
            'marketing_airline',
            'airlineCode',
        ] as $key) {
            $sanitized = $this->traditionalPnrSanitizeCarrierTokenForAirPriceCompare($seg[$key] ?? null);
            if ($sanitized !== null) {
                return $sanitized;
            }
        }

        return null;
    }

    /**
     * Safe diagnostics for IATI v2.4 CommandPricing schema shape (no PII).
     *
     * @param  array<string, mixed>  $wire
     * @return array{
     *     command_pricing_schema_valid: bool,
     *     command_pricing_rejected_keys: list<string>|null,
     *     command_pricing_allowed_shape: string,
     *     command_pricing_wire_keys: list<string>|null
     * }
     */
    public function inspectIatiV24CommandPricingSchema(array $wire): array
    {
        $allowedRowKeys = ['RPH', 'FareBasis'];
        $allowedFareBasisKeys = ['Code'];
        $rejected = [];
        $wireKeys = [];

        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null)
            ? $wire['CreatePassengerNameRecordRQ']
            : $wire;
        $ap = is_array($cpnr['AirPrice'] ?? null) ? $cpnr['AirPrice'] : [];
        $first = is_array($ap[0] ?? null) ? $ap[0] : [];
        $pq = data_get($first, 'PriceRequestInformation.OptionalQualifiers.PricingQualifiers', []);
        if (! is_array($pq)) {
            return [
                'command_pricing_schema_valid' => true,
                'command_pricing_rejected_keys' => null,
                'command_pricing_allowed_shape' => 'RPH+FareBasis.Code',
                'command_pricing_wire_keys' => null,
            ];
        }

        if (array_key_exists('ItineraryOptions', $pq)) {
            $itineraryOptions = $pq['ItineraryOptions'];
            if (! is_array($itineraryOptions)) {
                $rejected[] = 'PricingQualifiers.ItineraryOptions';
            } else {
                foreach (array_keys($itineraryOptions) as $ioKey) {
                    if ($ioKey !== 'SegmentSelect') {
                        $rejected[] = 'ItineraryOptions.'.$ioKey;
                    }
                }
                $segmentSelect = $itineraryOptions['SegmentSelect'] ?? null;
                if (is_array($segmentSelect)) {
                    $ssRows = array_is_list($segmentSelect) ? $segmentSelect : [$segmentSelect];
                    foreach ($ssRows as $ssRow) {
                        if (! is_array($ssRow)) {
                            continue;
                        }
                        foreach (array_keys($ssRow) as $ssKey) {
                            if (! in_array($ssKey, ['RPH', 'Number'], true)) {
                                $rejected[] = 'SegmentSelect.'.$ssKey;
                            }
                        }
                    }
                }
            }
        }

        $commandPricing = $pq['CommandPricing'] ?? null;
        if (! is_array($commandPricing)) {
            return [
                'command_pricing_schema_valid' => $rejected === [],
                'command_pricing_rejected_keys' => $rejected !== [] ? array_values(array_unique($rejected)) : null,
                'command_pricing_allowed_shape' => 'RPH+FareBasis.Code',
                'command_pricing_wire_keys' => null,
            ];
        }

        $rows = array_is_list($commandPricing) ? $commandPricing : [$commandPricing];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach (array_keys($row) as $key) {
                $wireKeys[] = $key;
                if (! in_array($key, $allowedRowKeys, true)) {
                    $rejected[] = $key;
                }
            }
            $fb = $row['FareBasis'] ?? null;
            if (is_array($fb)) {
                foreach (array_keys($fb) as $fbKey) {
                    $wireKeys[] = 'FareBasis.'.$fbKey;
                    if (! in_array($fbKey, $allowedFareBasisKeys, true)) {
                        $rejected[] = 'FareBasis.'.$fbKey;
                    }
                }
            }
        }

        $rejected = array_values(array_unique($rejected));
        $wireKeys = array_values(array_unique($wireKeys));

        return [
            'command_pricing_schema_valid' => $rejected === [],
            'command_pricing_rejected_keys' => $rejected !== [] ? $rejected : null,
            'command_pricing_allowed_shape' => 'RPH+FareBasis.Code',
            'command_pricing_wire_keys' => $wireKeys !== [] ? $wireKeys : null,
        ];
    }

    /**
     * Safe diagnostics for IATI v2.4 CommandPricing ↔ ItineraryOptions.SegmentSelect RPH pairing (no PII).
     *
     * @param  array<string, mixed>  $wire
     * @return array{
     *     segment_select_present: bool,
     *     segment_select_rph_count: int,
     *     segment_select_rph_values: list<string>|null,
     *     command_pricing_rph_values: list<string>|null,
     *     command_pricing_segmentselect_pairing_complete: bool,
     *     command_pricing_segmentselect_missing_rph: list<string>|null
     * }
     */
    public function inspectIatiV24CommandPricingSegmentSelectPairing(array $wire): array
    {
        $cpRows = $this->extractTraditionalPnrCommandPricingRows($wire);
        $cpRphs = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['rph'] ?? '')),
            $cpRows,
        ), static fn (string $v): bool => $v !== '')));
        sort($cpRphs, SORT_STRING);

        $ssRows = $this->extractTraditionalPnrSegmentSelectRows($wire);
        $ssRphs = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['rph'] ?? '')),
            $ssRows,
        ), static fn (string $v): bool => $v !== '')));
        sort($ssRphs, SORT_STRING);

        $missing = $cpRphs === [] ? [] : array_values(array_diff($cpRphs, $ssRphs));
        $pairingComplete = $cpRphs === [] || ($missing === [] && $cpRphs === $ssRphs);

        return [
            'segment_select_present' => $ssRphs !== [],
            'segment_select_rph_count' => count($ssRphs),
            'segment_select_rph_values' => $ssRphs !== [] ? $ssRphs : null,
            'command_pricing_rph_values' => $cpRphs !== [] ? $cpRphs : null,
            'command_pricing_segmentselect_pairing_complete' => $pairingComplete,
            'command_pricing_segmentselect_missing_rph' => $missing !== [] ? $missing : null,
        ];
    }

    /**
     * Normalize pricing RPH tokens for safe pairing comparison (SegmentSelect string vs Brand integer).
     */
    public function normalizeIatiV24PricingRphToComparableString(mixed $rph): ?string
    {
        if (is_int($rph) && $rph > 0) {
            return (string) $rph;
        }
        if (is_string($rph)) {
            $trimmed = trim($rph);
            if ($trimmed !== '' && ctype_digit($trimmed) && (int) $trimmed > 0) {
                return (string) (int) $trimmed;
            }
        }

        return null;
    }

    /**
     * Sabre v2.4 Brand.RPH wire value must be a positive integer.
     */
    public function normalizeIatiV24BrandRphToSchemaInteger(mixed $rph): ?int
    {
        if (is_int($rph) && $rph > 0) {
            return $rph;
        }
        if (is_string($rph)) {
            $trimmed = trim($rph);
            if ($trimmed !== '' && ctype_digit($trimmed) && (int) $trimmed > 0) {
                return (int) $trimmed;
            }
        }

        return null;
    }

    public function iatiV24BrandRphWireValueIsSchemaValid(mixed $rph): bool
    {
        return is_int($rph) && $rph > 0;
    }

    /**
     * @param  array<string, mixed>  $wire
     * @return list<array{rph: string, rph_raw: mixed, rph_type: string, code: string}>
     */
    public function extractTraditionalPnrBrandRows(array $wire): array
    {
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null)
            ? $wire['CreatePassengerNameRecordRQ']
            : $wire;
        $ap = is_array($cpnr['AirPrice'] ?? null) ? $cpnr['AirPrice'] : [];
        $first = is_array($ap[0] ?? null) ? $ap[0] : [];
        $brand = data_get(
            $first,
            'PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand',
        );
        if (! is_array($brand)) {
            return [];
        }
        $rows = array_is_list($brand) ? $brand : [$brand];
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                if (is_string($row) && trim($row) !== '') {
                    $out[] = ['rph' => '', 'rph_raw' => null, 'rph_type' => 'null', 'code' => strtoupper(trim($row))];
                }

                continue;
            }
            $code = strtoupper(trim((string) (
                $row['content'] ?? $row['Code'] ?? $row['value'] ?? $row['text'] ?? ''
            )));
            $rphRaw = $row['RPH'] ?? null;
            $normalized = $this->normalizeIatiV24PricingRphToComparableString($rphRaw);
            $out[] = [
                'rph' => $normalized ?? '',
                'rph_raw' => $rphRaw,
                'rph_type' => $rphRaw === null ? 'null' : gettype($rphRaw),
                'code' => $code,
            ];
        }

        return $out;
    }

    /**
     * Safe diagnostics for IATI v2.4 Brand ↔ ItineraryOptions.SegmentSelect RPH pairing (no PII).
     *
     * @param  array<string, mixed>  $wire
     * @return array{
     *     brand_present: bool,
     *     brand_code: string|null,
     *     brand_rph_present: bool,
     *     brand_rph_type: string|null,
     *     brand_rph_values: list<string>|null,
     *     brand_rph_values_raw: list<int|string>|null,
     *     brand_rph_values_normalized: list<string>|null,
     *     brand_rph_schema_valid: bool,
     *     brand_segmentselect_pairing_required: bool,
     *     brand_segmentselect_pairing_complete: bool,
     *     brand_segmentselect_pairing_values_match_normalized: bool,
     *     brand_segmentselect_missing_rph: list<string>|null,
     *     brand_wire_keys: list<string>|null,
     *     brand_wire_shape: string|null,
     *     brand_schema_valid: bool,
     *     brand_schema_rejected_pointer: string|null,
     *     brand_schema_rejected_message: string|null,
     *     brand_omitted_for_mixed_v24_segmentselect: bool,
     *     brand_omission_reason: string|null
     * }
     */
    public function inspectIatiV24BrandSegmentSelectPairing(array $wire, ?string $resolvedBrandCode = null): array
    {
        $allowedRowKeys = ['RPH', 'content', 'Code'];
        $rejected = [];
        $wireKeys = [];

        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null)
            ? $wire['CreatePassengerNameRecordRQ']
            : $wire;
        $ap = is_array($cpnr['AirPrice'] ?? null) ? $cpnr['AirPrice'] : [];
        $first = is_array($ap[0] ?? null) ? $ap[0] : [];
        $brandNode = data_get($first, 'PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand');
        if (is_array($brandNode)) {
            $brandRowsRaw = array_is_list($brandNode) ? $brandNode : [$brandNode];
            foreach ($brandRowsRaw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach (array_keys($row) as $key) {
                    $wireKeys[] = $key;
                    if (! in_array($key, $allowedRowKeys, true)) {
                        $rejected[] = $key;
                    }
                }
            }
        }

        $brandRows = $this->extractTraditionalPnrBrandRows($wire);
        $brandPresent = $brandRows !== [];
        $brandCode = null;
        foreach ($brandRows as $row) {
            if (($row['code'] ?? '') !== '') {
                $brandCode = $row['code'];
                break;
            }
        }
        if ($brandCode === null && $resolvedBrandCode !== null && trim($resolvedBrandCode) !== '') {
            $brandCode = strtoupper(trim($resolvedBrandCode));
        }

        $brandRphs = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['rph'] ?? '')),
            $brandRows,
        ), static fn (string $v): bool => $v !== '')));
        sort($brandRphs, SORT_STRING);

        $brandRphsRaw = [];
        $brandRphTypes = [];
        $brandRphSchemaValid = true;
        $schemaRejectedPointer = null;
        $schemaRejectedMessage = null;
        $brandRowsRaw = is_array($brandNode) ? (array_is_list($brandNode) ? $brandNode : [$brandNode]) : [];
        foreach ($brandRows as $index => $row) {
            $wireRow = is_array($brandRowsRaw[$index] ?? null) ? $brandRowsRaw[$index] : [];
            if (! array_key_exists('RPH', $wireRow)) {
                continue;
            }
            $rphRaw = $row['rph_raw'] ?? null;
            $brandRphsRaw[] = $rphRaw;
            $brandRphTypes[] = (string) ($row['rph_type'] ?? ($rphRaw === null ? 'null' : gettype($rphRaw)));
            if (! $this->iatiV24BrandRphWireValueIsSchemaValid($rphRaw)) {
                $brandRphSchemaValid = false;
                $schemaRejectedPointer ??= self::AIRPRICE_BRAND_RPH_REJECTED_POINTER;
                $schemaRejectedMessage ??= 'instance type ('.($rphRaw === null ? 'null' : gettype($rphRaw)).') does not match schema type (integer)';
            }
        }

        $brandRphType = null;
        if ($brandRphTypes !== []) {
            $uniqueTypes = array_values(array_unique($brandRphTypes));
            $brandRphType = count($uniqueTypes) === 1 ? $uniqueTypes[0] : 'mixed';
        }

        $brandRphsNormalized = array_values(array_unique(array_filter(array_map(
            fn (array $row): ?string => $this->normalizeIatiV24PricingRphToComparableString($row['rph_raw'] ?? null),
            $brandRows,
        ))));
        sort($brandRphsNormalized, SORT_STRING);

        $segmentSelectDiag = $this->inspectIatiV24CommandPricingSegmentSelectPairing($wire);
        $ssRphs = is_array($segmentSelectDiag['segment_select_rph_values'] ?? null)
            ? array_values($segmentSelectDiag['segment_select_rph_values'])
            : [];
        $segmentSelectPresent = ($segmentSelectDiag['segment_select_present'] ?? false) === true;

        $pairingRequired = $segmentSelectPresent && $brandPresent;
        $missingRph = [];
        if ($pairingRequired) {
            if ($brandRphsNormalized === []) {
                $missingRph = $ssRphs;
            } else {
                $missingRph = array_values(array_diff($ssRphs, $brandRphsNormalized));
            }
        }
        $pairingValuesMatch = ! $pairingRequired
            || ($brandRphsNormalized !== [] && $brandRphsNormalized === $ssRphs);
        $pairingComplete = ! $pairingRequired
            || ($missingRph === [] && $brandRphsNormalized !== [] && $pairingValuesMatch);

        $wireShape = null;
        if ($brandPresent) {
            if ($pairingRequired && $brandRphsRaw !== []) {
                $wireShape = $brandRphSchemaValid ? 'object_rph_integer_content' : 'object_rph_invalid_type_content';
            } elseif ($brandRphsRaw === []) {
                $wireShape = 'object_content';
            } else {
                $wireShape = 'object_content';
            }
        }

        $schemaValid = $rejected === []
            && $brandRphSchemaValid
            && (! $pairingRequired || $brandRphsNormalized !== []);

        $rejected = array_values(array_unique($rejected));
        $wireKeys = array_values(array_unique($wireKeys));

        return [
            'brand_present' => $brandPresent,
            'brand_code' => $brandCode,
            'brand_rph_present' => $brandRphsNormalized !== [],
            'brand_rph_type' => $brandRphType,
            'brand_rph_values' => $brandRphs !== [] ? $brandRphs : null,
            'brand_rph_values_raw' => $brandRphsRaw !== [] ? $brandRphsRaw : null,
            'brand_rph_values_normalized' => $brandRphsNormalized !== [] ? $brandRphsNormalized : null,
            'brand_rph_schema_valid' => $brandRphSchemaValid,
            'brand_segmentselect_pairing_required' => $pairingRequired,
            'brand_segmentselect_pairing_complete' => $pairingComplete,
            'brand_segmentselect_pairing_values_match_normalized' => $pairingValuesMatch,
            'brand_segmentselect_missing_rph' => $missingRph !== [] ? $missingRph : null,
            'brand_wire_keys' => $wireKeys !== [] ? $wireKeys : null,
            'brand_wire_shape' => $wireShape,
            'brand_schema_valid' => $schemaValid,
            'brand_schema_rejected_pointer' => $schemaRejectedPointer,
            'brand_schema_rejected_message' => $schemaRejectedMessage,
            'brand_omitted_for_mixed_v24_segmentselect' => false,
            'brand_omission_reason' => null,
        ];
    }

    /**
     * Safe diagnostics for IATI mixed-carrier CommandPricing ↔ segment carrier mapping (no PII).
     *
     * @param  array<string, mixed>  $wire
     * @param  list<array<string, mixed>>  $segments
     * @param  list<array<string, mixed>>  $fareComponentRows
     * @return array<string, mixed>
     */
    public function summarizeIatiMixedCarrierCommandPricingMapping(
        array $wire,
        array $segments,
        array $fareComponentRows = [],
        array $mappingContext = [],
    ): array {
        $apiDraftSegments = is_array($mappingContext['api_draft_segments'] ?? null)
            ? array_values($mappingContext['api_draft_segments'])
            : [];
        $marketingCarrierChain = is_array($mappingContext['marketing_carrier_chain'] ?? null)
            ? array_values($mappingContext['marketing_carrier_chain'])
            : [];
        $resolved = $this->resolveMixedCarrierMappingExpectedCarriers(
            $segments,
            $apiDraftSegments,
            $fareComponentRows,
            $marketingCarrierChain,
        );
        $expectedCarriers = $resolved['expected_carriers'];
        $expectedCarriersProven = ($resolved['expected_carriers_proven'] ?? false) === true;
        $segmentCount = (int) ($resolved['segment_count'] ?? count($expectedCarriers));

        $cpRows = $this->extractTraditionalPnrCommandPricingRows($wire);
        $sellRowCarriers = is_array($mappingContext['airbook_sell_carriers'] ?? null)
            ? array_values(array_map(
                static fn ($c): string => strtoupper(trim((string) $c)),
                $mappingContext['airbook_sell_carriers'],
            ))
            : [];
        $associationCarriers = [];
        for ($i = 0; $i < $segmentCount; $i++) {
            $snap = is_array($segments[$i] ?? null) ? $segments[$i] : [];
            $draft = is_array($apiDraftSegments[$i] ?? null) ? $apiDraftSegments[$i] : [];
            $merged = array_merge($snap, $draft);
            $fromSell = strtoupper(trim((string) ($sellRowCarriers[$i] ?? '')));
            if ($fromSell !== '') {
                $associationCarriers[] = $fromSell;
                continue;
            }
            $fromContext = strtoupper(trim((string) (
                $this->traditionalPnrSegmentMarketingCarrierCode($merged) ?? ''
            )));
            $associationCarriers[] = $fromContext;
        }
        $commandPricingCarriers = $associationCarriers;
        $fareComponentCarriers = array_values(array_filter(array_map(
            static fn (array $row): string => strtoupper(trim((string) ($row['carrier'] ?? ''))),
            $fareComponentRows,
        ), static fn (string $c): bool => $c !== ''));
        $uniqueExpected = array_values(array_unique(array_filter($expectedCarriers, static fn (string $c): bool => $c !== '')));
        $uniqueCommandPricing = array_values(array_unique($commandPricingCarriers));
        $uniqueFareComponent = array_values(array_unique($fareComponentCarriers));
        $isMixed = count($uniqueExpected) > 1 || count($uniqueCommandPricing) > 1;
        $missingReasons = [];

        if ($segmentCount <= 0) {
            $missingReasons[] = 'segment_context_empty';
        }
        if (! $expectedCarriersProven) {
            $missingReasons[] = 'segment_marketing_carrier_missing';
        }
        if ($cpRows === []) {
            $missingReasons[] = 'command_pricing_rows_missing';
        }
        if (count($cpRows) < $segmentCount) {
            $missingReasons[] = 'command_pricing_row_count_below_segment_count';
        }
        foreach ($expectedCarriers as $i => $expected) {
            if ($expected === '') {
                continue;
            }
            $actual = $commandPricingCarriers[$i] ?? '';
            if ($actual !== '' && $actual !== $expected) {
                $missingReasons[] = 'command_pricing_carrier_segment_mismatch';
                break;
            }
        }
        if ($isMixed && $commandPricingCarriers === []) {
            $missingReasons[] = 'airbook_segment_carrier_missing';
        }
        if ($isMixed && count($uniqueCommandPricing) <= 1 && count(array_filter($commandPricingCarriers, static fn (string $c): bool => $c !== '')) > 0) {
            $missingReasons[] = 'segment_carrier_chain_not_mixed';
        }
        $schemaDiag = $this->inspectIatiV24CommandPricingSchema($wire);
        $schemaValid = ($schemaDiag['command_pricing_schema_valid'] ?? false) === true;
        if (! $schemaValid) {
            $missingReasons[] = 'command_pricing_schema_invalid';
        }
        $pairingDiag = $this->inspectIatiV24CommandPricingSegmentSelectPairing($wire);
        $pairingComplete = ($pairingDiag['command_pricing_segmentselect_pairing_complete'] ?? false) === true;
        if (! $pairingComplete && $cpRows !== []) {
            $missingReasons[] = 'command_pricing_segmentselect_pairing_missing';
        }
        $wireProvesMapping = $expectedCarriersProven
            && $schemaValid
            && $pairingComplete
            && $segmentCount > 0
            && count($cpRows) >= $segmentCount
            && count(array_filter($commandPricingCarriers, static fn (string $c): bool => $c !== '')) >= $segmentCount
            && ! in_array('command_pricing_carrier_segment_mismatch', $missingReasons, true)
            && ! in_array('airbook_segment_carrier_missing', $missingReasons, true)
            && (! $isMixed || count($uniqueCommandPricing) > 1);
        foreach ($expectedCarriers as $i => $expected) {
            if ($expected === '') {
                continue;
            }
            if (($commandPricingCarriers[$i] ?? '') !== $expected) {
                $wireProvesMapping = false;
                break;
            }
        }
        if (! $wireProvesMapping && $isMixed && $fareComponentRows !== []) {
            if (count($fareComponentCarriers) < $segmentCount) {
                $missingReasons[] = 'fare_component_carrier_rows_incomplete';
            }
            foreach ($fareComponentCarriers as $i => $fcCarrier) {
                $expected = $expectedCarriers[$i] ?? '';
                if ($expected !== '' && $fcCarrier !== '' && $fcCarrier !== $expected) {
                    $missingReasons[] = 'fare_component_carrier_segment_mismatch';
                    break;
                }
            }
        }
        if (! $wireProvesMapping && $isMixed && $commandPricingCarriers === [] && $fareComponentRows === []) {
            $missingReasons[] = 'fare_component_carrier_mapping_unavailable';
        }
        if ($isMixed && ! $expectedCarriersProven) {
            $missingReasons[] = 'fare_component_carrier_mapping_unavailable';
        }

        $missingReasons = array_values(array_unique($missingReasons));
        $carrierComplete = $wireProvesMapping
            && $expectedCarriersProven
            && $pairingComplete
            && $missingReasons === [];
        $comparison = 'unavailable';
        if ($segmentCount > 0 && $cpRows !== [] && $expectedCarriersProven) {
            $comparison = $carrierComplete ? 'match' : 'mismatch';
        } elseif ($segmentCount > 0 && $cpRows !== [] && ! $expectedCarriersProven) {
            $comparison = 'unavailable';
        }

        $fareBasisCount = count(array_filter($cpRows, static fn (array $row): bool => trim((string) ($row['fare_basis'] ?? '')) !== ''));
        $sellRowRbds = is_array($mappingContext['airbook_sell_rbds'] ?? null)
            ? array_values($mappingContext['airbook_sell_rbds'])
            : [];
        $rbdCount = count(array_filter($sellRowRbds, static fn ($v): bool => trim((string) $v) !== ''));
        $segmentRefCount = count($this->extractTraditionalPnrSegmentSelectRows($wire));

        return [
            'mixed_fare_carrier_mapping_complete' => $carrierComplete,
            'command_pricing_schema_valid' => $schemaValid,
            'command_pricing_rejected_keys' => $schemaDiag['command_pricing_rejected_keys'] ?? null,
            'command_pricing_allowed_shape' => $schemaDiag['command_pricing_allowed_shape'] ?? 'RPH+FareBasis.Code',
            'command_pricing_wire_keys' => $schemaDiag['command_pricing_wire_keys'] ?? null,
            'segment_select_present' => ($pairingDiag['segment_select_present'] ?? false) === true,
            'segment_select_rph_count' => (int) ($pairingDiag['segment_select_rph_count'] ?? 0),
            'segment_select_rph_values' => $pairingDiag['segment_select_rph_values'] ?? null,
            'command_pricing_rph_values' => $pairingDiag['command_pricing_rph_values'] ?? null,
            'command_pricing_segmentselect_pairing_complete' => $pairingComplete,
            'command_pricing_segmentselect_missing_rph' => $pairingDiag['command_pricing_segmentselect_missing_rph'] ?? null,
            'fare_component_count' => max(count($uniqueFareComponent), count($cpRows)),
            'fare_component_carrier_count' => count($uniqueFareComponent) > 0 ? count($uniqueFareComponent) : count($uniqueExpected),
            'fare_component_carriers' => $uniqueFareComponent !== [] ? $uniqueFareComponent : null,
            'segment_marketing_carrier_count' => $expectedCarriersProven ? count($expectedCarriers) : count(array_filter($expectedCarriers, static fn (string $c): bool => $c !== '')),
            'segment_marketing_carriers' => $expectedCarriersProven ? $expectedCarriers : null,
            'command_pricing_carrier_count' => count($uniqueCommandPricing),
            'command_pricing_carriers' => $commandPricingCarriers !== [] ? $commandPricingCarriers : null,
            'command_pricing_fare_basis_count' => $fareBasisCount,
            'command_pricing_rbd_count' => $rbdCount,
            'command_pricing_segment_ref_count' => $segmentRefCount,
            'mixed_mapping_missing_reasons' => $missingReasons !== [] ? $missingReasons : null,
            'mixed_mapping_expected_carriers' => $expectedCarriersProven ? $expectedCarriers : null,
            'mixed_mapping_actual_carriers' => $commandPricingCarriers !== [] ? $commandPricingCarriers : null,
            'mixed_mapping_comparison_result' => $comparison,
            'mapping_unavailable' => $isMixed && (
                ! $expectedCarriersProven
                || in_array('fare_component_carrier_mapping_unavailable', $missingReasons, true)
            ),
        ];
    }

    /**
     * Resolve per-segment marketing carriers for mixed mapping comparison (supplier context only).
     *
     * @param  list<array<string, mixed>>  $snapshotSegments
     * @param  list<array<string, mixed>>  $apiDraftSegments
     * @param  list<array<string, mixed>>  $fareComponentRows
     * @param  list<string>  $marketingCarrierChain
     * @return array{expected_carriers: list<string>, expected_carriers_proven: bool, segment_count: int}
     */
    public function resolveMixedCarrierMappingExpectedCarriers(
        array $snapshotSegments,
        array $apiDraftSegments = [],
        array $fareComponentRows = [],
        array $marketingCarrierChain = [],
    ): array {
        $segmentCount = max(
            count($snapshotSegments),
            count($apiDraftSegments),
            count($fareComponentRows),
            count($marketingCarrierChain),
        );
        $expectedCarriers = [];
        for ($i = 0; $i < $segmentCount; $i++) {
            $snap = is_array($snapshotSegments[$i] ?? null) ? $snapshotSegments[$i] : [];
            $draft = is_array($apiDraftSegments[$i] ?? null) ? $apiDraftSegments[$i] : [];
            $fc = is_array($fareComponentRows[$i] ?? null) ? $fareComponentRows[$i] : [];
            $merged = array_merge($snap, $draft);
            $carrier = $this->traditionalPnrSegmentMarketingCarrierCode($merged);
            if ($carrier === null && $fc !== []) {
                $carrier = $this->traditionalPnrSanitizeCarrierTokenForAirPriceCompare($fc['carrier'] ?? null);
            }
            if ($carrier === null && isset($marketingCarrierChain[$i])) {
                $carrier = $this->traditionalPnrSanitizeCarrierTokenForAirPriceCompare($marketingCarrierChain[$i]);
            }
            $expectedCarriers[] = $carrier ?? '';
        }
        $nonEmptyCount = count(array_filter($expectedCarriers, static fn (string $c): bool => $c !== ''));

        return [
            'expected_carriers' => $expectedCarriers,
            'expected_carriers_proven' => $segmentCount > 0 && $nonEmptyCount === $segmentCount,
            'segment_count' => $segmentCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $wire
     * @return list<array{rph: string, fare_basis: string, airline: string, rbd: string}>
     */
    public function extractTraditionalPnrCommandPricingRows(array $wire): array
    {
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null)
            ? $wire['CreatePassengerNameRecordRQ']
            : $wire;
        $ap = is_array($cpnr['AirPrice'] ?? null) ? $cpnr['AirPrice'] : [];
        $first = is_array($ap[0] ?? null) ? $ap[0] : [];
        $pq = data_get($first, 'PriceRequestInformation.OptionalQualifiers.PricingQualifiers', []);
        if (! is_array($pq)) {
            return [];
        }
        $commandPricing = $pq['CommandPricing'] ?? null;
        if (! is_array($commandPricing)) {
            return [];
        }
        $rows = array_is_list($commandPricing) ? $commandPricing : [$commandPricing];
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $fb = $row['FareBasis'] ?? null;
            $fbCode = is_array($fb) ? trim((string) ($fb['Code'] ?? '')) : trim((string) $fb);
            $airline = data_get($row, 'Airline.Code', '');
            if (! is_string($airline) || trim($airline) === '') {
                $airline = trim((string) ($row['Airline'] ?? ''));
            }
            $out[] = [
                'rph' => trim((string) ($row['RPH'] ?? '')),
                'fare_basis' => strtoupper($fbCode),
                'airline' => strtoupper(trim((string) $airline)),
                'rbd' => strtoupper(trim((string) ($row['ResBookDesigCode'] ?? ''))),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $wire
     * @return list<array{rph: string, number: string}>
     */
    public function extractTraditionalPnrSegmentSelectRows(array $wire): array
    {
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null)
            ? $wire['CreatePassengerNameRecordRQ']
            : $wire;
        $ap = is_array($cpnr['AirPrice'] ?? null) ? $cpnr['AirPrice'] : [];
        $first = is_array($ap[0] ?? null) ? $ap[0] : [];
        $segmentSelect = data_get(
            $first,
            'PriceRequestInformation.OptionalQualifiers.PricingQualifiers.ItineraryOptions.SegmentSelect',
        );
        if (! is_array($segmentSelect)) {
            return [];
        }
        $rows = array_is_list($segmentSelect) ? $segmentSelect : [$segmentSelect];
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = [
                'rph' => trim((string) ($row['RPH'] ?? '')),
                'number' => trim((string) ($row['Number'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    protected function segmentMarketingCarrierList(array $segments): array
    {
        $out = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                $out[] = '';
                continue;
            }
            $out[] = strtoupper(trim((string) (
                $this->traditionalPnrSegmentMarketingCarrierCode($seg) ?? ''
            )));
        }

        return $out;
    }

    /**
     * V25-CPNR: Omit Brand qualifier from wire (Sabre v2.5 REST rejects object-at-0 and string-at-0).
     * Selected brand remains in {@code sabre_booking_context} / booking meta only.
     *
     * @param  array<string, mixed>  $cpnr
     */
    protected function traditionalPnrApplyGdsV25RootAirPriceBrandQualifier(array $cpnr, string $brandCode): array
    {
        if (! isset($cpnr['AirPrice']) || ! is_array($cpnr['AirPrice']) || ! array_is_list($cpnr['AirPrice']) || $cpnr['AirPrice'] === []) {
            return $cpnr;
        }
        $ap = $cpnr['AirPrice'];
        $first = is_array($ap[0] ?? null) ? $ap[0] : [];
        $pri = is_array($first['PriceRequestInformation'] ?? null) ? $first['PriceRequestInformation'] : [];
        $oq = is_array($pri['OptionalQualifiers'] ?? null) ? $pri['OptionalQualifiers'] : [];
        $pq = is_array($oq['PricingQualifiers'] ?? null) ? $oq['PricingQualifiers'] : [];
        $brandNode = $this->resolveAirPriceBrandNodeForV25GdsWire($brandCode);
        if ($brandNode === null) {
            unset($pq['Brand']);
        } else {
            $pq['Brand'] = $brandNode;
        }
        $oq['PricingQualifiers'] = $pq;
        $pri['OptionalQualifiers'] = $oq;
        $first['PriceRequestInformation'] = $pri;
        $ap[0] = $first;
        $cpnr['AirPrice'] = array_values($ap);

        return $cpnr;
    }

    /**
     * @return list<string>|null null = omit Brand (production v2.5 GDS default)
     */
    protected function resolveAirPriceBrandNodeForV25GdsWire(string $brandCode): ?array
    {
        return null;
    }

    /**
     * V25-CPNR: Strip v2.4/IATI-style PricingQualifiers not proven schema-safe on Sabre v2.5 REST.
     *
     * @param  array<string, mixed>  $cpnr
     * @return array<string, mixed>
     */
    protected function traditionalPnrHardenGdsV25AirPriceOptionalQualifiers(array $cpnr): array
    {
        if (! isset($cpnr['AirPrice']) || ! is_array($cpnr['AirPrice']) || ! array_is_list($cpnr['AirPrice']) || $cpnr['AirPrice'] === []) {
            return $cpnr;
        }

        $ap = $cpnr['AirPrice'];
        $first = is_array($ap[0] ?? null) ? $ap[0] : [];
        $pri = is_array($first['PriceRequestInformation'] ?? null) ? $first['PriceRequestInformation'] : [];
        $oq = is_array($pri['OptionalQualifiers'] ?? null) ? $pri['OptionalQualifiers'] : [];
        $pq = is_array($oq['PricingQualifiers'] ?? null) ? $oq['PricingQualifiers'] : [];

        foreach (self::V25_GDS_FORBIDDEN_PRICING_QUALIFIER_KEYS as $forbiddenKey) {
            unset($pq[$forbiddenKey]);
        }

        foreach (array_keys($pq) as $pqKey) {
            if (! is_string($pqKey) || in_array($pqKey, self::V25_GDS_ALLOWED_PRICING_QUALIFIER_KEYS, true)) {
                continue;
            }
            unset($pq[$pqKey]);
        }

        unset($pq['Brand'], $pq['ItineraryOptions'], $pq['ValidatingCarrier']);

        $oq['PricingQualifiers'] = $pq;
        $pri['OptionalQualifiers'] = $oq;
        $first['PriceRequestInformation'] = $pri;
        $ap[0] = $first;
        $cpnr['AirPrice'] = array_values($ap);

        return $cpnr;
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    public static function v25OptionalQualifierCustomerMessageFromOutcome(array $outcome): ?string
    {
        if (($outcome['safe_reason_code'] ?? '') === self::V25_AIRPRICE_OPTIONAL_QUALIFIER_SCHEMA_ERROR) {
            return (string) __(self::V25_GDS_OPTIONAL_QUALIFIER_CUSTOMER_MESSAGE);
        }

        $msg = trim((string) ($outcome['customer_safe_message'] ?? ''));
        if ($msg !== '' && $msg === self::V25_GDS_OPTIONAL_QUALIFIER_CUSTOMER_MESSAGE) {
            return (string) __($msg);
        }

        return $msg !== '' ? $msg : null;
    }

    /**
     * V25-CPNR: Safe structural digest for AirPrice PricingQualifiers (no PII, no raw payload).
     *
     * @param  array<string, mixed>  $wire  Stripped wire or {@code CreatePassengerNameRecordRQ} block
     * @param  array<string, mixed>  $context  Optional {@code brand_code} / {@code selected_brand_code} from booking context
     * @return array<string, mixed>
     */
    public function summarizeV25AirPricePricingQualifiersStructuralDigest(array $wire, array $context = []): array
    {
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null)
            ? $wire['CreatePassengerNameRecordRQ']
            : $wire;
        $pq = data_get($cpnr, 'AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers');
        $pqArray = is_array($pq) ? $pq : [];
        $oq = data_get($cpnr, 'AirPrice.0.PriceRequestInformation.OptionalQualifiers');
        $oqArray = is_array($oq) ? $oq : [];
        $vcCode = $this->traditionalPnrExtractValidatingCarrierCodeFromAirPriceOptionalQualifiers($oqArray);
        $brandShape = $this->classifyAirPriceQualifierNodeShape($pqArray['Brand'] ?? null);
        $brandOnWire = $brandShape !== 'missing';
        $selectedBrand = strtoupper(trim((string) (
            $context['selected_brand_code']
            ?? $context['brand_code']
            ?? ''
        )));
        $selectedBrandPresent = $selectedBrand !== '' && preg_match('/^[A-Z0-9]{2,16}$/', $selectedBrand) === 1;
        $fareBasisPresent = $this->classifyV25CommandPricingFareBasisShape($pqArray['CommandPricing'] ?? null) !== 'missing';
        $commandPricingShape = $this->classifyAirPriceQualifierNodeShape($pqArray['CommandPricing'] ?? null);
        $commandPricingPresent = $commandPricingShape !== 'missing';
        $itineraryOptions = $pqArray['ItineraryOptions'] ?? null;
        $itineraryOptionsShape = $this->classifyAirPriceQualifierNodeShape($itineraryOptions);
        $segmentSelect = is_array($itineraryOptions) ? ($itineraryOptions['SegmentSelect'] ?? null) : null;
        $segmentSelectShape = $this->classifyAirPriceQualifierNodeShape($segmentSelect);
        $segmentSelectPresent = $segmentSelectShape !== 'missing';
        $manualTicketingMarker = data_get($cpnr, 'TravelItineraryAddInfo.AgencyInfo.Ticketing.TicketType');
        $manualTicketingMarkerPresent = is_string($manualTicketingMarker) && strtoupper(trim($manualTicketingMarker)) === '7TAW';

        $digest = [
            'pricing_qualifiers_present' => $pqArray !== [],
            'pricing_qualifier_keys' => array_slice(array_values(array_map('strval', array_keys($pqArray))), 0, 12),
            'command_pricing_present' => $commandPricingPresent,
            'command_pricing_shape' => $commandPricingShape,
            'brand_qualifier_present' => $brandOnWire,
            'brand_qualifier_shape' => $brandShape,
            'itinerary_options_present' => $itineraryOptionsShape !== 'missing',
            'itinerary_options_shape' => $itineraryOptionsShape,
            'segment_select_present' => $segmentSelectPresent,
            'segment_select_shape' => $segmentSelectShape,
            'fare_basis_shape' => $this->classifyV25CommandPricingFareBasisShape($pqArray['CommandPricing'] ?? null),
            'fare_basis_present' => $fareBasisPresent,
            'validating_carrier_shape' => $vcCode !== null ? 'string' : 'missing',
            'validating_carrier_present' => $vcCode !== null,
            'manual_ticketing_marker_present' => $manualTicketingMarkerPresent,
            'selected_brand_code_present' => $selectedBrandPresent,
            'selected_brand_code_context_only' => $selectedBrandPresent && ! $brandOnWire,
            'ticket_issuance_attempted' => false,
            'airticket_attempted' => false,
        ];

        if ($selectedBrandPresent && ! $brandOnWire) {
            $digest['brand_qualifier_omitted_reason'] = self::V25_GDS_BRAND_QUALIFIER_OMITTED_REASON;
        }

        if ($fareBasisPresent && ! $segmentSelectPresent) {
            $digest['segment_select_omitted_reason'] = self::V25_GDS_SEGMENT_SELECT_OMITTED_REASON;
        }

        return $digest;
    }

    /**
     * @return 'missing'|'string'|'array'|'object'
     */
    public function classifyAirPriceQualifierNodeShape(mixed $node): string
    {
        if ($node === null || $node === false || $node === '') {
            return 'missing';
        }
        if (is_string($node)) {
            return 'string';
        }
        if (is_array($node)) {
            return array_is_list($node) ? 'array' : 'object';
        }

        return 'object';
    }

    /**
     * @return 'missing'|'string'|'array'|'object'
     */
    protected function classifyV25CommandPricingFareBasisShape(mixed $commandPricing): string
    {
        if ($commandPricing === null || $commandPricing === false) {
            return 'missing';
        }
        $rows = is_array($commandPricing)
            ? (array_is_list($commandPricing) ? $commandPricing : [$commandPricing])
            : [];
        if ($rows === []) {
            return 'missing';
        }
        $first = $rows[0] ?? null;
        if (! is_array($first)) {
            return 'missing';
        }
        $fb = $first['FareBasis'] ?? null;
        if ($fb === null) {
            return 'missing';
        }
        if (is_string($fb)) {
            return 'string';
        }
        if (is_array($fb)) {
            return array_is_list($fb) ? 'array' : 'object';
        }

        return 'object';
    }

    protected function traditionalPnrSanitizeFareBasisCodeForGdsAirPrice(mixed $raw): ?string
    {
        $fb = strtoupper(trim((string) ($raw ?? '')));
        if ($fb === '') {
            return null;
        }
        if (preg_match('/^[A-Z0-9\\/\\-]{3,24}$/', $fb) !== 1) {
            return null;
        }

        return substr($fb, 0, 24);
    }

    protected function traditionalPnrApplyRootAirPriceFlightQualifiersVendorPrefsValidatingCarrier(array $cpnr, array $internalDraft): array
    {
        if (! isset($cpnr['AirPrice']) || ! is_array($cpnr['AirPrice']) || ! array_is_list($cpnr['AirPrice']) || $cpnr['AirPrice'] === []) {
            return $cpnr;
        }
        $ap = $cpnr['AirPrice'];
        $first = is_array($ap[0] ?? null) ? $ap[0] : [];
        $pri = is_array($first['PriceRequestInformation'] ?? null) ? $first['PriceRequestInformation'] : [];
        $oq = is_array($pri['OptionalQualifiers'] ?? null) ? $pri['OptionalQualifiers'] : [];
        $pq = is_array($oq['PricingQualifiers'] ?? null) ? $oq['PricingQualifiers'] : [];
        unset($pq['ValidatingCarrier']);
        $oq['PricingQualifiers'] = $pq;

        $vc = $this->traditionalPnrSanitizeCarrierTokenForAirPriceCompare($internalDraft['validating_carrier'] ?? null);
        if ($vc !== null) {
            $fq = is_array($oq['FlightQualifiers'] ?? null) ? $oq['FlightQualifiers'] : [];
            $fq['VendorPrefs'] = ['Airline' => ['Code' => $vc]];
            $oq['FlightQualifiers'] = $fq;
        } else {
            unset($oq['FlightQualifiers']);
        }

        $pri['OptionalQualifiers'] = $oq;
        $first['PriceRequestInformation'] = $pri;
        $ap[0] = $first;
        $cpnr['AirPrice'] = array_values($ap);

        return $cpnr;
    }

    /**
     * F9K: Sanitized validating-carrier code from AirPrice optional qualifiers (FlightQualifiers first, PricingQualifiers fallback).
     *
     * @param  array<string, mixed>  $optionalQualifiers
     */
    public function traditionalPnrExtractValidatingCarrierCodeFromAirPriceOptionalQualifiers(array $optionalQualifiers): ?string
    {
        $fqCode = data_get($optionalQualifiers, 'FlightQualifiers.VendorPrefs.Airline.Code');
        if (is_string($fqCode) && trim($fqCode) !== '') {
            $sanitized = $this->traditionalPnrSanitizeCarrierTokenForAirPriceCompare($fqCode);

            return $sanitized;
        }

        $pq = is_array($optionalQualifiers['PricingQualifiers'] ?? null)
            ? $optionalQualifiers['PricingQualifiers']
            : [];
        $fromPq = $this->traditionalPnrExtractValidatingCarrierCodesSanitizedFromPricingQualifiers($pq);

        return $fromPq[0] ?? null;
    }

    /**
     * P4: Merge {@code CommandPricing} fare-basis hints from draft segments into root {@code AirPrice[0]} (compare-only).
     *
     * @param  array<string, mixed>  $cpnr
     * @param  array<string, mixed>  $internalDraft
     * @return array<string, mixed>
     */
    protected function traditionalPnrApplyRootAirPricePerSegmentFareBasisCompareQualifier(array $cpnr, array $internalDraft): array
    {
        if (! isset($cpnr['AirPrice']) || ! is_array($cpnr['AirPrice']) || ! array_is_list($cpnr['AirPrice']) || $cpnr['AirPrice'] === []) {
            return $cpnr;
        }
        $segments = is_array($internalDraft['segments'] ?? null) ? array_values($internalDraft['segments']) : [];
        $commandRows = [];
        $segmentSelectRows = [];
        foreach ($segments as $i => $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $fb = strtoupper(trim((string) ($seg['fare_basis_code'] ?? '')));
            if ($fb === '' || ! preg_match('/^[A-Z0-9]{4,15}$/', $fb)) {
                continue;
            }
            $rph = (string) ($i + 1);
            $segmentSelectRows[] = ['RPH' => $rph, 'Number' => $rph];
            $commandRows[] = [
                'RPH' => $rph,
                'FareBasis' => ['Code' => substr($fb, 0, 24)],
            ];
        }
        if ($commandRows === []) {
            return $cpnr;
        }
        $ap = $cpnr['AirPrice'];
        $first = is_array($ap[0] ?? null) ? $ap[0] : [];
        $pri = is_array($first['PriceRequestInformation'] ?? null) ? $first['PriceRequestInformation'] : [];
        $oq = is_array($pri['OptionalQualifiers'] ?? null) ? $pri['OptionalQualifiers'] : [];
        $pq = is_array($oq['PricingQualifiers'] ?? null) ? $oq['PricingQualifiers'] : [];
        $pq['ItineraryOptions'] = [
            'SegmentSelect' => count($segmentSelectRows) === 1
                ? $segmentSelectRows[0]
                : array_values($segmentSelectRows),
        ];
        $pq['CommandPricing'] = count($commandRows) === 1
            ? $commandRows[0]
            : array_values($commandRows);
        $oq['PricingQualifiers'] = $pq;
        $pri['OptionalQualifiers'] = $oq;
        $first['PriceRequestInformation'] = $pri;
        $ap[0] = $first;
        $cpnr['AirPrice'] = array_values($ap);

        return $cpnr;
    }

    /**
     * B79: 2–3 character validating-carrier token for optional AirPrice qualifier (alphanumeric; uppercased).
     */
    protected function traditionalPnrSanitizeCarrierTokenForAirPriceCompare(mixed $raw): ?string
    {
        $c = strtoupper(trim((string) ($raw ?? '')));
        if ($c === '') {
            return null;
        }

        return preg_match('/^[A-Z0-9]{2,3}$/', $c) ? $c : null;
    }

    /**
     * B79: Carrier codes from {@code PricingQualifiers.ValidatingCarrier} for wire diagnostics (no PII).
     *
     * @param  array<string, mixed>  $pricingQualifiers
     * @return list<string>
     */
    protected function traditionalPnrExtractValidatingCarrierCodesSanitizedFromPricingQualifiers(array $pricingQualifiers): array
    {
        $vcRaw = $pricingQualifiers['ValidatingCarrier'] ?? null;
        $out = [];
        if (is_array($vcRaw)) {
            if (isset($vcRaw['Code'])) {
                $one = strtoupper(trim((string) $vcRaw['Code']));
                if (preg_match('/^[A-Z0-9]{2,3}$/', $one)) {
                    $out[] = $one;
                }
            } elseif (array_is_list($vcRaw)) {
                foreach ($vcRaw as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $one = strtoupper(trim((string) ($row['Code'] ?? '')));
                    if (preg_match('/^[A-Z0-9]{2,3}$/', $one)) {
                        $out[] = $one;
                    }
                }
            }
        }
        sort($out);

        return array_slice(array_values(array_unique($out)), 0, 8);
    }

    /**
     * @return array<string, int>
     */
    protected function traditionalPnrCountPassengerTypesForRootAirPrice(array $passengers): array
    {
        $counts = ['ADT' => 0, 'CNN' => 0, 'INF' => 0];
        foreach ($passengers as $p) {
            if (! is_array($p)) {
                continue;
            }
            $code = $this->traditionalPnrMapPassengerRowToAirPricePassengerTypeCode($p);
            if (! isset($counts[$code])) {
                $code = 'ADT';
            }
            $counts[$code]++;
        }

        return $counts;
    }

    /**
     * B59: Map internal draft passenger row to Sabre {@code AirPrice...PassengerType} {@code Code} (ADT/CNN/INF).
     * Child fares use {@code CNN} (IATI-style); {@see passengerTypeToSabreCode} uses {@code CHD} for Trip Orders —
     * traditional CPNR pricing qualifiers normalize {@code CHD} → {@code CNN} here only.
     */
    protected function traditionalPnrMapPassengerRowToAirPricePassengerTypeCode(array $p): string
    {
        $typeUpper = strtoupper(trim((string) ($p['type'] ?? '')));
        $ptLower = strtolower(trim((string) ($p['passenger_type'] ?? '')));
        if ($typeUpper === 'INF' || $ptLower === 'infant') {
            return 'INF';
        }
        if (
            in_array($typeUpper, ['CNN', 'CHD'], true)
            || in_array($ptLower, ['child', 'children'], true)
        ) {
            return 'CNN';
        }

        return 'ADT';
    }

    /**
     * B58: Passenger Records {@code TravelItineraryAddInfo.CustomerInfo.Email} must be a JSON **array** of rows; each row
     * with a non-empty {@code Address} includes {@code Type=TO} (Binham IATI GDS parity). Coerces a single associative
     * email object into a one-element list and drops empty address rows.
     *
     * @param  array<string, mixed>  $customerInfo  {@code TravelItineraryAddInfo.CustomerInfo}
     * @return array<string, mixed>
     */
    protected function traditionalPnrNormalizeCustomerInfoEmailForTraditionalPnr(array $customerInfo): array
    {
        if (! array_key_exists('Email', $customerInfo)) {
            return $customerInfo;
        }
        $em = $customerInfo['Email'];
        if ($em === null || $em === [] || $em === false) {
            unset($customerInfo['Email']);

            return $customerInfo;
        }
        if (! is_array($em)) {
            return $customerInfo;
        }
        $rows = array_is_list($em) ? $em : [$em];
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $addr = trim((string) ($row['Address'] ?? ''));
            if ($addr === '') {
                continue;
            }
            $row['Address'] = $addr;
            $row['Type'] = 'TO';
            $out[] = $row;
        }
        if ($out === []) {
            unset($customerInfo['Email']);
        } else {
            $customerInfo['Email'] = array_values($out);
        }

        return $customerInfo;
    }

    /**
     * True when CPNR carries non-ticketing / manual markers (structure walk only; no serialized PII dump).
     */
    protected function traditionalPnrWireHasManualTicketingMarker(array $cpnr): bool
    {
        $tia = is_array($cpnr['TravelItineraryAddInfo'] ?? null) ? $cpnr['TravelItineraryAddInfo'] : [];
        $ticketing = is_array(data_get($tia, 'AgencyInfo.Ticketing')) ? data_get($tia, 'AgencyInfo.Ticketing') : [];
        $ticketType = strtoupper(trim((string) ($ticketing['TicketType'] ?? '')));
        if ($ticketType === '7TAW' || str_starts_with($ticketType, 'TAW') || $ticketType === 'TTL') {
            return true;
        }

        $sr = is_array($cpnr['SpecialReqDetails'] ?? null) ? $cpnr['SpecialReqDetails'] : [];
        $svc = is_array($sr['SpecialService']['Service'] ?? null) ? $sr['SpecialService']['Service'] : [];
        if (strtoupper(trim((string) ($svc['SSR_Code'] ?? ''))) === 'OTHS') {
            return true;
        }
        $remarks = $this->traditionalPnrExtractAddRemarkRows($cpnr);
        foreach ($remarks as $r) {
            if (! is_array($r)) {
                continue;
            }
            $t = strtoupper((string) ($r['Text'] ?? ''));
            if ($t !== '' && (str_contains($t, 'MANUAL') || str_contains($t, 'PNR_ONLY') || str_contains($t, 'TICKETING'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * B34: True when {@code $wire} has a non-empty string at a dot path (supports numeric list segments).
     */
    public function wireHasNonEmptyScalarAtDotPath(array $wire, string $dotPath): bool
    {
        $parts = explode('.', $dotPath);
        $cur = $wire;
        foreach ($parts as $seg) {
            if (! is_array($cur)) {
                return false;
            }
            if (ctype_digit($seg)) {
                $idx = (int) $seg;
                if (! array_key_exists($idx, $cur)) {
                    return false;
                }
                $cur = $cur[$idx];
            } else {
                if (! array_key_exists($seg, $cur)) {
                    return false;
                }
                $cur = $cur[$seg];
            }
        }

        return is_string($cur) && trim($cur) !== '';
    }

    /**
     * B34: Safe dot paths where an agency/office phone scalar is present on the wire root (no values).
     *
     * @return list<string>
     */
    public function collectWireAgencyPhonePathsPresent(array $wire): array
    {
        $paths = [];
        $aci = is_array($wire['agencyContactInfo'] ?? null) ? $wire['agencyContactInfo'] : [];
        if (trim((string) ($aci['phone'] ?? '')) !== '') {
            $paths[] = 'agencyContactInfo.phone';
        }
        if (trim((string) ($aci['phoneNumber'] ?? '')) !== '') {
            $paths[] = 'agencyContactInfo.phoneNumber';
        }
        $phones = is_array($aci['phones'] ?? null) ? $aci['phones'] : [];
        foreach (array_slice($phones, 0, 6) as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            if (trim((string) ($row['number'] ?? '')) !== '') {
                $paths[] = 'agencyContactInfo.phones.'.$i.'.number';
            }
            if (trim((string) ($row['phoneNumber'] ?? '')) !== '') {
                $paths[] = 'agencyContactInfo.phones.'.$i.'.phoneNumber';
            }
        }
        $ainfo = is_array($wire['agencyInfo'] ?? null) ? $wire['agencyInfo'] : [];
        if (trim((string) ($ainfo['phone'] ?? '')) !== '') {
            $paths[] = 'agencyInfo.phone';
        }
        $agency = is_array($wire['agency'] ?? null) ? $wire['agency'] : [];
        if (trim((string) ($agency['phone'] ?? '')) !== '') {
            $paths[] = 'agency.phone';
        }
        if (trim((string) ($agency['phoneNumber'] ?? '')) !== '') {
            $paths[] = 'agency.phoneNumber';
        }
        if (trim((string) ($wire['agencyPhone'] ?? '')) !== '') {
            $paths[] = 'agencyPhone';
        }
        $pn = is_array($wire['phoneNumbers'] ?? null) ? $wire['phoneNumbers'] : [];
        foreach (array_slice($pn, 0, 6) as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            if (trim((string) ($row['number'] ?? '')) !== '') {
                $paths[] = 'phoneNumbers.'.$i.'.number';
            }
            if (trim((string) ($row['phoneNumber'] ?? '')) !== '') {
                $paths[] = 'phoneNumbers.'.$i.'.phoneNumber';
            }
        }
        $rootPhones = is_array($wire['phones'] ?? null) ? $wire['phones'] : [];
        foreach (array_slice($rootPhones, 0, 6) as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            if (trim((string) ($row['phoneNumber'] ?? '')) !== '') {
                $paths[] = 'phones.'.$i.'.phoneNumber';
            }
            if (trim((string) ($row['number'] ?? '')) !== '') {
                $paths[] = 'phones.'.$i.'.number';
            }
        }
        $ci = is_array($wire['contactInfo'] ?? null) ? $wire['contactInfo'] : [];
        $ciAgencyPhone = $ci['agencyPhone'] ?? null;
        if (is_string($ciAgencyPhone) && trim($ciAgencyPhone) !== '') {
            $paths[] = 'contactInfo.agencyPhone';
        }
        if (is_array($ciAgencyPhone) && trim((string) ($ciAgencyPhone['Number'] ?? '')) !== '') {
            $paths[] = 'contactInfo.agencyPhone.Number';
        }
        $ciPhones = is_array($ci['phones'] ?? null) ? $ci['phones'] : [];
        foreach (array_slice($ciPhones, 0, 6) as $i => $row) {
            if (is_array($row) && trim((string) ($row['phoneNumber'] ?? '')) !== '') {
                $paths[] = 'contactInfo.phones.'.$i.'.phoneNumber';
            }
        }
        $posBlock = is_array($wire['POS'] ?? null) ? $wire['POS'] : [];
        $sources = is_array($posBlock['Source'] ?? null) ? $posBlock['Source'] : [];
        foreach (array_slice($sources, 0, 4) as $i => $src) {
            if (! is_array($src)) {
                continue;
            }
            $ap = is_array($src['AgencyPhone'] ?? null) ? $src['AgencyPhone'] : [];
            if (trim((string) ($ap['PhoneNumber'] ?? '')) !== '') {
                $paths[] = 'POS.Source.'.$i.'.AgencyPhone.PhoneNumber';
            }
        }
        $posCamel = is_array($wire['pos'] ?? null) ? $wire['pos'] : [];
        $posSrc = is_array($posCamel['source'] ?? null) ? $posCamel['source'] : [];
        if (trim((string) ($posSrc['agencyPhone'] ?? '')) !== '') {
            $paths[] = 'pos.source.agencyPhone';
        }
        $ta = is_array($wire['travelAgency'] ?? null) ? $wire['travelAgency'] : [];
        if (trim((string) ($ta['phoneNumber'] ?? '')) !== '') {
            $paths[] = 'travelAgency.phoneNumber';
        }
        $cust = is_array($wire['customerInfo'] ?? null) ? $wire['customerInfo'] : [];
        if (trim((string) ($cust['agencyPhone'] ?? '')) !== '') {
            $paths[] = 'customerInfo.agencyPhone';
        }
        $pl = is_array($wire['phoneLine'] ?? null) ? $wire['phoneLine'] : [];
        if (trim((string) ($pl['Number'] ?? '')) !== '') {
            $paths[] = 'phoneLine.Number';
        }
        foreach (array_slice(is_array($wire['phoneLines'] ?? null) ? $wire['phoneLines'] : [], 0, 6) as $i => $row) {
            if (is_array($row) && trim((string) ($row['Number'] ?? '')) !== '') {
                $paths[] = 'phoneLines.'.$i.'.Number';
            }
        }
        foreach (array_slice(is_array($wire['contactNumbers'] ?? null) ? $wire['contactNumbers'] : [], 0, 6) as $i => $row) {
            if (is_array($row) && trim((string) ($row['Number'] ?? '')) !== '') {
                $paths[] = 'contactNumbers.'.$i.'.Number';
            }
        }
        $pnr = is_array($wire['pnrContact'] ?? null) ? $wire['pnrContact'] : [];
        $pnrPh = is_array($pnr['phone'] ?? null) ? $pnr['phone'] : [];
        if (trim((string) ($pnrPh['Number'] ?? '')) !== '') {
            $paths[] = 'pnrContact.phone.Number';
        }
        $res = is_array($wire['reservationContact'] ?? null) ? $wire['reservationContact'] : [];
        $resPhones = is_array($res['phones'] ?? null) ? $res['phones'] : [];
        foreach (array_slice($resPhones, 0, 6) as $i => $row) {
            if (is_array($row) && trim((string) ($row['Number'] ?? '')) !== '') {
                $paths[] = 'reservationContact.phones.'.$i.'.Number';
            }
        }
        $tv = is_array($wire['travelers'] ?? null) ? $wire['travelers'] : [];
        foreach (array_slice($tv, 0, 6) as $i => $t) {
            if (! is_array($t)) {
                continue;
            }
            $tp = is_array($t['phone'] ?? null) ? $t['phone'] : [];
            if (trim((string) ($tp['Number'] ?? '')) !== '') {
                $paths[] = 'travelers.'.$i.'.phone.Number';
            }
        }

        return array_values(array_slice(array_unique($paths), 0, 24));
    }

    /**
     * B35: Phone use-type / type codes present on wire phone rows (sanitized labels only; no numbers).
     *
     * @return list<string>
     */
    public function collectWirePhoneUseTypeLikeValuesSanitized(array $wire): array
    {
        $seen = [];
        $out = [];
        $push = static function (mixed $v) use (&$seen, &$out): void {
            if (! is_string($v)) {
                return;
            }
            $t = strtoupper(trim($v));
            if ($t === '' || strlen($t) > 24) {
                return;
            }
            if (! isset($seen[$t])) {
                $seen[$t] = true;
                $out[] = $t;
            }
        };
        $scanRows = static function (?array $list) use ($push): void {
            if ($list === null) {
                return;
            }
            foreach (array_slice($list, 0, 8) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach (['phoneUseType', 'phone_use_type', 'type', 'Type', 'PhoneUseType'] as $k) {
                    if (array_key_exists($k, $row)) {
                        $push($row[$k]);
                    }
                }
            }
        };
        $scanRows(is_array($wire['phones'] ?? null) ? $wire['phones'] : []);
        $scanRows(is_array($wire['phoneNumbers'] ?? null) ? $wire['phoneNumbers'] : []);
        $ci = is_array($wire['contactInfo'] ?? null) ? $wire['contactInfo'] : [];
        $scanRows(is_array($ci['phones'] ?? null) ? $ci['phones'] : []);
        $aci = is_array($wire['agencyContactInfo'] ?? null) ? $wire['agencyContactInfo'] : [];
        $scanRows(is_array($aci['phones'] ?? null) ? $aci['phones'] : []);
        $posBlock = is_array($wire['POS'] ?? null) ? $wire['POS'] : [];
        $sources = is_array($posBlock['Source'] ?? null) ? $posBlock['Source'] : [];
        foreach (array_slice($sources, 0, 4) as $src) {
            if (! is_array($src)) {
                continue;
            }
            $ap = is_array($src['AgencyPhone'] ?? null) ? $src['AgencyPhone'] : [];
            if (array_key_exists('PhoneUseType', $ap)) {
                $push($ap['PhoneUseType']);
            }
        }
        $posCamel = is_array($wire['pos'] ?? null) ? $wire['pos'] : [];
        $posSrc = is_array($posCamel['source'] ?? null) ? $posCamel['source'] : [];
        if (array_key_exists('phoneUseType', $posSrc)) {
            $push($posSrc['phoneUseType']);
        }
        $agencyRoot = is_array($wire['agency'] ?? null) ? $wire['agency'] : [];
        if (array_key_exists('phoneUseType', $agencyRoot)) {
            $push($agencyRoot['phoneUseType']);
        }
        $ta = is_array($wire['travelAgency'] ?? null) ? $wire['travelAgency'] : [];
        if (array_key_exists('phoneUseType', $ta)) {
            $push($ta['phoneUseType']);
        }
        $pl = is_array($wire['phoneLine'] ?? null) ? $wire['phoneLine'] : [];
        foreach (['Type', 'PhoneUseType', 'phoneUseType', 'type'] as $k) {
            if (array_key_exists($k, $pl)) {
                $push($pl[$k]);
            }
        }
        $scanRows(is_array($wire['phoneLines'] ?? null) ? $wire['phoneLines'] : []);
        $scanRows(is_array($wire['contactNumbers'] ?? null) ? $wire['contactNumbers'] : []);
        $pnr = is_array($wire['pnrContact'] ?? null) ? $wire['pnrContact'] : [];
        $pnrPh = is_array($pnr['phone'] ?? null) ? $pnr['phone'] : [];
        foreach (['Type', 'PhoneUseType', 'phoneUseType', 'type'] as $k) {
            if (array_key_exists($k, $pnrPh)) {
                $push($pnrPh[$k]);
            }
        }
        $rc = is_array($wire['reservationContact'] ?? null) ? $wire['reservationContact'] : [];
        $scanRows(is_array($rc['phones'] ?? null) ? $rc['phones'] : []);
        $ciAp = is_array($ci['agencyPhone'] ?? null) ? $ci['agencyPhone'] : [];
        foreach (['Type', 'PhoneUseType', 'phoneUseType', 'type'] as $k) {
            if (array_key_exists($k, $ciAp)) {
                $push($ciAp[$k]);
            }
        }
        $tv = is_array($wire['travelers'] ?? null) ? $wire['travelers'] : [];
        foreach (array_slice($tv, 0, 6) as $t) {
            if (! is_array($t)) {
                continue;
            }
            $tp = is_array($t['phone'] ?? null) ? $t['phone'] : [];
            foreach (['Type', 'PhoneUseType', 'phoneUseType', 'type'] as $k) {
                if (array_key_exists($k, $tp)) {
                    $push($tp[$k]);
                }
            }
        }

        return array_values(array_slice($out, 0, 12));
    }

    /**
     * B37: {@code LocationCode} values on PNR-style phone rows (3-letter IATA-style codes only; no phone digits).
     *
     * @return list<string>
     */
    public function collectWirePhoneLocationValuesSanitized(array $wire): array
    {
        $seen = [];
        $out = [];
        $push = static function (mixed $v) use (&$seen, &$out): void {
            if (! is_string($v)) {
                return;
            }
            $t = strtoupper(trim($v));
            $t = preg_replace('/[^A-Z]/', '', $t) ?? '';
            if (strlen($t) !== 3) {
                return;
            }
            if (! isset($seen[$t])) {
                $seen[$t] = true;
                $out[] = $t;
            }
        };
        $fromRow = static function (?array $row) use ($push): void {
            if ($row === null) {
                return;
            }
            foreach (['LocationCode', 'locationCode'] as $k) {
                if (array_key_exists($k, $row)) {
                    $push($row[$k]);
                }
            }
        };
        $fromRow(is_array($wire['phoneLine'] ?? null) ? $wire['phoneLine'] : null);
        foreach (array_slice(is_array($wire['phoneLines'] ?? null) ? $wire['phoneLines'] : [], 0, 8) as $row) {
            $fromRow(is_array($row) ? $row : null);
        }
        foreach (array_slice(is_array($wire['contactNumbers'] ?? null) ? $wire['contactNumbers'] : [], 0, 8) as $row) {
            $fromRow(is_array($row) ? $row : null);
        }
        $pnr = is_array($wire['pnrContact'] ?? null) ? $wire['pnrContact'] : [];
        $fromRow(is_array($pnr['phone'] ?? null) ? $pnr['phone'] : null);
        $rc = is_array($wire['reservationContact'] ?? null) ? $wire['reservationContact'] : [];
        foreach (array_slice(is_array($rc['phones'] ?? null) ? $rc['phones'] : [], 0, 8) as $row) {
            $fromRow(is_array($row) ? $row : null);
        }
        $ci = is_array($wire['contactInfo'] ?? null) ? $wire['contactInfo'] : [];
        $fromRow(is_array($ci['agencyPhone'] ?? null) ? $ci['agencyPhone'] : null);
        foreach (array_slice(is_array($wire['travelers'] ?? null) ? $wire['travelers'] : [], 0, 8) as $t) {
            if (! is_array($t)) {
                continue;
            }
            $fromRow(is_array($t['phone'] ?? null) ? $t['phone'] : null);
        }

        return array_values(array_slice($out, 0, 12));
    }

    protected function sabreAgencyDisplayNameForWire(): string
    {
        $n = trim((string) config('suppliers.sabre.agency_name', ''));

        return $n !== '' ? $n : 'OTA_WEB';
    }

    protected function sabreAgencyIso2CountryForWire(): string
    {
        $ac = strtoupper(trim((string) config('suppliers.sabre.agency_country', '')));
        if (strlen($ac) >= 2) {
            return substr($ac, 0, 2);
        }
        $cc = strtoupper(trim((string) config('suppliers.sabre.agency_phone_country_code', 'PK')));

        return substr($cc !== '' ? $cc : 'PK', 0, 2);
    }

    protected function sabrePosPhoneUseTypeSingleChar(): string
    {
        $t = strtoupper(trim((string) config('suppliers.sabre.agency_pos_phone_use_type', 'A')));
        $t = preg_replace('/[^A-Z0-9]/', '', $t) ?? '';

        return $t !== '' ? substr($t, 0, 1) : 'A';
    }

    /**
     * B37: Sabre traditional PNR {@code LocationCode} on phone-line rows (IATA-style; no secrets).
     */
    protected function sabreAgencyPhoneLocationCodeForWire(): string
    {
        $t = strtoupper(trim((string) config('suppliers.sabre.agency_phone_location', 'LHE')));
        $t = preg_replace('/[^A-Z]/', '', $t) ?? '';

        return strlen($t) >= 3 ? substr($t, 0, 3) : (strlen($t) > 0 ? str_pad($t, 3, 'X') : 'LHE');
    }

    /**
     * B37: Single Sabre-style agency phone line row ({@code Number}, {@code Type}, {@code LocationCode}).
     *
     * @return array{Number: string, Type: string, LocationCode: string}|null
     */
    protected function sabreAgencyPnrPhoneLineRow(): ?array
    {
        $phone = trim((string) config('suppliers.sabre.agency_phone', ''));
        if ($phone === '') {
            return null;
        }

        return [
            'Number' => $phone,
            'Type' => $this->sabrePosPhoneUseTypeSingleChar(),
            'LocationCode' => $this->sabreAgencyPhoneLocationCodeForWire(),
        ];
    }

    /**
     * B33: Sabre Trip Orders traditional booking default agency/office phone block (wire root {@code agencyContactInfo} with {@code phone}).
     *
     * @return array<string, mixed>|null
     */
    protected function buildTripOrdersAgencyContactWireBlock(): ?array
    {
        $phone = trim((string) config('suppliers.sabre.agency_phone', ''));
        if ($phone === '') {
            return null;
        }
        $cc = strtoupper(trim((string) config('suppliers.sabre.agency_phone_country_code', 'PK')));
        if (strlen($cc) > 3) {
            $cc = substr($cc, 0, 3);
        }
        $pt = strtoupper(trim((string) config('suppliers.sabre.agency_phone_type', 'AGENCY')));
        if ($pt === '') {
            $pt = 'AGENCY';
        }

        return array_filter([
            'phone' => $phone,
            'phoneCountryCode' => $cc !== '' ? $cc : null,
            'phoneType' => $pt,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * B34/B36: Merge agency/office phone onto Trip Orders wire root per payload style (requires {@code SABRE_AGENCY_PHONE}).
     *
     * @param  array<string, mixed>  $common
     */
    protected function applyTripOrdersSabreAgencyPhoneForStyle(string $style, array &$common, string $pseudoCityCode = ''): void
    {
        $phone = trim((string) config('suppliers.sabre.agency_phone', ''));
        if ($phone === '') {
            return;
        }
        $cc = strtoupper(trim((string) config('suppliers.sabre.agency_phone_country_code', 'PK')));
        if (strlen($cc) > 3) {
            $cc = substr($cc, 0, 3);
        }
        $pt = strtoupper(trim((string) config('suppliers.sabre.agency_phone_type', 'AGENCY')));
        if ($pt === '') {
            $pt = 'AGENCY';
        }
        $st = trim($style);
        $pcc = strtoupper(trim($pseudoCityCode));
        if ($st === 'trip_orders_flight_details_sabre_pos_source_phone_v1') {
            $iso2 = $this->sabreAgencyIso2CountryForWire();
            $cityCfg = trim((string) config('suppliers.sabre.agency_city', ''));
            $addr = array_filter([
                'AddressLine' => 'ONLINE',
                'CityName' => $cityCfg !== '' ? $cityCfg : null,
                'CountryCode' => $iso2 !== '' ? $iso2 : null,
            ], static fn ($v) => $v !== null && $v !== '');
            $sourceRow = array_filter([
                'RequestorID' => [
                    'Type' => '1',
                    'ID' => '1',
                    'CompanyName' => ['Code' => 'TN'],
                ],
                'AgencyAddress' => $addr !== [] ? $addr : null,
                'AgencyPhone' => [
                    'PhoneNumber' => $phone,
                    'PhoneUseType' => $this->sabrePosPhoneUseTypeSingleChar(),
                ],
            ], static fn ($v) => $v !== null && $v !== []);
            if ($pcc !== '') {
                $sourceRow = array_merge(['PseudoCityCode' => $pcc], $sourceRow);
            }
            $common['POS'] = ['Source' => [$sourceRow]];
        } elseif ($st === 'trip_orders_flight_details_sabre_pos_phone_v1') {
            $common['pos'] = [
                'source' => array_filter([
                    'agencyPhone' => $phone,
                    'agencyPhoneCountryCode' => $this->sabreAgencyIso2CountryForWire(),
                    'phoneUseType' => $this->sabrePosPhoneUseTypeSingleChar(),
                ], static fn ($v) => $v !== null && $v !== ''),
            ];
        } elseif ($st === 'trip_orders_flight_details_sabre_agency_root_camel_v1') {
            $common['agency'] = array_filter([
                'name' => $this->sabreAgencyDisplayNameForWire(),
                'phoneNumber' => $phone,
                'phoneUseType' => $this->sabrePosPhoneUseTypeSingleChar(),
                'countryCode' => $this->sabreAgencyIso2CountryForWire(),
            ], static fn ($v) => $v !== null && $v !== '');
        } elseif ($st === 'trip_orders_flight_details_sabre_travelAgency_v1') {
            $common['travelAgency'] = array_filter([
                'phoneNumber' => $phone,
                'phoneUseType' => $this->sabrePosPhoneUseTypeSingleChar(),
                'countryCode' => $this->sabreAgencyIso2CountryForWire(),
            ], static fn ($v) => $v !== null && $v !== '');
        } elseif ($st === 'trip_orders_flight_details_sabre_customerInfo_phone_v1') {
            $nested = [];
            if (isset($common['contactInfo']) && is_array($common['contactInfo']) && $common['contactInfo'] !== []) {
                $nested['contactInfo'] = $common['contactInfo'];
            }
            $nested['agencyPhone'] = $phone;
            $common['customerInfo'] = $nested;
        } elseif ($st === 'trip_orders_flight_details_sabre_phoneLine_v1') {
            $row = $this->sabreAgencyPnrPhoneLineRow();
            if ($row !== null) {
                $common['phoneLine'] = $row;
            }
        } elseif ($st === 'trip_orders_flight_details_sabre_phoneLines_v1') {
            $row = $this->sabreAgencyPnrPhoneLineRow();
            if ($row !== null) {
                $common['phoneLines'] = [$row];
            }
        } elseif ($st === 'trip_orders_flight_details_sabre_contactNumbers_v1') {
            $common['contactNumbers'] = [[
                'Number' => $phone,
                'PhoneUseType' => $this->sabrePosPhoneUseTypeSingleChar(),
                'LocationCode' => $this->sabreAgencyPhoneLocationCodeForWire(),
            ]];
        } elseif ($st === 'trip_orders_flight_details_sabre_pnrContact_v1') {
            $row = $this->sabreAgencyPnrPhoneLineRow();
            if ($row !== null) {
                $common['pnrContact'] = ['phone' => $row];
            }
        } elseif ($st === 'trip_orders_flight_details_sabre_reservationContact_v1') {
            $row = $this->sabreAgencyPnrPhoneLineRow();
            if ($row !== null) {
                $common['reservationContact'] = ['phones' => [$row]];
            }
        } elseif ($st === 'trip_orders_flight_details_sabre_contactInfo_phoneLine_v1') {
            $row = $this->sabreAgencyPnrPhoneLineRow();
            if ($row !== null) {
                $ci = is_array($common['contactInfo'] ?? null) ? $common['contactInfo'] : [];
                $ci['agencyPhone'] = $row;
                $common['contactInfo'] = $ci;
            }
        } elseif ($st === 'trip_orders_flight_details_sabre_travelers_phone_v1') {
            $row = $this->sabreAgencyPnrPhoneLineRow();
            if ($row !== null && isset($common['travelers']) && is_array($common['travelers'])) {
                foreach ($common['travelers'] as $i => $t) {
                    if (is_array($t)) {
                        $common['travelers'][$i]['phone'] = $row;
                    }
                }
            }
        } elseif ($st === 'trip_orders_flight_details_sabre_agencyInfo_v1') {
            $common['agencyInfo'] = array_filter([
                'phone' => $phone,
                'phoneCountryCode' => $cc !== '' ? $cc : null,
                'phoneType' => $pt,
            ], static fn ($v) => $v !== null && $v !== '');
        } elseif ($st === 'trip_orders_flight_details_sabre_agencyPhoneNumber_v1') {
            $common['agencyContactInfo'] = array_filter([
                'phoneNumber' => $phone,
                'phoneCountryCode' => $cc !== '' ? $cc : null,
                'phoneType' => $pt,
            ], static fn ($v) => $v !== null && $v !== '');
        } elseif ($st === 'trip_orders_flight_details_sabre_agencyPhonesArray_v1') {
            $common['agencyContactInfo'] = [
                'phones' => [array_filter([
                    'number' => $phone,
                    'countryCode' => $cc !== '' ? $cc : null,
                    'type' => $pt,
                ], static fn ($v) => $v !== null && $v !== '')],
            ];
        } elseif ($st === 'trip_orders_flight_details_sabre_rootAgencyPhone_v1') {
            $common['agencyPhone'] = $phone;
            if ($cc !== '') {
                $common['agencyPhoneCountryCode'] = $cc;
            }
        } elseif ($st === 'trip_orders_flight_details_sabre_phoneNumbers_v1') {
            $common['phoneNumbers'] = [array_filter([
                'number' => $phone,
                'type' => $pt,
                'countryCode' => $cc !== '' ? $cc : null,
            ], static fn ($v) => $v !== null && $v !== '')];
        } elseif ($st === 'trip_orders_flight_details_sabre_rootPhones_v1') {
            $common['phones'] = [['phoneNumber' => $phone, 'phoneUseType' => 'A']];
        } elseif ($st === 'trip_orders_flight_details_sabre_rootPhoneNumbers_v1') {
            $common['phoneNumbers'] = [['phoneNumber' => $phone, 'phoneUseType' => 'A']];
        } elseif ($st === 'trip_orders_flight_details_sabre_contactInfoPhones_v1') {
            $ci = is_array($common['contactInfo'] ?? null) ? $common['contactInfo'] : [];
            $existing = is_array($ci['phones'] ?? null) ? $ci['phones'] : [];
            $ci['phones'] = array_merge($existing, [['phoneNumber' => $phone, 'phoneUseType' => 'A']]);
            $common['contactInfo'] = $ci;
        } elseif ($st === 'trip_orders_flight_details_sabre_agencyPhoneUseType_v1') {
            $common['agencyContactInfo'] = [
                'phones' => [['phoneNumber' => $phone, 'phoneUseType' => 'A']],
            ];
        } elseif ($st === 'trip_orders_flight_details_sabre_phone_use_business_v1') {
            $common['phones'] = [['number' => $phone, 'type' => 'BUSINESS']];
        } elseif ($st === 'trip_orders_flight_details_sabre_phone_use_agency_v1') {
            $common['phones'] = [['number' => $phone, 'type' => 'AGENCY']];
        } elseif ($st === 'trip_orders_root_flight_details_v2_agency_phone_flat') {
            $common['agencyContactInfo'] = array_filter([
                'phoneNumber' => $phone,
                'phoneCountryCode' => $cc !== '' ? $cc : null,
                'phoneType' => $pt,
            ], static fn ($v) => $v !== null && $v !== '');
        } elseif ($st === 'trip_orders_root_flight_details_v2_agency_phone_nested') {
            $common['agencyContactInfo'] = [
                'phones' => [array_filter([
                    'number' => $phone,
                    'countryCode' => $cc !== '' ? $cc : null,
                    'type' => $pt,
                ], static fn ($v) => $v !== null && $v !== '')],
            ];
        } elseif ($st === 'trip_orders_root_flight_details_v2_agency_contact_as_contactInfo') {
            $this->applyTripOrdersSabreAgencyPhoneDefaultContactInfo($common);
            $ci = is_array($common['contactInfo'] ?? null) ? $common['contactInfo'] : [];
            $existing = is_array($ci['phones'] ?? null) ? $ci['phones'] : [];
            $ci['phones'] = array_merge($existing, [[
                'phoneNumber' => $phone,
                'phoneUseType' => $this->sabrePosPhoneUseTypeSingleChar(),
            ]]);
            $common['contactInfo'] = $ci;
        } else {
            $this->applyTripOrdersSabreAgencyPhoneDefaultContactInfo($common);
        }
    }

    /**
     * @param  array<string, mixed>  $common
     */
    protected function applyTripOrdersSabreAgencyPhoneDefaultContactInfo(array &$common): void
    {
        $block = $this->buildTripOrdersAgencyContactWireBlock();
        if ($block !== null) {
            $common['agencyContactInfo'] = $block;
        }
    }

    /**
     * @param  array<string, mixed>  $createBooking  Full inner createBooking object (pre-return)
     * @return array<string, mixed>
     */
    protected function buildTripOrdersRootWireBody(array $createBooking, string $style, bool $ticketingEnabled, string $pseudoCityCode = ''): array
    {
        $commit = is_array($createBooking['trip_orders_reservation_action'] ?? null)
            ? $createBooking['trip_orders_reservation_action']
            : ['endTransaction' => true, 'commitWithoutTicketing' => true, 'receivedFrom' => 'OTA_WEB'];
        $ticketing = ['enabled' => $ticketingEnabled === true];
        $travelers = is_array($createBooking['travelers'] ?? null) ? $createBooking['travelers'] : [];
        $contact = is_array($createBooking['contact'] ?? null)
            ? array_filter([
                'email' => isset($createBooking['contact']['email']) && is_string($createBooking['contact']['email']) && trim($createBooking['contact']['email']) !== ''
                    ? $createBooking['contact']['email'] : null,
                'phone' => isset($createBooking['contact']['phone']) && is_string($createBooking['contact']['phone']) && trim($createBooking['contact']['phone']) !== ''
                    ? $createBooking['contact']['phone'] : null,
            ], static fn ($v) => $v !== null && $v !== '')
            : [];
        $payment = is_array($createBooking['payment'] ?? null) ? $createBooking['payment'] : ['mode' => 'pnr_only', 'capture' => false];

        $common = [
            'travelers' => $travelers,
            'commit' => $commit,
            'ticketing' => $ticketing,
            'payment' => $payment,
        ];
        if ($contact !== []) {
            if ($this->tripOrdersStyleUsesSabreTripOrdersContactInfo($style)) {
                $common['contactInfo'] = $contact;
            } else {
                $common['contact'] = $contact;
            }
        }
        if ($this->tripOrdersStyleRequiresSabreAgencyPhone($style)) {
            $this->applyTripOrdersSabreAgencyPhoneForStyle($style, $common, $pseudoCityCode);
        }
        foreach ([
            'supplier_context' => is_array($createBooking['supplier_context'] ?? null) ? $createBooking['supplier_context'] : null,
            'shop_context' => is_array($createBooking['shop_context'] ?? null) ? $createBooking['shop_context'] : null,
            'fare_linkage' => is_array($createBooking['fare_linkage'] ?? null) ? $createBooking['fare_linkage'] : null,
            'validating_carrier' => isset($createBooking['validating_carrier']) && is_string($createBooking['validating_carrier']) && trim($createBooking['validating_carrier']) !== ''
                ? trim($createBooking['validating_carrier'])
                : null,
            'pricing' => is_array($createBooking['pricing'] ?? null) ? $createBooking['pricing'] : null,
            'passenger_type_counts' => is_array($createBooking['passenger_type_counts'] ?? null) ? $createBooking['passenger_type_counts'] : null,
            'remarks' => is_array($createBooking['remarks'] ?? null) ? $createBooking['remarks'] : null,
            'itinerary' => is_array($createBooking['itinerary'] ?? null) ? $createBooking['itinerary'] : null,
        ] as $k => $v) {
            if ($v !== null && $v !== [] && $v !== '') {
                $common[$k] = $v;
            }
        }

        if ($style === 'trip_orders_flight_offer_root_v1' || $style === 'trip_orders_flight_offer_camel_v1') {
            $fo = is_array($createBooking['flightOffer'] ?? null) ? $createBooking['flightOffer'] : [];

            return array_merge(['flightOffer' => $fo], $common);
        }
        $sabreFdRootStyles = [
            'trip_orders_flight_details_root_v1', 'trip_orders_flight_details_camel_v1', 'trip_orders_flight_details_full_camel_v1',
            'trip_orders_flight_details_sabre_v1', 'trip_orders_flight_details_sabre_agency_v1',
            'trip_orders_flight_details_sabre_agencyInfo_v1', 'trip_orders_flight_details_sabre_agencyPhoneNumber_v1',
            'trip_orders_flight_details_sabre_agencyPhonesArray_v1', 'trip_orders_flight_details_sabre_rootAgencyPhone_v1',
            'trip_orders_flight_details_sabre_phoneNumbers_v1',
            'trip_orders_flight_details_sabre_rootPhones_v1', 'trip_orders_flight_details_sabre_rootPhoneNumbers_v1',
            'trip_orders_flight_details_sabre_contactInfoPhones_v1', 'trip_orders_flight_details_sabre_agencyPhoneUseType_v1',
            'trip_orders_flight_details_sabre_phone_use_business_v1', 'trip_orders_flight_details_sabre_phone_use_agency_v1',
            'trip_orders_flight_details_sabre_pos_source_phone_v1', 'trip_orders_flight_details_sabre_pos_phone_v1',
            'trip_orders_flight_details_sabre_agency_root_camel_v1', 'trip_orders_flight_details_sabre_travelAgency_v1',
            'trip_orders_flight_details_sabre_customerInfo_phone_v1',
            'trip_orders_flight_details_sabre_phoneLine_v1',
            'trip_orders_flight_details_sabre_phoneLines_v1',
            'trip_orders_flight_details_sabre_contactNumbers_v1',
            'trip_orders_flight_details_sabre_pnrContact_v1',
            'trip_orders_flight_details_sabre_reservationContact_v1',
            'trip_orders_flight_details_sabre_contactInfo_phoneLine_v1',
            'trip_orders_flight_details_sabre_travelers_phone_v1',
            'trip_orders_create_booking_root_flight_details_v2',
            'trip_orders_root_flight_details_v2_agency_phone_flat',
            'trip_orders_root_flight_details_v2_agency_phone_nested',
            'trip_orders_root_flight_details_v2_agency_contact_as_contactInfo',
            'trip_orders_root_flight_details_v2_no_agency_contact',
        ];
        if (in_array($style, $sabreFdRootStyles, true)) {
            $fd = is_array($createBooking['flightDetails'] ?? null) ? $createBooking['flightDetails'] : [];

            return array_merge(['flightDetails' => $fd], $common);
        }
        if ($style === 'trip_orders_product_array_v1') {
            $fd = is_array($createBooking['flightDetails'] ?? null) ? $createBooking['flightDetails'] : [];
            $products = $fd !== [] ? [['type' => 'flight', 'flightDetails' => $fd]] : [];

            return array_merge(['products' => $products], $common);
        }

        return $common;
    }

    /**
     * @param  list<array<string, mixed>>  $products
     */
    protected function wireProductsContainFlight(array $products): bool
    {
        foreach (array_slice($products, 0, 24) as $p) {
            if (! is_array($p)) {
                continue;
            }
            $t = strtolower(trim((string) ($p['type'] ?? '')));
            if ($t === 'flight' && isset($p['flightDetails']) && is_array($p['flightDetails']) && $p['flightDetails'] !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $wire
     * @param  array<string, mixed>  $createBookingNested
     */
    protected function wireSegmentCount(array $wire, array $createBookingNested): int
    {
        $paths = [
            ['flightOffer', 'segments'],
            ['flightDetails', 'segments'],
            ['flightOffer', 'itinerary', 'segments'],
            ['flightDetails', 'itinerary', 'segments'],
            ['itinerary', 'segments'],
            ['createBooking', 'itinerary', 'segments'],
            ['createBooking', 'flightOffer', 'segments'],
            ['createBooking', 'flightDetails', 'segments'],
        ];
        foreach ($paths as $path) {
            $cur = $wire;
            $ok = true;
            foreach ($path as $seg) {
                if (! is_array($cur) || ! array_key_exists($seg, $cur)) {
                    $ok = false;
                    break;
                }
                $cur = $cur[$seg];
            }
            if ($ok && is_array($cur)) {
                $n = count($cur);
                if ($n > 0) {
                    return $n;
                }
            }
        }
        $products = is_array($wire['products'] ?? null) ? $wire['products'] : [];
        foreach (array_slice($products, 0, 12) as $p) {
            if (! is_array($p)) {
                continue;
            }
            $fd = is_array($p['flightDetails'] ?? null) ? $p['flightDetails'] : [];
            $segs = is_array($fd['segments'] ?? null) ? $fd['segments'] : [];
            if ($segs !== []) {
                return count($segs);
            }
        }

        return 0;
    }

    protected function redactWireValueForPreview(mixed $v, int $depth): mixed
    {
        if ($depth > 14) {
            return 'max_depth';
        }
        if ($v === null || is_bool($v)) {
            return $v;
        }
        if (is_int($v) || is_float($v)) {
            return $v;
        }
        if (is_string($v)) {
            $t = trim($v);

            return $t === '' ? '' : substr($t, 0, 64);
        }
        if (! is_array($v)) {
            return 'redacted';
        }
        if ($v === []) {
            return [];
        }
        if (array_is_list($v)) {
            $out = [];
            foreach ($v as $child) {
                $out[] = $this->redactWireValueForPreview($child, $depth + 1);
            }

            return $out;
        }
        $out = [];
        foreach ($v as $k => $child) {
            if (! is_string($k)) {
                continue;
            }
            $lk = strtolower($k);
            if (in_array($k, ['phoneLine', 'phoneLines', 'contactNumbers', 'pnrContact', 'reservationContact'], true) && is_array($child)) {
                $out[$k] = $this->redactWireValueForPreview($child, $depth + 1);

                continue;
            }
            if ($k === 'agencyPhone' && is_array($child) && array_key_exists('Number', $child)) {
                $out[$k] = $this->redactWireValueForPreview($child, $depth + 1);

                continue;
            }
            if ($k === 'phone' && is_array($child) && array_key_exists('Number', $child)
                && (array_key_exists('Type', $child) || array_key_exists('LocationCode', $child) || array_key_exists('PhoneUseType', $child))) {
                $out[$k] = $this->redactWireValueForPreview($child, $depth + 1);

                continue;
            }
            if (str_contains($lk, 'email')) {
                $out[$k] = '[redacted]';

                continue;
            }
            if ($lk !== 'phoneusetype' && (str_contains($lk, 'phone') || str_contains($lk, 'mobile'))) {
                $out[$k] = '[redacted]';

                continue;
            }
            if ($lk === 'gender') {
                $gv = is_string($child) ? strtoupper(trim($child)) : '';
                $out[$k] = in_array($gv, self::sabreTripOrdersGenderEnumAccepted(), true) ? $gv : '[redacted]';

                continue;
            }
            if ($lk === 'address' && is_string($child)) {
                $out[$k] = '[redacted]';

                continue;
            }
            if (str_contains($lk, 'given') || str_contains($lk, 'surname') || $lk === 'name' || str_contains($lk, 'passengername')) {
                $out[$k] = '[redacted]';

                continue;
            }
            if (str_contains($lk, 'birth') || $lk === 'dob' || str_contains($lk, 'date_of_birth')) {
                $out[$k] = '[redacted]';

                continue;
            }
            if (str_contains($lk, 'passport') || str_contains($lk, 'national') || str_contains($lk, 'document') || $lk === 'number') {
                $out[$k] = is_array($child) ? $this->redactWireValueForPreview($child, $depth + 1) : '[redacted]';

                continue;
            }
            if (str_contains($lk, 'token') || str_contains($lk, 'secret') || str_contains($lk, 'authorization') || str_contains($lk, 'password')) {
                $out[$k] = '[redacted]';

                continue;
            }
            if (str_contains($lk, 'pcc') || str_contains($lk, 'pseudocity')) {
                $out[$k] = '[redacted]';

                continue;
            }
            $out[$k] = $this->redactWireValueForPreview($child, $depth + 1);
        }

        return $out;
    }

    /**
     * Build a synthetic {@code createBooking} slice from a root-wire Trip Orders envelope for diagnostics parity.
     *
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    protected function tripOrdersVirtualCreateBookingFromEnvelope(array $envelope): array
    {
        if (($envelope['_ota_payload_schema'] ?? '') !== 'trip_orders_create_booking_v1') {
            return [];
        }
        if (isset($envelope['createBooking']) && is_array($envelope['createBooking'])) {
            return [];
        }
        $keys = [
            'itinerary', 'flightOffer', 'flightDetails', 'travelers', 'contact', 'contactInfo', 'agencyContactInfo', 'agencyInfo', 'agency', 'agencyPhone', 'agencyPhoneCountryCode', 'phoneNumbers', 'phones', 'payment', 'pricing',
            'supplier_context', 'shop_context', 'fare_linkage', 'validating_carrier', 'passenger_type_counts', 'remarks', 'products',
            'POS', 'pos', 'travelAgency', 'customerInfo',
            'phoneLine', 'phoneLines', 'contactNumbers', 'pnrContact', 'reservationContact',
        ];
        $out = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $envelope)) {
                $out[$k] = $envelope[$k];
            }
        }
        if (isset($envelope['commit']) && is_array($envelope['commit'])) {
            $out['trip_orders_reservation_action'] = $envelope['commit'];
        }
        if (($envelope['_ota_createbooking_payload_style'] ?? '') === 'trip_orders_product_array_v1') {
            $fd = data_get($envelope, 'products.0.flightDetails');
            if (is_array($fd) && $fd !== []) {
                $out['flightDetails'] = $fd;
            }
        }
        if (array_key_exists('ticketing', $envelope)) {
            $out['ticketing'] = is_array($envelope['ticketing']) ? $envelope['ticketing'] : [];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $envelope  Trip Orders envelope including \_ota* diagnostics
     * @return array<string, mixed>
     */
    protected function summarizeTripOrdersEnvelopeForDiagnostics(array $envelope): array
    {
        $cb = is_array($envelope['createBooking'] ?? null) ? $envelope['createBooking'] : $this->tripOrdersVirtualCreateBookingFromEnvelope($envelope);
        $segments = is_array($cb['itinerary']['segments'] ?? null) ? $cb['itinerary']['segments'] : [];
        $fo = is_array($cb['flightOffer'] ?? null) ? $cb['flightOffer'] : [];
        $fd = is_array($cb['flightDetails'] ?? null) ? $cb['flightDetails'] : [];
        $hasFlightOffer = $fo !== [];
        $hasFlightDetails = $fd !== [];
        $hasRequiredBookingProductObject = $hasFlightOffer || $hasFlightDetails;
        $segFo = is_array($fo['segments'] ?? null) ? $fo['segments'] : [];
        if ($segFo === [] && is_array($fo['itinerary']['segments'] ?? null)) {
            $segFo = $fo['itinerary']['segments'];
        }
        $segFd = is_array($fd['segments'] ?? null) ? $fd['segments'] : [];
        if ($segFd === [] && is_array($fd['itinerary']['segments'] ?? null)) {
            $segFd = $fd['itinerary']['segments'];
        }
        $hasSegmentsInsideFlightOffer = is_array($segFo) && $segFo !== [];
        $hasSegmentsInsideFlightDetails = is_array($segFd) && $segFd !== [];
        $payloadStyle = (string) ($envelope['_ota_createbooking_payload_style'] ?? $this->resolveCreatebookingPayloadStyle());
        $travelers = is_array($cb['travelers'] ?? null) ? $cb['travelers'] : [];
        $pricing = is_array($cb['pricing'] ?? null) ? $cb['pricing'] : [];
        $payment = is_array($cb['payment'] ?? null) ? $cb['payment'] : [];
        $ticketing = is_array($cb['ticketing'] ?? null) ? $cb['ticketing'] : [];

        $hasBookingClass = false;
        $hasFareBasis = false;
        foreach (array_merge($segments, $segFo, $segFd) as $s) {
            if (! is_array($s)) {
                continue;
            }
            $cos = trim((string) ($s['class_of_service'] ?? $s['booking_class'] ?? ''));
            if ($cos !== '') {
                $hasBookingClass = true;
            }
            $fb = trim((string) ($s['fare_basis_code'] ?? $s['fareBasisCode'] ?? ''));
            if ($fb !== '') {
                $hasFareBasis = true;
            }
        }
        if (! $hasFareBasis && $hasFlightOffer) {
            $fbc = is_array($fo['fare_basis_codes'] ?? null) ? $fo['fare_basis_codes'] : [];
            foreach ($fbc as $code) {
                if (is_string($code) && trim($code) !== '') {
                    $hasFareBasis = true;
                    break;
                }
            }
        }

        $hasPassportDoc = (bool) ($envelope['_ota_has_passport_doc'] ?? false);
        $hasPaymentMode = isset($payment['mode']) && is_string($payment['mode']) && trim($payment['mode']) !== '';
        $amount = $pricing['total'] ?? null;
        $hasAmount = is_numeric($amount) && (float) $amount > 0;
        $hasCurrency = isset($pricing['currency']) && is_string($pricing['currency']) && trim($pricing['currency']) !== '';
        $hasTicketingDisabledMarker = ($ticketing['manual_ticketing_only'] ?? false) === true
            || ($ticketing['automated_issue'] ?? true) === false
            || (array_key_exists('enabled', $ticketing) && $ticketing['enabled'] === false);

        $resAction = is_array($cb['trip_orders_reservation_action'] ?? null) ? $cb['trip_orders_reservation_action'] : [];
        $hasCommitOrEnd = ($resAction['endTransaction'] ?? false) === true
            || ($resAction['commitWithoutTicketing'] ?? false) === true;

        $shopCtx = is_array($cb['shop_context'] ?? null) ? $cb['shop_context'] : [];
        $hasOfferReference = false;
        $hasFareReference = false;
        $hasPriceQuoteReference = false;
        foreach ($shopCtx as $k => $v) {
            if (! is_string($k) || ! is_string($v) || trim($v) === '') {
                continue;
            }
            $kl = strtolower($k);
            if (str_contains($kl, 'offeritem') || str_contains($kl, 'offer_item') || $kl === 'pricing_information_ref') {
                $hasOfferReference = true;
            }
            if (str_contains($kl, 'fare') && (str_contains($kl, 'ref') || str_contains($kl, 'reference'))) {
                $hasFareReference = true;
            }
            if (str_contains($kl, 'pricequote') || str_contains($kl, 'price_quote')) {
                $hasPriceQuoteReference = true;
            }
            if (str_contains($kl, 'fare_basis') || str_contains($kl, 'farebasis')) {
                $hasFareBasis = true;
            }
        }

        $fareLinkage = is_array($cb['fare_linkage'] ?? null) ? $cb['fare_linkage'] : [];
        $hasRevalidationReference = isset($fareLinkage['revalidation_reference']) && trim((string) $fareLinkage['revalidation_reference']) !== '';
        $hasItineraryReference = isset($fareLinkage['itinerary_reference']) && trim((string) $fareLinkage['itinerary_reference']) !== '';
        $hasValidatingCarrier = isset($fareLinkage['validating_carrier']) && trim((string) $fareLinkage['validating_carrier']) !== '';
        if (! $hasValidatingCarrier) {
            $hasValidatingCarrier = trim((string) ($cb['validating_carrier'] ?? '')) !== '';
        }
        if (! $hasValidatingCarrier && $hasFlightOffer && is_string($fo['validating_carrier'] ?? null) && trim((string) $fo['validating_carrier']) !== '') {
            $hasValidatingCarrier = true;
        }
        if (! $hasValidatingCarrier && $hasFlightDetails && is_string($fd['validating_carrier'] ?? null) && trim((string) $fd['validating_carrier']) !== '') {
            $hasValidatingCarrier = true;
        }
        $linkageFareBasis = is_array($fareLinkage['fare_basis_codes'] ?? null) ? $fareLinkage['fare_basis_codes'] : [];
        if ($linkageFareBasis !== []) {
            $hasFareBasis = true;
        }
        if (isset($fareLinkage['fare_reference']) && trim((string) $fareLinkage['fare_reference']) !== '') {
            $hasFareReference = true;
        }
        if (isset($fareLinkage['price_quote_reference']) && trim((string) $fareLinkage['price_quote_reference']) !== '') {
            $hasPriceQuoteReference = true;
        }
        if (isset($fareLinkage['offer_reference']) && trim((string) $fareLinkage['offer_reference']) !== '') {
            $hasOfferReference = true;
        }

        $pricingBlock = is_array($cb['pricing'] ?? null) ? $cb['pricing'] : [];
        $revalidatedTotalDiag = $pricingBlock['revalidated_total'] ?? ($fareLinkage['revalidated_total'] ?? null);
        $revalidatedCurrencyDiag = (string) ($pricingBlock['revalidated_currency'] ?? ($fareLinkage['revalidated_currency'] ?? ''));
        $hasRevalidatedFare = is_numeric($revalidatedTotalDiag) && (float) $revalidatedTotalDiag > 0;
        $hasRevalidatedCurrency = $revalidatedCurrencyDiag !== '';

        $sc = is_array($cb['supplier_context'] ?? null) ? $cb['supplier_context'] : [];
        $selOffer = trim((string) ($sc['selected_offer_id'] ?? ''));
        $suppOffer = trim((string) ($sc['supplier_offer_id'] ?? ''));
        $hasSelectedOfferId = $selOffer !== '';
        $hasSupplierOfferId = $suppOffer !== '';

        $hasTravelerDocs = false;
        foreach ($travelers as $tv) {
            if (! is_array($tv)) {
                continue;
            }
            if (isset($tv['passport']) && is_array($tv['passport']) && $tv['passport'] !== []) {
                $hasTravelerDocs = true;
                break;
            }
        }

        $contactBlock = is_array($cb['contact'] ?? null) ? $cb['contact'] : [];
        $contactInfoBlock = is_array($cb['contactInfo'] ?? null) ? $cb['contactInfo'] : [];
        $hasContact = (trim((string) ($contactBlock['email'] ?? '')) !== '')
            || (trim((string) ($contactBlock['phone'] ?? '')) !== '')
            || (trim((string) ($contactInfoBlock['email'] ?? '')) !== '')
            || (trim((string) ($contactInfoBlock['phone'] ?? '')) !== '');

        $segmentSellOk = true;
        foreach ($segments as $s) {
            if (! is_array($s)) {
                continue;
            }
            $need = ['origin', 'destination', 'departure_at', 'marketing_carrier', 'flight_number'];
            foreach ($need as $nk) {
                if (trim((string) ($s[$nk] ?? '')) === '') {
                    $segmentSellOk = false;
                    break 2;
                }
            }
        }
        if (! $segmentSellOk && $hasSegmentsInsideFlightDetails) {
            $segmentSellOk = true;
            $firstFd = is_array($segFd[0] ?? null) ? $segFd[0] : [];
            $useFullCamelFd = $firstFd !== [] && array_key_exists('departureDateTime', $firstFd);
            foreach ($segFd as $s) {
                if (! is_array($s)) {
                    continue;
                }
                if ($useFullCamelFd) {
                    $need = ['origin', 'destination', 'departureDateTime', 'marketingAirline', 'flightNumber'];
                } else {
                    $need = ['origin', 'destination', 'departure_datetime', 'marketing_airline', 'flight_number'];
                }
                foreach ($need as $nk) {
                    if (trim((string) ($s[$nk] ?? '')) === '') {
                        $segmentSellOk = false;
                        break 2;
                    }
                }
            }
        }
        if (! $segmentSellOk && $hasSegmentsInsideFlightOffer) {
            $segmentSellOk = true;
            foreach ($segFo as $s) {
                if (! is_array($s)) {
                    continue;
                }
                $need = ['origin', 'destination', 'departure_at', 'marketing_carrier', 'flight_number'];
                foreach ($need as $nk) {
                    if (trim((string) ($s[$nk] ?? '')) === '') {
                        $segmentSellOk = false;
                        break 2;
                    }
                }
            }
        }

        $ptcCb = is_array($cb['passenger_type_counts'] ?? null) ? $cb['passenger_type_counts'] : [];
        $hasPassengerCounts = false;
        foreach ($ptcCb as $c) {
            if (is_int($c) && $c > 0) {
                $hasPassengerCounts = true;
                break;
            }
            if (is_numeric($c) && (int) $c > 0) {
                $hasPassengerCounts = true;
                break;
            }
        }

        $fareBasisWarning = ! $hasFareBasis
            ? 'Trip Orders booking: no fare basis on itinerary segments in payload — Sabre may require fare basis / shop pricing linkage; run sabre:inspect-booking-payload before live POST.'
            : null;
        $inspectWarningTripOrdersFlightProduct = ($payloadStyle === 'trip_orders_create_booking_v1_current' && ! $hasRequiredBookingProductObject)
            ? 'Trip Orders createBooking: no flightOffer/flightDetails node (legacy style). Sabre may require a flight product object — set SABRE_CREATEBOOKING_PAYLOAD_STYLE to trip_orders_flight_offer_v1, trip_orders_flight_details_v1, or a B23 root style (trip_orders_flight_offer_root_v1 / trip_orders_flight_details_root_v1 / trip_orders_product_array_v1).'
            : null;
        $validationOk = $payloadStyle === 'trip_orders_create_booking_v1_current' || $hasRequiredBookingProductObject;

        $wireDiag = $this->summarizeTripOrdersWirePostBodyForEnvelope($envelope);
        $wireOnly = [];
        foreach ($wireDiag as $wk => $wv) {
            if (is_string($wk) && (str_starts_with($wk, 'wire_') || str_starts_with($wk, 'traveler_'))) {
                $wireOnly[$wk] = $wv;
            }
        }
        if (($wireDiag['wire_gender_enum_valid'] ?? true) === false) {
            $validationOk = false;
        }
        if (($wireDiag['wire_traveler_required_fields_valid'] ?? true) === false) {
            $validationOk = false;
        }
        if (($wireDiag['wire_payload_null_free'] ?? true) === false) {
            $validationOk = false;
        }
        if (($wireDiag['wire_contract_valid'] ?? true) === false) {
            $validationOk = false;
        }
        if (($wireDiag['wire_segment_required_fields_valid'] ?? true) === false) {
            $validationOk = false;
        }
        $inspectWarningWireRoot = (! ($wireDiag['wire_has_required_product_at_root'] ?? false))
            && ($wireDiag['wire_has_required_booking_product_nested'] ?? false)
            ? 'Sabre Trip Orders wire POST: flight product exists under createBooking but not at JSON root (Sabre often requires flightOffer, flightDetails, hotel, or car at root). Use trip_orders_flight_offer_root_v1 or trip_orders_flight_details_root_v1 (or trip_orders_product_array_v1).'
            : null;

        $inspectWarningWireRootIncomplete = null;
        if ($this->tripOrdersStyleUsesRootWireBody($payloadStyle) && ($wireDiag['wire_has_required_product_at_root'] ?? false)) {
            $wMsgs = [];
            if ((int) ($wireDiag['wire_traveler_count'] ?? 0) < 1) {
                $wMsgs[] = 'travelers count is 0';
                $validationOk = false;
            }
            if ($payloadStyle === 'trip_orders_flight_offer_root_v1' || $payloadStyle === 'trip_orders_flight_offer_camel_v1') {
                if ((int) ($wireDiag['wire_flight_offer_segment_count'] ?? 0) < 1) {
                    $wMsgs[] = 'flightOffer.segments count is 0';
                    $validationOk = false;
                }
            }
            if (in_array($payloadStyle, [
                'trip_orders_flight_details_root_v1', 'trip_orders_flight_details_camel_v1', 'trip_orders_flight_details_full_camel_v1',
                'trip_orders_flight_details_sabre_v1', 'trip_orders_flight_details_sabre_agency_v1',
                'trip_orders_flight_details_sabre_agencyInfo_v1', 'trip_orders_flight_details_sabre_agencyPhoneNumber_v1',
                'trip_orders_flight_details_sabre_agencyPhonesArray_v1', 'trip_orders_flight_details_sabre_rootAgencyPhone_v1',
                'trip_orders_flight_details_sabre_phoneNumbers_v1',
                'trip_orders_flight_details_sabre_rootPhones_v1', 'trip_orders_flight_details_sabre_rootPhoneNumbers_v1',
                'trip_orders_flight_details_sabre_contactInfoPhones_v1', 'trip_orders_flight_details_sabre_agencyPhoneUseType_v1',
                'trip_orders_flight_details_sabre_phone_use_business_v1', 'trip_orders_flight_details_sabre_phone_use_agency_v1',
                'trip_orders_flight_details_sabre_pos_source_phone_v1', 'trip_orders_flight_details_sabre_pos_phone_v1',
                'trip_orders_flight_details_sabre_agency_root_camel_v1', 'trip_orders_flight_details_sabre_travelAgency_v1',
                'trip_orders_flight_details_sabre_customerInfo_phone_v1',
                'trip_orders_flight_details_sabre_phoneLine_v1',
                'trip_orders_flight_details_sabre_phoneLines_v1',
                'trip_orders_flight_details_sabre_contactNumbers_v1',
                'trip_orders_flight_details_sabre_pnrContact_v1',
                'trip_orders_flight_details_sabre_reservationContact_v1',
                'trip_orders_flight_details_sabre_contactInfo_phoneLine_v1',
                'trip_orders_flight_details_sabre_travelers_phone_v1',
                'trip_orders_product_array_v1',
            ], true)
                && (int) ($wireDiag['wire_flight_details_segment_count'] ?? 0) < 1) {
                $wMsgs[] = 'flightDetails.segments count is 0';
                $validationOk = false;
            }
            if ($wMsgs !== []) {
                $inspectWarningWireRootIncomplete = 'Trip Orders root-style createBooking wire: '.implode('; ', $wMsgs).' — fix booking snapshot/passengers before live POST.';
            }
        }

        return array_merge([
            'has_trip_orders_schema' => true,
            'payload_schema' => 'trip_orders_create_booking_v1',
            'payload_style' => $payloadStyle,
            'has_flight_offer' => $hasFlightOffer,
            'has_flight_details' => $hasFlightDetails,
            'has_required_booking_product_object' => $hasRequiredBookingProductObject,
            'has_segments_inside_flight_offer' => $hasSegmentsInsideFlightOffer,
            'has_segments_inside_flight_details' => $hasSegmentsInsideFlightDetails,
            'has_passenger_counts' => $hasPassengerCounts,
            'validation_ok' => $validationOk,
            'booking_transport' => 'rest_json',
            'booking_mode' => (string) ($envelope['_ota_booking_mode'] ?? config('suppliers.sabre.booking_mode', 'pnr_only')),
            'ticketing_enabled' => (bool) config('suppliers.sabre.ticketing_enabled', false),
            'segment_count' => count($segments),
            'passenger_count' => count($travelers),
            'has_contact_email' => (bool) ($envelope['_ota_has_contact_email'] ?? false),
            'has_contact_phone' => (bool) ($envelope['_ota_has_contact_phone'] ?? false),
            'has_contact' => $hasContact,
            'has_passport_doc' => $hasPassportDoc,
            'has_traveler_documents' => $hasTravelerDocs,
            'has_booking_class' => $hasBookingClass,
            'has_fare_basis' => $hasFareBasis,
            'has_fare_reference' => $hasFareReference,
            'has_price_quote_reference' => $hasPriceQuoteReference,
            'has_offer_reference' => $hasOfferReference,
            'has_revalidation_reference' => $hasRevalidationReference,
            'has_itinerary_reference' => $hasItineraryReference,
            'has_validating_carrier' => $hasValidatingCarrier,
            'has_revalidated_fare' => $hasRevalidatedFare,
            'has_revalidated_currency' => $hasRevalidatedCurrency,
            'has_selected_offer_id' => $hasSelectedOfferId,
            'has_supplier_offer_id' => $hasSupplierOfferId,
            'has_segment_sell_details' => $segmentSellOk,
            'has_agency_received_from' => trim((string) ($resAction['receivedFrom'] ?? '')) !== '',
            'has_ticketing_instruction' => ($ticketing['time_limit_hint'] ?? null) !== null && $ticketing['time_limit_hint'] !== '',
            'has_commit_or_end_transaction' => $hasCommitOrEnd,
            'has_end_transaction' => $hasCommitOrEnd,
            'has_payment_mode' => $hasPaymentMode,
            'has_payment_or_hold_mode' => $hasPaymentMode,
            'has_amount' => $hasAmount,
            'has_currency' => $hasCurrency,
            'has_ticketing_disabled_marker' => $hasTicketingDisabledMarker,
            'inspect_warning_fare_basis' => $fareBasisWarning,
            'inspect_warning_trip_orders_flight_product' => $inspectWarningTripOrdersFlightProduct,
            'inspect_warning_trip_orders_wire_root' => $inspectWarningWireRoot,
            'inspect_warning_wire_root_incomplete' => $inspectWarningWireRootIncomplete,
        ], $wireOnly);
    }

    /**
     * Normalize a fare-linkage extraction (from {@see SabreRevalidationPayloadBuilder::extractFareLinkage()}) into
     * the safe trip-orders {@code createBooking.fare_linkage} block (capped strings, no raw PII).
     *
     * @param  array<string, mixed>  $linkage
     * @return array<string, mixed>
     */
    protected function normalizeFareLinkageBlock(array $linkage): array
    {
        if ($linkage === []) {
            return [];
        }
        $fareBasisCodes = is_array($linkage['fare_basis_codes'] ?? null)
            ? array_values(array_unique(array_filter(array_map(
                static fn ($v): string => is_scalar($v) ? substr(strtoupper(trim((string) $v)), 0, 24) : '',
                $linkage['fare_basis_codes']
            ), static fn (string $v): bool => $v !== '')))
            : [];

        $perSegment = is_array($linkage['per_segment'] ?? null)
            ? array_values(array_filter(array_map(static function ($row) {
                if (! is_array($row)) {
                    return null;
                }
                $out = [];
                foreach (['fare_basis_code', 'class_of_service', 'origin', 'destination'] as $k) {
                    $v = $row[$k] ?? null;
                    if (is_string($v) && trim($v) !== '') {
                        $out[$k] = substr(strtoupper(trim($v)), 0, 24);
                    }
                }

                return $out !== [] ? $out : null;
            }, $linkage['per_segment']), static fn ($r): bool => $r !== null))
            : [];

        $block = [
            'fare_basis_codes' => $fareBasisCodes,
            'per_segment' => $perSegment,
            'fare_reference' => $this->safeLinkageScalar($linkage['fare_reference'] ?? null),
            'price_quote_reference' => $this->safeLinkageScalar($linkage['price_quote_reference'] ?? null),
            'offer_reference' => $this->safeLinkageScalar($linkage['offer_reference'] ?? null),
            'itinerary_reference' => $this->safeLinkageScalar($linkage['itinerary_reference'] ?? null),
            'revalidation_reference' => $this->safeLinkageScalar($linkage['revalidation_reference'] ?? null),
            'validating_carrier' => $this->safeLinkageScalar($linkage['validating_carrier'] ?? null, 8),
            'revalidated_total' => is_numeric($linkage['revalidated_total'] ?? null) && (float) $linkage['revalidated_total'] > 0
                ? (float) $linkage['revalidated_total']
                : null,
            'revalidated_currency' => $this->safeLinkageScalar($linkage['revalidated_currency'] ?? null, 6),
            'ticketing_time_limit' => $this->safeLinkageScalar($linkage['ticketing_time_limit'] ?? null, 48),
            'baggage_summary' => $this->safeLinkageScalar($linkage['baggage_summary'] ?? null, 160),
        ];

        return array_filter($block, static fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * Where a normalized segment is missing a fare_basis_code, fill it from the revalidation per-segment hint when one
     * matches origin/destination; falls back to the first fare-basis code when only a single fare component returned.
     *
     * @param  list<array<string, mixed>>  $segments  Trip Orders segments
     * @param  array<string, mixed>  $linkage  Output of {@see SabreRevalidationPayloadBuilder::extractFareLinkage()}
     * @return list<array<string, mixed>>
     */
    /**
     * B67: Apply revalidated booking class (RBD) per segment for traditional CPNR AirBook sell rows.
     *
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $linkage  Output of {@see SabreRevalidationPayloadBuilder::extractFareLinkage()}
     * @return list<array<string, mixed>>
     */
    public function mergeRevalidatedClassOfServiceIntoSegments(array $segments, array $linkage): array
    {
        if ($segments === [] || $linkage === []) {
            return $segments;
        }
        $perSegment = is_array($linkage['per_segment'] ?? null) ? $linkage['per_segment'] : [];
        if ($perSegment === []) {
            return $segments;
        }

        foreach ($segments as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $orig = strtoupper(trim((string) ($row['origin'] ?? '')));
            $dest = strtoupper(trim((string) ($row['destination'] ?? '')));
            $picked = '';
            foreach ($perSegment as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $eo = strtoupper(trim((string) ($entry['origin'] ?? '')));
                $ed = strtoupper(trim((string) ($entry['destination'] ?? '')));
                if ($eo !== '' && $ed !== '' && $eo === $orig && $ed === $dest) {
                    $picked = strtoupper(trim((string) ($entry['class_of_service'] ?? '')));
                    break;
                }
            }
            if ($picked === '' && isset($perSegment[$i]) && is_array($perSegment[$i])) {
                $picked = strtoupper(trim((string) ($perSegment[$i]['class_of_service'] ?? '')));
            }
            if ($picked !== '') {
                $segments[$i]['booking_class'] = substr($picked, 0, 2);
                $segments[$i]['class_of_service'] = substr($picked, 0, 2);
            }
        }

        return $segments;
    }

    /**
     * B67: True when every draft segment has a matching revalidation row with non-empty {@code class_of_service}.
     *
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $linkage
     */
    public function linkageCoversSegmentsWithClassOfService(array $segments, array $linkage): bool
    {
        if ($segments === [] || $linkage === []) {
            return false;
        }
        $perSegment = is_array($linkage['per_segment'] ?? null) ? $linkage['per_segment'] : [];
        if ($perSegment === []) {
            return false;
        }
        foreach ($segments as $i => $row) {
            if (! is_array($row)) {
                return false;
            }
            $orig = strtoupper(trim((string) ($row['origin'] ?? '')));
            $dest = strtoupper(trim((string) ($row['destination'] ?? '')));
            $picked = '';
            foreach ($perSegment as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $eo = strtoupper(trim((string) ($entry['origin'] ?? '')));
                $ed = strtoupper(trim((string) ($entry['destination'] ?? '')));
                if ($eo !== '' && $ed !== '' && $eo === $orig && $ed === $dest) {
                    $picked = strtoupper(trim((string) ($entry['class_of_service'] ?? '')));
                    break;
                }
            }
            if ($picked === '' && isset($perSegment[$i]) && is_array($perSegment[$i])) {
                $picked = strtoupper(trim((string) ($perSegment[$i]['class_of_service'] ?? '')));
            }
            if ($picked === '') {
                return false;
            }
        }

        return true;
    }

    protected function mergeRevalidatedFareBasisIntoSegments(array $segments, array $linkage): array
    {
        if ($segments === [] || $linkage === []) {
            return $segments;
        }
        $perSegment = is_array($linkage['per_segment'] ?? null) ? $linkage['per_segment'] : [];
        $codes = is_array($linkage['fare_basis_codes'] ?? null) ? array_values($linkage['fare_basis_codes']) : [];
        $singleCode = count($codes) === 1 ? strtoupper((string) $codes[0]) : '';

        foreach ($segments as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            if (trim((string) ($row['fare_basis_code'] ?? '')) !== '') {
                continue;
            }
            $orig = strtoupper((string) ($row['origin'] ?? ''));
            $dest = strtoupper((string) ($row['destination'] ?? ''));
            $picked = '';
            foreach ($perSegment as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $eo = strtoupper((string) ($entry['origin'] ?? ''));
                $ed = strtoupper((string) ($entry['destination'] ?? ''));
                if ($eo !== '' && $ed !== '' && $eo === $orig && $ed === $dest) {
                    $picked = strtoupper((string) ($entry['fare_basis_code'] ?? ''));
                    break;
                }
            }
            if ($picked === '' && isset($perSegment[$i]) && is_array($perSegment[$i])) {
                $picked = strtoupper((string) ($perSegment[$i]['fare_basis_code'] ?? ''));
            }
            if ($picked === '' && $singleCode !== '') {
                $picked = $singleCode;
            }
            if ($picked !== '') {
                $segments[$i]['fare_basis_code'] = substr($picked, 0, 24);
            }
        }

        return $segments;
    }

    /** B38: Inspect/compare-only traditional PNR JSON root (CreatePassengerNameRecordRQ) for legacy passenger-record REST paths. */
    public const TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1 = 'traditional_pnr_create_passenger_name_record_v1';

    /**
     * Sprint 2A: IATI GDS parity CPNR wire — {@code CreatePassengerNameRecordRQ} version {@code 2.4.0},
     * {@code /v2.4.0/passenger/records?mode=create}. Side-by-side with {@see TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1};
     * enable via {@code suppliers.sabre.booking_payload_style} only (not public-checkout certified default).
     */
    public const IATI_LIKE_CPNR_V2_4_GDS = 'iati_like_cpnr_v2_4_gds';

    /** v2.5 GDS strategy — traditional CPNR wire on certified v2.5 endpoint. */
    public const PASSENGER_RECORDS_V2_5_GDS = 'passenger_records_v2_5_gds';

    /** Minimal GDS strategy — AirBook + AirPrice + EndTransaction only when context complete. */
    public const MINIMAL_AIRBOOK_AIRPRICE_ENDTRANSACTION_GDS = 'minimal_airbook_airprice_endtransaction_gds';

    /** Passenger Records create path paired with {@see IATI_LIKE_CPNR_V2_4_GDS}. */
    public const PASSENGER_RECORDS_V24_CREATE_PATH = '/v2.4.0/passenger/records?mode=create';

    /**
     * B79: Compare/inspect-only — same wire as {@see self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1} plus root {@code AirPrice}
     * {@code OptionalQualifiers.PricingQualifiers.ValidatingCarrier} when snapshot validating carrier is present/safe (no live default).
     */
    public const TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_VALIDATING_CARRIER_COMPARE_V1 = 'traditional_pnr_create_passenger_name_record_v1_airprice_validating_carrier_compare_v1';

    /** P4: Compare/inspect-only — {@code AirPrice...CommandPricing} with per-segment {@code FareBasis} from draft. */
    public const TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_PER_SEGMENT_FARE_BASIS_COMPARE_V1 = 'traditional_pnr_create_passenger_name_record_v1_airprice_per_segment_fare_basis_compare_v1';

    /** P4: Compare/inspect-only — AirBook {@code RetryRebook} + {@code RedisplayReservation} for mixed/interline experiments. */
    public const TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRBOOK_RETRY_REBOOK_REDISPLAY_COMPARE_V1 = 'traditional_pnr_create_passenger_name_record_v1_airbook_retry_rebook_redisplay_compare_v1';

    /**
     * P4: Traditional Passenger Records styles allowed in {@code sabre:compare-booking-endpoints} matrix (inspect/send; not checkout default).
     *
     * @var list<string>
     */
    public const BOOKING_ENDPOINT_COMPARE_PASSENGER_RECORDS_P4_STYLES = [
        self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
        self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_VALIDATING_CARRIER_COMPARE_V1,
        self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_PER_SEGMENT_FARE_BASIS_COMPARE_V1,
        self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRBOOK_RETRY_REBOOK_REDISPLAY_COMPARE_V1,
        self::IATI_LIKE_CPNR_V2_4_GDS,
        self::PASSENGER_RECORDS_V2_5_GDS,
        self::MINIMAL_AIRBOOK_AIRPRICE_ENDTRANSACTION_GDS,
    ];

    public static function isIatiLikeCpnrV24GdsWireStyle(string $style): bool
    {
        return $style === self::IATI_LIKE_CPNR_V2_4_GDS;
    }

    public static function isPassengerRecordsV25GdsWireStyle(string $style): bool
    {
        return $style === self::PASSENGER_RECORDS_V2_5_GDS;
    }

    /**
     * Sprint 2B: Required CPNR blocks for IATI-like v2.4 GDS wire (safe flags only; no payload values).
     *
     * @param  array<string, mixed>  $diagFlags  {@see summarizeTraditionalPnrWirePostBody()} / envelope diagnostics
     * @return array{cpnr_required_blocks_present: list<string>, cpnr_required_blocks_missing: list<string>}
     */
    public function assessIatiLikeCpnrRequiredBlocks(array $diagFlags): array
    {
        $required = [
            'target_city' => (bool) ($diagFlags['wire_has_target_city'] ?? $diagFlags['target_city_present'] ?? false),
            'air_book' => (bool) ($diagFlags['wire_has_air_book'] ?? $diagFlags['airbook_present'] ?? false),
            'air_price' => (bool) ($diagFlags['wire_has_air_price'] ?? $diagFlags['airprice_present'] ?? false),
            'end_transaction' => (bool) ($diagFlags['wire_has_end_transaction'] ?? $diagFlags['end_transaction_present'] ?? false),
            'received_from' => (bool) ($diagFlags['wire_has_received_from'] ?? $diagFlags['received_from_present'] ?? false),
            'ticketing_time_limit' => (bool) ($diagFlags['ticketing_present'] ?? false),
        ];
        $present = [];
        $missing = [];
        foreach ($required as $key => $ok) {
            if ($ok) {
                $present[] = $key;
            } else {
                $missing[] = $key;
            }
        }

        return [
            'cpnr_required_blocks_present' => $present,
            'cpnr_required_blocks_missing' => $missing,
        ];
    }

    public static function isTraditionalPnrPassengerRecordsWireStyle(string $style): bool
    {
        return in_array($style, self::BOOKING_ENDPOINT_COMPARE_PASSENGER_RECORDS_P4_STYLES, true);
    }

    /**
     * Active Passenger Records wire style for CPNR booking (config override; default traditional v2.5 wire).
     */
    public function resolvePassengerRecordsBookingPayloadStyle(): string
    {
        $raw = trim((string) config('suppliers.sabre.booking_payload_style', ''));

        return $this->normalizePassengerRecordsBookingPayloadStyle($raw !== '' ? $raw : self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1);
    }

    public function normalizePassengerRecordsBookingPayloadStyle(string $style): string
    {
        $s = trim($style);

        return in_array($s, self::BOOKING_ENDPOINT_COMPARE_PASSENGER_RECORDS_P4_STYLES, true)
            ? $s
            : self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1;
    }

    /**
     * REST path for Passenger Records create for the given wire style (v2.4 only when IATI-like style is selected).
     */
    public function resolvePassengerRecordsCreateEndpointPath(string $payloadStyle): string
    {
        if (self::isIatiLikeCpnrV24GdsWireStyle($payloadStyle)) {
            $v24 = trim((string) config('suppliers.sabre.passenger_records_endpoint_v24', self::PASSENGER_RECORDS_V24_CREATE_PATH));

            return $v24 !== '' ? $v24 : self::PASSENGER_RECORDS_V24_CREATE_PATH;
        }

        if ($payloadStyle === self::PASSENGER_RECORDS_V2_5_GDS) {
            return '/v2.5.0/passenger/records?mode=create';
        }

        $path = trim((string) config('suppliers.sabre.booking_path', ''));
        if ($path !== '') {
            return $path;
        }

        return '/v2.5.0/passenger/records?mode=create';
    }

    public static function isTraditionalPnrAirbookRetryRedisplayCompareStyle(string $style): bool
    {
        return $style === self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRBOOK_RETRY_REBOOK_REDISPLAY_COMPARE_V1;
    }

    /**
     * @var list<string>
     */
    public const CREATEBOOKING_PAYLOAD_STYLES = [
        'trip_orders_create_booking_v1_current',
        'trip_orders_flight_offer_v1',
        'trip_orders_flight_details_v1',
        'trip_orders_flight_offer_root_v1',
        'trip_orders_flight_details_root_v1',
        'trip_orders_flight_offer_camel_v1',
        'trip_orders_flight_details_camel_v1',
        'trip_orders_flight_details_full_camel_v1',
        'trip_orders_create_booking_root_flight_details_v2',
        'trip_orders_root_flight_details_v2_agency_phone_flat',
        'trip_orders_root_flight_details_v2_agency_phone_nested',
        'trip_orders_root_flight_details_v2_agency_contact_as_contactInfo',
        'trip_orders_root_flight_details_v2_no_agency_contact',
        'trip_orders_flight_details_sabre_v1',
        'trip_orders_flight_details_sabre_agency_v1',
        'trip_orders_flight_details_sabre_agencyInfo_v1',
        'trip_orders_flight_details_sabre_agencyPhoneNumber_v1',
        'trip_orders_flight_details_sabre_agencyPhonesArray_v1',
        'trip_orders_flight_details_sabre_rootAgencyPhone_v1',
        'trip_orders_flight_details_sabre_phoneNumbers_v1',
        'trip_orders_flight_details_sabre_rootPhones_v1',
        'trip_orders_flight_details_sabre_rootPhoneNumbers_v1',
        'trip_orders_flight_details_sabre_contactInfoPhones_v1',
        'trip_orders_flight_details_sabre_agencyPhoneUseType_v1',
        'trip_orders_flight_details_sabre_phone_use_business_v1',
        'trip_orders_flight_details_sabre_phone_use_agency_v1',
        'trip_orders_flight_details_sabre_pos_source_phone_v1',
        'trip_orders_flight_details_sabre_pos_phone_v1',
        'trip_orders_flight_details_sabre_agency_root_camel_v1',
        'trip_orders_flight_details_sabre_travelAgency_v1',
        'trip_orders_flight_details_sabre_customerInfo_phone_v1',
        'trip_orders_flight_details_sabre_phoneLine_v1',
        'trip_orders_flight_details_sabre_phoneLines_v1',
        'trip_orders_flight_details_sabre_contactNumbers_v1',
        'trip_orders_flight_details_sabre_pnrContact_v1',
        'trip_orders_flight_details_sabre_reservationContact_v1',
        'trip_orders_flight_details_sabre_contactInfo_phoneLine_v1',
        'trip_orders_flight_details_sabre_travelers_phone_v1',
        'trip_orders_product_array_v1',
        self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
        self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_VALIDATING_CARRIER_COMPARE_V1,
        self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_PER_SEGMENT_FARE_BASIS_COMPARE_V1,
        self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRBOOK_RETRY_REBOOK_REDISPLAY_COMPARE_V1,
        self::IATI_LIKE_CPNR_V2_4_GDS,
    ];

    /**
     * Payload styles supported by {@code sabre:compare-createbooking-styles} (excludes legacy {@code trip_orders_create_booking_v1_current}).
     *
     * @var list<string>
     */
    public const TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES = [
        'trip_orders_flight_offer_v1',
        'trip_orders_flight_offer_root_v1',
        'trip_orders_flight_offer_camel_v1',
        'trip_orders_flight_details_v1',
        'trip_orders_flight_details_root_v1',
        'trip_orders_flight_details_camel_v1',
        'trip_orders_flight_details_full_camel_v1',
        'trip_orders_create_booking_root_flight_details_v2',
        'trip_orders_root_flight_details_v2_agency_phone_flat',
        'trip_orders_root_flight_details_v2_agency_phone_nested',
        'trip_orders_root_flight_details_v2_agency_contact_as_contactInfo',
        'trip_orders_root_flight_details_v2_no_agency_contact',
        'trip_orders_flight_details_sabre_v1',
        'trip_orders_flight_details_sabre_agency_v1',
        'trip_orders_flight_details_sabre_agencyInfo_v1',
        'trip_orders_flight_details_sabre_agencyPhoneNumber_v1',
        'trip_orders_flight_details_sabre_agencyPhonesArray_v1',
        'trip_orders_flight_details_sabre_rootAgencyPhone_v1',
        'trip_orders_flight_details_sabre_phoneNumbers_v1',
        'trip_orders_flight_details_sabre_rootPhones_v1',
        'trip_orders_flight_details_sabre_rootPhoneNumbers_v1',
        'trip_orders_flight_details_sabre_contactInfoPhones_v1',
        'trip_orders_flight_details_sabre_agencyPhoneUseType_v1',
        'trip_orders_flight_details_sabre_phone_use_business_v1',
        'trip_orders_flight_details_sabre_phone_use_agency_v1',
        'trip_orders_flight_details_sabre_pos_source_phone_v1',
        'trip_orders_flight_details_sabre_pos_phone_v1',
        'trip_orders_flight_details_sabre_agency_root_camel_v1',
        'trip_orders_flight_details_sabre_travelAgency_v1',
        'trip_orders_flight_details_sabre_customerInfo_phone_v1',
        'trip_orders_flight_details_sabre_phoneLine_v1',
        'trip_orders_flight_details_sabre_phoneLines_v1',
        'trip_orders_flight_details_sabre_contactNumbers_v1',
        'trip_orders_flight_details_sabre_pnrContact_v1',
        'trip_orders_flight_details_sabre_reservationContact_v1',
        'trip_orders_flight_details_sabre_contactInfo_phoneLine_v1',
        'trip_orders_flight_details_sabre_travelers_phone_v1',
        'trip_orders_product_array_v1',
    ];

    /**
     * B38: Compare styles that only relocate agency/office phone on Trip Orders wire (B34–B37 experiments).
     *
     * @var list<string>
     */
    public const AGENCY_PHONE_BODY_VARIANT_COMPARE_STYLES = [
        'trip_orders_flight_details_sabre_agencyInfo_v1',
        'trip_orders_flight_details_sabre_agencyPhoneNumber_v1',
        'trip_orders_flight_details_sabre_agencyPhonesArray_v1',
        'trip_orders_flight_details_sabre_rootAgencyPhone_v1',
        'trip_orders_flight_details_sabre_phoneNumbers_v1',
        'trip_orders_flight_details_sabre_rootPhones_v1',
        'trip_orders_flight_details_sabre_rootPhoneNumbers_v1',
        'trip_orders_flight_details_sabre_contactInfoPhones_v1',
        'trip_orders_flight_details_sabre_agencyPhoneUseType_v1',
        'trip_orders_flight_details_sabre_phone_use_business_v1',
        'trip_orders_flight_details_sabre_phone_use_agency_v1',
        'trip_orders_flight_details_sabre_pos_source_phone_v1',
        'trip_orders_flight_details_sabre_pos_phone_v1',
        'trip_orders_flight_details_sabre_agency_root_camel_v1',
        'trip_orders_flight_details_sabre_travelAgency_v1',
        'trip_orders_flight_details_sabre_customerInfo_phone_v1',
        'trip_orders_flight_details_sabre_phoneLine_v1',
        'trip_orders_flight_details_sabre_phoneLines_v1',
        'trip_orders_flight_details_sabre_contactNumbers_v1',
        'trip_orders_flight_details_sabre_pnrContact_v1',
        'trip_orders_flight_details_sabre_reservationContact_v1',
        'trip_orders_flight_details_sabre_contactInfo_phoneLine_v1',
        'trip_orders_flight_details_sabre_travelers_phone_v1',
        'trip_orders_root_flight_details_v2_agency_phone_flat',
        'trip_orders_root_flight_details_v2_agency_phone_nested',
        'trip_orders_root_flight_details_v2_agency_contact_as_contactInfo',
    ];

    /** B38: Default Trip Orders style paired with {@code /v1/trip/orders/createBooking} in endpoint matrix commands. */
    public const BOOKING_ENDPOINT_COMPARE_TRIP_ORDERS_STYLE = 'trip_orders_flight_details_sabre_v1';

    /**
     * P5: Curated Trip Orders styles for mixed/interline certification ({@code sabre:certify-alternative-booking-path}).
     *
     * @var list<string>
     */
    public const BOOKING_ENDPOINT_COMPARE_TRIP_ORDERS_P5_STYLES = [
        'trip_orders_create_booking_root_flight_details_v2',
        'trip_orders_root_flight_details_v2_agency_phone_flat',
        'trip_orders_root_flight_details_v2_agency_phone_nested',
        'trip_orders_root_flight_details_v2_agency_contact_as_contactInfo',
        'trip_orders_root_flight_details_v2_no_agency_contact',
        'trip_orders_flight_details_sabre_v1',
        'trip_orders_flight_details_sabre_agency_v1',
        'trip_orders_flight_offer_v1',
        'trip_orders_flight_details_sabre_agencyInfo_v1',
    ];

    public static function isTripOrdersCreatebookingCompareStyle(string $style): bool
    {
        return $style === self::BOOKING_ENDPOINT_COMPARE_TRIP_ORDERS_STYLE
            || in_array($style, self::BOOKING_ENDPOINT_COMPARE_TRIP_ORDERS_P5_STYLES, true)
            || in_array($style, self::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES, true);
    }

    /**
     * @var list<string>
     */
    public const BOOKING_ENDPOINT_COMPARE_TRADITIONAL_STYLES = [
        self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
        self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_VALIDATING_CARRIER_COMPARE_V1,
        self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_PER_SEGMENT_FARE_BASIS_COMPARE_V1,
        self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRBOOK_RETRY_REBOOK_REDISPLAY_COMPARE_V1,
        self::IATI_LIKE_CPNR_V2_4_GDS,
    ];

    public function resolveCreatebookingPayloadStyle(): string
    {
        $raw = trim((string) config('suppliers.sabre.createbooking_payload_style', 'trip_orders_flight_offer_v1'));

        return $this->normalizeCreatebookingPayloadStyle($raw);
    }

    public function normalizeCreatebookingPayloadStyle(string $style): string
    {
        $s = trim($style);

        return in_array($s, self::CREATEBOOKING_PAYLOAD_STYLES, true) ? $s : 'trip_orders_flight_offer_v1';
    }

    /**
     * Safe structural preview of {@code createBooking} (no traveler/contact values, no passport numbers).
     *
     * @param  array<string, mixed>  $envelope  Trip Orders envelope (may include \_ota*)
     * @return array<string, mixed>
     */
    public function previewRedactedTripOrdersCreateBookingShape(array $envelope): array
    {
        $cb = is_array($envelope['createBooking'] ?? null) ? $envelope['createBooking'] : $this->tripOrdersVirtualCreateBookingFromEnvelope($envelope);

        return $this->redactCreateBookingBranchForPreview($cb);
    }

    /**
     * @param  array<string, mixed>  $cb
     * @return array<string, mixed>
     */
    protected function redactCreateBookingBranchForPreview(array $cb): array
    {
        $out = [];
        foreach ($cb as $k => $v) {
            if ($k === 'travelers') {
                $out[$k] = [
                    'redacted' => true,
                    'count' => is_array($v) ? count($v) : 0,
                ];

                continue;
            }
            if ($k === 'contact' || $k === 'contactInfo') {
                $arr = is_array($v) ? $v : [];
                $phones = is_array($arr['phones'] ?? null) ? $arr['phones'] : [];
                $p0 = is_array($phones[0] ?? null) ? $phones[0] : [];
                $apn = is_array($arr['agencyPhone'] ?? null) ? $arr['agencyPhone'] : [];
                $scalarAgency = is_string($arr['agencyPhone'] ?? null) && trim((string) $arr['agencyPhone']) !== '';
                $out[$k] = [
                    'has_email' => trim((string) ($arr['email'] ?? '')) !== '',
                    'has_phone' => trim((string) ($arr['phone'] ?? '')) !== '',
                    'has_phones' => $phones !== [],
                    'phones_0_has_phoneNumber' => trim((string) ($p0['phoneNumber'] ?? '')) !== '',
                    'phones_0_has_phoneUseType' => trim((string) ($p0['phoneUseType'] ?? '')) !== '',
                    'agencyPhone_has_Number' => trim((string) ($apn['Number'] ?? '')) !== '',
                    'agencyPhone_has_Type' => trim((string) ($apn['Type'] ?? '')) !== '',
                    'agencyPhone_has_LocationCode' => trim((string) ($apn['LocationCode'] ?? '')) !== '',
                    'has_scalar_agencyPhone' => $scalarAgency,
                ];

                continue;
            }
            if ($k === 'phoneLine') {
                $pl = is_array($v) ? $v : [];
                $out[$k] = [
                    'has_Number' => trim((string) ($pl['Number'] ?? '')) !== '',
                    'has_Type' => trim((string) ($pl['Type'] ?? '')) !== '',
                    'has_LocationCode' => trim((string) ($pl['LocationCode'] ?? '')) !== '',
                ];

                continue;
            }
            if ($k === 'phoneLines') {
                $pn = is_array($v) ? $v : [];
                $first = is_array($pn[0] ?? null) ? $pn[0] : [];
                $out[$k] = [
                    'count' => count($pn),
                    'row_0_has_Number' => trim((string) ($first['Number'] ?? '')) !== '',
                ];

                continue;
            }
            if ($k === 'contactNumbers') {
                $pn = is_array($v) ? $v : [];
                $first = is_array($pn[0] ?? null) ? $pn[0] : [];
                $out[$k] = [
                    'count' => count($pn),
                    'row_0_has_Number' => trim((string) ($first['Number'] ?? '')) !== '',
                    'row_0_has_PhoneUseType' => trim((string) ($first['PhoneUseType'] ?? '')) !== '',
                ];

                continue;
            }
            if ($k === 'pnrContact') {
                $p = is_array($v) ? $v : [];
                $ph = is_array($p['phone'] ?? null) ? $p['phone'] : [];
                $out[$k] = [
                    'phone_has_Number' => trim((string) ($ph['Number'] ?? '')) !== '',
                ];

                continue;
            }
            if ($k === 'reservationContact') {
                $p = is_array($v) ? $v : [];
                $phones = is_array($p['phones'] ?? null) ? $p['phones'] : [];
                $p0 = is_array($phones[0] ?? null) ? $phones[0] : [];
                $out[$k] = [
                    'phones_count' => count($phones),
                    'phones_0_has_Number' => trim((string) ($p0['Number'] ?? '')) !== '',
                ];

                continue;
            }
            if ($k === 'agencyContactInfo') {
                $arr = is_array($v) ? $v : [];
                $phones = is_array($arr['phones'] ?? null) ? $arr['phones'] : [];
                $first = is_array($phones[0] ?? null) ? $phones[0] : [];
                $out[$k] = [
                    'has_phone' => trim((string) ($arr['phone'] ?? '')) !== '',
                    'has_phoneNumber' => trim((string) ($arr['phoneNumber'] ?? '')) !== '',
                    'has_phoneCountryCode' => trim((string) ($arr['phoneCountryCode'] ?? '')) !== '',
                    'has_phoneType' => trim((string) ($arr['phoneType'] ?? '')) !== '',
                    'has_phones' => $phones !== [],
                    'phones_0_has_number' => trim((string) ($first['number'] ?? '')) !== '',
                    'phones_0_has_phoneNumber' => trim((string) ($first['phoneNumber'] ?? '')) !== '',
                    'phones_0_has_phoneUseType' => trim((string) ($first['phoneUseType'] ?? '')) !== '',
                ];

                continue;
            }
            if (in_array($k, ['agencyInfo', 'agency'], true)) {
                $arr = is_array($v) ? $v : [];
                $out[$k] = [
                    'has_phone' => trim((string) ($arr['phone'] ?? '')) !== '',
                    'has_phoneNumber' => trim((string) ($arr['phoneNumber'] ?? '')) !== '',
                    'has_phoneCountryCode' => trim((string) ($arr['phoneCountryCode'] ?? '')) !== '',
                    'has_countryCode' => trim((string) ($arr['countryCode'] ?? '')) !== '',
                    'has_phoneType' => trim((string) ($arr['phoneType'] ?? '')) !== '',
                    'has_phoneUseType' => trim((string) ($arr['phoneUseType'] ?? '')) !== '',
                    'has_name' => trim((string) ($arr['name'] ?? '')) !== '',
                ];

                continue;
            }
            if ($k === 'POS') {
                $pos = is_array($v) ? $v : [];
                $sources = is_array($pos['Source'] ?? null) ? $pos['Source'] : [];
                $s0 = is_array($sources[0] ?? null) ? $sources[0] : [];
                $ap = is_array($s0['AgencyPhone'] ?? null) ? $s0['AgencyPhone'] : [];
                $out[$k] = [
                    'source_count' => count($sources),
                    'source_0_has_pseudoCityCode' => trim((string) ($s0['PseudoCityCode'] ?? '')) !== '',
                    'source_0_AgencyPhone_has_PhoneNumber' => trim((string) ($ap['PhoneNumber'] ?? '')) !== '',
                    'source_0_AgencyPhone_has_PhoneUseType' => trim((string) ($ap['PhoneUseType'] ?? '')) !== '',
                ];

                continue;
            }
            if ($k === 'pos') {
                $p = is_array($v) ? $v : [];
                $src = is_array($p['source'] ?? null) ? $p['source'] : [];
                $out[$k] = [
                    'has_source' => $src !== [],
                    'source_has_agencyPhone' => trim((string) ($src['agencyPhone'] ?? '')) !== '',
                    'source_has_agencyPhoneCountryCode' => trim((string) ($src['agencyPhoneCountryCode'] ?? '')) !== '',
                    'source_has_phoneUseType' => trim((string) ($src['phoneUseType'] ?? '')) !== '',
                ];

                continue;
            }
            if ($k === 'travelAgency') {
                $arr = is_array($v) ? $v : [];
                $out[$k] = [
                    'has_phoneNumber' => trim((string) ($arr['phoneNumber'] ?? '')) !== '',
                    'has_phoneUseType' => trim((string) ($arr['phoneUseType'] ?? '')) !== '',
                    'has_countryCode' => trim((string) ($arr['countryCode'] ?? '')) !== '',
                ];

                continue;
            }
            if ($k === 'customerInfo') {
                $arr = is_array($v) ? $v : [];
                $ci = is_array($arr['contactInfo'] ?? null) ? $arr['contactInfo'] : [];
                $out[$k] = [
                    'has_agencyPhone' => trim((string) ($arr['agencyPhone'] ?? '')) !== '',
                    'contactInfo_has_email' => trim((string) ($ci['email'] ?? '')) !== '',
                    'contactInfo_has_phone' => trim((string) ($ci['phone'] ?? '')) !== '',
                ];

                continue;
            }
            if ($k === 'agencyPhone') {
                $out[$k] = ['present' => is_string($v) && trim($v) !== ''];

                continue;
            }
            if ($k === 'agencyPhoneCountryCode') {
                $out[$k] = ['present' => is_string($v) && trim($v) !== ''];

                continue;
            }
            if ($k === 'phoneNumbers') {
                $pn = is_array($v) ? $v : [];
                $first = is_array($pn[0] ?? null) ? $pn[0] : [];
                $out[$k] = [
                    'count' => count($pn),
                    'row_0_has_number' => trim((string) ($first['number'] ?? '')) !== '',
                    'row_0_has_phoneNumber' => trim((string) ($first['phoneNumber'] ?? '')) !== '',
                    'row_0_has_phoneUseType' => trim((string) ($first['phoneUseType'] ?? '')) !== '',
                ];

                continue;
            }
            if ($k === 'phones') {
                $pn = is_array($v) ? $v : [];
                $first = is_array($pn[0] ?? null) ? $pn[0] : [];
                $out[$k] = [
                    'count' => count($pn),
                    'row_0_has_number' => trim((string) ($first['number'] ?? '')) !== '',
                    'row_0_has_phoneNumber' => trim((string) ($first['phoneNumber'] ?? '')) !== '',
                    'row_0_has_phoneUseType' => trim((string) ($first['phoneUseType'] ?? '')) !== '',
                    'row_0_has_type' => trim((string) ($first['type'] ?? '')) !== '',
                ];

                continue;
            }
            if (in_array($k, [
                'flightOffer', 'flightDetails', 'itinerary', 'supplier_context', 'shop_context', 'fare_linkage',
                'pricing', 'payment', 'ticketing', 'passenger_type_counts', 'remarks', 'trip_orders_reservation_action',
                'products',
            ], true)) {
                $out[$k] = $this->shapeOnlyValueForPreview($v, 0, 7);

                continue;
            }
            if ($k === 'validating_carrier') {
                $out[$k] = is_string($v) && trim($v) !== '' ? 'set' : 'empty';

                continue;
            }
            $out[$k] = is_array($v) ? ['keys' => array_slice(array_keys($v), 0, 24)] : gettype($v);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|string
     */
    protected function shapeOnlyValueForPreview(mixed $v, int $depth, int $maxDepth): mixed
    {
        if ($depth >= $maxDepth) {
            return 'max_depth';
        }
        if (! is_array($v)) {
            return is_scalar($v) ? gettype($v) : 'non_scalar';
        }
        if ($v === []) {
            return [];
        }
        if (array_is_list($v)) {
            $first = $v[0] ?? null;

            return [
                'list_len' => count($v),
                'first_item' => is_array($first) ? $this->shapeOnlyValueForPreview($first, $depth + 1, $maxDepth) : gettype($first),
            ];
        }
        $out = [];
        foreach ($v as $kk => $vv) {
            if (! is_string($kk)) {
                continue;
            }
            $lk = strtolower($kk);
            if (str_contains($lk, 'email') || str_contains($lk, 'phone') || str_contains($lk, 'passport')
                || str_contains($lk, 'given') || str_contains($lk, 'surname') || str_contains($lk, 'birth')
                || $lk === 'number' || str_contains($lk, 'passengername')) {
                $out[$kk] = 'redacted';

                continue;
            }
            $out[$kk] = $this->shapeOnlyValueForPreview($vv, $depth + 1, $maxDepth);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $tripSegments  Normalized trip segment rows (post fare-basis merge)
     * @param  array<string, int>  $ptcCounts
     * @param  array<string, mixed>  $shopContext
     * @param  array<string, mixed>  $fareLinkageBlock
     * @return array<string, mixed>
     */
    protected function buildTripOrdersFlightOfferNode(
        array $internalDraft,
        array $tripSegments,
        array $ptcCounts,
        array $shopContext,
        array $fareLinkageBlock,
        float $amount,
        string $currency,
        string $validatingCarrier,
        string $baggageSummary,
        ?float $revalidatedTotal,
        string $revalidatedCurrency,
    ): array {
        $itineraryRef = $this->safeLinkageScalar($fareLinkageBlock['itinerary_reference'] ?? null);
        if ($itineraryRef === null || $itineraryRef === '') {
            foreach (['itinerary_ref', 'itinerary_id', 'itineraryRef', 'itineraryID'] as $ik) {
                $cand = $shopContext[$ik] ?? null;
                if (is_string($cand) && trim($cand) !== '') {
                    $itineraryRef = substr(trim($cand), 0, 120);
                    break;
                }
            }
        }
        $rawRef = null;
        foreach (['raw_reference', 'rawReference', 'itinerary_raw_reference'] as $rk) {
            $cand = $shopContext[$rk] ?? null;
            if (is_string($cand) && trim($cand) !== '') {
                $rawRef = substr(trim($cand), 0, 120);
                break;
            }
        }
        $fareBasisCodes = [];
        foreach ($tripSegments as $row) {
            if (! is_array($row)) {
                continue;
            }
            $fb = strtoupper(trim((string) ($row['fare_basis_code'] ?? '')));
            if ($fb !== '') {
                $fareBasisCodes[] = substr($fb, 0, 24);
            }
        }
        $fareBasisCodes = array_values(array_unique($fareBasisCodes));
        $fareComponentRefs = $this->collectFareComponentReferencesFromShopAndLinkage($shopContext, $fareLinkageBlock);
        $segOut = [];
        foreach ($tripSegments as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $bookingClass = '';
            if (isset($row['class_of_service']) && trim((string) $row['class_of_service']) !== '') {
                $bookingClass = strtoupper(trim((string) $row['class_of_service']));
            } elseif (isset($row['booking_class']) && trim((string) $row['booking_class']) !== '') {
                $bookingClass = strtoupper(trim((string) $row['booking_class']));
            }
            $segOut[] = array_filter([
                'segment_number' => $i + 1,
                'origin' => $row['origin'] ?? null,
                'destination' => $row['destination'] ?? null,
                'departure_at' => $row['departure_at'] ?? null,
                'arrival_at' => $row['arrival_at'] ?? null,
                'marketing_carrier' => $row['marketing_carrier'] ?? null,
                'operating_carrier' => $row['operating_carrier'] ?? null,
                'flight_number' => $row['flight_number'] ?? null,
                'class_of_service' => $bookingClass !== '' ? $bookingClass : null,
                'booking_class' => $bookingClass !== '' ? $bookingClass : null,
                'cabin' => $row['cabin'] ?? null,
                'fare_basis_code' => $row['fare_basis_code'] ?? null,
            ], fn ($x) => $x !== null && $x !== '');
        }

        return array_filter([
            'offer_id' => trim((string) ($internalDraft['selected_offer_id'] ?? '')) !== ''
                ? substr(trim((string) $internalDraft['selected_offer_id']), 0, 120) : null,
            'supplier_offer_id' => trim((string) ($internalDraft['supplier_offer_id'] ?? '')) !== ''
                ? substr(trim((string) $internalDraft['supplier_offer_id']), 0, 120) : null,
            'itinerary_ref' => $itineraryRef !== null && $itineraryRef !== '' ? $itineraryRef : null,
            'raw_reference' => $rawRef,
            'validating_carrier' => $validatingCarrier !== '' ? $validatingCarrier : null,
            'pricing' => array_filter([
                'total' => $amount > 0 ? $amount : null,
                'currency' => $currency !== '' ? $currency : null,
                'revalidated_total' => $revalidatedTotal !== null && $revalidatedTotal > 0 ? $revalidatedTotal : null,
                'revalidated_currency' => $revalidatedCurrency !== '' ? $revalidatedCurrency : null,
            ], fn ($x) => $x !== null && $x !== ''),
            'passenger_type_counts' => array_filter($ptcCounts, fn (int $n): bool => $n > 0),
            'fare_basis_codes' => $fareBasisCodes !== [] ? $fareBasisCodes : null,
            'fare_component_references' => $fareComponentRefs !== [] ? $fareComponentRefs : null,
            'baggage_summary' => $baggageSummary !== '' ? substr($baggageSummary, 0, 160) : null,
            'segments' => $segOut !== [] ? $segOut : null,
        ], fn ($x) => $x !== null && $x !== [] && $x !== '');
    }

    /**
     * @param  array<string, mixed>  $tripSegments
     * @param  array<string, int>  $ptcCounts
     * @param  'legacy'|'full_camel'  $segmentWireKind
     * @return array<string, mixed>
     */
    protected function buildTripOrdersFlightDetailsNode(
        array $tripSegments,
        array $ptcCounts,
        float $amount,
        string $currency,
        string $validatingCarrier,
        string $baggageSummary,
        ?float $revalidatedTotal,
        string $revalidatedCurrency,
        string $segmentWireKind = 'legacy',
    ): array {
        $segOut = [];
        foreach ($tripSegments as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $mkt = strtoupper(trim((string) ($row['marketing_carrier'] ?? '')));
            $op = strtoupper(trim((string) ($row['operating_carrier'] ?? '')));
            $bookingClass = '';
            if (isset($row['class_of_service']) && trim((string) $row['class_of_service']) !== '') {
                $bookingClass = strtoupper(trim((string) $row['class_of_service']));
            } elseif (isset($row['booking_class']) && trim((string) $row['booking_class']) !== '') {
                $bookingClass = strtoupper(trim((string) $row['booking_class']));
            }
            $cabinRaw = trim((string) ($row['cabin'] ?? $row['segment_cabin_code'] ?? ''));
            $cabin = $cabinRaw !== '' ? strtoupper($cabinRaw) : null;
            $fbRaw = trim((string) ($row['fare_basis_code'] ?? ''));
            $fareBasis = $fbRaw !== '' ? strtoupper(substr($fbRaw, 0, 24)) : null;
            if ($segmentWireKind === 'full_camel') {
                $dep = (string) ($row['departure_at'] ?? '');
                $arr = (string) ($row['arrival_at'] ?? '');
                $fn = isset($row['flight_number']) ? trim((string) $row['flight_number']) : '';
                $segOut[] = array_filter([
                    'origin' => isset($row['origin']) ? strtoupper(trim((string) $row['origin'])) : null,
                    'destination' => isset($row['destination']) ? strtoupper(trim((string) $row['destination'])) : null,
                    'departureDateTime' => $dep !== '' ? $dep : null,
                    'arrivalDateTime' => $arr !== '' ? $arr : null,
                    'marketingAirline' => $mkt !== '' ? $mkt : null,
                    'flightNumber' => $fn !== '' ? $fn : null,
                    'classOfService' => $bookingClass !== '' ? $bookingClass : null,
                    'cabinCode' => $cabin,
                    'fareBasisCode' => $fareBasis,
                ], fn ($x) => $x !== null && $x !== '');

                continue;
            }
            $segOut[] = array_filter([
                'segment_number' => $i + 1,
                'marketing_airline' => $mkt !== '' ? $mkt : null,
                'operating_airline' => $op !== '' ? $op : null,
                'flight_number' => isset($row['flight_number']) ? trim((string) $row['flight_number']) : null,
                'origin' => isset($row['origin']) ? strtoupper(trim((string) $row['origin'])) : null,
                'destination' => isset($row['destination']) ? strtoupper(trim((string) $row['destination'])) : null,
                'departure_datetime' => (string) ($row['departure_at'] ?? ''),
                'arrival_datetime' => (string) ($row['arrival_at'] ?? ''),
                'class_of_service' => $bookingClass !== '' ? $bookingClass : null,
                'cabin_code' => $cabin,
                'fare_basis_code' => $fareBasis,
                'baggage_summary' => $baggageSummary !== '' ? substr($baggageSummary, 0, 120) : null,
            ], fn ($x) => $x !== null && $x !== '');
        }

        return array_filter([
            'validating_carrier' => $validatingCarrier !== '' ? $validatingCarrier : null,
            'passenger_type_counts' => array_filter($ptcCounts, fn (int $n): bool => $n > 0),
            'pricing' => array_filter([
                'total' => $amount > 0 ? $amount : null,
                'currency' => $currency !== '' ? $currency : null,
                'revalidated_total' => $revalidatedTotal !== null && $revalidatedTotal > 0 ? $revalidatedTotal : null,
                'revalidated_currency' => $revalidatedCurrency !== '' ? $revalidatedCurrency : null,
            ], fn ($x) => $x !== null && $x !== ''),
            'baggage_summary' => $baggageSummary !== '' ? substr($baggageSummary, 0, 160) : null,
            'segments' => $segOut !== [] ? $segOut : null,
        ], fn ($x) => $x !== null && $x !== [] && $x !== '');
    }

    /**
     * @param  array<string, mixed>  $shopContext
     * @param  array<string, mixed>  $fareLinkageBlock
     * @return list<string>
     */
    protected function collectFareComponentReferencesFromShopAndLinkage(array $shopContext, array $fareLinkageBlock): array
    {
        $out = [];
        foreach ($fareLinkageBlock as $k => $v) {
            if (! is_string($k) || ! is_string($v) || trim($v) === '') {
                continue;
            }
            $kl = strtolower($k);
            if (str_contains($kl, 'fare_component') || str_contains($kl, 'component_ref')) {
                $out[] = substr(trim($v), 0, 64);
            }
        }
        foreach ($shopContext as $k => $v) {
            if (! is_string($k) || ! is_string($v) || trim($v) === '') {
                continue;
            }
            $kl = strtolower($k);
            if (str_contains($kl, 'fare_component') || str_contains($kl, 'farecomponent')) {
                $out[] = substr(trim($v), 0, 64);
            }
        }

        return array_values(array_unique(array_slice($out, 0, 24)));
    }

    protected function safeLinkageScalar(mixed $v, int $max = 120): ?string
    {
        if (! is_scalar($v)) {
            return null;
        }
        $t = trim((string) $v);
        if ($t === '') {
            return null;
        }

        return substr($t, 0, $max);
    }

    /**
     * Trip Orders traveler passport block; returns null when no passport number or when mandatory fields are missing on an international route.
     *
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>|null
     */
    protected function tripOrdersTravelerPassportNode(array $p, bool $requirePassportRoute): ?array
    {
        $num = trim((string) ($p['passport_number'] ?? ''));
        if ($num === '') {
            return null;
        }
        $mappedType = $this->mapSabreTripOrdersTravelerDocumentType((string) ($p['document_type'] ?? 'passport'));
        if ($mappedType === null || $mappedType === '') {
            $mappedType = $this->sabrePassportDocumentTypeValue();
        }
        $iss = strtoupper(trim((string) ($p['passport_issuing_country'] ?? '')));
        $nat = strtoupper(trim((string) ($p['nationality'] ?? '')));
        $exp = trim((string) ($p['passport_expiry_date'] ?? ''));
        if ($requirePassportRoute) {
            if ($iss === '' || $nat === '' || $exp === '' || ! $this->iso3166Alpha2Valid($iss) || ! $this->iso3166Alpha2Valid($nat) || ! $this->tripOrdersExpiryDateShapeValid($exp)) {
                return null;
            }
        }

        return array_filter([
            'document_type' => $mappedType,
            'issuing_country' => $iss !== '' ? $iss : null,
            'nationality' => $nat !== '' ? $nat : null,
            'expiry_date' => $exp !== '' ? $exp : null,
            'number' => $num,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Safe scalar summary for logs, attempts table, and inspect commands (no PII values).
     *
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    public function summarizeEnvelopeForDiagnostics(array $envelope): array
    {
        if (($envelope['_ota_payload_schema'] ?? '') === 'trip_orders_create_booking_v1') {
            return $this->summarizeTripOrdersEnvelopeForDiagnostics($envelope);
        }

        /** B62: Traditional Passenger Records wire uses only {@code CreatePassengerNameRecordRQ} at HTTP root — reuse consolidated contract diagnostics. */
        /** B79: Airprice validating-carrier compare wire shares the same transport diagnostics with explicit {@code payload_schema}. */
        $cpnrSchema = (string) ($envelope['_ota_payload_schema'] ?? '');
        if (self::isTraditionalPnrPassengerRecordsWireStyle($cpnrSchema)
            || $cpnrSchema === self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1) {
            $wireBody = $this->stripOtaInternalKeysFromBookingWire($envelope);
            $trad = $this->summarizeTraditionalPnrWirePostBody($wireBody, null, $cpnrSchema);
            $schema = $cpnrSchema !== '' ? $cpnrSchema : self::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1;

            return array_merge($trad, [
                'payload_style' => $schema,
                'payload_schema' => $schema,
                'booking_transport' => 'rest_json_passenger_records_cpnr',
                'booking_mode' => (string) config('suppliers.sabre.booking_mode', 'pnr_only'),
                'ticketing_enabled' => (bool) config('suppliers.sabre.ticketing_enabled', false),
                'segment_count' => (int) ($trad['wire_segment_count'] ?? 0),
                'passenger_count' => (int) ($trad['wire_passenger_count'] ?? 0),
                'has_contact_email' => (bool) ($trad['wire_customer_info_has_email'] ?? false),
                'has_contact_phone' => (bool) ($trad['wire_customer_info_has_contact_numbers'] ?? false),
                'has_booking_class' => (bool) ($trad['wire_flight_segment_has_res_book_desig_code'] ?? false),
                'has_fare_basis' => (bool) ($trad['wire_flight_segment_has_fare_basis_code'] ?? false)
                    || (bool) ($trad['wire_airprice_has_fare_basis'] ?? false)
                    || (int) ($trad['wire_fare_basis_count'] ?? 0) > 0,
                'has_validating_carrier' => (bool) ($trad['wire_airprice_has_validating_carrier'] ?? false),
                'has_end_transaction' => (bool) ($trad['wire_post_processing_has_end_transaction'] ?? false),
                'has_ticketing_disabled_marker' => true,
            ]);
        }

        $segments = is_array($envelope['itinerary']['segments'] ?? null) ? $envelope['itinerary']['segments'] : [];
        $travelers = is_array($envelope['travelers'] ?? null) ? $envelope['travelers'] : [];
        $contact = is_array($envelope['contact'] ?? null) ? $envelope['contact'] : [];
        $ticketing = is_array($envelope['ticketing'] ?? null) ? $envelope['ticketing'] : [];
        $hasBookingClass = false;
        $hasFareBasis = false;
        foreach ($segments as $s) {
            if (! is_array($s)) {
                continue;
            }
            if (trim((string) ($s['booking_class'] ?? '')) !== '') {
                $hasBookingClass = true;
            }
            if (trim((string) ($s['fare_basis_code'] ?? '')) !== '') {
                $hasFareBasis = true;
            }
        }
        $hasPassportDoc = false;
        foreach ($travelers as $t) {
            if (! is_array($t)) {
                continue;
            }
            $doc = $t['Document'] ?? null;
            if (is_array($doc) && trim((string) ($doc['Number'] ?? '')) !== '') {
                $hasPassportDoc = true;
                break;
            }
        }

        $cpnrBlock = is_array($envelope['CreatePassengerNameRecordRQ'] ?? null) ? $envelope['CreatePassengerNameRecordRQ'] : [];
        $hasSpecialReq = is_array($cpnrBlock['SpecialReqDetails'] ?? null) && $cpnrBlock['SpecialReqDetails'] !== [];
        $mode = (string) ($envelope['ota_booking_mode'] ?? config('suppliers.sabre.booking_mode', 'pnr_only'));
        $pricingBlock = is_array($envelope['pricing'] ?? null) ? $envelope['pricing'] : [];
        $total = $pricingBlock['total'] ?? null;
        $hasAmount = is_numeric($total) && (float) $total > 0;
        $cur = (string) ($pricingBlock['currency'] ?? '');
        $hasCurrency = trim($cur) !== '';
        $hasPaymentMode = $mode !== '';
        $hasTicketingDisabledMarker = $hasSpecialReq || (($ticketing['ticketing_enabled'] ?? true) === false);

        return [
            'payload_schema' => (string) ($envelope['ota_schema'] ?? ''),
            'booking_transport' => 'rest_json',
            'booking_mode' => $mode,
            'ticketing_enabled' => (bool) config('suppliers.sabre.ticketing_enabled', false),
            'segment_count' => count($segments),
            'passenger_count' => count($travelers),
            'has_contact_email' => trim((string) ($contact['email'] ?? '')) !== '',
            'has_contact_phone' => trim((string) ($contact['phone'] ?? '')) !== '',
            'has_passport_doc' => $hasPassportDoc,
            'has_booking_class' => $hasBookingClass,
            'has_fare_basis' => $hasFareBasis,
            'has_ticketing_instruction' => ($ticketing['time_limit_hint'] ?? null) !== null
                || (($ticketing['remarks'] ?? null) !== null && trim((string) $ticketing['remarks']) !== '')
                || $hasSpecialReq,
            'has_end_transaction' => is_array($envelope['PostProcessing']['EndTransaction'] ?? null),
            'has_payment_mode' => $hasPaymentMode,
            'has_amount' => $hasAmount,
            'has_currency' => $hasCurrency,
            'has_ticketing_disabled_marker' => $hasTicketingDisabledMarker,
        ];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>|null
     */
    protected function travelerDocumentNode(array $p): ?array
    {
        $num = trim((string) ($p['passport_number'] ?? ''));
        if ($num === '') {
            return null;
        }

        return array_filter([
            'Type' => 'PASSPORT',
            'Number' => $num,
            'IssueCountry' => strtoupper(trim((string) ($p['passport_issuing_country'] ?? ''))),
            'NationalityCountry' => strtoupper(trim((string) ($p['nationality'] ?? ''))),
            'ExpirationDate' => (string) ($p['passport_expiry_date'] ?? ''),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $fare
     * @param  list<array<string, mixed>>  $passengers
     * @return list<array<string, mixed>>
     */
    protected function buildPtcFareBreakdowns(array $segments, array $fare, array $passengers): array
    {
        $ptc = [];
        foreach ($passengers as $p) {
            if (! is_array($p)) {
                continue;
            }
            $code = (string) ($p['type'] ?? 'ADT');
            $ptc[] = [
                'PassengerTypeQuantity' => ['Code' => $code, 'Quantity' => 1],
                'FareBasisCodes' => array_values(array_filter(array_map(
                    fn ($s) => is_array($s) ? trim((string) ($s['fare_basis_code'] ?? '')) : '',
                    $segments
                ))),
            ];
        }

        return $ptc !== [] ? $ptc : [[
            'PassengerTypeQuantity' => ['Code' => 'ADT', 'Quantity' => 1],
            'FareBasisCodes' => array_values(array_filter(array_map(
                fn ($s) => is_array($s) ? trim((string) ($s['fare_basis_code'] ?? '')) : '',
                $segments
            ))),
        ]];
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    protected function normalizeOfferForPayload(array $offer): array
    {
        $fare = is_array($offer['fare_breakdown'] ?? null) ? $offer['fare_breakdown'] : [];
        $amount = (float) ($fare['supplier_total'] ?? $offer['final_customer_price'] ?? 0);
        $currency = trim((string) ($fare['currency'] ?? $offer['pricing_currency'] ?? ''));
        $baseFare = (float) ($fare['base_fare'] ?? 0);
        $taxes = (float) ($fare['taxes'] ?? 0);

        $carrier = strtoupper(trim((string) ($offer['airline_code'] ?? $offer['carrier_code'] ?? '')));
        $segmentsOut = [];
        $segments = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];
        $b65Prep = SabrePassengerRecordsMultiSegmentSellVerifier::prepareSegmentsForPayload($offer, array_values($segments));
        $segments = is_array($b65Prep['segments'] ?? null) ? array_values($b65Prep['segments']) : $segments;
        $handoff = is_array(data_get($offer, 'raw_payload.sabre_booking_context'))
            ? data_get($offer, 'raw_payload.sabre_booking_context')
            : (is_array($offer['sabre_booking_context'] ?? null) ? $offer['sabre_booking_context'] : []);
        $bookingBySeg = is_array($handoff['booking_classes_by_segment'] ?? null) ? $handoff['booking_classes_by_segment'] : [];
        $fareBasisBySeg = is_array($handoff['fare_basis_codes_by_segment'] ?? null) ? $handoff['fare_basis_codes_by_segment'] : [];
        $cabinBySeg = is_array($handoff['cabin_by_segment'] ?? null) ? $handoff['cabin_by_segment'] : [];

        foreach ($segments as $idx => $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $bc = strtoupper(trim((string) ($seg['booking_class'] ?? $seg['class_of_service'] ?? $seg['res_book_desig_code'] ?? '')));
            if ($bc === '' && isset($bookingBySeg[$idx]) && trim((string) $bookingBySeg[$idx]) !== '') {
                $bc = strtoupper(trim((string) $bookingBySeg[$idx]));
            }
            $fb = isset($seg['fare_basis_code']) ? strtoupper(trim((string) $seg['fare_basis_code'])) : '';
            if ($fb === '' && isset($fareBasisBySeg[$idx]) && trim((string) $fareBasisBySeg[$idx]) !== '') {
                $fb = strtoupper(trim((string) $fareBasisBySeg[$idx]));
            }
            $cab = isset($seg['segment_cabin_code']) ? strtoupper(trim((string) $seg['segment_cabin_code'])) : '';
            if ($cab === '' && isset($cabinBySeg[$idx]) && trim((string) $cabinBySeg[$idx]) !== '') {
                $cab = strtoupper(trim((string) $cabinBySeg[$idx]));
            }
            $segmentsOut[] = array_filter([
                'origin' => strtoupper(trim((string) ($seg['origin'] ?? ''))),
                'destination' => strtoupper(trim((string) ($seg['destination'] ?? ''))),
                'carrier' => strtoupper(trim((string) ($seg['carrier'] ?? $seg['airline_code'] ?? $seg['marketing_airline'] ?? $carrier))),
                'flight_number' => trim((string) ($seg['flight_number'] ?? $seg['flight_no'] ?? $offer['flight_number'] ?? '')),
                'departure_at' => (string) ($seg['departure_at'] ?? $seg['depart_at'] ?? ''),
                'arrival_at' => (string) ($seg['arrival_at'] ?? $seg['arrive_at'] ?? ''),
                'operating_airline_code' => isset($seg['operating_airline_code']) ? strtoupper(trim((string) $seg['operating_airline_code'])) : null,
                'booking_class' => $bc !== '' ? $bc : null,
                'fare_basis_code' => $fb !== '' ? $fb : null,
                'segment_cabin_code' => $cab !== '' ? $cab : null,
            ], fn ($v) => $v !== null && $v !== '');
        }

        $baggage = is_array($offer['baggage'] ?? null) ? $offer['baggage'] : [];
        $baggageSummary = trim((string) ($baggage['summary'] ?? ''));

        $base = $offer;
        $base['_fare_amount'] = $amount;
        $base['_fare_currency'] = $currency;
        $base['_fare_base'] = $baseFare;
        $base['_fare_taxes'] = $taxes;
        $base['_segments_out'] = $segmentsOut;
        $base['_b65_multi_segment_prep'] = [
            'segment_order_repaired_for_sell' => (bool) ($b65Prep['segment_order_repaired'] ?? false),
            'date_repair_applied' => (bool) ($b65Prep['date_repair_applied'] ?? false),
        ];
        $base['_baggage_summary'] = $baggageSummary;
        $handoffVc = strtoupper(trim((string) ($handoff['validating_carrier'] ?? '')));
        $base['validating_carrier'] = strtoupper(trim((string) ($offer['validating_carrier'] ?? '')));
        if ($base['validating_carrier'] === '' && $handoffVc !== '') {
            $base['validating_carrier'] = $handoffVc;
        }
        $shop = [];
        if (is_array($offer['raw_payload']['sabre_shop_identifiers'] ?? null)) {
            $shop = $offer['raw_payload']['sabre_shop_identifiers'];
        } elseif (is_array($offer['sabre_shop_identifiers'] ?? null)) {
            $shop = $offer['sabre_shop_identifiers'];
        }
        $shopContext = [];
        if (is_array($offer['raw_payload']['sabre_shop_context'] ?? null)) {
            $shopContext = $offer['raw_payload']['sabre_shop_context'];
        } elseif (is_array($offer['sabre_shop_context'] ?? null)) {
            $shopContext = $offer['sabre_shop_context'];
        }
        $cleanShop = [];
        if (is_array($shop)) {
            foreach ($shop as $k => $v) {
                if (! is_string($k) || trim($k) === '') {
                    continue;
                }
                if (is_string($v) && trim($v) !== '') {
                    $cleanShop[substr($k, 0, 64)] = substr(trim($v), 0, 120);
                }
            }
        }
        $mergedContext = $this->mergeSabreShopIdentifiersIntoContext($shopContext, $cleanShop);
        $base['_sabre_shop_identifiers'] = $cleanShop;
        $base['_sabre_shop_context'] = $this->sanitizeShopContext($mergedContext !== [] ? $mergedContext : $cleanShop);
        if ($handoff !== []) {
            $base['_sabre_booking_context'] = $handoff;
        }
        $girArchive = is_array($offer['raw_payload']['sabre_bfm_gir_archive'] ?? null)
            ? $offer['raw_payload']['sabre_bfm_gir_archive']
            : (is_array($offer['sabre_bfm_gir_archive'] ?? null) ? $offer['sabre_bfm_gir_archive'] : []);
        if ($girArchive !== []) {
            $base['_sabre_bfm_gir_archive'] = $girArchive;
        }

        return $base;
    }

    /**
     * Fill missing pricing / itinerary reference tokens on {@code sabre_shop_context} from the compact
     * {@code sabre_shop_identifiers} snapshot (BFM may expose linkage only on one side).
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $identifiers
     * @return array<string, mixed>
     */
    protected function mergeSabreShopIdentifiersIntoContext(array $context, array $identifiers): array
    {
        if ($identifiers === []) {
            return $context;
        }
        $out = $context;
        foreach ($identifiers as $k => $v) {
            if (! is_string($k) || trim($k) === '') {
                continue;
            }
            if (! array_key_exists($k, $out)) {
                $out[$k] = $v;

                continue;
            }
            $cur = $out[$k];
            $curEmpty = $cur === null || $cur === '' || $cur === [];
            if ($curEmpty) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function sanitizeShopContext(array $context): array
    {
        $out = [];
        foreach ($context as $k => $v) {
            if (! is_string($k) || trim($k) === '') {
                continue;
            }
            $key = substr(trim($k), 0, 64);
            if (is_string($v) || is_numeric($v) || is_bool($v)) {
                $safe = substr(trim((string) $v), 0, 120);
                if ($safe !== '') {
                    $out[$key] = $safe;
                }

                continue;
            }
            if (! is_array($v)) {
                continue;
            }
            $clean = [];
            foreach ($v as $nk => $nv) {
                if (is_string($nv) || is_numeric($nv) || is_bool($nv)) {
                    $safe = substr(trim((string) $nv), 0, 120);
                    if ($safe !== '') {
                        $clean[$nk] = $safe;
                    }
                } elseif (is_array($nv)) {
                    $nested = [];
                    foreach ($nv as $nnk => $nnv) {
                        if (is_string($nnv) || is_numeric($nnv) || is_bool($nnv)) {
                            $safe = substr(trim((string) $nnv), 0, 120);
                            if ($safe !== '') {
                                $nested[$nnk] = $safe;
                            }
                        }
                    }
                    if ($nested !== []) {
                        $clean[$nk] = array_is_list($nv) ? array_slice(array_values($nested), 0, 24) : $nested;
                    }
                }
            }
            if ($clean !== []) {
                $out[$key] = array_is_list($v) ? array_slice(array_values($clean), 0, 48) : $clean;
            }
        }

        return $out;
    }

    protected function passengerTypeToSabreCode(string $type): string
    {
        return match (strtolower(trim($type))) {
            'child' => 'CHD',
            'infant' => 'INF',
            default => 'ADT',
        };
    }

    /**
     * Sabre Trip Orders {@code GenderEnum} strings accepted by the REST deserializer.
     *
     * @return list<string>
     */
    public static function sabreTripOrdersGenderEnumAccepted(): array
    {
        return ['UNDEFINED', 'UNDISCLOSED', 'INFANT_FEMALE', 'INFANT_MALE', 'FEMALE', 'MALE'];
    }

    /**
     * Map UI / DB / internal passenger gender input to Sabre Trip Orders {@code GenderEnum}.
     * Unknown or unrecognized values → {@code UNDISCLOSED} (privacy-safe default; {@code UNDEFINED} is reserved for explicit pass-through only).
     */
    public function mapToSabreTripOrdersGenderEnum(mixed $raw): string
    {
        if ($raw === null) {
            return 'UNDISCLOSED';
        }
        $s = is_string($raw) ? trim($raw) : trim((string) $raw);
        if ($s === '') {
            return 'UNDISCLOSED';
        }
        $u = strtoupper($s);
        $lower = strtolower($s);
        if ($lower === 'infant_male' || $u === 'INFANT_MALE' || $u === 'IM') {
            return 'INFANT_MALE';
        }
        if ($lower === 'infant_female' || $u === 'INFANT_FEMALE' || $u === 'IF') {
            return 'INFANT_FEMALE';
        }
        if ($u === 'MALE' || $u === 'M') {
            return 'MALE';
        }
        if ($u === 'FEMALE' || $u === 'F') {
            return 'FEMALE';
        }
        if ($lower === 'male') {
            return 'MALE';
        }
        if ($lower === 'female') {
            return 'FEMALE';
        }
        if ($u === 'UNDEFINED' || $u === 'UNDISCLOSED') {
            return $u;
        }

        return 'UNDISCLOSED';
    }

    /**
     * CreatePassengerNameRecord-style traveler {@code Gender} uses single-letter M/F when known; undisclosed/undefined → omit.
     */
    protected function tripOrdersGenderEnumToCpnrGenderCode(?string $enum): ?string
    {
        if ($enum === null || $enum === '') {
            return null;
        }

        return match (strtoupper(trim($enum))) {
            'MALE', 'INFANT_MALE' => 'M',
            'FEMALE', 'INFANT_FEMALE' => 'F',
            default => null,
        };
    }

    /**
     * Sabre Trip Orders person-name pattern (digits/spaces-only tokens rejected).
     */
    public static function sabreTripOrdersPersonNamePatternValid(string $name): bool
    {
        return $name !== '' && (bool) preg_match('/^[^\d\s]+( [^\d\s]+)*$/u', $name);
    }

    protected function tripOrdersWireTravelersUseCamelCaseKeys(array $travelers): bool
    {
        foreach ($travelers as $tv) {
            if (! is_array($tv)) {
                continue;
            }
            if (array_key_exists('givenName', $tv)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $travelers
     */
    protected function wireTravelersHasNonEmptyKey(array $travelers, string $key): bool
    {
        foreach ($travelers as $tv) {
            if (! is_array($tv)) {
                continue;
            }
            if (trim((string) ($tv[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $travelers
     * @param  'passengerTypeCode'|'passengerCode'|null  $camelPrimaryPtcWireKey  When {@code $camelWireTravelers}, which top-level PTC field is required (null => {@code passengerTypeCode}).
     * @return array<string, mixed>
     */
    protected function summarizeTripOrdersWireTravelerFieldDiagnostics(
        array $travelers,
        bool $requiresPassportRoute,
        bool $camelWireTravelers = false,
        ?string $camelPrimaryPtcWireKey = null,
    ): array {
        $acceptedDocTypes = $this->sabreAcceptedTravelerDocumentTypes();
        $invalidKeys = [];
        $out = [];
        $i = 0;
        $ptcReq = $camelWireTravelers
            ? (($camelPrimaryPtcWireKey === 'passengerCode') ? 'passengerCode' : 'passengerTypeCode')
            : 'passenger_type_code';
        foreach ($travelers as $tv) {
            if (! is_array($tv)) {
                continue;
            }
            $i++;
            $pfx = 'traveler_'.$i.'_';
            $givenSnake = isset($tv['given_name']) ? trim((string) $tv['given_name']) : '';
            $givenCamel = isset($tv['givenName']) ? trim((string) $tv['givenName']) : '';
            $given = $camelWireTravelers ? $givenCamel : $givenSnake;
            $surname = isset($tv['surname']) ? trim((string) $tv['surname']) : '';
            $gender = isset($tv['gender']) ? strtoupper(trim((string) $tv['gender'])) : '';
            $dobSnake = isset($tv['birth_date']) ? trim((string) $tv['birth_date']) : '';
            $dobCamel = isset($tv['birthDate']) ? trim((string) $tv['birthDate']) : '';
            $dob = $camelWireTravelers ? $dobCamel : $dobSnake;
            $doc = isset($tv['passport']) && is_array($tv['passport']) ? $tv['passport'] : null;

            $hasGiven = $given !== '';
            $givenPatOk = $hasGiven && self::sabreTripOrdersPersonNamePatternValid($given);
            $hasSurname = $surname !== '';
            $surnamePatOk = $hasSurname && self::sabreTripOrdersPersonNamePatternValid($surname);
            $hasGender = $gender !== '';
            $genderEnumOk = $hasGender && in_array($gender, self::sabreTripOrdersGenderEnumAccepted(), true);
            $hasDob = $dob !== '' && $this->tripOrdersBirthDateShapeValid($dob);
            $hasDoc = $doc !== null && $doc !== [];
            $docTypeRaw = $hasDoc ? trim((string) ($doc['documentType'] ?? $doc['document_type'] ?? '')) : '';
            $docType = strtoupper($docTypeRaw);
            $hasDocType = $docType !== '';
            $docTypeOk = $hasDocType && in_array($docType, $acceptedDocTypes, true);
            $iss = $hasDoc ? strtoupper(trim((string) ($doc['issuingCountry'] ?? $doc['issuing_country'] ?? ''))) : '';
            $nat = $hasDoc ? strtoupper(trim((string) ($doc['nationality'] ?? ''))) : '';
            $exp = $hasDoc ? trim((string) ($doc['expiryDate'] ?? $doc['expiry_date'] ?? '')) : '';
            $num = $hasDoc ? trim((string) ($doc['number'] ?? '')) : '';
            $hasIss = $iss !== '';
            $issOk = $hasIss && $this->iso3166Alpha2Valid($iss);
            $hasNat = $nat !== '';
            $natOk = $hasNat && $this->iso3166Alpha2Valid($nat);
            $hasExp = $exp !== '';
            $expOk = $hasExp && $this->tripOrdersExpiryDateShapeValid($exp);
            $hasNum = $num !== '';
            $ptcSnake = isset($tv['passenger_type_code']) ? trim((string) $tv['passenger_type_code']) : '';
            $ptcCamel = isset($tv['passengerTypeCode']) ? trim((string) $tv['passengerTypeCode']) : '';
            $pcCamel = isset($tv['passengerCode']) ? trim((string) $tv['passengerCode']) : '';
            $hasPtcPrimary = $camelWireTravelers
                ? ($ptcReq === 'passengerCode' ? ($pcCamel !== '') : ($ptcCamel !== ''))
                : ($ptcSnake !== '');

            $base = $pfx;
            $out[$base.'has_given_name'] = $givenSnake !== '';
            $out[$base.'has_givenName'] = $givenCamel !== '';
            $out[$base.'given_name_pattern_valid'] = $givenPatOk;
            $out[$base.'has_surname'] = $hasSurname;
            $out[$base.'surname_pattern_valid'] = $surnamePatOk;
            $out[$base.'has_gender'] = $hasGender;
            $out[$base.'gender_enum_valid'] = $genderEnumOk;
            $out[$base.'has_birth_date'] = $dobSnake !== '' && $this->tripOrdersBirthDateShapeValid($dobSnake);
            $out[$base.'has_birthDate'] = $dobCamel !== '' && $this->tripOrdersBirthDateShapeValid($dobCamel);
            $out[$base.'has_document'] = $hasDoc;
            $out[$base.'has_document_type'] = $hasDoc && trim((string) ($doc['document_type'] ?? '')) !== '';
            $out[$base.'has_documentType'] = $hasDoc && trim((string) ($doc['documentType'] ?? '')) !== '';
            $out[$base.'document_type_valid'] = $docTypeOk;
            $out[$base.'has_issuing_country'] = $hasDoc && trim((string) ($doc['issuing_country'] ?? '')) !== '';
            $out[$base.'has_issuingCountry'] = $hasDoc && trim((string) ($doc['issuingCountry'] ?? '')) !== '';
            $out[$base.'has_nationality'] = $hasNat;
            $out[$base.'nationality_valid'] = $natOk;
            $out[$base.'has_expiry_date'] = $hasDoc && trim((string) ($doc['expiry_date'] ?? '')) !== '';
            $out[$base.'has_expiryDate'] = $hasDoc && trim((string) ($doc['expiryDate'] ?? '')) !== '';
            $out[$base.'has_document_number'] = $hasNum;
            $out[$base.'has_passenger_type_code'] = $ptcSnake !== '';
            $out[$base.'has_passengerTypeCode'] = $ptcCamel !== '';
            $out[$base.'has_passengerCode'] = $pcCamel !== '';

            $gk = $camelWireTravelers ? 'givenName' : 'given_name';
            $dobk = $camelWireTravelers ? 'birthDate' : 'birth_date';
            $ptck = $camelWireTravelers ? $ptcReq : 'passenger_type_code';

            if (! $hasGiven) {
                $invalidKeys[] = $pfx.$gk;
            } elseif (! $givenPatOk) {
                $invalidKeys[] = $pfx.$gk.'_pattern';
            }
            if (! $hasSurname) {
                $invalidKeys[] = $pfx.'surname';
            } elseif (! $surnamePatOk) {
                $invalidKeys[] = $pfx.'surname_pattern';
            }
            if (! $hasGender) {
                $invalidKeys[] = $pfx.'gender';
            } elseif (! $genderEnumOk) {
                $invalidKeys[] = $pfx.'gender_enum';
            }
            if (! $hasDob) {
                $invalidKeys[] = $pfx.$dobk;
            }
            if ($camelWireTravelers && ! $hasPtcPrimary) {
                $invalidKeys[] = $pfx.$ptck;
            }
            if ($requiresPassportRoute) {
                if (! $hasDoc) {
                    $invalidKeys[] = $pfx.'passport';
                } else {
                    if (! $hasDocType || ! $docTypeOk) {
                        $invalidKeys[] = $pfx.'passport.'.($camelWireTravelers ? 'documentType' : 'document_type');
                    }
                    if (! $hasIss || ! $issOk) {
                        $invalidKeys[] = $pfx.'passport.'.($camelWireTravelers ? 'issuingCountry' : 'issuing_country');
                    }
                    if (! $hasNat || ! $natOk) {
                        $invalidKeys[] = $pfx.'passport.nationality';
                    }
                    if (! $hasExp || ! $expOk) {
                        $invalidKeys[] = $pfx.'passport.'.($camelWireTravelers ? 'expiryDate' : 'expiry_date');
                    }
                    if (! $hasNum) {
                        $invalidKeys[] = $pfx.'passport.number';
                    }
                }
            } elseif ($hasDoc && $hasNum) {
                if (! $hasDocType || ! $docTypeOk) {
                    $invalidKeys[] = $pfx.'passport.'.($camelWireTravelers ? 'documentType' : 'document_type');
                }
                if (! $hasIss || ! $issOk) {
                    $invalidKeys[] = $pfx.'passport.'.($camelWireTravelers ? 'issuingCountry' : 'issuing_country');
                }
                if (! $hasNat || ! $natOk) {
                    $invalidKeys[] = $pfx.'passport.nationality';
                }
                if (! $hasExp || ! $expOk) {
                    $invalidKeys[] = $pfx.'passport.'.($camelWireTravelers ? 'expiryDate' : 'expiry_date');
                }
            }
        }

        $out['wire_traveler_required_fields_valid'] = $invalidKeys === [];
        $out['wire_invalid_traveler_field_keys'] = array_values(array_unique(array_slice($invalidKeys, 0, 48)));

        return $out;
    }

    protected function normalizeTripOrdersPersonName(string $raw): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
        $s = preg_replace('/\d+/u', '', $s) ?? '';
        $s = preg_replace('/[^\p{L}\p{M}\s\'\-\.]/u', '', $s) ?? '';
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');

        return $s;
    }

    protected function iso3166Alpha2Valid(string $code): bool
    {
        return (bool) preg_match('/^[A-Z]{2}$/', strtoupper(trim($code)));
    }

    protected function tripOrdersExpiryDateShapeValid(string $exp): bool
    {
        $t = trim($exp);

        return $t !== '' && (bool) preg_match('/^\d{4}-\d{2}-\d{2}/', $t);
    }

    protected function tripOrdersBirthDateShapeValid(string $dob): bool
    {
        return $this->tripOrdersExpiryDateShapeValid($dob);
    }

    /**
     * @return list<string>
     */
    protected function sabreAcceptedTravelerDocumentTypes(): array
    {
        $p = $this->sabrePassportDocumentTypeValue();

        return array_values(array_unique(array_filter([strtoupper($p), 'PASSPORT', 'NATIONAL_ID'], static fn (string $x): bool => $x !== '')));
    }

    protected function sabrePassportDocumentTypeValue(): string
    {
        return strtoupper(trim((string) config('suppliers.sabre.document_type_passport_value', 'PASSPORT')));
    }

    protected function mapSabreTripOrdersTravelerDocumentType(string $raw): ?string
    {
        $u = strtoupper(trim($raw));
        $l = strtolower(trim($raw));
        if ($l === 'passport' || $u === 'P' || $u === 'PP' || $u === 'PASSPORT') {
            return $this->sabrePassportDocumentTypeValue();
        }
        if ($l === 'national_id' || $u === 'NID' || $u === 'NATIONAL_ID') {
            return 'NATIONAL_ID';
        }

        return null;
    }

    /**
     * When false (default), Trip Orders wire JSON omits {@code remarks} (Sabre {@code BookRemark} contract).
     */
    protected function tripOrdersWireRemarksEnabled(): bool
    {
        return (bool) config('suppliers.sabre.createbooking_send_remarks', false);
    }

    /**
     * Trip Orders {@code remarks[]} as {@code BookRemark}-style maps (not plain strings).
     *
     * @return list<array{type: string, text: string}>
     */
    protected function buildTripOrdersWireRemarks(bool $ticketingEnabled, string $baggageSummary): array
    {
        $out = [];
        if (! $ticketingEnabled) {
            $out[] = ['type' => 'GENERAL', 'text' => 'TICKETING_DISABLED_PENDING_MANUAL'];
        }
        if ($baggageSummary !== '') {
            $out[] = ['type' => 'GENERAL', 'text' => 'BAGGAGE: '.substr($baggageSummary, 0, 160)];
        }

        return $out;
    }
}
