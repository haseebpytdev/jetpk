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
  test.beforeEach(async ({ page }) => {
    await page.route("**/laravel/customer?format=json", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          metrics: {
            upcoming_trips: 0,
            pending_payment: 0,
            ticketing_pending: 0,
            confirmed_bookings: 0,
            total_bookings: 0,
            open_support_cases: 0,
            unread_notifications: 0,
          },
          notifications_available: false,
          recent_bookings: [],
          upcoming_booking: null,
          first_pending_payment_booking: null,
          quick_actions: [],
        }),
      });
    });
    await page.route("**/laravel/customer/bookings?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          filter: "all",
          allowed_filters: ["all"],
          bookings: [],
          pagination: { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null },
        }),
      });
    });
  });

  test("authenticated customer can open /customer/bookings", async ({ page }) => {
    await setSessionFixture(page, "customer");

    await page.goto("/customer/bookings");
    await expect(page.getByRole("heading", { name: /my bookings/i })).toBeVisible();
    await expect(page.getByTestId("customer-dashboard-shell")).toBeVisible();
  });

  test("unauthenticated user is sent to login from /customer/bookings", async ({ page }) => {
    await setSessionFixture(page, "anonymous");

    await page.goto("/customer/bookings");
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole("heading", { name: /log in to your account/i })).toBeVisible();
  });

  test("agent is not treated as customer on /customer/bookings", async ({ page }) => {
    await page.route("**/laravel/agent?format=json", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, metrics: {}, recent_bookings: [], quick_actions: [] }),
      });
    });
    await setSessionFixture(page, "agent");

    await page.goto("/customer/bookings");
    await expect(page).toHaveURL(/\/agent\/dashboard$/);
    await expect(page.getByTestId("agent-dashboard-shell")).toBeVisible();
  });

  test("/customer redirects to /customer/dashboard without loop", async ({ page }) => {
    await setSessionFixture(page, "customer");

    await page.goto("/customer");
    await expect(page).toHaveURL(/\/customer\/dashboard$/);
    await expect(page.getByRole("heading", { name: /dashboard overview/i })).toBeVisible();
  });

  test("customer login handoff destination /customer/bookings is owned", async ({ page }) => {
    await setSessionFixture(page, "anonymous");
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
    await page.getByRole("textbox", { name: /email or username/i }).fill("ayesha@example.com");
    await page.getByLabel(/^password/i).fill("SecretPass1");
    await page.getByRole("button", { name: /sign in/i }).click();
    await setSessionFixture(page, "customer");

    await expect(page).toHaveURL(/\/customer\/bookings$/);
    await expect(page.getByTestId("customer-dashboard-shell")).toBeVisible();
  });
});
