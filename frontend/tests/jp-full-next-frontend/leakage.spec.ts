import { expect, test } from "@playwright/test";

const LEAKAGE_PATTERNS = [/Parwaaz/i, /Master OTA/i, /YoursDomain/i, /YD Travel/i, /haseeb-master/i];

test.describe("JP-FULL-NEXT-FRONTEND-01B leakage", () => {
  const pages = ["/", "/login", "/register", "/support", "/flights/fare-selection", "/verify-email"];

  for (const path of pages) {
    test(`no branding leakage on ${path}`, async ({ page }) => {
      await page.goto(path, { waitUntil: "domcontentloaded" });
      const text = await page.locator("body").innerText();
      for (const pattern of LEAKAGE_PATTERNS) {
        expect(text).not.toMatch(pattern);
      }
    });
  }

  test("payment card page has no PAN/CVV fields", async ({ page }) => {
    await page.goto("/booking/payment/card", { waitUntil: "domcontentloaded" });
    await expect(page.locator('input[autocomplete="cc-number"]')).toHaveCount(0);
    await expect(page.locator('input[name*="cvv" i]')).toHaveCount(0);
    await expect(page.locator('input[name*="card" i][type="text"]')).toHaveCount(0);
  });

  test("/booking/seats does not exist", async ({ request }) => {
    const response = await request.get("/booking/seats");
    expect(response.status()).toBe(404);
  });

  test("/preview does not exist", async ({ request }) => {
    const response = await request.get("/preview");
    expect(response.status()).toBe(404);
  });
});
