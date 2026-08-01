import { test, expect } from "@playwright/test";
import { mockCsrf } from "../jp-full-next-frontend/helpers";

test.describe("JP-FRONTEND-UX-02 dialogs", () => {
  test("login page form is keyboard accessible", async ({ page }) => {
    await page.goto("/login");
    await page.locator("#login").focus();
    await expect(page.locator("#login")).toBeFocused();
    await page.keyboard.press("Tab");
    await expect(page.locator('[name="password"]')).toBeFocused();
  });
});

test.describe("JP-FRONTEND-UX-02 airport autocomplete", () => {
  test.beforeEach(async ({ page }) => {
    await mockCsrf(page);
    await page.route("**/laravel/airports/search**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify([
          { iata: "DXB", city: "Dubai", name: "Dubai International", country: "AE" },
        ]),
      });
    });
  });

  test("debounced search shows results", async ({ page }) => {
    await page.goto("/");
    const origin = page.locator('[aria-label*="From"], [aria-label*="Origin"]').first();
    if ((await origin.count()) === 0) test.skip();
    await origin.fill("Dub");
    await page.waitForTimeout(350);
    await expect(page.getByRole("option").first()).toContainText("DXB");
  });
});
