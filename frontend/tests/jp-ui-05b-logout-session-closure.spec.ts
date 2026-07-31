import { expect, test } from "@playwright/test";
import { sessionFixtureCookieName } from "../features/auth/server/session-fixture";

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:3002";

const CUSTOMER_SESSION = {
  authenticated: true,
  user: {
    id: "fixture-customer-1",
    name: "Ayesha Khan",
    email: "ayesha.khan@example.com",
    account_type: "customer",
  },
  role: "customer",
  dashboard_url: "/customer/dashboard",
};

const AGENT_SESSION = {
  authenticated: true,
  user: {
    id: "fixture-agent-1",
    name: "Agency Owner",
    email: "agent@example.com",
    account_type: "agent",
  },
  role: "agent",
  dashboard_url: "/agent/dashboard",
};

const LOGGED_OUT_SESSION = { authenticated: false };

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

async function installSessionState(
  page: import("@playwright/test").Page,
  fixture: "customer" | "agent" | "anonymous",
) {
  const state = { fixture };

  await page.context().addCookies([
    {
      name: sessionFixtureCookieName,
      value: state.fixture === "anonymous" ? "anonymous" : state.fixture,
      url: baseURL,
    },
    { name: "XSRF-TOKEN", value: "test-csrf-token", url: baseURL },
  ]);

  await page.route("**/laravel/api/public/auth/session", async (route) => {
    const body =
      state.fixture === "customer"
        ? CUSTOMER_SESSION
        : state.fixture === "agent"
          ? AGENT_SESSION
          : LOGGED_OUT_SESSION;
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(body) });
  });

  await page.route("**/laravel/logout", async (route) => {
    if (route.request().method() !== "POST") {
      await route.continue();
      return;
    }
    state.fixture = "anonymous";
    await page.context().clearCookies();
    await page.context().addCookies([
      { name: sessionFixtureCookieName, value: "anonymous", url: baseURL },
    ]);
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ ok: true, redirect: "/login" }),
    });
  });

  return state;
}

const ownedBookingDetail = {
  ok: true,
  booking_reference: "BKG-1001",
  booking_status: { code: "confirmed", label: "Confirmed", terminal: false },
  payment_status: { code: "paid", label: "Paid", terminal: true },
  ticketing_status: { code: "ticketed", label: "Ticketed", terminal: true },
  pricing: { currency: "PKR", total: 45000, formatted_total: "Rs. 45,000", base_fare: 40000, taxes: 5000, service_charges: 0 },
  itinerary: {
    trip_type: "one_way",
    origin: "LHE",
    destination: "KHI",
    depart_date: "2026-08-01",
    route_label: "LHE → KHI",
    segments: [{ origin: "LHE", destination: "KHI", flight_number: "PK301" }],
    return_segments: [],
    airline_name: "PIA",
    cabin: "economy",
    total_formatted: "45,000",
    currency: "PKR",
  },
  passengers: [{ title: "Ms", first_name: "Ayesha", last_name: "Khan", passenger_type: "adult" }],
  presentation: { heading: "Booking confirmed", subtitle: "Confirmed", tone: "success", show_celebration: false },
  pnr_details: { booking_reference: "ABC123", airline_locator: null, available: true },
  tickets: [{ passenger_name: "Ayesha Khan", ticket_number: "1234567890" }],
  actions: [],
  contact: { name: "Ayesha Khan", email: "ayesha.khan@example.com", phone: "+923001234567", country: "PK" },
  documents_portal: [],
  support: { support_url: "/support", lookup_url: "/lookup-booking" },
  poll: { should_poll: false, interval_ms: 4000, max_attempts: 45 },
  cancellation: { eligible: false, request_pending: false, already_cancelled: false, message: "" },
  refund: { available: false, status: null, label: null },
};

const agentWalletPayload = {
  ok: true,
  summary: {
    balance: 25000,
    available_balance: 25000,
    pending_deposits: 0,
    credit_limit: 50000,
    credit_enabled: true,
    currency: "PKR",
    wallet_status: "active",
  },
  recent_ledger_entries: [],
  capabilities: { can_view_ledger: true, can_create_deposit: true },
  quick_actions: [],
};

test.describe("JP-UI-05B logout and stale-session closure", () => {
  test("customer logout clears private access and redirects safely", async ({ page }) => {
    await mockCsrf(page);
    await installSessionState(page, "customer");

    await page.route("**/laravel/customer/bookings/BKG-1001?format=json", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(ownedBookingDetail) });
    });

    await page.goto("/customer/bookings/BKG-1001");
    await expect(page.getByTestId("customer-booking-detail")).toBeVisible({ timeout: 30_000 });
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute("content", /noindex/i);
    await expect(page.getByText("ABC123")).toBeVisible();

    await page.goto("/");
    const logoutRequest = page.waitForRequest(
      (request) => request.url().includes("/laravel/logout") && request.method() === "POST",
    );
    await page.getByRole("button", { name: /ayesha khan/i }).click();
    await page.getByRole("menuitem", { name: /log out/i }).click();
    const request = await logoutRequest;
    expect(request.headers()["x-xsrf-token"]).toBe("test-csrf-token");
    await expect(page).toHaveURL(/\/login$/);

    await page.goto("/customer/bookings/BKG-1001");
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByTestId("customer-booking-detail")).toHaveCount(0);
    await expect(page.getByText("ABC123")).toHaveCount(0);
  });

  test("agent logout clears wallet access and redirects safely", async ({ page }) => {
    await mockCsrf(page);
    await installSessionState(page, "agent");

    await page.route("**/laravel/agent/wallet?format=json", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(agentWalletPayload) });
    });
    await page.route("**/laravel/agent?format=json", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
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
            permissions: { bookings_view: true, wallet_view: true, ledger_view: true },
            modules: { agent_wallet: true, agent_deposits: true, agent_ledger: true },
            navigation: [
              { code: "wallet", label: "Wallet", href: "/agent/wallet", available: true },
            ],
          },
          metrics: { wallet_balance: 25000, available_balance: 25000 },
          recent_bookings: [],
          quick_actions: [],
        }),
      });
    });

    await page.goto("/agent/wallet");
    await expect(page.getByTestId("agent-dashboard-shell")).toBeVisible({ timeout: 30_000 });
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute("content", /noindex/i);
    await expect(page.getByTestId("agent-wallet-overview")).toContainText(/25,000/);

    await page.goto("/");
    await page.getByRole("button", { name: /agency owner/i }).click();
    await page.getByRole("menuitem", { name: /log out/i }).click();
    await expect(page).toHaveURL(/\/login$/);

    await page.goto("/agent/wallet");
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByText(/25,000/)).toHaveCount(0);
  });

  test("browser back and reload do not restore actionable private content after logout", async ({ page }) => {
    await mockCsrf(page);
    await installSessionState(page, "customer");

    await page.route("**/laravel/customer/bookings/BKG-1001?format=json", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(ownedBookingDetail) });
    });
    await page.route("**/laravel/customer?format=json", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, metrics: {}, recent_bookings: [], quick_actions: [] }),
      });
    });

    await page.goto("/customer/bookings/BKG-1001");
    await expect(page.getByTestId("customer-booking-detail")).toBeVisible({ timeout: 30_000 });

    await page.goto("/");
    await page.getByRole("button", { name: /ayesha khan/i }).click();
    await page.getByRole("menuitem", { name: /log out/i }).click();
    await expect(page).toHaveURL(/\/login$/);

    await page.goBack();
    await page.goBack();
    const detailVisible = await page.getByTestId("customer-booking-detail").isVisible().catch(() => false);
    if (detailVisible) {
      await expect(page.getByRole("link", { name: /download/i })).toHaveCount(0);
      await page.reload();
      await expect(page).toHaveURL(/\/login$/);
    } else {
      await page.goto("/customer/bookings/BKG-1001").catch(() => null);
      await expect(page).toHaveURL(/\/login$/);
    }

    await expect(page.getByText("BKG-1001")).toHaveCount(0);
    await expect(page.getByText("ABC123")).toHaveCount(0);
  });

  test("profile menu logout is keyboard reachable with escape focus return", async ({ page }) => {
    await mockCsrf(page);
    await installSessionState(page, "customer");

    await page.goto("/");
    const trigger = page.getByRole("button", { name: /ayesha khan/i });
    await expect(trigger).toBeVisible();
    await trigger.focus();
    await page.keyboard.press("Enter");
    const logoutItem = page.getByRole("menuitem", { name: /log out/i });
    await expect(logoutItem).toBeVisible();
    await page.keyboard.press("Escape");
    await expect(logoutItem).toHaveCount(0);
    await expect(trigger).toBeFocused();
  });
});
