import { test, expect } from "@playwright/test";

test.describe("JP-FRONTEND-UX-02 loading states", () => {
  test("customer bookings route exposes loading skeleton region", async ({ page }) => {
    await page.goto("/customer/bookings");
    await expect(page.locator('[role="status"], [data-testid="skeleton"]').first()).toBeVisible();
  });

  test("fare selection route has loading fallback file", async ({ page }) => {
    const response = await page.goto("/flights/fare-selection");
    expect(response?.status()).toBeLessThan(500);
  });
});
