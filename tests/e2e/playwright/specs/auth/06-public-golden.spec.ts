import { test, expect } from "@playwright/test";

test.describe("public golden (isolated origin)", () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  const routes = ["/", "/login", "/register", "/forgot-password", "/groups"];

  for (const route of routes) {
    test(`${route} responds without server error`, async ({ page }) => {
      const response = await page.goto(route, { waitUntil: "domcontentloaded" });
      const status = response?.status() ?? 0;
      expect(status, `status for ${route}`).toBeLessThan(500);
      const body = await page.textContent("body");
      expect(body ?? "").not.toMatch(/Parwaaz Travels|YoursDomain|YD Travel/i);
    });
  }
});
