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

const overviewPayload = {
  ok: true,
  metrics: {
    upcoming_trips: 1,
    pending_payment: 0,
    ticketing_pending: 0,
    confirmed_bookings: 1,
    total_bookings: 1,
    open_support_cases: 0,
    unread_notifications: 0,
  },
  notifications_available: false,
  recent_bookings: [],
  upcoming_booking: null,
  first_pending_payment_booking: null,
  quick_actions: [{ code: "search_flights", label: "Search flights", available: true, url: "/flights/search" }],
};

const bookingsPayload = {
  ok: true,
  filter: "all",
  allowed_filters: ["all", "pending_payment"],
  bookings: [
    {
      booking_reference: "BKG-1001",
      trip_type: "one_way",
      route: "LHE-KHI",
      departure_date: "2026-08-01",
      airline: "PK",
      passenger_count: 1,
      total: 45000,
      currency: "PKR",
      booking_status: { code: "confirmed", label: "Confirmed" },
      payment_status: { code: "paid", label: "Paid" },
      ticketing_status: { code: "issued", label: "issued" },
      booking_type: "standard",
      detail_url: "/customer/bookings/BKG-1001",
    },
  ],
  pagination: { current_page: 1, last_page: 1, per_page: 15, total: 1, from: 1, to: 1 },
};

test.describe("JP-FE-11 customer dashboard", () => {
  test.beforeEach(async ({ page }) => {
    await setSessionFixture(page, "customer");
    await page.route("**/laravel/customer?format=json", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(overviewPayload) });
    });
    await page.route("**/laravel/customer/bookings?format=json*", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(bookingsPayload) });
    });
  });

  test("customer dashboard shell and overview load", async ({ page }) => {
    await page.goto("/customer/dashboard");
    await expect(page.getByTestId("customer-dashboard-shell")).toBeVisible();
    await expect(page.getByTestId("customer-dashboard-overview")).toBeVisible();
    await expect(page.getByRole("heading", { name: /dashboard overview/i })).toBeVisible();
  });

  test("customer bookings list loads from Laravel JSON", async ({ page }) => {
    await page.goto("/customer/bookings");
    await expect(page.getByTestId("customer-bookings-list")).toBeVisible();
    await expect(page.getByText("BKG-1001")).toBeVisible();
  });

  test("agent is rejected from customer dashboard", async ({ page }) => {
    await setSessionFixture(page, "agent");
    await page.goto("/customer/dashboard");
    await expect(page).toHaveURL(/\/agent\/dashboard$/);
  });

  test("unauthenticated user is redirected to login", async ({ page }) => {
    await setSessionFixture(page, "anonymous");
    await page.goto("/customer/dashboard");
    await expect(page).toHaveURL(/\/login$/);
  });

  test("/customer redirects to dashboard", async ({ page }) => {
    await page.goto("/customer");
    await expect(page).toHaveURL(/\/customer\/dashboard$/);
  });
});
