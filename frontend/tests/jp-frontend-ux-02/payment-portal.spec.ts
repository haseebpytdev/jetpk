import { test, expect } from "@playwright/test";
import { setSessionFixture } from "../jp-full-next-frontend/helpers";

test.describe("JP-FRONTEND-UX-02 payment safety", () => {
  test("payment status page does not mark paid from query param alone", async ({ page }) => {
    await page.route("**/laravel/booking/payment/status**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          booking_reference: "JP-TEST",
          payment_status: { code: "pending", label: "Pending verification" },
          booking_status: { code: "awaiting_payment", label: "Awaiting payment" },
          poll: { should_poll: false, interval_ms: 2000, max_attempts: 1 },
        }),
      });
    });

    await page.goto("/booking/payment/status?paid=1");
    await expect(page.getByTestId("payment-status-label")).toHaveText("Pending verification");
    await expect(page.getByTestId("payment-status-label")).not.toHaveText(/^Paid$/i);
  });
});

test.describe("JP-FRONTEND-UX-02 portal safety", () => {
  test("customer session fixture still required for dashboard", async ({ page }) => {
    await setSessionFixture(page, "customer");
    await page.goto("/customer/dashboard");
    await expect(page).not.toHaveURL(/access-denied/);
  });
});
