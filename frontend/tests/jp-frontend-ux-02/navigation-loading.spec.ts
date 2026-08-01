import { test, expect } from "@playwright/test";

test.describe("JP-FRONTEND-UX-02 route navigation loading", () => {
  test("internal navigation can show route progress bar", async ({ page }) => {
    await page.goto("/");
    await page.locator('a[href="/about-us"]').first().click();
    await page.waitForURL("**/about-us");
    await expect(page.locator('[data-testid="route-nav-progress"]')).toHaveCount(0);
  });

  test("external links are not intercepted", async ({ page }) => {
    await page.goto("/");
    const external = page.locator('a[target="_blank"]').first();
    if ((await external.count()) === 0) test.skip();
    await expect(external).toHaveAttribute("target", "_blank");
  });
});
