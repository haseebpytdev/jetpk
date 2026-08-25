import { test, expect, type Page, type Route } from "@playwright/test";
import { mkdirSync } from "node:fs";
import { join } from "node:path";

/**
 * JP-BO-04G commerce flow matrix — fixture/route-mocked evidence.
 * Includes split-return explicit branded-fare Book race regression.
 */

const OUT = join(process.cwd(), "tmp", "jp-bo-04g", "playwright");
const SEARCH_ID = "jp-bo-04g-search";

test.beforeAll(() => {
  mkdirSync(OUT, { recursive: true });
});

async function mockCsrf(page: Page) {
  await page.route("**/laravel/api/public/content/csrf-token", async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ csrf_token: "jp-bo-04g-csrf" }),
      headers: { "set-cookie": "XSRF-TOKEN=jp-bo-04g-csrf; Path=/" },
    });
  });
  await page.route("**/api/public/content/csrf-token", async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ csrf_token: "jp-bo-04g-csrf" }),
      headers: { "set-cookie": "XSRF-TOKEN=jp-bo-04g-csrf; Path=/" },
    });
  });
}

async function mockResults(page: Page, body: Record<string, unknown>) {
  await page.route("**/flights/results/data**", async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(body),
    });
  });
  await page.route("**/flights/results/search**", async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ ok: true, search_id: SEARCH_ID, status: "ready" }),
    });
  });
}

function parseFormBody(raw: string | null): Record<string, string> {
  if (!raw) return {};
  return Object.fromEntries(
    raw
      .split("&")
      .map((pair) => pair.split("="))
      .map(([key, value]) => [decodeURIComponent(key.replace(/\+/g, " ")), decodeURIComponent((value ?? "").replace(/\+/g, " "))]),
  );
}

const brandedOffer = {
  offer_id: "offer-1",
  airline_code: "PK",
  airline_name: "Pakistan International",
  flight_number: "PK301",
  departure_time: "08:00",
  arrival_time: "11:00",
  departure_airport_code: "LHE",
  arrival_airport_code: "DXB",
  stops: 0,
  can_book: true,
  displayed_price: 85000,
  final_customer_price: 85000,
  has_branded_fares: true,
  branded_fares_display_options: [
    {
      option_key: "fare-basic",
      name: "Economy Basic",
      selection_key_authoritative: true,
      displayed_price: 85000,
      price_display: "PKR 85,000",
    },
    {
      option_key: "fare-comfort",
      name: "Economy Comfort",
      selection_key_authoritative: true,
      displayed_price: 95000,
      price_display: "PKR 95,000",
    },
  ],
  segments: [
    {
      origin_airport_code: "LHE",
      destination_airport_code: "DXB",
      departure_time_display: "08:00",
      arrival_time_display: "11:00",
    },
  ],
};

const returnBrandedFares = [
  {
    option_key: "return-basic",
    name: "Economy Basic",
    selection_key_authoritative: true,
    displayed_price: 180000,
    price_display: "PKR 180,000",
  },
  {
    option_key: "return-flex",
    name: "Economy Flex",
    selection_key_authoritative: true,
    displayed_price: 195000,
    price_display: "PKR 195,000",
  },
];

function returnOptionsBody() {
  return {
    flow: "return_split_return",
    search_id: SEARCH_ID,
    outbound_key: "out-1",
    return_options: [
      {
        combo_id: "combo-1",
        fare_option_key: "return-basic",
        displayed_price: 180000,
        journey_display: {
          departure_time_display: "15:00",
          arrival_time_display: "20:00",
        },
        branded_fares_display_options: returnBrandedFares,
      },
    ],
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
  };
}

function returnSplitItinerary() {
  return {
    trip_type: "round_trip",
    origin: "LHE",
    destination: "DXB",
    depart_date: "2026-09-01",
    return_date: "2026-09-08",
    airline_name: "Pakistan International",
    airline_code: "PK",
    cabin: "economy",
    fare_family: "Economy Flex",
    total_formatted: "195,000",
    currency: "PKR",
    segments: [],
    return_segments: [],
    return_split: {
      is_return_split: true,
      outbound: {
        branded_fare_title: "Economy Comfort",
        fare_option_key: "fare-comfort",
        route_label: "LHE → DXB",
        departure_time: "08:00",
        arrival_time: "11:00",
        cabin: "economy",
        price_display: "PKR 95,000",
      },
      return: {
        branded_fare_title: "Economy Flex",
        fare_option_key: "return-flex",
        route_label: "DXB → LHE",
        departure_time: "15:00",
        arrival_time: "20:00",
        cabin: "economy",
        price_display: "PKR 195,000",
      },
      totals: {
        grand_total_display: "PKR 195,000",
        selected_total_display: "PKR 195,000",
      },
    },
  };
}

async function mockReturnSplitCheckout(page: Page) {
  const itinerary = returnSplitItinerary();
  const passengersContext = {
    ok: true,
    booking_session: {
      id: "jp-bo-04g-session",
      status: "passenger_details",
      expires_at: new Date(Date.now() + 15 * 60 * 1000).toISOString(),
      server_time: new Date().toISOString(),
      next_url: null,
      previous_url: "/flights/results",
      progress: [
        { key: "flight_selected", label: "Flight Selected", state: "completed", href: null },
        { key: "passenger_details", label: "Passenger Details", state: "current", href: null },
        { key: "review", label: "Review", state: "upcoming", href: null },
        { key: "payment", label: "Payment", state: "upcoming", href: null },
      ],
    },
    selection: {
      search_id: SEARCH_ID,
      offer_id: "combo-1",
      outbound_fare_option_key: "fare-comfort",
      return_fare_option_key: "return-flex",
      fare_option_key: "return-flex",
      from: "LHE",
      to: "DXB",
      depart: "2026-09-01",
      return_date: "2026-09-08",
      trip_type: "round_trip",
      cabin: "economy",
    },
    itinerary,
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
      passport_required: false,
      national_id_allowed: true,
      passport_fields: [],
      national_id_fields: [],
    },
    existing_values: { passengers: [{}], contact: {} },
    checkout_summary: { currency: "PKR", passenger_counts: { adults: 1, children: 0, infants: 0, total: 1 } },
    seat_extras_capability: {
      seat_map_available: false,
      ancillaries_available: false,
      message: "Seat selection unavailable for this fixture.",
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
      id: "jp-bo-04g-session",
      status: "review",
      server_time: new Date().toISOString(),
      progress: [
        { key: "passenger_details", label: "Passenger Details", state: "completed", href: "/booking/passengers" },
        { key: "review", label: "Review", state: "current", href: null },
      ],
    },
    itinerary,
    passengers: [{ first_name: "Test", last_name: "Traveller", passenger_type: "adult" }],
    contact: { contact_email: "test@example.com", contact_phone: "+923001234567" },
    documents: [],
    pricing: {
      currency: "PKR",
      base_fare: 180000,
      taxes: 15000,
      service_charges: 0,
      total: 195000,
      formatted_total: "195,000",
      selected_fare_total: 195000,
    },
    payment_methods: [
      {
        code: "manual",
        canonical: "manual",
        label: "Pay later",
        description: "Manual payment",
        available: true,
        fee: null,
        currency: "PKR",
      },
    ],
    terms: { required: true, terms_url: "/terms", privacy_url: "/privacy" },
    submit_blocked: false,
    notices: [],
    next_actions: { edit_passengers_url: "/booking/passengers" },
  };

  await page.route("**/laravel/booking/passengers**", async (route: Route) => {
    if (route.request().method().toUpperCase() === "GET") {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(passengersContext) });
      return;
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ ok: true, status: "accepted", next_url: "/booking/review" }),
    });
  });
  await page.route("**/laravel/booking/review**", async (route: Route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(reviewContext) });
  });
  await page.route("**/booking/commerce-gates**", async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ ok: true, guest_booking_enabled: true, card_payment_enabled: true }),
    });
  });
}

async function interceptSelectReturnCombo(page: Page) {
  let posted: Record<string, string> | null = null;
  await page.route("**/flights/select-return-combo**", async (route: Route) => {
    posted = parseFormBody(route.request().postData());
    await route.fulfill({
      status: 200,
      contentType: "text/html",
      body: "<html><body>select-return-combo accepted</body></html>",
    });
  });
  return {
    getPosted: () => posted,
  };
}

async function openSegmentedOutbound(page: Page) {
  await mockResults(page, {
    search_id: SEARCH_ID,
    flow: "return_split_outbound",
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
    offers: [],
    outbound_options: [
      {
        outbound_key: "out-1",
        from_total_amount: 180000,
        from_total_display: "PKR 180,000",
        combo_count: 2,
        journey_display: {
          departure_time_display: "08:00",
          arrival_time_display: "11:00",
          origin_airport_code: "LHE",
          destination_airport_code: "DXB",
          airline_code: "PK",
          airline_name: "Pakistan International",
        },
        branded_fares_display_options: brandedOffer.branded_fares_display_options,
      },
    ],
    status: "ready",
  });
  await page.goto(
    `/flights/results?search_id=${SEARCH_ID}&trip_type=round_trip&view=segmented&from=LHE&to=DXB&depart=2026-09-01&return_date=2026-09-08&cabin=economy&adults=1`,
  );
  await expect(page.getByTestId("outbound-option-card").first()).toBeVisible({ timeout: 15000 });
}

async function mockReturnOptions(page: Page) {
  await page.route("**/flights/return-options/data**", async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(returnOptionsBody()),
    });
  });
}

test("01 one-way results", async ({ page }) => {
  await mockResults(page, {
    search_id: SEARCH_ID,
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
    offers: [brandedOffer],
    status: "ready",
  });
  await page.goto(
    `/flights/results?search_id=${SEARCH_ID}&trip_type=one_way&from=LHE&to=DXB&depart=2026-09-01&cabin=economy&adults=1`,
  );
  await expect(page.getByTestId("flight-result-card").first()).toBeVisible({ timeout: 15000 });
  await page.screenshot({ path: join(OUT, "01-one-way-results.png"), fullPage: true });
});

test("02 one-way brand selected", async ({ page }) => {
  await mockResults(page, {
    search_id: SEARCH_ID,
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
    offers: [brandedOffer],
    status: "ready",
  });
  await page.goto(
    `/flights/results?search_id=${SEARCH_ID}&trip_type=one_way&from=LHE&to=DXB&depart=2026-09-01&cabin=economy&adults=1`,
  );
  await expect(page.getByTestId("flight-result-card").first()).toBeVisible({ timeout: 15000 });
  const comfort = page.getByText("Economy Comfort").first();
  if (await comfort.isVisible().catch(() => false)) {
    await comfort.click();
  }
  await page.screenshot({ path: join(OUT, "02-one-way-brand-selected.png"), fullPage: true });
});

test("04 return paired results", async ({ page }) => {
  await mockResults(page, {
    search_id: SEARCH_ID,
    flow: "return_pair",
    pairing_authority: "SUPPLIER_RETURNED",
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
    offers: [],
    paired_options: [
      {
        combo_id: "combo-1",
        outbound_key: "out-1",
        can_book: true,
        total_display: "PKR 180,000",
        airline_name: "Pakistan International",
        fare_family: "Economy Flex",
        cabin: "economy",
        outbound_journey: {
          departure_time_display: "08:00",
          arrival_time_display: "11:00",
          origin_airport_code: "LHE",
          destination_airport_code: "DXB",
        },
        return_journey: {
          departure_time_display: "15:00",
          arrival_time_display: "20:00",
          origin_airport_code: "DXB",
          destination_airport_code: "LHE",
        },
        branded_fares_display_options: brandedOffer.branded_fares_display_options,
      },
    ],
    status: "ready",
  });
  await page.goto(
    `/flights/results?search_id=${SEARCH_ID}&trip_type=round_trip&view=pair&from=LHE&to=DXB&depart=2026-09-01&return_date=2026-09-08&cabin=economy&adults=1`,
  );
  await expect(page.getByTestId("pair-return-card").first()).toBeVisible({ timeout: 15000 });
  await page.screenshot({ path: join(OUT, "04-return-paired-results.png"), fullPage: true });
});

test("07 return split outbound brand", async ({ page }) => {
  await openSegmentedOutbound(page);
  await page.screenshot({ path: join(OUT, "07-return-split-outbound-brand.png"), fullPage: true });
});

test("08 one-way explicit fare book", async ({ page }) => {
  await mockCsrf(page);
  await mockResults(page, {
    search_id: SEARCH_ID,
    flow: "one_way",
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
    offers: [brandedOffer],
    status: "ready",
  });
  await page.route("**/flights/results/offer**", async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        search_id: SEARCH_ID,
        offer_id: "offer-1",
        offer: {
          ...brandedOffer,
          supplier_provider: "iati",
          select_url: "/booking/passengers?offer_id=offer-1",
          can_book: true,
        },
      }),
    });
  });

  let continueRaw: string | null = null;
  await page.route("**/flights/results/revalidate**", async (route: Route) => {
    continueRaw = route.request().postData();
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        passengers_url: "/booking/passengers?offer_id=offer-1&fare_option_key=fare-comfort",
      }),
    });
  });
  await page.route("**/booking/commerce-gates**", async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ ok: true, guest_booking_enabled: true, card_payment_enabled: true }),
    });
  });

  // One-way branded path: Book Now opens Details drawer for explicit fare confirmation.
  await page.goto(
    `/flights/results?search_id=${SEARCH_ID}&trip_type=one_way&from=LHE&to=DXB&depart=2026-09-01&cabin=economy&adults=1`,
  );
  await expect(page.getByTestId("book-now-trigger").first()).toBeVisible({ timeout: 15000 });
  await expect(page.getByTestId("selected-fare-brand")).toHaveCount(0);
  await page.getByTestId("book-now-trigger").first().click();
  await expect(page.getByTestId("flight-details-drawer")).toBeVisible({ timeout: 15000 });
  await expect(page.getByTestId("fare-family-details")).toBeVisible();

  // Explicitly choose non-default comfort, then continue — selected key must be fare-comfort.
  await page
    .locator("[data-fare-family-card]", { hasText: "Economy Comfort" })
    .getByRole("button", { name: /Select fare/i })
    .click();
  await page.getByTestId("continue-to-passengers").click();
  await expect.poll(() => continueRaw).not.toBeNull();
  expect(continueRaw!).toContain("selected_fare_option_id");
  expect(continueRaw!).toContain("fare-comfort");
  expect(continueRaw!).not.toMatch(/selected_fare_option_id[\s\S]{0,80}fare-basic/);
});

test("09 return paired explicit fare book", async ({ page }) => {
  await mockCsrf(page);
  await mockResults(page, {
    search_id: SEARCH_ID,
    flow: "return_pair",
    pairing_authority: "SUPPLIER_RETURNED",
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
    offers: [],
    paired_options: [
      {
        combo_id: "combo-1",
        outbound_key: "out-1",
        can_book: true,
        total_display: "PKR 180,000",
        airline_name: "Pakistan International",
        cabin: "economy",
        outbound_journey: {
          departure_time_display: "08:00",
          arrival_time_display: "11:00",
          origin_airport_code: "LHE",
          destination_airport_code: "DXB",
        },
        return_journey: {
          departure_time_display: "15:00",
          arrival_time_display: "20:00",
          origin_airport_code: "DXB",
          destination_airport_code: "LHE",
        },
        branded_fares_display_options: brandedOffer.branded_fares_display_options,
      },
    ],
    status: "ready",
  });
  await page.route("**/flights/results/offer**", async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        search_id: SEARCH_ID,
        offer_id: "combo-1",
        offer: {
          offer_id: "combo-1",
          can_book: true,
          select_url: "/booking/passengers",
          branded_fares_display_options: brandedOffer.branded_fares_display_options,
        },
      }),
    });
  });
  const intercept = await interceptSelectReturnCombo(page);
  await page.goto(
    `/flights/results?search_id=${SEARCH_ID}&trip_type=round_trip&view=pair&from=LHE&to=DXB&depart=2026-09-01&return_date=2026-09-08&cabin=economy&adults=1`,
  );
  await expect(page.getByTestId("pair-return-card").first()).toBeVisible({ timeout: 15000 });
  await page.getByTestId("fare-price-fare-comfort").click();
  await expect(page.getByTestId("flight-details-drawer")).toBeVisible({ timeout: 15000 });
  await page.getByTestId("continue-to-passengers").click();
  await expect.poll(() => intercept.getPosted()).not.toBeNull();
  const posted = intercept.getPosted()!;
  expect(posted.fare_option_key).toBe("fare-comfort");
  expect(posted.return_fare_option_key).toBe("fare-comfort");
  expect(posted.fare_option_key).not.toBe("fare-basic");
});

test("10 return split direct book non-default", async ({ page }) => {
  await mockCsrf(page);
  await mockReturnOptions(page);
  await mockReturnSplitCheckout(page);
  const intercept = await interceptSelectReturnCombo(page);

  await openSegmentedOutbound(page);
  // Book outbound Economy Comfort (non-default).
  await page.getByTestId("fare-price-fare-comfort").click();
  await expect(page).toHaveURL(/\/flights\/return-options/);
  await expect(page).toHaveURL(/outbound_fare_option_key=fare-comfort/);
  await expect(page.getByTestId("return-option-card").first()).toBeVisible({ timeout: 15000 });
  await expect(page.getByTestId("outbound-fare-preserved")).toBeVisible();

  await page.route("**/flights/results/offer**", async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        search_id: SEARCH_ID,
        offer_id: "combo-1",
        offer: {
          offer_id: "combo-1",
          can_book: true,
          select_url: "/booking/passengers",
          branded_fares_display_options: [
            {
              option_key: "return-basic",
              name: "Economy Basic",
              displayed_price: 180000,
              selection_key_authoritative: true,
            },
            {
              option_key: "return-flex",
              name: "Economy Flex",
              displayed_price: 195000,
              selection_key_authoritative: true,
            },
          ],
        },
      }),
    });
  });

  // CRITICAL: do NOT click return-flex card first — Book directly (stale-state race).
  await page.getByTestId("fare-price-return-flex").click();
  await expect(page.getByTestId("flight-details-drawer")).toBeVisible({ timeout: 15000 });
  await page.getByTestId("continue-to-passengers").click();
  await expect.poll(() => intercept.getPosted()).not.toBeNull();
  const posted = intercept.getPosted()!;
  expect(posted.outbound_fare_option_key).toBe("fare-comfort");
  expect(posted.return_fare_option_key).toBe("return-flex");
  expect(posted.fare_option_key).toBe("return-flex");
  expect(posted.return_fare_option_key).not.toBe("return-basic");
  expect(posted.fare_option_key).not.toBe("return-basic");

  await page.goto("/booking/passengers?search_id=" + SEARCH_ID);
  await expect(page.getByTestId("return-split-outbound")).toContainText("Economy Comfort");
  await expect(page.getByTestId("return-split-return")).toContainText("Economy Flex");
  await expect(page.getByTestId("return-split-total")).toContainText("195,000");

  await page.goto("/booking/review");
  await expect(page.getByTestId("return-split-outbound").first()).toContainText("Economy Comfort");
  await expect(page.getByTestId("return-split-return").first()).toContainText("Economy Flex");
  await expect(page.getByTestId("return-split-total").first()).toContainText("195,000");
  await expect(page.getByTestId("review-price-summary").getByTestId("order-summary-total")).toContainText("195,000");
  await page.screenshot({ path: join(OUT, "10-return-split-direct-book-non-default.png"), fullPage: true });
});

test("11 return split preselect then book", async ({ page }) => {
  await mockCsrf(page);
  await mockReturnOptions(page);
  const intercept = await interceptSelectReturnCombo(page);

  await page.route("**/flights/results/offer**", async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        search_id: SEARCH_ID,
        offer_id: "combo-1",
        offer: {
          offer_id: "combo-1",
          can_book: true,
          select_url: "/booking/passengers",
          branded_fares_display_options: [
            {
              option_key: "return-basic",
              name: "Economy Basic",
              displayed_price: 180000,
              selection_key_authoritative: true,
            },
            {
              option_key: "return-flex",
              name: "Economy Flex",
              displayed_price: 195000,
              selection_key_authoritative: true,
            },
          ],
        },
      }),
    });
  });

  await page.goto(
    `/flights/return-options?search_id=${SEARCH_ID}&outbound_key=out-1&outbound_fare_option_key=fare-comfort`,
  );
  await expect(page.getByTestId("return-option-card").first()).toBeVisible({ timeout: 15000 });

  // Normal path: select return-flex card, then Book → Details confirmation.
  await page.getByTestId("fare-price-return-flex").click();
  await expect(page.getByTestId("flight-details-drawer")).toBeVisible({ timeout: 15000 });
  await page.getByTestId("continue-to-passengers").click();

  await expect.poll(() => intercept.getPosted()).not.toBeNull();
  const posted = intercept.getPosted()!;
  expect(posted.outbound_fare_option_key).toBe("fare-comfort");
  expect(posted.return_fare_option_key).toBe("return-flex");
  expect(posted.fare_option_key).toBe("return-flex");
});
