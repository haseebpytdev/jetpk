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
  capabilities: {
    ok: true,
    identity: {
      display_name: "Agency Owner",
      email: "agent@example.com",
      role: "agent",
      role_label: "Agency owner",
      is_owner: true,
    },
    agency: { name: "Alpha Travel" },
    permissions: {
      bookings_view: true,
      wallet_view: true,
      ledger_view: true,
      payments_upload: true,
      support_manage: true,
    },
    modules: {
      agent_wallet: true,
      agent_deposits: true,
      agent_ledger: true,
      agent_support: true,
    },
    navigation: [
      { code: "overview", label: "Overview", href: "/agent/dashboard", available: true },
      { code: "bookings", label: "Bookings", href: "/agent/bookings", available: true },
      { code: "wallet", label: "Wallet", href: "/agent/wallet", available: true },
      { code: "profile", label: "Profile", href: "/agent/profile", available: true },
    ],
  },
  metrics: {
    total_bookings: 2,
    pending_payment: 1,
    ticketing_pending: 0,
    confirmed_bookings: 1,
    upcoming_trips: 1,
    open_support_cases: 0,
    unread_notifications: 0,
    wallet_balance: 25000,
    available_balance: 75000,
    pending_deposits: 0,
  },
  notifications_available: false,
  wallet_summary: {
    balance: 25000,
    available_balance: 75000,
    pending_deposits: 0,
    credit_limit: 50000,
    credit_enabled: true,
    currency: "PKR",
  },
  recent_bookings: [],
  upcoming_booking: null,
  first_pending_payment_booking: null,
  quick_actions: [{ code: "view_bookings", label: "View bookings", available: true, url: "/agent/bookings" }],
};

const bookingsPayload = {
  ok: true,
  filter: "all",
  allowed_filters: ["all", "pending_payment"],
  bookings: [
    {
      booking_reference: "BKG-AGENT-1001",
      trip_type: "one_way",
      route: "LHE-DXB",
      departure_date: "2026-09-01",
      airline: "EK",
      passenger_count: 2,
      total: 185000,
      currency: "PKR",
      booking_status: { code: "confirmed", label: "Confirmed" },
      payment_status: { code: "paid", label: "Paid" },
      ticketing_status: { code: "issued", label: "Issued" },
      booking_type: "standard",
      detail_url: "/agent/bookings/BKG-AGENT-1001",
    },
  ],
  pagination: { current_page: 1, last_page: 1, per_page: 20, total: 1, from: 1, to: 1 },
};

test.describe("JP-FE-12 agent dashboard", () => {
  test.beforeEach(async ({ page }) => {
    await setSessionFixture(page, "agent");
    await page.route("**/laravel/agent?format=json", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(overviewPayload) });
    });
    await page.route("**/laravel/agent/bookings?format=json*", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(bookingsPayload) });
    });
  });

  test("agent dashboard shell and overview load", async ({ page }) => {
    await page.goto("/agent/dashboard");
    await expect(page.getByTestId("agent-dashboard-shell")).toBeVisible();
    await expect(page.getByTestId("agent-dashboard-overview")).toBeVisible();
    await expect(page.getByRole("heading", { name: /dashboard overview/i })).toBeVisible();
    await expect(page.getByTestId("agent-dashboard-shell").getByText("Agency owner", { exact: true })).toBeVisible();
  });

  test("agent bookings list loads from Laravel JSON", async ({ page }) => {
    await page.goto("/agent/bookings");
    await expect(page.getByTestId("agent-bookings-list")).toBeVisible();
    await expect(page.getByText("BKG-AGENT-1001")).toBeVisible();
  });

  test("customer is rejected from agent dashboard", async ({ page }) => {
    await setSessionFixture(page, "customer");
    await page.goto("/agent/dashboard");
    await expect(page).toHaveURL(/\/customer\/dashboard$/);
  });

  test("unauthenticated user is redirected to login", async ({ page }) => {
    await setSessionFixture(page, "anonymous");
    await page.goto("/agent/dashboard");
    await expect(page).toHaveURL(/\/login$/);
  });

  test("/agent redirects to dashboard", async ({ page }) => {
    await page.goto("/agent");
    await expect(page).toHaveURL(/\/agent\/dashboard$/);
  });
});
