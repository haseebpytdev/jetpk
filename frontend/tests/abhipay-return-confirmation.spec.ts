import { expect, test } from "@playwright/test";

function checkoutFixture(overrides: Record<string, unknown> = {}) {
  return {
    ok: true,
    booking_session: { id: "s1", status: "payment", server_time: new Date().toISOString(), progress: [] },
    booking_reference: "JP01DREF",
    booking_method: "pay_later",
    payment_method_code: "manual",
    booking_status: { code: "pending", label: "Pending", terminal: false },
    payment_status: { code: "not_started", label: "Unpaid", terminal: false },
    pricing: {
      currency: "PKR",
      base_fare: 100000,
      taxes: 20000,
      service_charges: 4999,
      total: 124999,
      formatted_total: "Rs. 124,999",
    },
    manual_payment: {
      amount_due: 124999,
      currency: "PKR",
      formatted_amount: "Rs. 124,999",
      instructions: ["Pay via bank transfer."],
      payment_status_label: "Unpaid",
      proof_upload_supported: true,
      payment_reference_supported: true,
    },
    card_payment: null,
    itinerary: {
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
    },
    passengers: [{ first_name: "Test", last_name: "User", passenger_type: "adult" }],
    contact: { email: "test@example.com", phone: "+923001234567", name: "Test User" },
    documents_portal: [],
    support: { support_url: "/support", lookup_url: "/lookup-booking" },
    ...overrides,
  };
}

test.describe("JP-FULLSTACK-01D AbhiPay return and confirmation", () => {
  test.beforeAll(async ({ request }) => {
    expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
  });

  test("payment return route forwards to status with reference", async ({ page }) => {
    await page.route("**/laravel/booking/payment/status**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          booking_reference: "JP01DREF",
          payment_status: { code: "pending", label: "Pending verification", terminal: false },
          booking_status: { code: "pending", label: "Pending", terminal: false },
          ticketing_status: { code: "not_started", label: "Not started", terminal: false },
          poll: { should_poll: true, interval_ms: 2000, max_attempts: 3 },
        }),
      });
    });

    await page.goto("/booking/payment/return?reference=txn-01d-test");
    await expect(page).toHaveURL(/\/booking\/payment\/status\?reference=txn-01d-test/);
    await expect(page.getByTestId("payment-status-page")).toBeVisible();
    await expect(page.getByTestId("payment-status-label")).toHaveText("Pending verification");
    await expect(page.getByTestId("payment-status-label")).not.toHaveText(/^Paid$/i);
  });

  test("payment status shows confirmation only when Laravel reports succeeded", async ({ page }) => {
    await page.route("**/laravel/booking/payment/status**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          booking_reference: "JP01DREF",
          payment_status: { code: "succeeded", label: "Paid", terminal: true },
          booking_status: { code: "pending", label: "Pending", terminal: false },
          ticketing_status: { code: "not_started", label: "Not started", terminal: false },
          confirmation_url: "/booking/confirmation",
          poll: { should_poll: false, interval_ms: 2000, max_attempts: 1 },
        }),
      });
    });

    await page.goto("/booking/payment/status?reference=txn-paid&paid=1");
    await expect(page.getByTestId("payment-status-label")).toHaveText("Paid");
    await expect(page.getByRole("link", { name: /continue to confirmation/i })).toHaveAttribute("href", "/booking/confirmation");
    await expect(page.getByTestId("pnr-value")).toHaveCount(0);
  });

  test("card payment rejects non-AbhiPay redirect URLs", async ({ page }) => {
    await page.route("**/laravel/api/public/content/csrf-token", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ csrf_token: "test-csrf" }),
        headers: { "set-cookie": "XSRF-TOKEN=test-csrf; Path=/" },
      });
    });

    await page.route("**/laravel/booking/checkout-state?**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(
          checkoutFixture({
            booking_reference: "JP01DREF",
            booking_method: "online_card",
            payment_method_code: "card",
            manual_payment: null,
            card_payment: {
              can_start: true,
              show_pay_button: true,
              payable_amount: 124999,
              currency: "PKR",
              start_endpoint: "/payments/abhipay/start/1",
              formatted_amount: "Rs. 124,999",
              payment_status_label: "Unpaid",
              ticketing_note: "Tickets after payment.",
            },
          }),
        ),
      });
    });

    await page.route("**/laravel/payments/abhipay/start/**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          redirect_url: "https://evil.example/phish",
        }),
      });
    });

    await page.goto("/booking/payment/card");
    await expect(page.getByTestId("card-payment-page")).toBeVisible();
    await page.getByTestId("card-pay-button").click();
    await expect(page.getByText(/redirect was rejected/i)).toBeVisible();
  });

  test("manual payment regression remains unpaid pending", async ({ page }) => {
    await page.route("**/laravel/api/public/content/csrf-token", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ csrf_token: "test-csrf" }),
        headers: { "set-cookie": "XSRF-TOKEN=test-csrf; Path=/" },
      });
    });

    await page.route("**/laravel/booking/checkout-state?**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(
          checkoutFixture({
            booking_reference: "JP01DMAN",
            booking_method: "pay_later",
            payment_method_code: "manual",
          }),
        ),
      });
    });

    await page.goto("/booking/payment/manual");
    await expect(page.getByTestId("manual-payment-page")).toBeVisible();
    await expect(page.getByTestId("manual-payment-page").getByText("Paid", { exact: true })).toHaveCount(0);
    await expect(page.getByTestId("manual-payment-page").getByText("Unpaid", { exact: true }).first()).toBeVisible();
  });
});
