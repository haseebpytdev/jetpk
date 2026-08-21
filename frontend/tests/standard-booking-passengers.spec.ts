import { expect, test } from "@playwright/test";

const mockContext = {
  ok: true,
  booking_session: {
    id: "abc123session",
    status: "passenger_details",
    expires_at: new Date(Date.now() + 15 * 60 * 1000).toISOString(),
    server_time: new Date().toISOString(),
    next_url: null,
    previous_url: "/flights/results?from=LHE&to=DXB",
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
    search_id: "search-1",
    offer_id: "offer-1",
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
    airline_name: "TestAir",
    airline_code: "TA",
    airline_logo_url: null,
    flight_number: "TA 204",
    cabin: "economy",
    fare_family: "Economy Flex",
    stops: 0,
    duration: "3h 20m",
    baggage: "30kg checked",
    total_formatted: "114,999",
    currency: "PKR",
    segments: [
      {
        origin_airport_code: "LHE",
        destination_airport_code: "DXB",
        departure_time_display: "08:15",
        arrival_time_display: "10:35",
        flight_number: "TA 204",
      },
    ],
    return_segments: [],
  },
  travellers: {
    adults: 1,
    children: 1,
    infants: 0,
    total: 2,
    expected: [
      { index: 0, type: "adult", label: "Adult" },
      { index: 1, type: "child", label: "Child" },
    ],
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
  existing_values: { passengers: [{}, {}], contact: {} },
  checkout_summary: { currency: "PKR", passenger_counts: { adults: 1, children: 1, infants: 0, total: 2 } },
  seat_extras_capability: {
    seat_map_available: false,
    ancillaries_available: false,
    message: "Seat selection and optional extras will be shown when supported for this fare.",
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

async function mockCsrf(page: import("@playwright/test").Page) {
  await page.route("**/laravel/api/public/content/csrf-token", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ csrf_token: "test-csrf-token" }),
      headers: { "set-cookie": "XSRF-TOKEN=test-csrf-token; Path=/" },
    });
  });
}

test("passenger page loads with authoritative traveller counts", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await mockCsrf(page);
  await page.route("**/laravel/booking/passengers?**", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(mockContext) });
  });

  await page.goto("/booking/passengers?search_id=search-1&offer_id=offer-1&from=LHE&to=DXB&depart=2026-08-15&adults=1&children=1");

  await expect(page.getByTestId("standard-passengers-form")).toBeVisible();
  await expect(page.getByTestId("passenger-card-0")).toBeVisible();
  await expect(page.getByTestId("passenger-card-1")).toBeVisible();
  await expect(page.getByText("Lead passenger")).toBeVisible();
  await expect(page.getByTestId("seat-extras-readiness")).toBeVisible();
  await expect(page.getByTestId("flight-preview")).toContainText("Flight preview");
  await expect(page.getByTestId("flight-preview")).toContainText("TA 204");
  await expect(page.getByTestId("flight-preview")).toContainText("Economy Flex");
  await expect(page.getByTestId("flight-preview")).toContainText("Direct");
  await expect(page.getByTestId("flight-preview")).toContainText("3h 20m");
  await expect(page.getByTestId("flight-preview")).toContainText("30kg checked");
  await expect(page.getByTestId("order-summary-total")).toContainText("PKR");
  await expect(page.getByTestId("order-summary-total")).toContainText("114,999");
  await expect(page.getByTestId("flight-preview-route")).toContainText("08:15");
  await expect(page.getByTestId("flight-preview-route")).toContainText("LHE");
  await expect(page.getByTestId("document-reader-0")).toBeVisible();
  await expect(page.getByTestId("document-reader-0")).toContainText(
    "Please verify extracted details against the passport before continuing.",
  );
  await expect(page.getByRole("heading", { name: "Personal information" }).first()).toBeVisible();
  await expect(page.getByRole("heading", { name: "Travel document" }).first()).toBeVisible();
  expect(page.url()).not.toMatch(/passport/i);
  await page.screenshot({ path: "tmp/flight-results-travelers-refinement-desktop.png", fullPage: true });
});

test("document reader paste confirms MRZ without overwriting existing values silently", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 900 });
  await mockCsrf(page);
  await page.route("**/laravel/booking/passengers?**", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(mockContext) });
  });
  await page.goto("/booking/passengers?search_id=search-1&offer_id=offer-1&from=LHE&to=DXB&depart=2026-08-15&adults=1&children=1");

  await page.getByTestId("passenger-card-0").getByLabel(/First name/i).fill("TYPED");
  await page.getByTestId("passenger-card-0").getByLabel(/Last name/i).fill("PERSON");

  const { SYNTHETIC_VALID_MRZ_FUTURE_EXPIRY } = await import("../features/standard-booking/document-reader/mrz/fixtures");
  await page.getByTestId("document-reader-paste-0").fill(SYNTHETIC_VALID_MRZ_FUTURE_EXPIRY);
  await expect(page.getByTestId("document-reader-preview-0")).toBeVisible();
  await expect(page.getByTestId("document-reader-conflicts-0")).toBeVisible();

  const conflictRows = page.getByTestId("document-reader-conflicts-0").locator("[data-testid^='document-reader-conflict-0-']");
  const count = await conflictRows.count();
  for (let i = 0; i < count; i += 1) {
    await conflictRows.nth(i).getByRole("button", { name: "Keep" }).click();
  }
  await page.getByTestId("document-reader-confirm-0").click();
  await expect(page.getByTestId("passenger-card-0").getByLabel(/First name/i)).toHaveValue("TYPED");
  await expect(page.getByTestId("passenger-card-0").getByLabel(/Last name/i)).toHaveValue("PERSON");
  await expect(page.getByTestId("passenger-card-0").getByLabel(/Passport number/i)).not.toHaveValue("");
});

test("document reader confirms synthetic MRZ into empty passenger fields", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 900 });
  await mockCsrf(page);
  await page.route("**/laravel/booking/passengers?**", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(mockContext) });
  });
  await page.goto("/booking/passengers?search_id=search-1&offer_id=offer-1&from=LHE&to=DXB&depart=2026-08-15&adults=1&children=1");

  const { SYNTHETIC_VALID_MRZ_FUTURE_EXPIRY } = await import("../features/standard-booking/document-reader/mrz/fixtures");
  await page.getByTestId("document-reader-paste-0").fill(SYNTHETIC_VALID_MRZ_FUTURE_EXPIRY);
  await expect(page.getByTestId("document-reader-preview-0")).toBeVisible();
  await expect(page.getByTestId("document-reader-preview-0")).toContainText(
    "Please verify extracted details against the passport before continuing.",
  );
  await page.getByTestId("document-reader-confirm-0").click();
  await expect(page.getByTestId("passenger-card-0").getByLabel(/Last name/i)).toHaveValue("SAMPLE");
  await expect(page.getByTestId("passenger-card-0").getByLabel(/First name/i)).toHaveValue("TRAVELER");
  await expect(page.getByTestId("passenger-card-0").getByLabel(/Passport number/i)).toHaveValue("X1234567");
  await expect(page.getByTestId("passenger-card-0").getByLabel(/Passport issue date/i)).toHaveValue("");
});

test("booking progress shows connected stepper with canonical labels", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await mockCsrf(page);
  await page.route("**/laravel/booking/passengers?**", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(mockContext) });
  });

  await page.goto("/booking/passengers?search_id=search-1&offer_id=offer-1&from=LHE&to=DXB&depart=2026-08-15&adults=1&children=1");

  const progress = page.getByTestId("booking-progress");
  await expect(progress).toBeVisible();
  await expect(progress).toContainText("Results");
  await expect(progress).toContainText("Travelers");
  await expect(progress).toContainText("Review");
  await expect(progress).toContainText("Payment");
  await expect(progress.locator(".jp-progress-fill").first()).toBeVisible();
  await expect(progress.locator(".jp-progress-fill")).not.toHaveCount(0);
});

test("missing booking session shows error state", async ({ page }) => {
  await mockCsrf(page);
  await page.route("**/laravel/booking/passengers?**", async (route) => {
    await route.fulfill({
      status: 404,
      contentType: "application/json",
      body: JSON.stringify({ ok: false, status: "missing_session", message: "No session" }),
    });
  });

  await page.goto("/booking/passengers");
  await expect(page.getByTestId("missing-booking-session")).toBeVisible();
});

test("expired offer shows error state", async ({ page }) => {
  await mockCsrf(page);
  await page.route("**/laravel/booking/passengers?**", async (route) => {
    await route.fulfill({
      status: 410,
      contentType: "application/json",
      body: JSON.stringify({
        ok: false,
        status: "offer_expired",
        message: "Expired",
        redirect_url: "/flights/results",
      }),
    });
  });

  await page.goto("/booking/passengers?search_id=search-1&offer_id=offer-1");
  await expect(page.getByTestId("offer-expired")).toBeVisible();
});

test("successful submission navigates to allowlisted review url", async ({ page }) => {
  await mockCsrf(page);
  await page.route("**/laravel/booking/passengers**", async (route) => {
    if (route.request().method() === "GET") {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(mockContext) });
      return;
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ ok: true, status: "accepted", next_url: "/booking/review" }),
    });
  });
  await page.route("**/booking/review**", async (route) => {
    await route.fulfill({ status: 200, contentType: "text/html", body: "<html><body>Review</body></html>" });
  });

  await page.goto("/booking/passengers?search_id=search-1&offer_id=offer-1");
  await page.getByTestId("save-and-continue").click();
  await page.waitForURL(/\/booking\/review/, { timeout: 15000 });
});

test("no passport data in localStorage", async ({ page }) => {
  await mockCsrf(page);
  await page.route("**/laravel/booking/passengers?**", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(mockContext) });
  });

  await page.goto("/booking/passengers?search_id=search-1&offer_id=offer-1");
  const storage = await page.evaluate(() => JSON.stringify({ local: localStorage, session: sessionStorage }));
  expect(storage).not.toContain("passport");
});

test("Travelers form and flight preview remain responsive", async ({ page }) => {
  await mockCsrf(page);
  await page.route("**/laravel/booking/passengers?**", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(mockContext) });
  });
  await page.goto("/booking/passengers?search_id=search-1&offer_id=offer-1");

  for (const [width, height] of [[1440, 900], [1366, 768], [1024, 768], [768, 900], [390, 844], [320, 800]]) {
    await page.setViewportSize({ width, height });
    await expect(page.getByTestId("standard-passengers-form")).toBeVisible();
    const overflows = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflows, `${width}px Travelers viewport should not overflow`).toBe(false);
  }
});
