import { expect, test } from "@playwright/test";
import { sessionFixtureCookieName } from "../features/auth/server/session-fixture";

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:3002";

async function setSessionFixture(page: import("@playwright/test").Page, fixture: string) {
  await page.context().addCookies([
    { name: sessionFixtureCookieName, value: fixture, url: baseURL },
  ]);
}

const agentCapabilities = {
  ok: true,
  identity: { display_name: "Agency Owner", email: "agent@example.com", role: "agent", role_label: "Agency owner", is_owner: true },
  agency: { name: "Alpha Travel" },
  permissions: { bookings_view: true, wallet_view: true, ledger_view: true },
  modules: { agent_wallet: true, agent_deposits: true, agent_ledger: true },
  navigation: [
    { code: "wallet", label: "Wallet", href: "/agent/wallet", available: true },
    { code: "bookings", label: "Bookings", href: "/agent/bookings", available: true },
  ],
};

const agentStaffCapabilities = {
  ok: true,
  identity: { display_name: "Agency Staff", email: "staff@example.com", role: "agent_staff", role_label: "Agency staff", is_owner: false },
  agency: { name: "Alpha Travel" },
  permissions: { bookings_view: true, wallet_view: false, ledger_view: true },
  modules: { agent_wallet: false, agent_deposits: false, agent_ledger: true },
  navigation: [
    { code: "bookings", label: "Bookings", href: "/agent/bookings", available: true },
    { code: "ledger", label: "Ledger", href: "/agent/wallet/ledger", available: true },
  ],
};

const agentBookingDetail = {
  ok: true,
  booking_reference: "BKG-2001",
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
  passengers: [{ title: "Mr", first_name: "Agency", last_name: "Traveler", passenger_type: "adult" }],
  presentation: { heading: "Booking confirmed", subtitle: "Confirmed", tone: "success", show_celebration: false },
  pnr_details: { booking_reference: "ABC123", airline_locator: null, available: true },
  tickets: [{ passenger_name: "Agency Traveler", ticket_number: "1234567890" }],
  actions: [],
  contact: { name: "Agency", email: "agent@example.com", phone: "+923001234567", country: "PK" },
  documents_portal: [],
  support: { support_url: "/support", lookup_url: "/lookup-booking" },
  poll: { should_poll: false, interval_ms: 4000, max_attempts: 45 },
  cancellation: { eligible: false, request_pending: false, already_cancelled: false, message: "" },
  refund: { available: false, status: null, label: null },
};

test.describe("JP-UI-05A agent agency isolation and RBAC", () => {
  test("agent can access agency booking", async ({ page }) => {
    await setSessionFixture(page, "agent");
    await page.route("**/laravel/agent/bookings/BKG-2001?format=json", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(agentBookingDetail),
      });
    });
    await page.goto("/agent/bookings/BKG-2001");
    await expect(page.getByTestId("agent-dashboard-shell")).toBeVisible();
    await expect(page.getByTestId("agent-booking-detail")).toBeVisible();
    await expect(page.getByRole("heading", { name: /BKG-2001/i })).toBeVisible();
  });

  test("cross-agency booking returns safe not-found", async ({ page }) => {
    await setSessionFixture(page, "agent");
    await page.route("**/laravel/agent/bookings/BKG-OTHER?format=json", async (route) => {
      await route.fulfill({ status: 404, contentType: "application/json", body: JSON.stringify({ ok: false, message: "Booking not found." }) });
    });
    await page.goto("/agent/bookings/BKG-OTHER");
    await expect(page.getByText(/not found|do not have access/i)).toBeVisible();
  });

  test("agent staff owner wallet route is forbidden", async ({ page }) => {
    await setSessionFixture(page, "agent_staff");
    await page.route("**/laravel/agent?format=json", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, capabilities: agentStaffCapabilities, metrics: {}, recent_bookings: [], quick_actions: [] }),
      });
    });
    await page.route("**/laravel/agent/wallet?format=json", async (route) => {
      await route.fulfill({ status: 403, contentType: "application/json", body: JSON.stringify({ ok: false, message: "You do not have permission to view the wallet." }) });
    });
    await page.goto("/agent/wallet");
    await expect(page.getByTestId("agent-permission-denied")).toBeVisible();
  });

  test("agent staff navigation omits wallet link", async ({ page }) => {
    await setSessionFixture(page, "agent_staff");
    await page.route("**/laravel/agent?format=json", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, capabilities: agentStaffCapabilities, metrics: {}, recent_bookings: [], quick_actions: [] }),
      });
    });
    await page.goto("/agent/dashboard");
    await expect(page.getByTestId("agent-dashboard-shell")).toBeVisible();
    await expect(page.getByTestId("agent-dashboard-shell").getByRole("link", { name: /^wallet$/i })).toHaveCount(0);
  });

  test("agent private route is noindex", async ({ page }) => {
    await setSessionFixture(page, "agent");
    await page.route("**/laravel/agent?format=json", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, capabilities: agentCapabilities, metrics: {}, recent_bookings: [], quick_actions: [] }),
      });
    });
    await page.goto("/agent/dashboard");
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute("content", /noindex/i);
  });
});
