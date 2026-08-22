import { test, expect } from "@playwright/test";

const searchId = "wave7-aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee";

const fares = [
  {
    option_key: "fare-basic",
    name: "ECONOMY BASIC",
    brand_name: "ECONOMY BASIC",
    displayed_price: 80400,
    price_display: "PKR 80,400",
    checked_baggage: "0 kg",
    cabin_baggage: "7 kg",
    baggage: "0 kg",
    refund_rule: "Non-refundable",
    change_rule: "Changes with fee",
    selection_key_authoritative: true,
    can_select: true,
  },
  {
    option_key: "fare-comfort",
    name: "ECONOMY COMFORT",
    brand_name: "ECONOMY COMFORT",
    displayed_price: 87460,
    price_display: "PKR 87,460",
    checked_baggage: "30 kg",
    cabin_baggage: "7 kg",
    baggage: "30 kg",
    refund_rule: "Refundable with fee",
    change_rule: "Changes with fee",
    meal: "Meal included",
    selection_key_authoritative: true,
    can_select: true,
  },
];

function details(selected = fares[0]) {
  return {
    success: true,
    search_id: searchId,
    offer_id: "offer-1",
    offer: {
      offer_id: "offer-1",
      airline_code: "EY",
      airline_name: "Etihad Airways",
      supplier_provider: "sabre",
      displayed_price: selected.displayed_price,
      final_customer_price: selected.displayed_price,
      price_display: selected.price_display,
      branded_fares_display_options: fares,
      fare_family_options_display: fares,
      baggage_summary_display: selected.baggage,
      baggage_checked_display: selected.checked_baggage,
      baggage_cabin_display: selected.cabin_baggage,
      refund_rule: selected.refund_rule,
      change_rule: selected.change_rule,
      can_book: true,
      flight_number: "EY231",
      segments: [
        {
          origin_airport_code: "ISB",
          destination_airport_code: "DXB",
          departure_time_display: "08:10",
          arrival_time_display: "11:25",
          flight_number: "EY231",
        },
      ],
      select_url: "/booking/passengers",
    },
  };
}

function passengersContext(selected = fares[1]) {
  return {
    ok: true,
    booking_session: {
      id: "wave7-session",
      status: "passenger_details",
      expires_at: new Date(Date.now() + 15 * 60_000).toISOString(),
      server_time: new Date().toISOString(),
      progress: [
        { key: "search", label: "Search", state: "completed" },
        { key: "results", label: "Results", state: "completed" },
        { key: "passenger_details", label: "Travelers", state: "current" },
        { key: "review", label: "Review", state: "upcoming" },
      ],
    },
    selection: {
      search_id: searchId,
      offer_id: "offer-1",
      fare_option_key: selected.option_key,
      from: "ISB",
      to: "DXB",
      depart: "2026-09-18",
      trip_type: "one_way",
      cabin: "economy",
    },
    itinerary: {
      trip_type: "one_way",
      origin: "ISB",
      destination: "DXB",
      depart_date: "2026-09-18",
      airline_name: "Etihad Airways",
      airline_code: "EY",
      flight_number: "EY231",
      cabin: "economy",
      fare_family: selected.name,
      stops: 0,
      duration: "3h 15m",
      baggage: selected.checked_baggage,
      checked_baggage: selected.checked_baggage,
      cabin_baggage: selected.cabin_baggage,
      segments: [
        {
          origin_airport_code: "ISB",
          destination_airport_code: "DXB",
          departure_time_display: "08:10",
          arrival_time_display: "11:25",
          flight_number: "EY231",
        },
      ],
      return_segments: [],
      total_formatted: selected.price_display,
      currency: "PKR",
      selected_fare_option_key: selected.option_key,
      selected_fare: {
        fare_option_key: selected.option_key,
        fare_family: selected.name,
        brand_name: selected.name,
        customer_total: selected.displayed_price,
        currency: "PKR",
        price_display: selected.price_display,
        checked_baggage: selected.checked_baggage,
        cabin_baggage: selected.cabin_baggage,
      },
    },
    selected_fare: {
      fare_option_key: selected.option_key,
      fare_family: selected.name,
      brand_name: selected.name,
      customer_total: selected.displayed_price,
      currency: "PKR",
      price_display: selected.price_display,
      checked_baggage: selected.checked_baggage,
      cabin_baggage: selected.cabin_baggage,
    },
    travellers: {
      adults: 1,
      children: 0,
      infants: 0,
      total: 1,
      expected: [{ index: 0, type: "adult", label: "Adult" }],
      lead_passenger_index: 0,
    },
    passenger_requirements: [
      { key: "title", label: "Title", required: true, input_type: "select", passenger_types: ["adult"] },
      { key: "first_name", label: "First name", required: true, input_type: "text", passenger_types: ["adult"] },
      { key: "last_name", label: "Last name", required: true, input_type: "text", passenger_types: ["adult"] },
      { key: "gender", label: "Gender", required: true, input_type: "select", passenger_types: ["adult"] },
      { key: "date_of_birth", label: "Date of birth", required: true, input_type: "date", passenger_types: ["adult"] },
    ],
    contact_requirements: [
      { key: "email", label: "Email", required: true, input_type: "email" },
      { key: "phone", label: "Phone", required: true, input_type: "tel" },
    ],
    document_requirements: { passport_required: false, national_id_allowed: true, passport_fields: [], national_id_fields: [] },
    existing_values: { passengers: [], contact: {} },
    checkout_summary: {
      total_formatted: selected.price_display,
      currency: "PKR",
      passenger_counts: { adults: 1, children: 0, infants: 0, total: 1 },
      lines: [],
    },
    seat_extras_capability: {
      seat_map_available: false,
      ancillaries_available: false,
      message: "Seat selection is not available for this fare.",
      progress_step: "skipped",
    },
    countries: [{ code: "PK", name: "Pakistan" }],
    phone_dial_codes: { "+92": "Pakistan (+92)" },
    auth: { authenticated: false, can_create_account: true, agent_booking_mode: false, agent_contact_locked: false },
    consent: {
      required: true,
      terms_version: "jp-checkout-terms-v1",
      terms_url: "/terms",
      privacy_url: "/privacy",
    },
    change_flight: {
      safe: true,
      abandon_url: "/booking/abandon-selected-offer",
      results_url: `/flights/results?search_id=${searchId}`,
    },
  };
}

test("wave-7 selected ECONOMY COMFORT survives Continue to Travelers", async ({ page }) => {
  test.setTimeout(120_000);

  let passengersFareKey: string | null = null;
  let revalidateFareKey: string | null = null;

  await page.route("**/laravel/api/public/content/csrf-token**", async (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ token: "wave7-csrf" }),
    }),
  );

  await page.route("**/laravel/flights/results/data**", async (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        search_id: searchId,
        page: 1,
        per_page: 12,
        total: 1,
        has_more: false,
        offers: [
          {
            offer_id: "offer-1",
            airline_code: "EY",
            airline_name: "Etihad Airways",
            supplier_provider: "sabre",
            provider: "sabre",
            departure_time: "08:10",
            arrival_time: "11:25",
            duration: "3h 15m",
            stops: 0,
            stops_label_display: "Direct",
            displayed_price: fares[0].displayed_price,
            final_customer_price: fares[0].displayed_price,
            can_book: true,
            flight_number: "EY231",
            segments: [
              {
                origin_airport_code: "ISB",
                destination_airport_code: "DXB",
                departure_time_display: "08:10",
                arrival_time_display: "11:25",
                flight_number: "EY231",
              },
            ],
            branded_fares_display_options: fares,
            fare_family_options_display: fares,
            has_branded_fares: true,
            select_url: "/booking/passengers",
          },
        ],
        filters: {
          stops: [],
          airlines: [],
          departure_windows: [],
          arrival_windows: [],
          refundable: [],
          baggage_options: [],
          fare_families: [],
          duration_buckets: [],
          layover_airports: [],
          price_range: { min: 80400, max: 87460 },
        },
        warnings: [],
      }),
    }),
  );

  await page.route("**/laravel/flights/results/nearby-dates**", async (route) =>
    route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ available: false, dates: [] }) }),
  );

  await page.route("**/laravel/flights/results/offer**", async (route) => {
    const key = new URL(route.request().url()).searchParams.get("fare_option_key");
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(details(fares.find((fare) => fare.option_key === key) ?? fares[0])),
    });
  });

  // Simulate Wave-6 bug shape: revalidation passengers_url omits fare_option_key.
  await page.route("**/laravel/flights/results/revalidate-offer**", async (route) => {
    const postData = route.request().postData() ?? "";
    const formMatch = postData.match(/name="selected_fare_option_id"\r?\n\r?\n([^\r\n]+)/);
    const queryMatch = postData.match(/selected_fare_option_id=([^&\r\n]+)/);
    revalidateFareKey = formMatch?.[1] ?? (queryMatch ? decodeURIComponent(queryMatch[1]) : null);
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        status: "valid",
        passengers_url: `/booking/passengers?search_id=${searchId}&offer_id=offer-1&flight_id=offer-1&from=ISB&to=DXB&depart=2026-09-18&adults=1`,
        selected_fare_option_id: "fare-comfort",
      }),
    });
  });

  await page.route("**/laravel/booking/passengers**", async (route) => {
    if (route.request().method() !== "GET") {
      await route.fallback();
      return;
    }
    const key = new URL(route.request().url()).searchParams.get("fare_option_key");
    passengersFareKey = key;
    const selected = fares.find((fare) => fare.option_key === key) ?? fares[0];
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(passengersContext(selected)),
    });
  });

  const query = new URLSearchParams({
    search_id: searchId,
    trip_type: "one_way",
    from: "ISB",
    to: "DXB",
    depart: "2026-09-18",
    adults: "1",
    children: "0",
    infants: "0",
    cabin: "economy",
  });

  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(`/flights/results?${query}`);
  await page.getByTestId("book-now-trigger").first().click();
  await expect(page.getByTestId("fare-family-details")).toBeVisible();

  await page.getByRole("listitem").filter({ hasText: "ECONOMY COMFORT" }).getByRole("button", { name: /Select fare|Selected/ }).click();
  await expect(page.getByRole("listitem").filter({ hasText: "ECONOMY COMFORT" })).toContainText("Selected");
  await expect(page.getByRole("listitem").filter({ hasText: "ECONOMY COMFORT" })).toContainText(/87,?460/);

  const passengersRequest = page.waitForRequest(
    (request) =>
      request.method() === "GET"
      && request.url().includes("/laravel/booking/passengers")
      && request.url().includes("fare_option_key=fare-comfort"),
    { timeout: 25_000 },
  );

  await page.getByRole("button", { name: /Continue with this fare/i }).click();
  await expect(page).toHaveURL(/\/booking\/passengers/, { timeout: 20_000 });
  await expect(page).toHaveURL(/fare_option_key=fare-comfort/);
  await expect.poll(() => revalidateFareKey, { timeout: 15_000 }).toBe("fare-comfort");
  await passengersRequest;
  await expect.poll(() => passengersFareKey, { timeout: 15_000 }).toBe("fare-comfort");

  await expect(page.getByTestId("flight-preview")).toContainText("ECONOMY COMFORT");
  await expect(page.getByTestId("flight-preview")).toContainText("30 kg");
  await expect(page.getByTestId("flight-preview")).toContainText(/87,?460/);
  await expect(page.getByTestId("flight-preview")).not.toContainText("ECONOMY BASIC");
});

test("wave-7 travelers require terms and expose Change flight", async ({ page }) => {
  test.setTimeout(90_000);
  let abandoned = false;

  await page.route("**/laravel/api/public/content/csrf-token**", async (route) =>
    route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ token: "wave7-csrf" }) }),
  );

  await page.route("**/laravel/booking/passengers**", async (route) => {
    if (route.request().method() === "GET") {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(passengersContext(fares[1])),
      });
      return;
    }
    await route.fulfill({
      status: 422,
      contentType: "application/json",
      body: JSON.stringify({ message: "Terms acceptance is required.", errors: { terms_accepted: ["Required"] } }),
    });
  });

  await page.route("**/laravel/booking/abandon-selected-offer**", async (route) => {
    abandoned = true;
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ ok: true, results_url: `/flights/results?search_id=${searchId}` }),
    });
  });

  await page.route("**/laravel/flights/results/data**", async (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        search_id: searchId,
        page: 1,
        per_page: 12,
        total: 0,
        has_more: false,
        offers: [],
        filters: {
          stops: [],
          airlines: [],
          departure_windows: [],
          arrival_windows: [],
          refundable: [],
          baggage_options: [],
          fare_families: [],
          duration_buckets: [],
          layover_airports: [],
          price_range: { min: 0, max: 0 },
        },
        warnings: [],
      }),
    }),
  );

  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(`/booking/passengers?search_id=${searchId}&offer_id=offer-1&fare_option_key=fare-comfort`);
  await expect(page.getByTestId("flight-preview")).toContainText("ECONOMY COMFORT");
  await expect(page.getByTestId("terms-acceptance-checkbox")).not.toBeChecked();
  await expect(page.getByRole("button", { name: /Continue to review/i })).toBeDisabled();

  page.once("dialog", async (dialog) => {
    await dialog.accept();
  });
  await page.getByTestId("change-flight-button").click();
  await expect.poll(() => abandoned).toBe(true);
  await expect(page).toHaveURL(/\/flights\/results/);
});

test("wave-7 Fare Details shows Adult Child Infant qty table", async ({ page }) => {
  test.setTimeout(90_000);

  await page.route("**/laravel/api/public/content/csrf-token**", async (route) =>
    route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ token: "wave7-csrf" }) }),
  );

  const multipaxDetails = details(fares[1]) as ReturnType<typeof details> & {
    offer: ReturnType<typeof details>["offer"] & { fallback_details?: Record<string, unknown> };
  };
  multipaxDetails.offer.fallback_details = {
    fare_breakdown: {
      currency: "PKR",
      displayed_price: 134400,
      grand_total: 134400,
      passenger_pricing: [
        { passenger_type: "ADULT", passenger_count: 2, base_amount: 56000, tax_amount: 14000, total_amount: 70000, currency: "PKR" },
        { passenger_type: "CHILD", passenger_count: 3, base_amount: 42000, tax_amount: 8400, total_amount: 50400, currency: "PKR" },
        { passenger_type: "INFANT", passenger_count: 1, base_amount: 11200, tax_amount: 2800, total_amount: 14000, currency: "PKR" },
      ],
    },
  };
  multipaxDetails.offer.displayed_price = 134400;
  multipaxDetails.offer.final_customer_price = 134400;
  multipaxDetails.offer.price_display = "PKR 134,400";

  await page.route("**/laravel/flights/results/offer**", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(multipaxDetails) });
  });

  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(`/flights/fare-selection?search_id=${searchId}&offer_id=offer-1&fare_option_key=fare-comfort`);
  await expect(page.getByTestId("fare-family-details")).toBeVisible({ timeout: 20_000 });
  await expect(page.getByTestId("passenger-fare-breakdown")).toBeVisible();
  await expect(page.getByTestId("passenger-fare-breakdown")).toContainText("ADULT");
  await expect(page.getByTestId("passenger-fare-breakdown")).toContainText("CHILD");
  await expect(page.getByTestId("passenger-fare-breakdown")).toContainText("INFANT");
  await expect(page.getByTestId("passenger-fare-breakdown")).toContainText("2");
  await expect(page.getByTestId("passenger-fare-breakdown")).toContainText("3");
});
