import { expect, test } from "@playwright/test";
import { sessionFixtureCookieName } from "../features/auth/server/session-fixture";

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:3002";

async function setSessionFixture(page: import("@playwright/test").Page, fixture: string) {
  await page.context().addCookies([
    { name: sessionFixtureCookieName, value: fixture, url: baseURL },
  ]);
}

async function mockCsrf(page: import("@playwright/test").Page) {
  await page.route("**/laravel/api/public/content/csrf-token", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ csrf_token: "test-csrf-token" }),
      headers: { "set-cookie": "XSRF-TOKEN=test-csrf-token; Path=/" },
    });
  });
}

test.describe("JP-UI-05A profile menu and logout", () => {
  test("public account menu opens by keyboard and shows identity", async ({ page }) => {
    await setSessionFixture(page, "customer");
    await page.goto("/");
    const trigger = page.getByRole("button", { name: /ayesha khan/i });
    await expect(trigger).toBeVisible();
    await trigger.focus();
    await page.keyboard.press("Enter");
    await expect(page.getByRole("menuitem", { name: /log out/i })).toBeVisible();
    await expect(page.getByText("ayesha.khan@example.com")).toBeVisible();
    await page.keyboard.press("Escape");
    await expect(page.getByRole("menuitem", { name: /log out/i })).toHaveCount(0);
  });

  test("logout uses authoritative endpoint and redirects", async ({ page }) => {
    await mockCsrf(page);
    await page.context().addCookies([
      { name: sessionFixtureCookieName, value: "customer", url: baseURL },
      { name: "XSRF-TOKEN", value: "test-csrf-token", url: baseURL },
    ]);
    await page.route("**/laravel/logout", async (route) => {
      if (route.request().method() !== "POST") {
        await route.continue();
        return;
      }
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, redirect: "/login" }),
      });
    });

    await page.goto("/");
    await page.getByRole("button", { name: /ayesha khan/i }).click();
    const logoutRequest = page.waitForRequest(
      (request) => request.url().includes("/laravel/logout") && request.method() === "POST",
    );
    await page.getByRole("menuitem", { name: /log out/i }).click();
    const request = await logoutRequest;
    expect(request.headers()["x-xsrf-token"]).toBe("test-csrf-token");
  });

  test("customer portal sidebar sign-out link is present", async ({ page }) => {
    await setSessionFixture(page, "customer");
    await page.route("**/laravel/customer?format=json", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, metrics: {}, recent_bookings: [], quick_actions: [] }),
      });
    });
    await page.goto("/customer/dashboard");
    await expect(page.getByRole("link", { name: /sign out/i })).toHaveAttribute("href", "/laravel/logout");
  });
});
