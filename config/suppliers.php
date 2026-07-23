<?php

use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Support\Bookings\SabreCertifiedRouteSelector;

return [
    'sabre' => [
        'default_base_url' => env('SABRE_BASE_URL', 'https://api-crt.cert.havail.sabre.com'),
        'token_path' => env('SABRE_TOKEN_PATH', '/v2/auth/token'),
        /** BFM / Offers Shop HTTP path (v4 default per Sabre entitlement). Override with SABRE_SHOP_PATH (legacy: SABRE_SEARCH_PATH). */
        'shop_path' => env('SABRE_SHOP_PATH', env('SABRE_SEARCH_PATH', '/v4/offers/shop')),
        'timeout_seconds' => (int) env('SABRE_TIMEOUT_SECONDS', 30),
        'connect_timeout_seconds' => (int) env('SABRE_CONNECT_TIMEOUT_SECONDS', 25),
        /** Travel Network default domain code in V1:EPR:PCC:{domain} user-id string. */
        'epr_domain_code' => 'AA',
        /**
         * Shopping/pricing currency for Sabre shop PriceRequestInformation.
         * Default USD improves compatibility with Sabre cert/sandbox; override via SABRE_SHOP_CURRENCY when needed.
         */
        'shop_currency_code' => env('SABRE_SHOP_CURRENCY', 'USD'),
        /**
         * When true, emit one {@code sabre.branded_fares_probe} warning per shop (metadata only).
         * Default false — set SABRE_BRANDED_FARES_PROBE_ENABLED=true temporarily for PI/branded-fare diagnostics.
         */
        'branded_fares_probe_enabled' => (bool) env('SABRE_BRANDED_FARES_PROBE_ENABLED', false),
        /**
         * BF2: When true, add BFM v4 {@code BrandedFareIndicators} to production shop requests (metadata probe).
         * Default false — set SABRE_BRANDED_FARES_SEARCH_ENABLED=true only for CERT/local branded-fare search diagnostics.
         */
        'branded_fares_search_enabled' => (bool) env('SABRE_BRANDED_FARES_SEARCH_ENABLED', false),
        /**
         * Temporary operational policy: hide mixed-marketing-carrier Sabre (and detectable mixed) offers
         * from public/customer/agent/admin flight search results until mixed-carrier booking is certified.
         * Reversible via SABRE_HIDE_MIXED_CARRIER_SEARCH_RESULTS=false.
         */
        'hide_mixed_carrier_search_results' => (bool) env('SABRE_HIDE_MIXED_CARRIER_SEARCH_RESULTS', true),
        /**
         * BF5: When true, show display-only branded fare / fare-family chips on public search result cards
         * (no checkout selection; {@code selectable} stays false). Default false — independent of
         * {@see branded_fares_search_enabled} so production stays safe if search probe is left on.
         */
        'branded_fares_display_enabled' => (bool) env('SABRE_BRANDED_FARES_DISPLAY_ENABLED', false),
        /**
         * BF6: When true (and {@see branded_fares_display_enabled}), branded fare cards on search results are
         * selectable and {@code fare_option_key} intent is stored in checkout draft (no AirPrice/PNR mutation until BF7).
         * Default false — preview-only display remains when false.
         */
        'branded_fares_selection_enabled' => (bool) env('SABRE_BRANDED_FARES_SELECTION_ENABLED', false),
        /**
         * BF7-A: When true (local/test only), {@see SabreBookingPayloadBuilder::summarizeAirPriceBrandQualifierForInspect()}
         * includes {@code candidate_shapes} for compare-only Brand wire variants. Default false — no live checkout change.
         */
        'branded_fares_airprice_brand_shape_compare_enabled' => (bool) env('SABRE_BRANDED_FARES_AIRPRICE_BRAND_SHAPE_COMPARE_ENABLED', false),
        /**
         * BF7-B/BF7-D/F: AirPrice Brand wire shape when {@see branded_fares_airprice_brand_shape_compare_enabled} is true.
         * Values: current_object_code | string_array | empty_object_array | object_value | object_content
         * | object_text | single_object_code | single_object_value | single_object_content | omit_brand.
         * Gate off — production wire uses object_content ({@code Brand:[{content:FL}]}); compare gate selects explicit variant when ON.
         */
        'branded_fares_airprice_brand_shape_compare_variant' => env(
            'SABRE_BRANDED_FARES_AIRPRICE_BRAND_SHAPE_COMPARE_VARIANT',
            'current_object_code'
        ),
        /**
         * BF3-CORRECTION / BF3-D: BrandedFareIndicators placement variant for BFM v4 shop probe (default current_tis_tpa).
         * Only applies when {@see branded_fares_search_enabled} is true.
         * Values: current_tis_tpa | root_price_tpa | root_optional_qualifiers | iati_full_tis_tpa | iati_exact_gds_v4.
         */
        'branded_fares_request_variant' => env('SABRE_BRANDED_FARES_REQUEST_VARIANT', 'current_tis_tpa'),
        /**
         * BF3-D / BF3-F: IntelliSell RequestType for {@code iati_full_tis_tpa} (default 100ITINS) or {@code iati_exact_gds_v4} (default 200ITINS when env unset).
         */
        'branded_fares_intellisell_request_type' => env('SABRE_BRANDED_FARES_INTELLISELL_REQUEST_TYPE', '100ITINS'),
        /**
         * When false (default), public Sabre checkout shows review but disables final submit with
         * "Sabre booking is not enabled yet." When true, review submit may proceed using local dry-run only
         * until {@see SabreBookingService::mayPerformLiveSabreBookingCall()} is true and booking APIs exist.
         */
        'booking_enabled' => (bool) env('SABRE_BOOKING_ENABLED', false),
        /**
         * Root gate for Sabre GDS PNR create (defaults to booking_enabled + booking_live_call_enabled when unset).
         */
        'pnr_create_enabled' => env('SABRE_PNR_CREATE_ENABLED'),
        'admin_manual_pnr_enabled' => (bool) env('SABRE_ADMIN_MANUAL_PNR_ENABLED', env('SABRE_BOOKING_ENABLED', false)),
        'pnr_retrieve_enabled' => (bool) env('SABRE_PNR_RETRIEVE_ENABLED', env('SABRE_BOOKING_ENABLED', false)),
        'unticketed_cancel_enabled' => (bool) env('SABRE_UNTICKETED_CANCEL_ENABLED', env('SABRE_ADMIN_CANCEL_LIVE_CALL_ENABLED', false)),
        /**
         * When false (default), Sabre booking/revalidate/PNR HTTP must not be invoked from {@see SabreBookingService}.
         * Public checkout can still complete a booking request as dry-run when {@see SabreBookingService::isBookingEnabled()} is true.
         * Keep false until Sabre booking endpoints and payloads are certified for your tenant.
         */
        'booking_live_call_enabled' => (bool) env('SABRE_BOOKING_LIVE_CALL_ENABLED', false),
        /**
         * R6: When true (default), public checkout uses {@see SabreCertifiedRouteSelector}
         * for a single certified Sabre path (no endpoint/style fallback chains). Disable only in controlled tests.
         */
        'certified_route_selector_public_checkout_enabled' => (bool) env('SABRE_CERTIFIED_ROUTE_SELECTOR_ENABLED', true),
        /**
         * Automated Sabre GDS ticketing HTTP ({@see SabreGdsTicketingService}). Keep false for operational PNR-only lane.
         */
        'ticketing_enabled' => (bool) env('SABRE_TICKETING_ENABLED', false),
        /**
         * When false (default), {@see SabreGdsTicketingService} must not POST to Sabre AirTicket even if ticketing_enabled is true.
         */
        'ticketing_live_call_enabled' => (bool) env('SABRE_TICKETING_LIVE_CALL_ENABLED', false),
        /**
         * Public/customer-facing ticketing triggers. Default false — admin/staff controlled issue only.
         */
        'public_ticketing_enabled' => (bool) env('SABRE_PUBLIC_TICKETING_ENABLED', false),
        'checkout_auto_ticketing_enabled' => (bool) env('SABRE_CHECKOUT_AUTO_TICKETING_ENABLED', false),
        /**
         * Enhanced Air Ticket REST path (official v1.3.0; Binham reference used v1.2.1).
         */
        'ticketing_path' => env('SABRE_TICKETING_PATH', '/v1.3.0/air/ticket'),
        'ticketing_printer_country_code' => strtoupper(trim((string) env('SABRE_TICKETING_PRINTER_COUNTRY_CODE', 'PK'))),
        'ticketing_printer_lniata' => trim((string) env('SABRE_TICKETING_PRINTER_LNIATA', '')),
        'ticketing_received_from' => trim((string) env('SABRE_TICKETING_RECEIVED_FROM', 'OTA')),
        /**
         * GDS ticket document retrieve via getBooking. Live call blocked unless explicitly enabled.
         */
        'ticket_documents_live_retrieve_enabled' => (bool) env('SABRE_TICKET_DOCUMENTS_LIVE_RETRIEVE_ENABLED', false),
        'get_booking_path' => env('SABRE_GET_BOOKING_PATH', '/v1/trip/orders/getBooking'),
        /**
         * GDS void/refund via Booking Management API. All false by default.
         */
        'void_enabled' => (bool) env('SABRE_VOID_ENABLED', false),
        'void_live_call_enabled' => (bool) env('SABRE_VOID_LIVE_CALL_ENABLED', false),
        'refund_enabled' => (bool) env('SABRE_REFUND_ENABLED', false),
        'refund_live_call_enabled' => (bool) env('SABRE_REFUND_LIVE_CALL_ENABLED', false),
        'void_flight_tickets_path' => env('SABRE_VOID_FLIGHT_TICKETS_PATH', '/v1/trip/orders/voidFlightTickets'),
        'refund_flight_tickets_path' => env('SABRE_REFUND_FLIGHT_TICKETS_PATH', '/v1/trip/orders/refundFlightTickets'),
        'check_flight_tickets_path' => env('SABRE_CHECK_FLIGHT_TICKETS_PATH', '/v1/trip/orders/checkFlightTickets'),
        /**
         * When true, suppresses Sabre GDS/BFM lane everywhere (explicit deployment kill switch).
         */
        'gds_global_kill_switch' => (bool) env('SABRE_GDS_GLOBAL_KILL_SWITCH', false),
        /**
         * Sabre NDC channel (separate from GDS PNR). Lane selection uses Admin connection
         * sabre_ndc_enabled + platform module; global_kill_switch is the env kill switch only.
         * Legacy enabled/search_enabled gate live HTTP mutations, not lane allowance.
         */
        'ndc' => [
            'global_kill_switch' => (bool) env('SABRE_NDC_GLOBAL_KILL_SWITCH', false),
            'enabled' => (bool) env('SABRE_NDC_ENABLED', false),
            'search_enabled' => (bool) env('SABRE_NDC_SEARCH_ENABLED', false),
            'search_request_variant' => env('SABRE_NDC_SEARCH_REQUEST_VARIANT', 'ndc_v5_pos_pcc_source'),
            'search_market_matrix_sleep_ms' => (int) env('SABRE_NDC_SEARCH_MARKET_MATRIX_SLEEP_MS', 1500),
            'order_create_enabled' => (bool) env('SABRE_NDC_ORDER_CREATE_ENABLED', false),
            'public_order_create_enabled' => (bool) env('SABRE_NDC_PUBLIC_ORDER_CREATE_ENABLED', false),
            'cancel_enabled' => (bool) env('SABRE_NDC_CANCEL_ENABLED', false),
            'offer_shop_path' => env('SABRE_NDC_OFFER_SHOP_PATH', '/v5/offers/shop'),
            'offer_price_path' => env('SABRE_NDC_OFFER_PRICE_PATH', '/v1/offers/price'),
            'order_create_path' => env('SABRE_NDC_ORDER_CREATE_PATH', '/v1/orders/create'),
            'order_retrieve_path' => env('SABRE_NDC_ORDER_RETRIEVE_PATH', '/v1/ndc/orders/retrieve'),
            'order_view_path' => env('SABRE_NDC_ORDER_VIEW_PATH', '/v1/orders/view'),
            'order_change_path' => env('SABRE_NDC_ORDER_CHANGE_PATH', '/v1/orders/change'),
            'reprice_order_path' => env('SABRE_NDC_REPRICE_ORDER_PATH', '/v1/offers/repriceOrder'),
            'offer_price_enabled' => (bool) env('SABRE_NDC_OFFER_PRICE_ENABLED', false),
            'reprice_order_enabled' => (bool) env('SABRE_NDC_REPRICE_ORDER_ENABLED', false),
            'order_change_enabled' => (bool) env('SABRE_NDC_ORDER_CHANGE_ENABLED', false),
            'order_retrieve_enabled' => (bool) env('SABRE_NDC_ORDER_RETRIEVE_ENABLED', false),
        ],
        /**
         * REST path for booking create. Default Trip Orders createBooking (B10). Legacy passenger-record paths stay available via
         * SABRE_BOOKING_PATH or SABRE_LEGACY_BOOKING_PATH when unset chain is not used — set SABRE_BOOKING_PATH explicitly to e.g.
         * /v2/passengers/create or /v2.5.0/passenger/records if your tenant still requires them.
         */
        'booking_path' => env('SABRE_BOOKING_PATH', env('SABRE_LEGACY_BOOKING_PATH', '/v1/trip/orders/createBooking')),
        /** OTA-internal mode label (e.g. pnr_only); mapped in {@see SabreBookingPayloadBuilder}. */
        'booking_mode' => env('SABRE_BOOKING_MODE', 'pnr_only'),
        /**
         * Payload family: create_passenger_name_record | trip_orders_create_booking. When null/empty, derived from booking_path
         * (Trip Orders path → trip_orders_create_booking). Override with SABRE_BOOKING_SCHEMA.
         * B62: {@code passenger_records_create_pnr} is an alias of {@code create_passenger_name_record} (normalized internally).
         */
        'booking_schema' => env('SABRE_BOOKING_SCHEMA'),
        /**
         * Passenger Records CPNR payload style when {@code booking_schema} is {@code create_passenger_name_record}.
         * Default unset → {@code traditional_pnr_create_passenger_name_record_v1} (v2.5.0-aligned wire). Set
         * {@code iati_like_cpnr_v2_4_gds} only after cert/sandbox validation — does not change Trip Orders or certified-route checkout.
         */
        'booking_payload_style' => env('SABRE_BOOKING_PAYLOAD_STYLE'),
        /**
         * REST path for IATI-like CPNR v2.4.0 when {@code booking_payload_style=iati_like_cpnr_v2_4_gds}. Production default
         * booking_path / certified route remain on v2.5.0 unless this style is explicitly enabled.
         */
        'passenger_records_endpoint_v24' => env(
            'SABRE_PASSENGER_RECORDS_ENDPOINT_V24',
            '/v2.4.0/passenger/records?mode=create'
        ),
        /**
         * Optional Ticketing.ShortText on IATI-like CPNR (e.g. {@code JTP}); omit when empty (schema-safe).
         */
        'cpnr_iati_ticketing_short_text' => trim((string) env('SABRE_CPNR_IATI_TICKETING_SHORT_TEXT', '')),
        /**
         * Sprint 2B: When true, certified public-checkout OW-direct Sabre routes may select
         * {@code iati_like_cpnr_v2_4_gds} only when {@see SabreBookingService::decidePassengerRecordsPayloadStyle()}
         * eligibility passes. Does not change default when {@code booking_payload_style} is unset.
         */
        'cpnr_iati_style_certified_gds_enabled' => (bool) env('SABRE_CPNR_IATI_STYLE_CERTIFIED_GDS_ENABLED', false),
        /**
         * Sprint 11B: When true, one-way same-carrier 2-segment GDS itineraries may use controlled iati-like CPNR v2.4
         * certification (admin/staff; public checkout only when {@see cpnr_connecting_same_carrier_public_checkout_enabled}).
         */
        'cpnr_connecting_same_carrier_gds_enabled' => (bool) env('SABRE_CPNR_CONNECTING_SAME_CARRIER_GDS_ENABLED', false),
        /**
         * Sprint 11B: When true with {@see cpnr_connecting_same_carrier_gds_enabled}, public checkout may create live PNR
         * for {@code one_way_connecting_same_carrier_gds}. Default false — admin/staff certification/retry only.
         */
        'cpnr_connecting_same_carrier_public_checkout_enabled' => (bool) env('SABRE_CPNR_CONNECTING_SAME_CARRIER_PUBLIC_CHECKOUT_ENABLED', false),
        /**
         * E5E / BF7-J-OPS: Master switch for operational same-carrier 2-segment public auto-PNR
         * ({@see SabreOperationalPnrReadiness}). Requires {@see cpnr_connecting_same_carrier_gds_enabled}
         * and {@see cpnr_connecting_same_carrier_public_checkout_enabled}. Structural gate only — does not
         * require verified-route historical evidence ({@see SabreVerifiedAutoPnrReadiness} remains diagnostics-only).
         * Default false — enable only after controlled production verification.
         */
        'verified_multiseg_auto_pnr_enabled' => (bool) env('SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED', false),
        /**
         * BF7-J-OPS-FIX2: CERT-only operational Passenger Records create may omit NN/WN from HaltOnStatus
         * ({@see SabreCpnrOperationalAllowNnPolicy}) for same-carrier 2-segment IATI v2.4 wire. Default false.
         * PNR created with unconfirmed NN segments stays manual_review — not auto-confirmed.
         */
        'cpnr_allow_nn_halt_on_status_cert_operational' => (bool) env('SABRE_CPNR_ALLOW_NN_HALT_ON_STATUS_CERT_OPERATIONAL', false),
        'cpnr_omit_nn_from_halt_on_status' => (bool) env('SABRE_CPNR_OMIT_NN_FROM_HALT_ON_STATUS', true),
        'cpnr_include_nn_in_halt_on_status' => (bool) env('SABRE_CPNR_INCLUDE_NN_IN_HALT_ON_STATUS', false),
        /**
         * E5J: When true, public checkout soft-blocks known failed / host-NOOP Sabre pre-checkout evidence
         * ({@see SabrePreCheckoutKnownFailureSoftBlock}). Default false — deploy with flag off.
         */
        'precheckout_known_failure_soft_block_enabled' => (bool) env('SABRE_PRECHECKOUT_KNOWN_FAILURE_SOFT_BLOCK_ENABLED', false),
        /**
         * B63: When true, live Passenger Records CPNR create is blocked for multi-segment offers or offers with
         * {@code raw_payload.sabre_segment_order.segment_order_corrected} (manual supplier booking required).
         */
        'passenger_records_block_risky_itinerary_live' => (bool) env('SABRE_PASSENGER_RECORDS_BLOCK_RISKY_ITINERARY_LIVE', true),
        /**
         * B65: When true (and {@see passenger_records_block_risky_itinerary_live} is true), multi-segment Passenger Records
         * live create is allowed only after {@see SabrePassengerRecordsMultiSegmentSellVerifier} passes. Default false.
         */
        'passenger_records_allow_verified_multi_segment' => (bool) env('SABRE_PASSENGER_RECORDS_ALLOW_VERIFIED_MULTI_SEGMENT', false),
        /**
         * B77: Before live Passenger Records CPNR (PNR-only + traditional wire), run one OW Offers shop per stored segment;
         * block POST when fresh shop does not confirm flight/time/(optional RBD). Not fare revalidation.
         */
        'passenger_records_fresh_shop_guard_before_live' => (bool) env('SABRE_PASSENGER_RECORDS_FRESH_SHOP_GUARD_BEFORE_LIVE', true),
        /**
         * R5: When false (default), return/multi-city and other complex itineraries do not get live Sabre PNR
         * from public checkout or admin Create/Retry. Public checkout always defers complex PNR even when true.
         * When true, admin/staff Create/Retry may proceed (payload still uncertified — use with care).
         */
        'complex_itinerary_pnr_enabled' => (bool) env('SABRE_COMPLEX_ITINERARY_PNR_ENABLED', false),
        /**
         * Optional GDS revalidation path used before Trip Orders {@code createBooking} to obtain fare/offer linkage
         * (fare basis, fare reference, price-quote reference, offer reference). Default remains BFM {@code /v4/shop/flights/revalidate}
         * for backward compatibility; `.env.example` documents {@code /v4/offers/shop/revalidate} when Sabre returns 27131 on the BFM path.
         */
        'revalidate_path' => env('SABRE_REVALIDATE_PATH', '/v4/shop/flights/revalidate'),
        /**
         * When true (and {@see self::booking_live_call_enabled} is also true), {@see SabreBookingService::createBooking()}
         * calls Sabre revalidation before {@code createBooking}; on success the safely-extracted fare linkage is merged
         * into the booking envelope. On revalidation failure the booking HTTP is skipped with {@code sabre_revalidation_failed}.
         */
        'revalidate_before_booking' => (bool) env('SABRE_REVALIDATE_BEFORE_BOOKING', true),
        /**
         * P3B: When true (default), public Sabre review submit re-shops the stored itinerary via
         * {@see SabreBookingOfferRefreshService::refresh()} before live PNR; price changes require customer acceptance.
         */
        'refresh_offer_before_public_pnr' => (bool) env('SABRE_REFRESH_OFFER_BEFORE_PUBLIC_PNR', true),
        /**
         * Phase B21 (local/testing only recommended): when {@code revalidate_before_booking} is true but Sabre returns no
         * usable revalidation linkage, allow Trip Orders {@code createBooking} anyway. Default false — never enable in
         * production without explicit risk acceptance; ticketing stays disabled separately via {@code ticketing_enabled}.
         */
        'allow_createbooking_without_revalidation' => (bool) env('SABRE_ALLOW_CREATEBOOKING_WITHOUT_REVALIDATION', false),
        /**
         * When true with PNR-only Passenger Records live create, mandatory pre-booking revalidation may be waived.
         * Default false — production should keep revalidation enabled via {@see revalidate_before_booking}.
         */
        'pnr_only_waive_mandatory_revalidation' => (bool) env('SABRE_PNR_ONLY_WAIVE_MANDATORY_REVALIDATION', false),
        /**
         * Experimental revalidate JSON shape for {@code /v4/shop/flights/revalidate}. Default {@code bfm_revalidate_v1}
         * matches the historical OTA envelope. Override with {@code bfm_revalidate_minimal_segments},
         * {@code bfm_revalidate_with_pricing_context}, {@code bfm_revalidate_original_like}, B17 {@code client_gds_revalidate_v1}
         * (separate {@code RevalidateItineraryRQ} root), or Sprint 11K-J {@code iati_like_bfm_revalidate_v1} (Binham/IATI-like
         * BFM wire: DataSources, 50ITINS, SeatsRequested, optional PCC — opt-in only after {@code sabre:compare-revalidate-payload-coverage} review).
         * Production should stay on {@code bfm_revalidate_v1} until certified.
         */
        'revalidate_payload_style' => env('SABRE_REVALIDATE_PAYLOAD_STYLE', 'bfm_revalidate_v1'),
        /**
         * Trip Orders {@code /v1/trip/orders/createBooking} flight product shape (B22/B23). {@code trip_orders_create_booking_v1_current}
         * keeps the legacy envelope without {@code flightOffer}/{@code flightDetails} (inspect may warn). Nested: {@code trip_orders_flight_offer_v1},
         * {@code trip_orders_flight_details_v1}. **B23 wire root:** {@code trip_orders_flight_offer_root_v1}, {@code trip_orders_flight_details_root_v1},
         * {@code trip_orders_product_array_v1} (Sabre validators read the HTTP JSON root). **B29 camel travelers:** {@code trip_orders_flight_offer_camel_v1},
         * {@code trip_orders_flight_details_camel_v1} (root wire + {@code givenName}/{@code birthDate}/{@code passengerTypeCode} / camelCase {@code passport} fields).
         * **B30:** {@code trip_orders_flight_details_full_camel_v1} adds Sabre-like {@code flightDetails} segment scalars ({@code departureDateTime}, {@code marketingAirline}, …).
         * **B31:** {@code trip_orders_flight_details_sabre_v1} — same root as B23 flight-details root styles but Sabre Trip Orders {@code travelers[].passengerCode} (not {@code passengerTypeCode}). **B32:** same style uses root {@code contactInfo} (not {@code contact}) for booker email/phone.
         * **B33:** {@code trip_orders_flight_details_sabre_agency_v1} matches {@code trip_orders_flight_details_sabre_v1} and both send root {@code agencyContactInfo} when {@code SABRE_AGENCY_PHONE} is set (traditional booking office phone; separate from customer {@code contactInfo}).
         * **B34 (compare / experiments):** {@code trip_orders_flight_details_sabre_agencyInfo_v1}, {@code trip_orders_flight_details_sabre_agencyPhoneNumber_v1}, {@code trip_orders_flight_details_sabre_agencyPhonesArray_v1}, {@code trip_orders_flight_details_sabre_rootAgencyPhone_v1}, {@code trip_orders_flight_details_sabre_phoneNumbers_v1} — alternate roots/keys for agency phone (see {@code SabreBookingPayloadBuilder::expectedSabreAgencyPhoneDotPathsForStyle()}); use {@code sabre:compare-createbooking-styles --style=} one at a time.
         * **B35 (compare / PNR-style phones):** {@code trip_orders_flight_details_sabre_rootPhones_v1}, {@code …_rootPhoneNumbers_v1}, {@code …_contactInfoPhones_v1}, {@code …_agencyPhoneUseType_v1}, {@code …_phone_use_business_v1}, {@code …_phone_use_agency_v1} — {@code phoneNumber}+{@code phoneUseType} or {@code number}+{@code type} rows; wire preview adds {@code wire_phone_use_type_values_sanitized}.
         * **B36 (compare / POS–agency metadata):** {@code trip_orders_flight_details_sabre_pos_source_phone_v1} (root {@code POS.Source[]} + {@code AgencyPhone}), {@code …_pos_phone_v1} (root {@code pos.source}), {@code …_agency_root_camel_v1}, {@code …_travelAgency_v1}, {@code …_customerInfo_phone_v1}; diagnostics expose structure flags + {@code wire_pcc_present} / config presence booleans (no values).
         * **B37 (compare / PNR phone-line):** {@code trip_orders_flight_details_sabre_phoneLine_v1}, {@code …_phoneLines_v1}, {@code …_contactNumbers_v1}, {@code …_pnrContact_v1}, {@code …_reservationContact_v1}, {@code …_contactInfo_phoneLine_v1}, {@code …_travelers_phone_v1} — Sabre-style {@code Number}/{@code Type}/{@code LocationCode} (or {@code PhoneUseType}) rows; {@code SABRE_AGENCY_PHONE_LOCATION} (default {@code LHE}); wire adds {@code wire_phone_location_values_sanitized}.
         * **B38 (compare / profile-level AGENCY_PHONE_MISSING + traditional fallback):** {@code sabre:compare-booking-endpoints} matrix + inspect style {@code traditional_pnr_create_passenger_name_record_v1} (see {@code SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1}); compare rows add {@code agency_phone_error}, {@code likely_profile_level_agency_phone_issue}, {@code suggested_next_path}; {@code sabre:inspect-booking-config} adds {@code trip_orders_agency_phone_still_rejected}, {@code suggested_booking_flow}.
         */
        'createbooking_payload_style' => env('SABRE_CREATEBOOKING_PAYLOAD_STYLE', 'trip_orders_flight_offer_v1'),
        /**
         * B33/B34/B35/B36: Sabre Trip Orders traditional booking — office/agency phone (default wire {@code agencyContactInfo}; B34–B36 compare styles use alternate shapes / POS).
         * Set in production/staging; local/tests may omit (live send blocked for Sabre traditional styles when missing).
         */
        'agency_phone' => trim((string) env('SABRE_AGENCY_PHONE', '')),
        'agency_phone_country_code' => strtoupper(trim((string) env('SABRE_AGENCY_PHONE_COUNTRY_CODE', 'PK'))),
        'agency_phone_type' => strtoupper(trim((string) env('SABRE_AGENCY_PHONE_TYPE', 'AGENCY'))),
        /** B36: Optional agency metadata for POS / camel agency blocks (values never echoed in inspect diagnostics). */
        'agency_name' => trim((string) env('SABRE_AGENCY_NAME', '')),
        'agency_city' => trim((string) env('SABRE_AGENCY_CITY', '')),
        'agency_country' => strtoupper(trim((string) env('SABRE_AGENCY_COUNTRY', ''))),
        /** Single-letter Sabre {@code PhoneUseType} on B36 POS-style payloads (default {@code A}); separate from {@code agency_phone_type} used on B33 {@code agencyContactInfo}. */
        'agency_pos_phone_use_type' => strtoupper(trim((string) env('SABRE_AGENCY_POS_PHONE_USE_TYPE', 'A'))),
        /**
         * B37: IATA-style {@code LocationCode} on traditional PNR phone-line compare payloads (default {@code LHE} for local/testing).
         */
        'agency_phone_location' => strtoupper(trim((string) env('SABRE_AGENCY_PHONE_LOCATION', 'LHE'))),
        /**
         * B26: When false (default), Trip Orders {@code createBooking} wire JSON omits the top-level {@code remarks} key
         * (Sabre expects {@code BookRemark} objects, not a string[] — plain strings cause INVALID_VALUE). When true,
         * remarks are sent as {@code [{ "type": "GENERAL", "text": "..." }]} for optional tenant experiments; keep false
         * until your Sabre contract confirms property names/shape.
         */
        'createbooking_send_remarks' => (bool) env('SABRE_CREATEBOOKING_SEND_REMARKS', false),
        /**
         * B27: Trip Orders traveler {@code passport.document_type} value when mapping UI "passport" variants (confirm with Sabre contract).
         */
        'document_type_passport_value' => strtoupper(trim((string) env('SABRE_DOCUMENT_TYPE_PASSPORT_VALUE', 'PASSPORT'))),
        /**
         * B61 (local/testing only): When true, traditional Passenger Records CPNR {@code AirBook} includes
         * {@code RetryRebook} + {@code RedisplayReservation} with **integer** {@code NumAttempts} / {@code WaitInterval} (**B61A**) and boolean {@code RetryRebook.Option=true} (**B61B**, Sabre required) for host
         * air-book experiments. Default false — do not enable in production without explicit acceptance.
         */
        'traditional_cpnr_airbook_retry_redisplay' => (bool) env('SABRE_TRADITIONAL_CPNR_AIRBOOK_RETRY_REDISPLAY', false),
        /**
         * D2C: When true, live traditional Passenger Records CPNR root {@code AirPrice...PricingQualifiers.ValidatingCarrier}
         * is merged when draft {@code validating_carrier} sanitizes to a 2–3 char carrier token (reuses B79 helper).
         * Default false — do not enable in production without explicit acceptance.
         */
        'traditional_cpnr_airprice_validating_carrier' => (bool) env('SABRE_TRADITIONAL_CPNR_AIRPRICE_VALIDATING_CARRIER', false),
        /**
         * Sprint 0: Sabre cancel inspect/cert tooling (Artisan {@code sabre:inspect-cancel-booking} only).
         * All false by default — no Admin UI, no automatic cancellation, no checkout integration.
         */
        'cancel_enabled' => (bool) env('SABRE_CANCEL_ENABLED', false),
        'cancel_live_call_enabled' => (bool) env('SABRE_CANCEL_LIVE_CALL_ENABLED', false),
        'admin_cancel_live_call_enabled' => (bool) env('SABRE_ADMIN_CANCEL_LIVE_CALL_ENABLED', false),
        'cancel_require_confirmation' => (bool) env('SABRE_CANCEL_REQUIRE_CONFIRMATION', true),
        'cancel_allow_production_send' => (bool) env('SABRE_CANCEL_ALLOW_PRODUCTION_SEND', false),
        'cancel_allow_production_host' => (bool) env('SABRE_CANCEL_ALLOW_PRODUCTION_HOST', false),
        'cancel_endpoint_path' => env('SABRE_CANCEL_ENDPOINT_PATH', '/v1/trip/orders/cancelBooking'),
        /**
         * Sprint 11K-Q: Inspect/cert-only cancel payload recommendation style.
         * Default preserves the current recommendation matrix; non-auto styles only select among existing
         * dry-run candidates and do not enable cancellation or live sends.
         */
        'cancel_payload_style' => env('SABRE_CANCEL_PAYLOAD_STYLE', 'auto_matrix_current'),
        /**
         * Production read-only PNR retrieve inspect ({@code sabre:inspect-pnr-retrieve --send} only).
         * No booking.status updates, no cancellation, no ticketing, no meta writes.
         */
        'pnr_retrieve_inspect_enabled' => (bool) env('SABRE_PNR_RETRIEVE_INSPECT_ENABLED', false),
        /**
         * Production SSH-only CERT entitlement matrix ({@code sabre:cert-entitlement-matrix}).
         * Requires CERT base host (e.g. api.cert.platform.sabre.com); blocks api.platform.sabre.com.
         */
        'cert_entitlement_matrix_enabled' => (bool) env('SABRE_CERT_ENTITLEMENT_MATRIX_ENABLED', false),
        /**
         * Sabre CERT/STL manager credential profiles for {@code sabre:cert-token-probe} only.
         * Values are env-only; never stored in supplier_connections or logged.
         */
        'cert_stl' => [
            'auth_url' => env('SABRE_CERT_AUTH_URL', 'https://stl.platform.sabre.com/v2/auth/token'),
            'base_url' => env('SABRE_CERT_BASE_URL', 'https://api.cert.platform.sabre.com'),
            'profiles' => [
                'cert_6md8' => [
                    'user' => env('SABRE_CERT_6MD8_USER'),
                    'secret' => env('SABRE_CERT_6MD8_SECRET'),
                    'pcc' => env('SABRE_CERT_6MD8_PCC'),
                    'domain' => env('SABRE_CERT_6MD8_DOMAIN', 'AA'),
                ],
                'cert_lu6k' => [
                    'user' => env('SABRE_CERT_LU6K_USER'),
                    'secret' => env('SABRE_CERT_LU6K_SECRET'),
                    'pcc' => env('SABRE_CERT_LU6K_PCC'),
                    'domain' => env('SABRE_CERT_LU6K_DOMAIN', 'AA'),
                ],
                'cert_test3' => [
                    'user' => env('SABRE_CERT_TEST3_USER'),
                    'secret' => env('SABRE_CERT_TEST3_SECRET'),
                    'pcc' => env('SABRE_CERT_TEST3_PCC'),
                    'domain' => env('SABRE_CERT_TEST3_DOMAIN', 'AA'),
                ],
            ],
        ],
    ],
    'iati' => [
        'test_host_base' => env('IATI_TEST_HOST_BASE', 'https://testapi.iati.com'),
        'prod_host_base' => env('IATI_PROD_HOST_BASE', 'https://api.iati.com'),
        'auth_token_path' => '/rest/auth/token',
        'flight_base_path' => '/rest/flight/v2',
        'search_path' => '/search',
        'fare_path' => '/fare',
        'book_path' => '/book',
        'option_path' => '/option',
        'order_path' => '/order',
        'ping_path' => '/test/ping',
        'airport_path' => '/airport',
        'balance_path' => '/balance',
        'timeout_seconds' => (int) env('IATI_TIMEOUT_SECONDS', 60),
        'connect_timeout_seconds' => (int) env('IATI_CONNECT_TIMEOUT_SECONDS', 10),
        'token_cache_ttl_seconds' => (int) env('IATI_TOKEN_CACHE_TTL_SECONDS', 86000),
        'branded_fares_display_enabled' => (bool) env('IATI_BRANDED_FARES_DISPLAY_ENABLED', true),
        'branded_fares_selection_enabled' => (bool) env('IATI_BRANDED_FARES_SELECTION_ENABLED', true),
    ],
    'pia_ndc' => [
        'timeout_seconds' => (int) env('PIA_NDC_TIMEOUT_SECONDS', 60),
        'connect_timeout_seconds' => (int) env('PIA_NDC_CONNECT_TIMEOUT_SECONDS', 10),
        'checkout_offer_price_enabled' => (bool) env('PIA_NDC_CHECKOUT_OFFER_PRICE_ENABLED', true),
        'branded_fare_dedup_log' => (bool) env('PIA_NDC_BRANDED_FARE_DEDUP_LOG', false),
        'status_refresh_stale_minutes' => (int) env('PIA_NDC_STATUS_REFRESH_STALE_MINUTES', 60),
        'username_header' => env('PIA_NDC_USERNAME_HEADER', 'username'),
        'password_header' => env('PIA_NDC_PASSWORD_HEADER', 'password'),
        'operations' => [
            'air_shopping' => ['soap_action' => 'doAirShopping'],
            'offer_price' => ['soap_action' => 'doOfferPrice'],
            'order_create' => ['soap_action' => 'doOrderCreate'],
            'order_retrieve' => ['soap_action' => 'doOrderRetrieve'],
            'ticket_preview' => ['soap_action' => 'doTicketPreview'],
            'order_change' => ['soap_action' => 'doOrderChange'],
            'cancel_preview' => ['soap_action' => 'doOrderCancelPreview'],
            'cancel_commit' => ['soap_action' => 'doOrderCancelCommit'],
            'cancel' => ['soap_action' => 'doOrderCancel'],
            'void_ticket' => ['soap_action' => 'doVoidTicket'],
            'reissue_preview' => ['soap_action' => 'doReissuePreview'],
            'reissue_commit' => ['soap_action' => 'doReissueCommit'],
            'general_params' => ['soap_action' => 'doGeneralParams'],
            'airline_profile' => ['soap_action' => 'doAirlineProfile'],
        ],
    ],
    'airblue' => [
        'timeout_seconds' => (int) env('AIRBLUE_TIMEOUT_SECONDS', 60),
        'connect_timeout_seconds' => (int) env('AIRBLUE_CONNECT_TIMEOUT_SECONDS', 10),
        'username_header' => env('AIRBLUE_USERNAME_HEADER', 'username'),
        'password_header' => env('AIRBLUE_PASSWORD_HEADER', 'password'),
        'default_ndc_base_url' => 'https://app.crane.aero/cranendc/v20.1/CraneNDCService',
        'default_ndc_wsdl' => 'https://app.crane.aero/cranendc/v20.1/CraneNDCService?wsdl',
        'default_ota_base_url' => env('AIRBLUE_OTA_BASE_URL', 'https://ota3.zapways.com/v2.0/OTAAPI.asmx'),
        'default_ota_qa_base_url' => 'https://ota.qa.zapways.com/v2.0/OTAAPI.asmx',
        'ndc_operations' => [
            'air_shopping' => ['soap_action' => 'doAirShopping'],
            'offer_price' => ['soap_action' => 'doOfferPrice'],
            'order_create' => ['soap_action' => 'doOrderCreate'],
            'order_retrieve' => ['soap_action' => 'doOrderRetrieve'],
            'ticket_preview' => ['soap_action' => 'doTicketPreview'],
            'order_change' => ['soap_action' => 'doOrderChange'],
            'cancel_preview' => ['soap_action' => 'doOrderCancelPreview'],
            'cancel_commit' => ['soap_action' => 'doOrderCancelCommit'],
            'void_ticket' => ['soap_action' => 'doVoidTicket'],
            'seat_availability' => ['soap_action' => 'doSeatAvailability'],
            'baggage_service_list' => ['soap_action' => 'doBaggageServiceList'],
            'add_ancillary' => ['soap_action' => 'doAddAncillary'],
            'sell_ancillary' => ['soap_action' => 'doSellAncillary'],
        ],
        'ota_operations' => [
            'air_low_fare_search' => ['soap_action' => 'http://zapways.com/air/ota/2.0/AirLowFareSearch'],
            'air_book' => ['soap_action' => 'http://zapways.com/air/ota/2.0/AirBook'],
            'air_demand_ticket' => ['soap_action' => 'http://zapways.com/air/ota/2.0/AirDemandTicket'],
            'read' => [
                'soap_action_live' => 'https://ota.zapways.com/Read',
                'soap_action_test' => 'https://ota.qa.zapways.com/Read',
            ],
            'cancel' => ['soap_action' => 'http://zapways.com/air/ota/2.0/Cancel'],
            'air_book_modify' => ['soap_action' => 'http://zapways.com/air/ota/2.0/AirBookModify'],
        ],
    ],
    'one_api' => [
        'connect_timeout_seconds' => (int) env('ONE_API_CONNECT_TIMEOUT_SECONDS', 10),
        'request_timeout_seconds' => (int) env('ONE_API_REQUEST_TIMEOUT_SECONDS', 60),
        'search_timeout_seconds' => (int) env('ONE_API_SEARCH_TIMEOUT_SECONDS', 90),
        'token_cache_fallback_ttl_seconds' => (int) env('ONE_API_TOKEN_CACHE_FALLBACK_TTL_SECONDS', 3000),
        'token_expiry_margin_seconds' => (int) env('ONE_API_TOKEN_EXPIRY_MARGIN_SECONDS', 120),
        'workflow_context_ttl_seconds' => (int) env('ONE_API_WORKFLOW_CONTEXT_TTL_SECONDS', 3600),
        'live_search_enabled' => (bool) env('ONE_API_LIVE_SEARCH_ENABLED', false),
        'live_booking_enabled' => (bool) env('ONE_API_LIVE_BOOKING_ENABLED', false),
        'live_payment_modification_enabled' => (bool) env('ONE_API_LIVE_PAYMENT_MODIFICATION_ENABLED', false),
        'soap_operations' => [
            'price' => ['soap_action' => 'OTA_AirPriceRQ'],
            'baggage' => ['soap_action' => 'AA_OTA_AirBaggageDetailsRQ'],
            'meal' => ['soap_action' => 'AA_OTA_AirMealDetailsRQ'],
            'seat_map' => ['soap_action' => 'OTA_AirSeatMapRQ'],
            'book' => ['soap_action' => 'OTA_AirBookRQ'],
            'read' => ['soap_action' => 'OTA_ReadRQ'],
            'modify' => ['soap_action' => 'OTA_AirBookModifyRQ'],
        ],
    ],
    'duffel' => [
        'default_base_url' => env('DUFFEL_DEFAULT_BASE_URL', 'https://api.duffel.com'),
        'offer_requests_path' => '/air/offer_requests',
        'offer_request_show_path' => '/air/offer_requests/{id}',
        'offers_path' => '/air/offers',
        'offer_show_path' => '/air/offers/{id}',
        'orders_path' => '/air/orders',
        'order_show_path' => '/air/orders/{id}',
        'api_version_header' => 'Duffel-Version',
        'api_version' => env('DUFFEL_API_VERSION', 'v2'),
        'timeout_seconds' => 30,
        'connect_timeout_seconds' => 10,
    ],
    'al_haider' => [
        'enabled' => (bool) env('ALHAIDER_API_ENABLED', false),
        'default_base_url' => env('ALHAIDER_API_BASE_URL', 'https://alhaidertravel.pk'),
        'username' => env('ALHAIDER_API_USERNAME'),
        'password' => env('ALHAIDER_API_PASSWORD'),
        'token' => env('ALHAIDER_API_TOKEN'),
        'login_path' => '/api/login',
        'groups_path' => '/api/available/groups',
        'airlines_path' => '/api/available/airlines',
        'group_detail_path' => '/api/group/detail/{id}',
        'seats_path' => '/api/available/seats/{id}',
        'timeout_seconds' => (int) env('ALHAIDER_API_TIMEOUT', 20),
        'connect_timeout_seconds' => 10,
        'cache_ttl_seconds' => (int) env('ALHAIDER_CACHE_TTL_SECONDS', 600),
        'token_cache_ttl_seconds' => (int) env('ALHAIDER_TOKEN_CACHE_TTL_SECONDS', 82800),
        'login_lock_seconds' => (int) env('ALHAIDER_LOGIN_LOCK_SECONDS', 15),
        'login_lock_wait_seconds' => (int) env('ALHAIDER_LOGIN_LOCK_WAIT_SECONDS', 10),
        'token_limit_block_seconds' => (int) env('ALHAIDER_TOKEN_LIMIT_BLOCK_SECONDS', 300),
        'booking_enabled' => (bool) env('ALHAIDER_BOOKING_ENABLED', false),
        'reserve_path' => env('ALHAIDER_RESERVE_PATH', '/api/group/reserve'),
        'cancel_path' => env('ALHAIDER_CANCEL_PATH', '/api/group/cancel'),
    ],
];
