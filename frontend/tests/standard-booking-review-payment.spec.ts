import { test, expect } from "@playwright/test";

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
