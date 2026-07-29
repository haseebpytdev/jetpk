import { expect, test } from "@playwright/test";
import { sessionFixtureCookieName } from "../features/auth/server/session-fixture";

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:3002";

async function setSessionFixture(
  page: import("@playwright/test").Page,
  fixture: "customer" | "agent" | "anonymous",
) {
  await page.context().addCookies([
    {
      name: sessionFixtureCookieName,
      value: fixture,
      url: baseURL,
    },
  ]);
}

test.describe("JP-FE-04A customer portal route closure", () => {
  test("authenticated customer can open /customer/bookings placeholder", async ({ page }) => {
    await setSessionFixture(page, "customer");

    await page.goto("/customer/bookings");
    await expect(page.getByRole("heading", { name: /my bookings/i })).toBeVisible();
    await expect(page.getByText(/coming in a later phase/i)).toBeVisible();
  });

  test("unauthenticated user is sent to login from /customer/bookings", async ({ page }) => {
    await setSessionFixture(page, "anonymous");

    await page.goto("/customer/bookings");
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole("heading", { name: "Sign in" })).toBeVisible();
  });

  test("agent is not treated as customer on /customer/bookings", async ({ page }) => {
    await setSessionFixture(page, "agent");

    await page.goto("/customer/bookings");
    await expect(page).toHaveURL(/\/agent$/);
    await expect(page.getByRole("heading", { name: /agent portal/i })).toBeVisible();
  });

  test("/customer redirects to /customer/bookings without loop", async ({ page }) => {
    await setSessionFixture(page, "customer");

    await page.goto("/customer");
    await expect(page).toHaveURL(/\/customer\/bookings$/);
    await expect(page.getByRole("heading", { name: /my bookings/i })).toBeVisible();
  });

  test("customer login handoff destination /customer/bookings is owned", async ({ page }) => {
    await setSessionFixture(page, "customer");
    await page.route("**/laravel/api/public/content/csrf-token", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ csrf_token: "test-csrf-token" }),
      });
    });
    await page.route("**/laravel/login", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, redirect: "/customer/bookings" }),
      });
    });

    await page.goto("/login");
    await page.locator("#main-content").getByLabel(/email or username/i).fill("ayesha@example.com");
    await page.locator("#main-content").getByLabel(/^password/i).fill("SecretPass1");
    await page.getByRole("button", { name: /sign in/i }).click();

    await expect(page).toHaveURL(/\/customer\/bookings$/);
    await expect(page.getByRole("heading", { name: /my bookings/i })).toBeVisible();
  });
});
