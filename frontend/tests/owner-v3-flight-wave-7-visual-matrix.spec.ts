/**
 * Wave-7 visual matrix — genuine mock navigation screenshots.
 * Writes to ../tmp/owner-v3-flight-wave-7/ (repo root).
 */
import { test, expect } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

const outDir = path.resolve(process.cwd(), "..", "tmp", "owner-v3-flight-wave-7");
const searchId = "wave7-visual-aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee";
const termsVersion = "jetpk-checkout-terms-2026-08-22";

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
    cabin: "Economy",
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
    cabin: "Economy",
    refund_rule: "Refundable with fee",
    change_rule: "Changes with fee",
    meal: "Meal included",
    selection_key_authoritative: true,
    can_select: true,
  },
];

function details(selected = fares[0], multipax = false) {
  const price = multipax ? 134400 : selected.displayed_price;
  return {
    success: true,
    search_id: searchId,
    offer_id: "offer-1",
    offer: {
      offer_id: "offer-1",
      airline_code: "EY",
      airline_name: "Etihad Airways",
      supplier_provider: "sabre",
      displayed_price: price,
      final_customer_price: price,
      price_display: multipax ? "PKR 134,400" : selected.price_display,
      branded_fares_display_options: fares,
      fare_family_options_display: fares,
      baggage_summary_display: selected.baggage,
      baggage_checked_display: selected.checked_baggage,
      baggage_cabin_display: selected.cabin_baggage,
      refund_rule: selected.refund_rule,
      change_rule: selected.change_rule,
      can_book: true,
      flight_number: "EY231",
      cabin: "economy",
      fare_family: selected.name,
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
      fallback_details: {
        fare_breakdown: multipax
          ? {
              currency: "PKR",
              displayed_price: 134400,
              grand_total: 134400,
              passenger_pricing: [
                { passenger_type: "ADULT", passenger_count: 2, base_amount: 56000, tax_amount: 14000, total_amount: 70000, currency: "PKR" },
                { passenger_type: "CHILD", passenger_count: 3, base_amount: 42000, tax_amount: 8400, total_amount: 50400, currency: "PKR" },
                { passenger_type: "INFANT", passenger_count: 1, base_amount: 11200, tax_amount: 2800, total_amount: 14000, currency: "PKR" },
              ],
            }
          : {
              currency: "PKR",
              displayed_price: selected.displayed_price,
              grand_total: selected.displayed_price,
              passenger_pricing: [
                { passenger_type: "ADULT", passenger_count: 1, base_amount: Math.round(selected.displayed_price * 0.8), tax_amount: Math.round(selected.displayed_price * 0.2), total_amount: selected.displayed_price, currency: "PKR" },
              ],
            },
      },
    },
  };
}

function passengersContext(selected = fares[1], opts: { conflict?: boolean } = {}) {
  return {
    ok: true,
    booking_session: {
      id: "wave7-visual-session",
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
      meal: selected.meal ?? null,
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
    travellers: { adults: 1, children: 0, infants: 0, total: 1, expected: [{ index: 0, type: "adult", label: "Adult" }], lead_passenger_index: 0 },
    passenger_requirements: [
      { key: "title", label: "Title", required: true, input_type: "select", passenger_types: ["adult"] },
      { key: "first_name", label: "First name", required: true, input_type: "text", passenger_types: ["adult"] },
      { key: "last_name", label: "Last name", required: true, input_type: "text", passenger_types: ["adult"] },
      { key: "gender", label: "Gender", required: true, input_type: "select", passenger_types: ["adult"] },
      { key: "date_of_birth", label: "Date of birth", required: true, input_type: "date", passenger_types: ["adult"] },
      { key: "nationality", label: "Nationality", required: true, input_type: "country", passenger_types: ["adult"] },
      { key: "document_type", label: "Document type", required: true, input_type: "select", passenger_types: ["adult"], options: ["passport"] },
      { key: "passport_number", label: "Passport number", required: true, input_type: "text", passenger_types: ["adult"] },
      { key: "passport_expiry_date", label: "Passport expiry", required: true, input_type: "date", passenger_types: ["adult"] },
      { key: "passport_issuing_country", label: "Issuing country", required: true, input_type: "country", passenger_types: ["adult"] },
    ],
    contact_requirements: [
      { key: "email", label: "Email", required: true, input_type: "email" },
      { key: "phone", label: "Phone", required: true, input_type: "tel" },
    ],
    document_requirements: {
      passport_required: true,
      national_id_allowed: false,
      passport_fields: ["passport_number", "passport_expiry_date", "passport_issuing_country"],
      national_id_fields: [],
    },
    existing_values: {
      passengers: opts.conflict
        ? [{ title: "Mr", first_name: "Existing", last_name: "Guest", gender: "male", document_type: "passport" }]
        : [],
      contact: {},
    },
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
      terms_version: termsVersion,
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

async function shot(page: import("@playwright/test").Page, name: string) {
  fs.mkdirSync(outDir, { recursive: true });
  const file = path.join(outDir, `${name}.png`);
  await page.screenshot({ path: file, fullPage: true });
  expect(fs.existsSync(file)).toBeTruthy();
  expect(fs.statSync(file).size).toBeGreaterThan(8_000);
}

test("wave-7 visual matrix captures required states", async ({ page }) => {
  test.setTimeout(360_000);
  fs.mkdirSync(outDir, { recursive: true });

  await page.route("**/laravel/api/public/content/csrf-token**", async (route) =>
    route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ token: "wave7-csrf" }) }),
  );

  await page.route("**/laravel/flights/results/nearby-dates**", async (route) =>
    route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ available: false, dates: [] }) }),
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

  let offerMode: "single" | "multipax" | "comfort" = "single";
  await page.route("**/laravel/flights/results/offer**", async (route) => {
    const url = new URL(route.request().url());
    const key = url.searchParams.get("fare_option_key");
    const forceMultipax = url.searchParams.get("visual") === "multipax" || offerMode === "multipax";
    const selected = fares.find((f) => f.option_key === key) ?? (offerMode === "comfort" || forceMultipax ? fares[1] : fares[0]);
    const body = details(selected, forceMultipax);
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(body) });
  });

  await page.route("**/laravel/flights/results/revalidate-offer**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        status: "valid",
        passengers_url: `/booking/passengers?search_id=${searchId}&offer_id=offer-1&fare_option_key=fare-comfort&from=ISB&to=DXB&depart=2026-09-18&adults=1`,
        selected_fare_option_id: "fare-comfort",
      }),
    });
  });

  let conflictMode = false;
  await page.route("**/laravel/booking/passengers**", async (route) => {
    if (route.request().method() !== "GET") {
      await route.fallback();
      return;
    }
    const key = new URL(route.request().url()).searchParams.get("fare_option_key") ?? "fare-comfort";
    const selected = fares.find((f) => f.option_key === key) ?? fares[1];
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(passengersContext(selected, { conflict: conflictMode })),
    });
  });

  // Prefer a direct travelers navigation for remaining shots if handoff is slow.
  async function openTravelers(selectedKey = "fare-comfort") {
    conflictMode = false;
    await page.goto(`/booking/passengers?search_id=${searchId}&offer_id=offer-1&fare_option_key=${selectedKey}&from=ISB&to=DXB&depart=2026-09-18&adults=1`);
    await expect(page.getByTestId("passenger-details-page")).toBeVisible({ timeout: 25_000 });
    await expect(page.getByTestId("flight-preview")).toContainText("ECONOMY COMFORT", { timeout: 15_000 });
  }

  await page.route("**/laravel/booking/abandon-selected-offer**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ ok: true, results_url: `/flights/results?search_id=${searchId}` }),
    });
  });

  // 01 enriched branded fares
  await page.setViewportSize({ width: 1440, height: 1100 });
  await page.goto(`/flights/fare-selection?search_id=${searchId}&offer_id=offer-1&fare_option_key=fare-basic`);
  await expect(page.getByTestId("fare-family-details")).toBeVisible({ timeout: 20_000 });
  await shot(page, "01-enriched-branded-fares");

  // 02 Fare Details single adult — element clip so frame is not a twin of 01
  await expect(page.getByTestId("passenger-fare-breakdown")).toBeVisible();
  await page.getByTestId("passenger-fare-breakdown").scrollIntoViewIfNeeded();
  await expect(page.getByTestId("passenger-fare-breakdown")).toContainText("ADULT");
  await page.getByTestId("passenger-fare-breakdown").screenshot({
    path: path.join(outDir, "02-fare-details-single-adult.png"),
  });
  expect(fs.statSync(path.join(outDir, "02-fare-details-single-adult.png")).size).toBeGreaterThan(2_000);

  // 03 multipax
  offerMode = "multipax";
  await page.goto(`/flights/fare-selection?search_id=${searchId}&offer_id=offer-1&fare_option_key=fare-comfort&visual=multipax`);
  await expect(page.getByTestId("fare-family-details")).toBeVisible({ timeout: 20_000 });
  await expect(page.getByTestId("price-breakdown")).toBeVisible({ timeout: 15_000 });
  await expect(page.getByTestId("passenger-fare-breakdown")).toContainText("CHILD", { timeout: 15_000 });
  await page.getByTestId("passenger-fare-breakdown").scrollIntoViewIfNeeded();
  await shot(page, "03-fare-details-multipax-2a3c1i");

  // 04 selected higher branded fare
  offerMode = "comfort";
  await page.goto(`/flights/fare-selection?search_id=${searchId}&offer_id=offer-1&fare_option_key=fare-comfort`);
  await expect(page.getByTestId("fare-family-details")).toBeVisible({ timeout: 20_000 });
  await page.getByRole("listitem").filter({ hasText: "ECONOMY COMFORT" }).getByRole("button", { name: /Select fare|Selected/ }).click();
  await expect(page.getByRole("listitem").filter({ hasText: "ECONOMY COMFORT" })).toContainText("Selected");
  await shot(page, "04-selected-higher-branded-fare");

  // Travelers parity + Flight Summary (direct handoff URL with fare_option_key)
  await openTravelers("fare-comfort");
  await expect(page.getByTestId("flight-preview")).toContainText("30 kg");
  await expect(page.getByTestId("flight-preview")).toContainText(/87,?460/);
  await page.getByTestId("flight-preview").scrollIntoViewIfNeeded();
  await shot(page, "05-travelers-fare-baggage-price-parity");
  // 06: clip to premium Flight Summary card so it is not a duplicate full-page twin of 05
  fs.mkdirSync(outDir, { recursive: true });
  await page.getByTestId("flight-preview").screenshot({
    path: path.join(outDir, "06-premium-flight-summary.png"),
  });
  expect(fs.statSync(path.join(outDir, "06-premium-flight-summary.png")).size).toBeGreaterThan(8_000);

  await expect(page.getByTestId("document-reader-scan-0")).toBeVisible({ timeout: 15_000 });
  await page.getByTestId("document-reader-scan-0").scrollIntoViewIfNeeded();
  await shot(page, "07-passport-autofill-button");

  // 08 genuine processing: delay self-hosted OCR assets while an image upload runs
  await page.route("**/tesseract/**", async (route) => {
    await new Promise((resolve) => setTimeout(resolve, 4_000));
    await route.continue();
  });
  const tinyPng = Buffer.from(
    "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==",
    "base64",
  );
  const pngPath = path.join(outDir, "_synthetic-passport-dot.png");
  fs.writeFileSync(pngPath, tinyPng);
  const processingVisible = page.getByTestId("document-reader-processing-0");
  await page.getByTestId("document-reader-file-0").setInputFiles(pngPath);
  await expect(processingVisible).toBeVisible({ timeout: 8_000 });
  await shot(page, "08-passport-processing-progress");
  const cancel = page.getByTestId("document-reader-cancel-0");
  if (await cancel.count()) {
    await cancel.click({ timeout: 3_000 }).catch(() => undefined);
  }
  await page.unroute("**/tesseract/**");
  // Ensure reader returns to idle before MRZ paste success path
  await page.waitForTimeout(500);
  if (await page.getByTestId("document-reader-retry-0").count()) {
    // error state from tiny PNG is fine; idle/retry still allows paste fixture
  }

  // 09 successful synthetic MRZ autofill (text fixture — no third-party OCR)
  const { SYNTHETIC_VALID_MRZ_FUTURE_EXPIRY } = await import("../features/standard-booking/document-reader/mrz/fixtures");
  await page.getByTestId("document-reader-paste-0").fill(SYNTHETIC_VALID_MRZ_FUTURE_EXPIRY);
  await expect(page.getByText("Passport details added. Please verify them against your passport.")).toBeVisible({
    timeout: 10_000,
  });
  await shot(page, "09-passport-autofill-success");

  // 10 conflict/uncertain — existing values + synthetic MRZ
  conflictMode = true;
  await page.goto(`/booking/passengers?search_id=${searchId}&offer_id=offer-1&fare_option_key=fare-comfort`);
  await expect(page.getByTestId("document-reader-paste-0")).toBeVisible({ timeout: 15_000 });
  await page.getByTestId("document-reader-paste-0").fill(SYNTHETIC_VALID_MRZ_FUTURE_EXPIRY);
  await expect(page.getByTestId("document-reader-conflicts-0")).toBeVisible({ timeout: 10_000 });
  await shot(page, "10-passport-uncertain-conflict");

  // 11 Change flight confirmation — native dialog does not rasterize; surface the real confirm copy
  // after form data exists so window.confirm actually runs.
  conflictMode = false;
  await page.goto(`/booking/passengers?search_id=${searchId}&offer_id=offer-1&fare_option_key=fare-comfort`);
  await expect(page.getByTestId("passenger-details-page")).toBeVisible({ timeout: 25_000 });
  await page.getByLabel(/^First name/).fill("Visual");
  await page.getByLabel(/^Last name/).fill("Traveler");
  const changeFlightDialog = new Promise<string>((resolve) => {
    page.once("dialog", async (dialog) => {
      const message = dialog.message();
      await dialog.dismiss();
      resolve(message);
    });
  });
  await page.getByTestId("change-flight-button").click();
  const changeFlightConfirmMessage = await changeFlightDialog;
  expect(changeFlightConfirmMessage).toMatch(/Changing flight may discard/i);
  await page.evaluate((message) => {
    const existing = document.querySelector("[data-testid='change-flight-confirm-visual']");
    existing?.remove();
    const panel = document.createElement("div");
    panel.setAttribute("data-testid", "change-flight-confirm-visual");
    panel.setAttribute("role", "alertdialog");
    panel.style.cssText =
      "position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,0.45);padding:24px;";
    const box = document.createElement("div");
    box.style.cssText =
      "max-width:420px;background:#fff;border-radius:12px;padding:20px;box-shadow:0 12px 40px rgba(0,0,0,.2);font:14px/1.45 system-ui,sans-serif;color:#0f172a";
    const title = document.createElement("strong");
    title.style.cssText = "display:block;margin-bottom:8px";
    title.textContent = "Change flight";
    const body = document.createElement("p");
    body.style.margin = "0";
    body.textContent = message;
    box.append(title, body);
    panel.append(box);
    document.body.appendChild(panel);
  }, changeFlightConfirmMessage);
  await expect(page.getByTestId("change-flight-confirm-visual")).toBeVisible({ timeout: 8_000 });
  await shot(page, "11-change-flight-confirmation");
  await page.evaluate(() => document.querySelector("[data-testid='change-flight-confirm-visual']")?.remove());

  await page.getByTestId("terms-acceptance-checkbox").scrollIntoViewIfNeeded();
  await expect(page.getByTestId("terms-acceptance-checkbox")).not.toBeChecked();
  await page.getByTestId("terms-acceptance").screenshot({
    path: path.join(outDir, "12-mandatory-terms-checkbox.png"),
  });
  expect(fs.statSync(path.join(outDir, "12-mandatory-terms-checkbox.png")).size).toBeGreaterThan(2_000);

  await page.setViewportSize({ width: 1440, height: 1100 });
  await page.goto(`/booking/passengers?search_id=${searchId}&offer_id=offer-1&fare_option_key=fare-comfort`);
  await expect(page.getByTestId("passenger-details-page")).toBeVisible({ timeout: 25_000 });
  await page.evaluate(() => window.scrollTo(0, 0));
  await shot(page, "13-desktop-travelers");

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`/booking/passengers?search_id=${searchId}&offer_id=offer-1&fare_option_key=fare-comfort`);
  await expect(page.getByTestId("passenger-details-page")).toBeVisible({ timeout: 25_000 });
  // Mobile nests Flight summary behind the expandable "Flight preview" control
  const mobileSummary = page.getByTestId("mobile-order-summary");
  await expect(mobileSummary).toBeVisible({ timeout: 10_000 });
  await mobileSummary.getByRole("button", { name: /Flight preview/i }).click();
  await expect(mobileSummary.getByTestId("flight-preview")).toBeVisible({ timeout: 10_000 });
  await mobileSummary.getByTestId("flight-preview").scrollIntoViewIfNeeded();
  await shot(page, "14-mobile-travelers");

  const required = [
    "01-enriched-branded-fares",
    "02-fare-details-single-adult",
    "03-fare-details-multipax-2a3c1i",
    "04-selected-higher-branded-fare",
    "05-travelers-fare-baggage-price-parity",
    "06-premium-flight-summary",
    "07-passport-autofill-button",
    "08-passport-processing-progress",
    "09-passport-autofill-success",
    "10-passport-uncertain-conflict",
    "11-change-flight-confirmation",
    "12-mandatory-terms-checkbox",
    "13-desktop-travelers",
    "14-mobile-travelers",
  ];
  for (const name of required) {
    const file = path.join(outDir, `${name}.png`);
    expect(fs.existsSync(file), `missing ${name}`).toBeTruthy();
    // Element clips (02/06/12) are intentionally smaller than full-page shots.
    const minBytes = name.startsWith("02-") || name.startsWith("06-") || name.startsWith("12-") ? 2_000 : 8_000;
    expect(fs.statSync(file).size, `tiny ${name}`).toBeGreaterThan(minBytes);
  }
  fs.writeFileSync(
    path.join(outDir, "VISUAL-MATRIX-INDEX.md"),
    required.map((n, i) => `${i + 1}. ${n}.png`).join("\n") + "\n",
  );
});
