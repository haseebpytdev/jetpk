import { expect, test } from "@playwright/test";
import { setSessionFixture } from "./helpers";

const LEAKAGE_PATTERNS = [/Parwaaz/i, /Master OTA/i, /YoursDomain/i, /YD Travel/i, /haseeb-master/i];

async function assertNoLeakage(page: import("@playwright/test").Page) {
  const text = await page.locator("body").innerText();
  for (const pattern of LEAKAGE_PATTERNS) {
    expect(text).not.toMatch(pattern);
  }
}

test.describe("JP-FULL-NEXT-FRONTEND-01B leakage", () => {
  const pages = [
    "/",
    "/about-us",
    "/faq",
    "/contact",
    "/login",
    "/register",
    "/support",
    "/flights/fare-selection",
    "/verify-email",
    "/legal/terms",
    "/booking/confirmation",
    "/booking/payment/return",
  ];

  for (const path of pages) {
    test(`no branding leakage on ${path}`, async ({ page }) => {
      await page.goto(path, { waitUntil: "domcontentloaded" });
      await assertNoLeakage(page);
    });
  }

  test("payment card page has no PAN/CVV fields", async ({ page }) => {
    await page.goto("/booking/payment/card", { waitUntil: "domcontentloaded" });
    await expect(page.locator('input[autocomplete="cc-number"]')).toHaveCount(0);
    await expect(page.locator('input[name*="cvv" i]')).toHaveCount(0);
    await expect(page.locator('input[name*="card" i][type="text"]')).toHaveCount(0);
  });

  test("customer portal shell has no branding leakage", async ({ page }) => {
    await setSessionFixture(page, "customer");
    await page.goto("/customer/dashboard", { waitUntil: "domcontentloaded" });
    await assertNoLeakage(page);
  });

  test("agent portal shell has no branding leakage", async ({ page }) => {
    await setSessionFixture(page, "agent");
    await page.goto("/agent/dashboard", { waitUntil: "domcontentloaded" });
    await assertNoLeakage(page);
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
