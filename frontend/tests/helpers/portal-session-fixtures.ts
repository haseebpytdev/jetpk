import type { Page } from "@playwright/test";
import { sessionFixtureCookieName } from "@/features/auth/server/session-fixture";

export const portalFixtureBaseUrl = process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:3002";

export type PortalSessionFixture = "customer" | "agent" | "agent_staff" | "anonymous";

export async function setPortalSessionFixture(page: Page, fixture: PortalSessionFixture): Promise<void> {
  await page.context().addCookies([
    {
      name: sessionFixtureCookieName,
      value: fixture,
      url: portalFixtureBaseUrl,
    },
  ]);
}

export const customerOverviewPayload = {
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

export const customerBookingsPayload = {
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

export const agentOverviewPayload = {
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

export const agentBookingsPayload = {
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

export async function mockCustomerPortalApis(page: Page): Promise<void> {
  await page.route("**/laravel/customer?format=json", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(customerOverviewPayload) });
  });
  await page.route("**/laravel/customer/bookings?format=json*", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(customerBookingsPayload) });
  });
}

export async function mockAgentPortalApis(page: Page): Promise<void> {
  await page.route("**/laravel/agent?format=json", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(agentOverviewPayload) });
  });
  await page.route("**/laravel/agent/bookings?format=json*", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(agentBookingsPayload) });
  });
}

export async function setupCustomerPortalSession(page: Page): Promise<void> {
  await setPortalSessionFixture(page, "customer");
  await mockCustomerPortalApis(page);
}

export async function setupAgentPortalSession(page: Page): Promise<void> {
  await setPortalSessionFixture(page, "agent");
  await mockAgentPortalApis(page);
}
