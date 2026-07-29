import { expect, test } from "@playwright/test";

const mockPackage = {
  id: 1,
  public_id: "ALH-TEST-1",
  title: "Test Package",
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
};

test.beforeEach(async ({ page }) => {
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
        cards: [mockPackage],
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
});

test("group search renders authoritative results", async ({ page }) => {
  await page.goto("/groups/search?sector=SKT-SHJ&date_from=2026-08-15");
  await expect(page.getByTestId("group-result-card")).toBeVisible();
  await expect(page.getByTestId("group-available-seats")).toHaveText("4 seats left");
  await expect(page.getByTestId("group-result-select")).toHaveText("PKR 99,000");
});

test("group search category All omits category param on navigation", async ({ page }) => {
  await page.goto("/groups/search");
  await page.getByTestId("group-sector-select").selectOption({ index: 1 });
  await page.getByLabel("Travel date").fill("2026-08-15");
  await page.getByRole("button", { name: "Search Group Fares" }).click();
  await page.waitForURL(/\/groups\/search\?/);
  expect(page.url()).toContain("sector=");
  expect(page.url()).toContain("date_from=");
  expect(page.url()).not.toContain("category=all");
});

test("manual payment page shows only manual methods", async ({ page }) => {
  await page.route("**/laravel/groups/booking/GRP-TEST/payment?**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        reference: "GRP-TEST",
        status: "payment_pending",
        status_label: "Payment pending",
        payment_status: "awaiting_payment",
        payment_status_label: "Awaiting payment",
        seat_count: 1,
        total_amount: 99000,
        total_formatted: "99,000",
        currency: "PKR",
        expires_at: new Date(Date.now() + 20 * 60 * 1000).toISOString(),
        server_time: new Date().toISOString(),
        hold_minutes: 25,
        is_expired: false,
        is_releasable: true,
        is_payment_window_open: true,
        contact: { name: "Ali", email: "ali@example.com", phone: "+923001234567" },
        passengers: [],
        inventory: mockPackage,
        checkout_summary: {},
        progress: [
          { key: "package", label: "Package Selected", state: "completed" },
          { key: "passengers", label: "Passenger Details", state: "completed" },
          { key: "review", label: "Review", state: "completed" },
          { key: "payment", label: "Manual Payment", state: "current" },
          { key: "confirmation", label: "Confirmation", state: "upcoming" },
        ],
        payment_methods: [
          { value: "bank_transfer", title: "Bank transfer", hint: "Transfer the total amount." },
          { value: "office", title: "Pay at office / consultant", hint: "Visit our office." },
          { value: "cash", title: "Cash deposit", hint: "Deposit cash at our office." },
        ],
        payment_proof_supported: true,
        payment_reference_required: true,
        instructions: ["Include your booking reference in the payment note."],
        support: { support_path: "/support" },
      }),
    });
  });

  await page.goto("/groups/booking/GRP-TEST/payment");
  await expect(page.getByRole("heading", { name: "Manual payment" })).toBeVisible();
  await expect(page.getByText("Bank transfer")).toBeVisible();
  await expect(page.getByText("AbhiPay")).toHaveCount(0);
  await expect(page.getByLabel("Card payment")).toHaveCount(0);
});

test("hold countdown derives from Laravel expires_at", async ({ page }) => {
  const expiresAt = new Date(Date.now() + 10 * 60 * 1000).toISOString();
  await page.route("**/laravel/groups/booking/GRP-TIMER/payment?**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        reference: "GRP-TIMER",
        status: "payment_pending",
        status_label: "Payment pending",
        payment_status: "awaiting_payment",
        payment_status_label: "Awaiting payment",
        seat_count: 1,
        total_amount: 99000,
        total_formatted: "99,000",
        currency: "PKR",
        expires_at: expiresAt,
        server_time: new Date().toISOString(),
        hold_minutes: 25,
        is_expired: false,
        is_releasable: true,
        is_payment_window_open: true,
        contact: {},
        passengers: [],
        inventory: mockPackage,
        checkout_summary: {},
        progress: [],
        payment_methods: [{ value: "bank_transfer", title: "Bank transfer", hint: "hint" }],
        payment_proof_supported: true,
        payment_reference_required: true,
        instructions: [],
        support: { support_path: "/support" },
      }),
    });
  });

  await page.goto("/groups/booking/GRP-TIMER/payment");
  const countdown = page.getByTestId("group-hold-countdown");
  await expect(countdown).toBeVisible();
  await expect(countdown).toContainText("09:");
});
