import type { Page } from "@playwright/test";
import type { VisualAuditScenario } from "./jp-ui-01-scenarios";

export const MOCK_SEARCH_ID = "jp-ui-01-audit-search-id";

export async function mockCsrf(page: Page): Promise<void> {
  await page.route("**/laravel/api/public/content/csrf-token", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ csrf_token: "jp-ui-01-audit-csrf" }),
      headers: { "set-cookie": "XSRF-TOKEN=jp-ui-01-audit-csrf; Path=/" },
    });
  });
}

export async function mockTurnstileDisabled(page: Page): Promise<void> {
  await page.route("**/laravel/api/public/content/turnstile-config", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ enabled: false, site_key: null, response_field: "cf-turnstile-response" }),
    });
  });
}

export async function mockAuthRegistrationChallenge(page: Page): Promise<void> {
  await page.route("**/laravel/api/public/auth/registration-security-challenge", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ security_question: "Audit fixture: what is 2 + 3?" }),
    });
  });
}

function tomorrowIso(): string {
  const date = new Date();
  date.setDate(date.getDate() + 1);
  return date.toISOString().slice(0, 10);
}

function mockOffer(branded = false) {
  return {
    offer_id: "audit-offer-1",
    airline_code: "EK",
    airline_name: "Audit Airline",
    airline_logo_url: null,
    departure_time: "08:30",
    arrival_time: "11:45",
    duration: "3h 15m",
    stops: 0,
    stops_label_display: "Nonstop",
    displayed_price: 134047,
    price_display: "134,047 PKR",
    can_book: true,
    refundable: false,
    baggage: "30kg checked",
    segments: [
      {
        origin_airport_code: "LHE",
        destination_airport_code: "DXB",
        departure_time_display: "08:30",
        arrival_time_display: "11:45",
        flight_number: "EK612",
      },
    ],
    select_url: "/booking/passengers",
    has_branded_fares: branded,
    fare_family_options_display: branded
      ? [
          {
            code: "economy-saver",
            label: "Economy Saver",
            price_display: "114,999 PKR",
            selected: true,
          },
          {
            code: "economy-flex",
            label: "Economy Flex",
            price_display: "129,999 PKR",
            selected: false,
          },
        ]
      : [],
  };
}

function mockResultsBody(branded = false) {
  return {
    search_id: MOCK_SEARCH_ID,
    page: 1,
    per_page: 12,
    total: 2,
    has_more: false,
    filters: {
      stops: [{ value: "direct", label: "Nonstop", count: 2 }],
      airlines: [{ code: "EK", name: "Audit Airline", count: 2 }],
      departure_windows: [{ value: "morning", label: "Morning", count: 2 }],
      refundable: [{ value: "0", label: "Non-refundable", count: 2 }],
    },
    offers: [mockOffer(branded), mockOffer(branded)],
    warnings: [],
    empty_message: "",
    search_freshness: { expires_display: "Results expire in 25 minutes" },
  };
}

export function resultsQuery(): string {
  return new URLSearchParams({
    search_id: MOCK_SEARCH_ID,
    trip_type: "one_way",
    from: "LHE",
    to: "DXB",
    depart: tomorrowIso(),
    adults: "1",
    children: "0",
    infants: "0",
    cabin: "economy",
  }).toString();
}

export async function setupResultsMocks(page: Page, branded = false): Promise<void> {
  await page.route("**/laravel/flights/results/data**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(mockResultsBody(branded)),
    });
  });
}

const passengerContext = {
  ok: true,
  booking_session: {
    id: "jp-ui-01-audit-session",
    status: "passenger_details",
    expires_at: new Date(Date.now() + 15 * 60 * 1000).toISOString(),
    server_time: new Date().toISOString(),
    next_url: null,
    previous_url: "/flights/results",
    progress: [
      { key: "flight_selected", label: "Flight Selected", state: "completed", href: null },
      { key: "passenger_details", label: "Passenger Details", state: "current", href: null },
      { key: "seat_extras", label: "Seat & Extras", state: "upcoming", href: null },
      { key: "review", label: "Review", state: "upcoming", href: null },
      { key: "payment", label: "Payment", state: "upcoming", href: null },
      { key: "confirmation", label: "Confirmation", state: "upcoming", href: null },
    ],
  },
  selection: {
    search_id: "audit-search",
    offer_id: "audit-offer",
    from: "LHE",
    to: "DXB",
    depart: "2026-08-15",
    trip_type: "one_way",
    cabin: "economy",
  },
  itinerary: {
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
  },
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
    progress_step: "upcoming",
  },
  countries: [],
  phone_dial_codes: [],
  auth: {
    authenticated: false,
    can_create_account: true,
    agent_booking_mode: false,
    agent_contact_locked: false,
  },
};

const reviewContext = {
  ok: true,
  booking_session: {
    id: "jp-ui-01-audit-session",
    status: "review",
    expires_at: new Date(Date.now() + 15 * 60 * 1000).toISOString(),
    server_time: new Date().toISOString(),
    progress: [
      { key: "flight_selected", label: "Flight Selected", state: "completed", href: null },
      { key: "passenger_details", label: "Passenger Details", state: "completed", href: null },
      { key: "seat_extras", label: "Seat & Extras", state: "skipped", href: null },
      { key: "review", label: "Review", state: "current", href: null },
      { key: "payment", label: "Payment", state: "upcoming", href: null },
      { key: "confirmation", label: "Confirmation", state: "upcoming", href: null },
    ],
  },
  itinerary: passengerContext.itinerary,
  passengers: [{ title: "Mr", first_name: "Audit", last_name: "Traveler", passenger_type: "adult" }],
  contact: { name: "Audit Traveler", email: "audit@example.com", phone: "+923001234567", country: "PK" },
  documents: [],
  pricing: {
    currency: "PKR",
    base_fare: 100000,
    taxes: 20000,
    service_charges: 4999,
    total: 124999,
    formatted_total: "Rs. 124,999",
  },
  payment_methods: [
    {
      code: "manual",
      canonical: "pay_later",
      label: "Manual Payment",
      description: "Pay via bank transfer and upload proof.",
      available: true,
      fee: null,
      currency: "PKR",
    },
    {
      code: "card",
      canonical: "online_card",
      label: "Pay by Card",
      description: "Secure card payment via AbhiPay.",
      available: true,
      fee: null,
      currency: "PKR",
    },
  ],
  terms: { required: true, terms_url: "/terms", privacy_url: "/privacy" },
  submit_blocked: false,
  notices: [],
  next_actions: {},
};

const checkoutState = {
  ok: true,
  booking_session: {
    id: "jp-ui-01-audit-session",
    status: "payment",
    server_time: new Date().toISOString(),
    progress: [
      { key: "flight_selected", label: "Flight Selected", state: "completed", href: null },
      { key: "passenger_details", label: "Passenger Details", state: "completed", href: null },
      { key: "seat_extras", label: "Seat & Extras", state: "skipped", href: null },
      { key: "review", label: "Review", state: "completed", href: null },
      { key: "payment", label: "Payment", state: "current", href: null },
      { key: "confirmation", label: "Confirmation", state: "upcoming", href: null },
    ],
  },
  booking_reference: "JPAUDIT01",
  booking_method: "pay_later",
  payment_method_code: "manual",
  booking_status: { code: "pending", label: "Pending", terminal: false },
  payment_status: { code: "not_started", label: "Unpaid", terminal: false },
  pricing: reviewContext.pricing,
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
  itinerary: passengerContext.itinerary,
  passengers: reviewContext.passengers,
  contact: reviewContext.contact,
  documents_portal: [],
  support: { support_url: "/support", lookup_url: "/lookup-booking" },
};

const confirmationFixture = {
  ok: true,
  booking_session: {
    id: "jp-ui-01-audit-session",
    status: "confirmation",
    server_time: new Date().toISOString(),
    progress: [{ key: "confirmation", label: "Confirmation", state: "current", href: null }],
  },
  booking_reference: "JPAUDIT01",
  booking_method: "pay_later",
  payment_method_code: "manual",
  booking_status: { code: "pending", label: "Pending", terminal: false },
  payment_status: { code: "not_started", label: "Unpaid", terminal: false },
  ticketing_status: { code: "not_started", label: "Not started", terminal: false },
  pricing: checkoutState.pricing,
  itinerary: {
    ...passengerContext.itinerary,
    route_label: "LHE → DXB",
    segments: [{ origin: "LHE", destination: "DXB", flight_number: "EK612" }],
  },
  passengers: reviewContext.passengers,
  contact: reviewContext.contact,
  documents_portal: [],
  support: { support_url: "/support", lookup_url: "/lookup-booking" },
  presentation: {
    heading: "Booking request received",
    subtitle: "Complete manual payment using the instructions provided.",
    tone: "pending",
    show_celebration: false,
  },
  pnr_details: { booking_reference: null, airline_locator: null, available: false },
  tickets: [],
  actions: [
    { code: "view_invoice", label: "View invoice", available: true, url: "/booking/invoice" },
    { code: "lookup_booking", label: "Lookup booking later", available: true, url: "/lookup-booking" },
  ],
  poll: { should_poll: false, interval_ms: 4000, max_attempts: 45 },
  cancellation: {
    eligible: false,
    request_pending: false,
    already_cancelled: false,
    message: "Use booking lookup to request cancellation.",
  },
  refund: { available: false, status: null, label: null },
};

export async function setupScenarioMocks(page: Page, setup: VisualAuditScenario["setup"]): Promise<void> {
  await mockCsrf(page);

  switch (setup) {
    case "auth":
      await mockAuthRegistrationChallenge(page);
      break;
    case "lookup":
      await mockTurnstileDisabled(page);
      break;
    case "results":
      await setupResultsMocks(page, false);
      break;
    case "results-branded":
      await setupResultsMocks(page, true);
      break;
    case "fare-selection":
      await page.route("**/laravel/flights/results/offer?**", async (route) => {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            success: true,
            search_id: MOCK_SEARCH_ID,
            offer_id: "audit-offer-1",
            offer: {
              ...mockOffer(true),
              branded_fares_display_options: [
                { option_key: "economy-saver", brand_name: "Economy Saver", price_display: "114,999 PKR" },
                { option_key: "economy-flex", brand_name: "Economy Flex", price_display: "129,999 PKR" },
              ],
            },
          }),
        });
      });
      break;
    case "passengers":
      await page.route("**/laravel/booking/passengers?**", async (route) => {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify(passengerContext),
        });
      });
      break;
    case "review":
      await page.route("**/laravel/booking/review?**", async (route) => {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify(reviewContext),
        });
      });
      break;
    case "payment":
      await page.route("**/laravel/booking/checkout-state?**", async (route) => {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify(checkoutState),
        });
      });
      break;
    case "confirmation":
      await page.route("**/laravel/booking/confirmation?**", async (route) => {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify(confirmationFixture),
        });
      });
      break;
    default:
      break;
  }
}

