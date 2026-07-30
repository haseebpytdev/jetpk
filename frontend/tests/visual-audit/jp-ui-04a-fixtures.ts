import type { Page } from "@playwright/test";
import type { JpUi04aScenario } from "./jp-ui-04a-scenarios";
import {
  mockCsrf,
  MOCK_SEARCH_ID,
  resultsQuery,
  setupResultsMocks,
} from "./jp-ui-01-fixtures";

export { MOCK_SEARCH_ID, resultsQuery };

const PROGRESS_SKIPPED_SEATS = [
  { key: "flight_selected", label: "Flight Selected", state: "completed", href: null },
  { key: "passenger_details", label: "Passenger Details", state: "completed", href: null },
  { key: "seat_extras", label: "Seat & Extras", state: "skipped", href: null },
  { key: "review", label: "Review", state: "current", href: null },
  { key: "payment", label: "Payment", state: "upcoming", href: null },
  { key: "confirmation", label: "Confirmation", state: "upcoming", href: null },
];

const basePricing = {
  currency: "PKR",
  base_fare: 100000,
  taxes: 20000,
  service_charges: 4999,
  total: 124999,
  formatted_total: "Rs. 124,999",
};

const baseItinerary = {
  trip_type: "one_way",
  origin: "LHE",
  destination: "DXB",
  depart_date: "2026-08-15",
  airline_name: "Audit Airline",
  cabin: "economy",
  total_formatted: "114,999",
  currency: "PKR",
  segments: [],
  return_segments: [],
};

function fareFamilies(count: number) {
  const labels = ["Economy Saver", "Economy Flex", "Economy Premium", "Business"];
  return Array.from({ length: count }, (_, index) => ({
    option_key: `fare-${index + 1}`,
    name: labels[index] ?? `Fare ${index + 1}`,
    price_display: `${(110000 + index * 15000).toLocaleString()} PKR`,
    displayed_price: 110000 + index * 15000,
    baggage: "30kg checked",
    refund_rule: index === 0 ? "Non-refundable" : "Refundable with fee",
    change_rule: "Changes with fee",
  }));
}

function mockOffer(overrides: Record<string, unknown> = {}) {
  return {
    offer_id: "audit-offer-1",
    airline_code: "EK",
    airline_name: "Audit Airline",
    departure_time: "08:30",
    arrival_time: "11:45",
    duration: "3h 15m",
    stops: 0,
    stops_label_display: "Nonstop",
    displayed_price: 134047,
    price_display: "134,047 PKR",
    can_book: true,
    segments: [{ origin_airport_code: "LHE", destination_airport_code: "DXB", flight_number: "EK612" }],
    has_branded_fares: false,
    branded_fares_display_options: [],
    fare_family_options_display: [],
    ...overrides,
  };
}

function mockResultsBody(overrides: Record<string, unknown> = {}) {
  return {
    search_id: MOCK_SEARCH_ID,
    page: 1,
    per_page: 12,
    total: 2,
    has_more: false,
    filters: {
      stops: [{ value: "direct", label: "Nonstop", count: 2 }],
      airlines: [{ code: "EK", name: "Audit Airline", count: 2 }],
    },
    offers: [mockOffer(), mockOffer({ offer_id: "audit-offer-2", displayed_price: 149999, price_display: "149,999 PKR" })],
    warnings: [],
    empty_message: "",
    search_freshness: { expires_display: "Results expire in 25 minutes" },
    ...overrides,
  };
}

function mockDetailsBody(overrides: Record<string, unknown> = {}) {
  return {
    success: true,
    search_id: MOCK_SEARCH_ID,
    offer_id: "audit-offer-1",
    flow: "one_way",
    revalidation_required: false,
    offer: mockOffer({
      has_branded_fares: true,
      branded_fares_display_options: fareFamilies(3),
      fare_family_options_display: fareFamilies(3),
    }),
    search_freshness: { expires_display: "Results expire in 25 minutes" },
    ...overrides,
  };
}

async function routeResults(page: Page, body: Record<string, unknown> | "hang" | "410") {
  await page.route("**/laravel/flights/results/data**", async (route) => {
    if (body === "hang") return;
    if (body === "410") {
      await route.fulfill({ status: 410, contentType: "application/json", body: JSON.stringify({ message: "Search expired" }) });
      return;
    }
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(body) });
  });
}

async function routeOfferDetails(page: Page, body: Record<string, unknown> | "410" | "unavailable") {
  await page.route("**/laravel/flights/results/offer?**", async (route) => {
    if (body === "410") {
      await route.fulfill({ status: 410, contentType: "application/json", body: JSON.stringify({ success: false, message: "Search expired" }) });
      return;
    }
    if (body === "unavailable") {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: false, message: "Fare unavailable" }) });
      return;
    }
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(body) });
  });
}

async function routeRevalidate(page: Page, body: Record<string, unknown>) {
  await page.route("**/laravel/flights/results/revalidate-offer**", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(body) });
  });
}

function mockRevalidationOffer(overrides: Record<string, unknown> = {}) {
  return mockOffer({
    supplier_provider: "iati",
    provider: "iati",
    select_url: "/booking/passengers",
    ...overrides,
  });
}

async function setupResultsFixture(page: Page, fixtureId: string): Promise<void> {
  await mockCsrf(page);
  switch (fixtureId) {
    case "results-loading":
      await routeResults(page, "hang");
      return;
    case "results-empty":
      await routeResults(page, mockResultsBody({ total: 0, offers: [], empty_message: "No flights match your search." }));
      return;
    case "results-partial":
      await routeResults(page, mockResultsBody({
        total: 1,
        offers: [mockOffer()],
        warnings: ["One supplier did not return results."],
      }));
      return;
    case "results-expired":
      await routeResults(page, "410");
      return;
    case "results-invalid":
      await routeResults(page, mockResultsBody({ search_id: "" }));
      return;
    case "results-branded":
      await setupResultsMocks(page, true);
      return;
    case "results-one-stop":
      await routeResults(page, mockResultsBody({
        offers: [mockOffer({ stops: 1, stops_label_display: "1 stop", layover_summary_display: ["2h 10m layover · DXB"] })],
        total: 1,
      }));
      return;
    case "results-return-split":
      await routeResults(page, {
        ...mockResultsBody({ offers: undefined, total: 1 }),
        flow: "return_split_outbound",
        outbound_options: [{
          outbound_key: "out-1",
          from_total_display: "PKR 134,047",
          combo_count: 3,
          journey_display: {
            departure_time_display: "08:30",
            arrival_time_display: "11:45",
            origin_airport_code: "LHE",
            destination_airport_code: "DXB",
            airline_name: "Audit Airline",
            stops_label_display: "Nonstop",
          },
        }],
      });
      return;
    case "groups-search":
      await page.addInitScript(() => {
        window.__jpResetGroupSearchFacetsCache?.();
      });
      await page.route("**/laravel/groups/search/facets**", async (route) => {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            sectors: [{ value: "SKT-SHJ", label: "SKT-SHJ" }],
            categories: [],
            date_bounds: { minimum: "2026-08-01", maximum: "2026-12-31" },
          }),
        });
      });
      await page.route("**/laravel/groups/search/data**", async (route) => {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            filters: { sector: "SKT-SHJ", date_from: "2026-08-15" },
            facets: { sectors: ["SKT-SHJ"], airlines: [], departure_dates: [], categories: [] },
            cards: [{
              id: 1,
              public_id: "ALH-TEST-1",
              title: "Audit Group Package",
              sector_code: "SKT-SHJ",
              route_line: "Sialkot → Sharjah",
              departure_date_short: "15 Aug 2026",
              airline_name: "Air Arabia",
              airline_code: "G9",
              airline_logo_url: null,
              baggage_line: "Baggage: Checked 30kg",
              price_formatted: "99,000",
              currency: "PKR",
              available_seats: 4,
              seat_label: "4 seats left",
              seats_badge_variant: "ok",
              cta_disabled: false,
              bookable: true,
            }],
            total: 1,
            page: 1,
            per_page: 15,
            has_more: false,
            bookable: true,
            count_label: "Showing 1 of 1 group departures",
            lock_state: { locked: false, unpaid_release_count: 0, block_threshold: 3 },
          }),
        });
      });
      return;
    default:
      await routeResults(page, mockResultsBody());
  }
}

async function setupFareFixture(page: Page, fixtureId: string): Promise<void> {
  await setupResultsFixture(page, "results-present");
  switch (fixtureId) {
    case "fare-one-family":
      await routeOfferDetails(page, mockDetailsBody({
        offer: mockOffer({ branded_fares_display_options: fareFamilies(1), fare_family_options_display: fareFamilies(1) }),
      }));
      return;
    case "fare-three-families":
      await routeOfferDetails(page, mockDetailsBody());
      return;
    case "fare-four-families":
      await routeOfferDetails(page, mockDetailsBody({
        offer: mockOffer({ branded_fares_display_options: fareFamilies(4), fare_family_options_display: fareFamilies(4) }),
      }));
      return;
    case "fare-selected":
      await routeOfferDetails(page, mockDetailsBody());
      return;
    case "fare-revalidating":
      await routeOfferDetails(page, mockDetailsBody({
        revalidation_required: true,
        offer: mockRevalidationOffer(),
      }));
      await page.route("**/laravel/flights/results/revalidate-offer**", async () => {});
      return;
    case "fare-price-changed":
      await routeOfferDetails(page, mockDetailsBody({
        revalidation_required: true,
        offer: mockRevalidationOffer(),
      }));
      await routeRevalidate(page, {
        success: true,
        status: "fare_changed",
        requires_fare_change_acceptance: true,
        passengers_url: "/booking/passengers?search_id=audit-search&offer_id=audit-offer-1",
        revalidation: {
          price_changed: true,
          original_total: 134047,
          confirmed_total: 139999,
          old_total: 134047,
          new_total: 139999,
          currency: "PKR",
          revalidation_status: "changed",
        },
      });
      return;
    case "fare-unavailable":
      await routeOfferDetails(page, mockDetailsBody({ offer: mockRevalidationOffer() }));
      await routeRevalidate(page, {
        success: false,
        status: "offer_not_found",
        message: "This fare is no longer available.",
      });
      return;
    case "fare-expired":
      await routeOfferDetails(page, "410");
      return;
    default:
      await routeOfferDetails(page, mockDetailsBody());
  }
}

function passengerContext(overrides: Record<string, unknown> = {}) {
  return {
    ok: true,
    booking_session: {
      id: "jp-ui-04a-session",
      status: "passenger_details",
      expires_at: new Date(Date.now() + 15 * 60 * 1000).toISOString(),
      server_time: new Date().toISOString(),
      progress: [
        { key: "flight_selected", label: "Flight Selected", state: "completed", href: null },
        { key: "passenger_details", label: "Passenger Details", state: "current", href: null },
        { key: "seat_extras", label: "Seat & Extras", state: "skipped", href: null },
        { key: "review", label: "Review", state: "upcoming", href: null },
        { key: "payment", label: "Payment", state: "upcoming", href: null },
        { key: "confirmation", label: "Confirmation", state: "upcoming", href: null },
      ],
    },
    itinerary: baseItinerary,
    travellers: {
      adults: 1,
      children: 0,
      infants: 0,
      total: 1,
      expected: [{ index: 0, type: "adult", label: "Adult" }],
      lead_passenger_index: 0,
    },
    passenger_requirements: [],
    contact_requirements: [],
    document_requirements: {
      passport_required: true,
      national_id_allowed: false,
      passport_fields: [],
      national_id_fields: [],
    },
    existing_values: { passengers: [{}], contact: {} },
    checkout_summary: { currency: "PKR", passenger_counts: { adults: 1, children: 0, infants: 0, total: 1 } },
    seat_extras_capability: {
      seat_map_available: false,
      ancillaries_available: false,
      message: "Seat selection is not available for this fare.",
      progress_step: "skipped",
    },
    countries: [],
    phone_dial_codes: [],
    auth: { authenticated: false, can_create_account: true, agent_booking_mode: false, agent_contact_locked: false },
    selection: { search_id: "audit-search", offer_id: "audit-offer", from: "LHE", to: "DXB", depart: "2026-08-15", trip_type: "one_way", cabin: "economy" },
    ...overrides,
  };
}

async function setupPassengersFixture(page: Page, fixtureId: string): Promise<void> {
  await mockCsrf(page);
  if (fixtureId === "passengers-expired") {
    await page.route("**/laravel/booking/passengers?**", async (route) => {
      await route.fulfill({ status: 410, contentType: "application/json", body: JSON.stringify({ status: "offer_expired", message: "Offer expired" }) });
    });
    return;
  }
  if (fixtureId === "passengers-save-failure") {
    await page.route("**/laravel/booking/passengers?**", async (route) => {
      if (route.request().method() === "GET") {
        await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(passengerContext()) });
        return;
      }
      await route.fulfill({ status: 422, contentType: "application/json", body: JSON.stringify({ message: "Unable to save passengers.", errors: { "passengers.0.first_name": ["Required"] } }) });
    });
    return;
  }
  if (fixtureId === "passengers-mixed") {
    await page.route("**/laravel/booking/passengers?**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(passengerContext({
          travellers: {
            adults: 1,
            children: 1,
            infants: 1,
            total: 3,
            expected: [
              { index: 0, type: "adult", label: "Adult" },
              { index: 1, type: "child", label: "Child" },
              { index: 2, type: "infant", label: "Infant" },
            ],
            lead_passenger_index: 0,
          },
          existing_values: { passengers: [{}, {}, {}], contact: {} },
        })),
      });
    });
    return;
  }
  await page.route("**/laravel/booking/passengers?**", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(passengerContext()) });
  });
}

function reviewContext(overrides: Record<string, unknown> = {}) {
  return {
    ok: true,
    booking_session: {
      id: "jp-ui-04a-session",
      status: "review",
      expires_at: new Date(Date.now() + 15 * 60 * 1000).toISOString(),
      server_time: new Date().toISOString(),
      progress: PROGRESS_SKIPPED_SEATS,
    },
    itinerary: baseItinerary,
    passengers: [{ title: "Mr", first_name: "Audit", last_name: "Traveler", passenger_type: "adult" }],
    contact: { name: "Audit Traveler", email: "audit@example.com", phone: "+923001234567", country: "PK" },
    documents: [],
    pricing: basePricing,
    payment_methods: [
      { code: "manual", canonical: "pay_later", label: "Manual Payment", description: "Pay via bank transfer.", available: true, fee: null, currency: "PKR" },
      { code: "card", canonical: "online_card", label: "AbhiPay", description: "Secure card payment via AbhiPay.", available: true, fee: null, currency: "PKR" },
    ],
    terms: { required: true, terms_url: "/terms", privacy_url: "/privacy" },
    submit_blocked: false,
    notices: [],
    next_actions: {},
    ...overrides,
  };
}

async function setupReviewFixture(page: Page, fixtureId: string): Promise<void> {
  await mockCsrf(page);
  const payloads: Record<string, Record<string, unknown>> = {
    "review-complete": reviewContext(),
    "review-no-seats": reviewContext({ notices: ["Seat selection is not available for this booking."] }),
    "review-blocked": reviewContext({ submit_blocked: true, submit_blocked_reason: "Please accept the terms before continuing." }),
    "review-fare-change": reviewContext({
      submit_blocked: true,
      fare_change: {
        requires_acceptance: true,
        old_total_formatted: "Rs. 124,999",
        new_total_formatted: "Rs. 129,999",
      },
    }),
    "review-submit-busy": reviewContext({ submit_blocked: true, submit_blocked_reason: "Submitting your booking…" }),
    "review-creation-failure": reviewContext({ notices: ["We could not complete your booking. Please try again."] }),
  };
  await page.route("**/laravel/booking/review?**", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(payloads[fixtureId] ?? reviewContext()) });
  });
}

function checkoutContext(overrides: Record<string, unknown> = {}) {
  return {
    ok: true,
    booking_session: { id: "jp-ui-04a-session", status: "payment", server_time: new Date().toISOString(), progress: PROGRESS_SKIPPED_SEATS.map((s, i) => ({ ...s, state: i < 4 ? "completed" : i === 4 ? "current" : "upcoming" })) },
    booking_reference: "JPAUDIT04A",
    booking_method: "pay_later",
    payment_method_code: "manual",
    booking_status: { code: "pending", label: "Pending", terminal: false },
    payment_status: { code: "not_started", label: "Unpaid", terminal: false },
    pricing: basePricing,
    manual_payment: {
      amount_due: 124999,
      currency: "PKR",
      formatted_amount: "Rs. 124,999",
      instructions: ["Transfer to the account shown on your invoice.", "Upload proof after payment."],
      payment_status_label: "Awaiting payment",
      proof_upload_supported: true,
      payment_reference_supported: true,
    },
    card_payment: null,
    itinerary: baseItinerary,
    passengers: [{ title: "Mr", first_name: "Audit", last_name: "Traveler", passenger_type: "adult" }],
    contact: { name: "Audit Traveler", email: "audit@example.com", phone: "+923001234567", country: "PK" },
    documents_portal: [],
    support: { support_url: "/support", lookup_url: "/lookup-booking" },
    ...overrides,
  };
}

async function setupPaymentFixture(page: Page, fixtureId: string): Promise<void> {
  await mockCsrf(page);
  if (fixtureId === "payment-expired") {
    await page.route("**/laravel/booking/checkout-state?**", async (route) => {
      await route.fulfill({ status: 404, contentType: "application/json", body: JSON.stringify({ ok: false, status: "missing_session" }) });
    });
    return;
  }

  const variants: Record<string, Record<string, unknown>> = {
    "payment-manual": checkoutContext(),
    "payment-abhipay": checkoutContext({
      payment_method_code: "card",
      booking_method: "online_card",
      manual_payment: null,
      card_payment: {
        can_start: true,
        show_pay_button: true,
        payable_amount: 124999,
        currency: "PKR",
        formatted_amount: "Rs. 124,999",
        payment_status_label: "Unpaid",
        start_endpoint: "/booking/1/pay/card",
        ticketing_note: "Ticket will be issued after payment confirmation.",
        blocked_message: null,
      },
    }),
    "payment-initiating": checkoutContext({
      payment_method_code: "card",
      card_payment: {
        can_start: true,
        show_pay_button: true,
        payable_amount: 124999,
        currency: "PKR",
        formatted_amount: "Rs. 124,999",
        payment_status_label: "Starting payment…",
        start_endpoint: "/booking/1/pay/card",
        ticketing_note: "Redirecting to AbhiPay…",
        blocked_message: null,
      },
    }),
    "payment-pending": checkoutContext({
      payment_status: { code: "pending", label: "Payment pending", terminal: false },
      manual_payment: {
        amount_due: 124999,
        currency: "PKR",
        formatted_amount: "Rs. 124,999",
        instructions: ["Your payment is being verified."],
        payment_status_label: "Pending verification",
        proof_upload_supported: true,
        payment_reference_supported: true,
      },
    }),
    "payment-failed": checkoutContext({
      payment_method_code: "card",
      payment_status: { code: "failed", label: "Payment failed", terminal: false },
      card_payment: {
        can_start: true,
        show_pay_button: true,
        payable_amount: 124999,
        currency: "PKR",
        formatted_amount: "Rs. 124,999",
        payment_status_label: "Failed",
        start_endpoint: "/booking/1/pay/card",
        ticketing_note: "Payment could not be completed.",
        blocked_message: "Payment failed. You can try again.",
      },
    }),
    "payment-canceled": checkoutContext({
      payment_method_code: "card",
      payment_status: { code: "canceled", label: "Payment canceled", terminal: false },
      card_payment: {
        can_start: true,
        show_pay_button: true,
        payable_amount: 124999,
        currency: "PKR",
        formatted_amount: "Rs. 124,999",
        payment_status_label: "Canceled",
        start_endpoint: "/booking/1/pay/card",
        ticketing_note: "Payment was canceled.",
        blocked_message: null,
      },
    }),
    "payment-provider-unavailable": checkoutContext({
      payment_method_code: "card",
      card_payment: {
        can_start: false,
        show_pay_button: false,
        payable_amount: 124999,
        currency: "PKR",
        formatted_amount: "Rs. 124,999",
        payment_status_label: "Unavailable",
        start_endpoint: null,
        ticketing_note: "Online payment is temporarily unavailable.",
        blocked_message: "AbhiPay is unavailable right now.",
      },
    }),
    "payment-manual-pending": checkoutContext({
      payment_status: { code: "pending", label: "Awaiting manual payment", terminal: false },
      manual_payment: {
        amount_due: 124999,
        currency: "PKR",
        formatted_amount: "Rs. 124,999",
        instructions: ["Complete bank transfer and upload proof."],
        payment_status_label: "Awaiting manual payment",
        proof_upload_supported: true,
        payment_reference_supported: true,
      },
    }),
  };

  await page.route("**/laravel/booking/checkout-state?**", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(variants[fixtureId] ?? checkoutContext()) });
  });
}

function confirmationContext(overrides: Record<string, unknown> = {}) {
  return {
    ok: true,
    booking_session: {
      id: "jp-ui-04a-session",
      status: "confirmation",
      server_time: new Date().toISOString(),
      progress: PROGRESS_SKIPPED_SEATS.map((s) => ({ ...s, state: "completed" })).concat([{ key: "confirmation", label: "Confirmation", state: "current", href: null }]),
    },
    booking_reference: "JPAUDIT04A",
    booking_method: "pay_later",
    payment_method_code: "manual",
    booking_status: { code: "confirmed", label: "Confirmed", terminal: false },
    payment_status: { code: "succeeded", label: "Paid", terminal: true },
    ticketing_status: { code: "ticketed", label: "Ticketed", terminal: true },
    pricing: basePricing,
    itinerary: { ...baseItinerary, route_label: "LHE → DXB", segments: [{ origin: "LHE", destination: "DXB", flight_number: "EK612" }] },
    passengers: [{ title: "Mr", first_name: "Audit", last_name: "Traveler", passenger_type: "adult" }],
    contact: { name: "Audit Traveler", email: "audit@example.com", phone: "+923001234567", country: "PK" },
    documents_portal: [],
    support: { support_url: "/support", lookup_url: "/lookup-booking" },
    presentation: { heading: "Booking confirmed", subtitle: "Your booking has been confirmed.", tone: "success", show_celebration: true },
    pnr_details: { booking_reference: "ABC123", airline_locator: null, available: true },
    tickets: [],
    actions: [
      { code: "view_invoice", label: "View invoice", available: true, url: "/booking/invoice" },
      { code: "lookup_booking", label: "Lookup booking", available: true, url: "/lookup-booking" },
    ],
    poll: { should_poll: false, interval_ms: 4000, max_attempts: 45 },
    cancellation: { eligible: false, request_pending: false, already_cancelled: false, message: "" },
    refund: { available: false, status: null, label: null },
    ...overrides,
  };
}

async function setupSuccessFixture(page: Page, fixtureId: string): Promise<void> {
  await mockCsrf(page);
  if (fixtureId === "success-not-found" || fixtureId === "success-unauthorized") {
    await page.route("**/laravel/booking/confirmation?**", async (route) => {
      await route.fulfill({
        status: fixtureId === "success-unauthorized" ? 403 : 404,
        contentType: "application/json",
        body: JSON.stringify({ ok: false, status: "missing_session" }),
      });
    });
    return;
  }

  const variants: Record<string, Record<string, unknown>> = {
    "success-confirmed": confirmationContext(),
    "success-payment-pending": confirmationContext({
      payment_status: { code: "pending", label: "Payment pending", terminal: false },
      presentation: { heading: "Booking received", subtitle: "Complete payment to confirm.", tone: "pending", show_celebration: false },
    }),
    "success-pnr-pending": confirmationContext({
      pnr_details: { booking_reference: null, airline_locator: null, available: false },
      presentation: { heading: "Booking confirmed", subtitle: "PNR will be issued shortly.", tone: "pending", show_celebration: false },
    }),
    "success-ticketing-pending": confirmationContext({
      ticketing_status: { code: "pending", label: "Ticketing pending", terminal: false },
      presentation: { heading: "Booking confirmed", subtitle: "Ticketing in progress.", tone: "pending", show_celebration: false },
    }),
    "success-ticketed": confirmationContext({
      ticketing_status: { code: "ticketed", label: "Ticketed", terminal: true },
      tickets: [{ passenger_name: "Audit Traveler", ticket_number: "1234567890" }],
    }),
    "success-no-invoice": confirmationContext({
      actions: [{ code: "lookup_booking", label: "Lookup booking", available: true, url: "/lookup-booking" }],
    }),
  };

  await page.route("**/laravel/booking/confirmation?**", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(variants[fixtureId] ?? confirmationContext()) });
  });
}

export async function setupJpUi04aScenario(page: Page, scenario: JpUi04aScenario): Promise<void> {
  switch (scenario.family) {
    case "results":
      await setupResultsFixture(page, scenario.fixtureId);
      break;
    case "fare":
      await setupFareFixture(page, scenario.fixtureId);
      break;
    case "passengers":
    case "seats":
      await setupPassengersFixture(page, scenario.fixtureId);
      break;
    case "review":
      await setupReviewFixture(page, scenario.fixtureId);
      break;
    case "payment":
      await setupPaymentFixture(page, scenario.fixtureId);
      break;
    case "success":
      await setupSuccessFixture(page, scenario.fixtureId);
      break;
    default:
      await mockCsrf(page);
  }
}
