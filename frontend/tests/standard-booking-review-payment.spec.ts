import { expect, test } from "@playwright/test";

const PROGRESS_REVIEW = [
  { key: "flight_selected", label: "Flight Selected", state: "completed", href: null },
  { key: "passenger_details", label: "Passenger Details", state: "completed", href: null },
  { key: "seat_extras", label: "Seat & Extras", state: "skipped", href: null },
  { key: "review", label: "Review", state: "current", href: null },
  { key: "payment", label: "Payment", state: "upcoming", href: null },
  { key: "confirmation", label: "Confirmation", state: "upcoming", href: null },
];

const PROGRESS_PAYMENT = PROGRESS_REVIEW.map((step, index) => ({
  ...step,
  state: index < 4 ? "completed" : index === 4 ? "current" : "upcoming",
}));

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
  airline_name: "Test Air",
  cabin: "economy",
  total_formatted: "114,999",
  currency: "PKR",
  segments: [],
  return_segments: [],
};

function reviewContext(overrides: Record<string, unknown> = {}) {
  return {
    ok: true,
    booking_session: {
      id: "jp-fs01c-session",
      status: "review",
      expires_at: new Date(Date.now() + 15 * 60 * 1000).toISOString(),
      server_time: new Date().toISOString(),
      progress: PROGRESS_REVIEW,
    },
    itinerary: baseItinerary,
    passengers: [{ title: "Mr", first_name: "Manual", last_name: "Pay", passenger_type: "adult" }],
    contact: { name: "Manual Pay", email: "manual@example.com", phone: "+923001234567", country: "PK" },
    documents: [],
    pricing: basePricing,
    payment_methods: [
      {
        code: "manual",
        canonical: "pay_later",
        label: "Manual Payment",
        description: "Pay via bank transfer.",
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
    ...overrides,
  };
}

function checkoutState(overrides: Record<string, unknown> = {}) {
  return {
    ok: true,
    booking_session: {
      id: "jp-fs01c-session",
      status: "payment",
      server_time: new Date().toISOString(),
      progress: PROGRESS_PAYMENT,
    },
    booking_reference: "JPFS01C01",
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
    passengers: [{ title: "Mr", first_name: "Manual", last_name: "Pay", passenger_type: "adult" }],
    contact: { name: "Manual Pay", email: "manual@example.com", phone: "+923001234567", country: "PK" },
    documents_portal: [],
    support: { support_url: "/support", lookup_url: "/lookup-booking" },
    confirmation_handoff_url: "/booking/confirmation",
    ...overrides,
  };
}

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

test.describe("standard booking review and payment", () => {
  test("review page shows missing session without checkout cookie", async ({ page }) => {
    await page.goto("/booking/review");
    await expect(page.getByTestId("missing-booking-session")).toBeVisible({ timeout: 15000 });
  });

  test("payment manual page shows missing session without checkout", async ({ page }) => {
    await page.goto("/booking/payment/manual");
    await expect(page.getByTestId("missing-booking-session")).toBeVisible();
  });

  test("invoice page shows missing session without checkout", async ({ page }) => {
    await page.goto("/booking/invoice");
    await expect(page.getByTestId("missing-booking-session")).toBeVisible();
  });

  test("card payment page shows missing session without checkout", async ({ page }) => {
    await page.goto("/booking/payment/card");
    await expect(page.getByTestId("missing-booking-session")).toBeVisible();
  });
});

test.describe("JP-FULLSTACK-01C manual pay_later path", () => {
  test.beforeAll(async ({ request }) => {
    expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
  });

  test("review page loads manual payment option from Laravel JSON", async ({ page }) => {
    await mockCsrf(page);
    await page.route("**/laravel/booking/review?**", async (route) => {
      if (route.request().method() !== "GET") {
        await route.continue();
        return;
      }
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(reviewContext()),
      });
    });

    await page.goto("/booking/review");
    await expect(page.getByTestId("booking-review-page")).toBeVisible();
    await expect(page.getByTestId("payment-method-manual")).toBeVisible();
    await expect(page.getByTestId("order-summary-total").filter({ hasText: "124,999" })).toBeVisible();
    await expect(page.getByTestId("review-continue-button")).toContainText("manual payment");
    await expect(page.getByTestId("pnr-value")).toHaveCount(0);
  });

  test("pay_later submit posts authoritative booking_method and opens manual payment", async ({ page }) => {
    await mockCsrf(page);

    await page.route("**/laravel/booking/review?**", async (route) => {
      if (route.request().method() === "GET") {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify(reviewContext()),
        });
        return;
      }

      const body = route.request().postData() ?? "";
      expect(body).toContain("pay_later");
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          status: "accepted",
          booking_method: "pay_later",
          payment_method_code: "manual",
          next_url: "/booking/payment/manual",
        }),
      });
    });

    await page.route("**/laravel/booking/checkout-state?**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(checkoutState()),
      });
    });

    await page.goto("/booking/review");
    await page.getByTestId("payment-method-manual").click();
    await page.getByTestId("review-continue-button").click();
    await page.waitForURL(/\/booking\/payment\/manual/, { timeout: 15000 });

    await expect(page.getByTestId("manual-payment-page")).toBeVisible();
    await expect(page.getByTestId("manual-amount-due")).toContainText("124,999");
    await expect(page.getByText("Awaiting payment")).toBeVisible();
    await expect(page.getByText("Unpaid")).toBeVisible();
    await expect(page.getByText("Pending")).toBeVisible();
    await expect(page.getByTestId("pnr-value")).toHaveCount(0);
    await expect(page.getByTestId("ticket-number-row")).toHaveCount(0);
  });

  test("manual payment page does not fabricate paid or ticketed state", async ({ page }) => {
    await mockCsrf(page);
    await page.route("**/laravel/booking/checkout-state?**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(
          checkoutState({
            booking_status: { code: "pending", label: "Pending", terminal: false },
            payment_status: { code: "not_started", label: "Unpaid", terminal: false },
            manual_payment: {
              amount_due: 124999,
              currency: "PKR",
              formatted_amount: "Rs. 124,999",
              instructions: ["Use booking reference JPFS01C01 on transfer."],
              payment_status_label: "Unpaid",
            },
          }),
        ),
      });
    });

    await page.goto("/booking/payment/manual");
    await expect(page.getByTestId("manual-payment-page")).toBeVisible();
    await expect(page.getByTestId("manual-payment-page").getByText("Paid", { exact: true })).toHaveCount(0);
    await expect(page.getByTestId("manual-payment-page").getByText("Ticketed", { exact: true })).toHaveCount(0);
    await expect(page.getByTestId("manual-payment-page").getByText("Booking complete")).toHaveCount(0);
  });

  test("review duplicate submit is blocked while first request is in flight", async ({ page }) => {
    await mockCsrf(page);
    let submitCount = 0;

    await page.route("**/laravel/booking/review?**", async (route) => {
      if (route.request().method() === "GET") {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify(reviewContext()),
        });
        return;
      }

      submitCount += 1;
      await new Promise((resolve) => setTimeout(resolve, 800));
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          status: "accepted",
          payment_method_code: "manual",
          next_url: "/booking/payment/manual",
        }),
      });
    });

    await page.route("**/laravel/booking/checkout-state?**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(checkoutState()),
      });
    });

    await page.goto("/booking/review");
    const button = page.getByTestId("review-continue-button");
    await button.click();
    await expect(button).toContainText("Submitting");
    await button.click({ force: true });
    await page.waitForURL(/\/booking\/payment\/manual/, { timeout: 15000 });
    expect(submitCount).toBe(1);
  });
});
