import { expect, test } from "@playwright/test";
import { attachRuntimeGuards, FORBIDDEN_ROUTES, PRODUCTION_ROUTES, REDIRECT_ROUTES, setSessionFixture } from "./helpers";

test.describe("JP-FULL-NEXT-FRONTEND-01B route matrix", () => {
  test.beforeAll(async ({ request }) => {
    expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
  });

  for (const route of PRODUCTION_ROUTES) {
    test(`GET ${route.path} is not a server error`, async ({ page }) => {
      const guards = await attachRuntimeGuards(page);
      const response = await page.goto(route.path, { waitUntil: "domcontentloaded", timeout: 60_000 });
      const status = response?.status() ?? 0;
      const allowed = Array.isArray(route.expectStatus) ? route.expectStatus : [route.expectStatus];
      expect(allowed).toContain(status);
      expect(status).toBeLessThan(500);
      await guards.assertClean();
    });
  }

  for (const route of REDIRECT_ROUTES) {
    test(`${route.path} redirects to ${route.target}`, async ({ request }) => {
      const response = await request.get(route.path, { maxRedirects: 0 });
      expect([301, 302, 303, 307, 308]).toContain(response.status());
      const location = response.headers().location ?? "";
      expect(location).toMatch(new RegExp(route.target.replace("/", "\\/")));
    });
  }

  for (const path of FORBIDDEN_ROUTES) {
    test(`${path} does not exist`, async ({ request }) => {
      const response = await request.get(path, { maxRedirects: 0 });
      expect([404, 308, 307, 301, 302]).toContain(response.status());
    });
  }

  test("unauthenticated customer portal redirects safely", async ({ page }) => {
    await setSessionFixture(page, "anonymous");
    const response = await page.goto("/customer/dashboard", { waitUntil: "domcontentloaded" });
    expect(response?.status()).toBeLessThan(500);
    await expect(page).not.toHaveURL(/\/customer\/bookings\/[^/]+\/detail/);
  });

  test("unauthenticated agent portal redirects safely", async ({ page }) => {
    await setSessionFixture(page, "anonymous");
    const response = await page.goto("/agent/dashboard", { waitUntil: "domcontentloaded" });
    expect(response?.status()).toBeLessThan(500);
  });

  test("customer fixture can open dashboard", async ({ page }) => {
    await setSessionFixture(page, "customer");
    const response = await page.goto("/customer/dashboard", { waitUntil: "domcontentloaded" });
    expect(response?.status()).toBeLessThan(500);
    await expect(page.getByTestId("customer-dashboard-shell")).toBeVisible({ timeout: 30_000 });
  });

  test("agent fixture can open dashboard", async ({ page }) => {
    await setSessionFixture(page, "agent");
    const response = await page.goto("/agent/dashboard", { waitUntil: "domcontentloaded" });
    expect(response?.status()).toBeLessThan(500);
    await expect(page.getByTestId("agent-dashboard-shell")).toBeVisible({ timeout: 30_000 });
  });
});
