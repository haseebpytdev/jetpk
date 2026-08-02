import { expect, test, type Page } from "@playwright/test";
import { sessionFixtureCookieName } from "../features/auth/server/session-fixture";

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:3002";

async function setSessionFixture(
  page: Page,
  fixture: "agent" | "agent_staff" | "expired" | "anonymous",
) {
  await page.context().addCookies([
    { name: sessionFixtureCookieName, value: fixture, url: baseURL },
    { name: "XSRF-TOKEN", value: "test-csrf-token", url: baseURL },
  ]);
}

function status(code: string, label: string, terminal = false) {
  return { code, label, terminal };
}

const walletSummary = {
  balance: 25000,
  available_balance: 24000,
  pending_deposits: 1000,
  credit_limit: 50000,
  credit_enabled: true,
  currency: "PKR",
  wallet_status: "active",
  last_updated: "2026-08-01T12:00:00Z",
};

const bookingListItem = {
  booking_reference: "BKG-OPS04",
  trip_type: "one_way",
  route: "LHE-DXB",
  departure_date: "2026-09-01",
  airline: "EK",
  passenger_count: 2,
  total: 45000,
  currency: "PKR",
  booking_status: status("confirmed", "Confirmed"),
  payment_status: status("paid", "Paid"),
  ticketing_status: status("issued", "Issued"),
  booking_type: "standard" as const,
  detail_url: "/agent/bookings/BKG-OPS04",
};

const capabilitiesOwner = {
  ok: true,
  session_usable: true,
  identity: {
    display_name: "Agency Owner",
    email: "agent@example.com",
    role: "agent" as const,
    role_label: "Agency owner",
    is_owner: true,
  },
  agency: { name: "Alpha Travel", status: "active" },
  permissions: {
    bookings_view: true,
    bookings_create: true,
    wallet_view: true,
    reports_view: true,
    staff_manage: true,
    agency_view: true,
    support_manage: true,
  },
  modules: {
    agent_wallet: true,
    agent_deposits: true,
    agent_reports: true,
    agent_staff: true,
    agent_support: true,
  },
  capabilities: {
    can_manage_staff: true,
    can_view_reports: true,
    can_submit_deposit: true,
    can_contact_support: true,
  },
  navigation: [
    { code: "overview", label: "Overview", href: "/agent/dashboard", available: true },
    { code: "bookings", label: "Bookings", href: "/agent/bookings", available: true },
    { code: "staff", label: "Staff", href: "/agent/staff", available: true },
    { code: "reports", label: "Reports", href: "/agent/reports", available: true },
    { code: "commissions", label: "Commissions", href: "/agent/commissions", available: true },
    { code: "booking_create", label: "New booking", href: "/agent/bookings/create", available: true },
    { code: "wallet", label: "Wallet", href: "/agent/wallet", available: true },
    { code: "support", label: "Support", href: "/agent/support", available: true },
  ],
};

const capabilitiesStaffRestricted = {
  ...capabilitiesOwner,
  identity: {
    display_name: "Staff User",
    email: "staff@example.com",
    role: "agent_staff" as const,
    role_label: "Agent staff",
    is_owner: false,
  },
  permissions: {
    bookings_view: true,
    bookings_create: false,
    wallet_view: false,
    reports_view: false,
    staff_manage: false,
    agency_view: false,
    support_manage: false,
  },
  capabilities: {
    can_manage_staff: false,
    can_view_reports: false,
    can_submit_deposit: false,
    can_contact_support: false,
  },
  navigation: [
    { code: "overview", label: "Overview", href: "/agent/dashboard", available: true },
    { code: "bookings", label: "Bookings", href: "/agent/bookings", available: true },
  ],
};

function buildOverviewPayload(caps: typeof capabilitiesOwner | typeof capabilitiesStaffRestricted = capabilitiesOwner) {
  return {
    ok: true,
    capabilities: caps,
    metrics: {
      total_bookings: 2,
      pending_payment: 1,
      ticketing_pending: 0,
      confirmed_bookings: 1,
      upcoming_trips: 1,
      open_support_cases: 0,
      unread_notifications: 0,
      commission_pending: caps.identity.is_owner ? 1200 : undefined,
      wallet_balance: caps.permissions.wallet_view ? walletSummary.balance : undefined,
      available_balance: caps.permissions.wallet_view ? walletSummary.available_balance : undefined,
      pending_deposits: caps.permissions.wallet_view ? walletSummary.pending_deposits : undefined,
    },
    notifications_available: false,
    wallet_summary: caps.permissions.wallet_view ? walletSummary : null,
    recent_bookings: [],
    upcoming_booking: null,
    first_pending_payment_booking: null,
    quick_actions: [{ code: "view_bookings", label: "View bookings", available: true, url: "/agent/bookings" }],
  };
}

async function mockAgentDashboard(page: Page, caps: typeof capabilitiesOwner | typeof capabilitiesStaffRestricted = capabilitiesOwner) {
  await page.route("**/laravel/agent?format=json", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(buildOverviewPayload(caps)),
    });
  });
}

async function mockAgentBootstrap(page: Page, caps = capabilitiesOwner) {
  await page.route("**/laravel/api/public/content/csrf-token", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ csrf_token: "test-csrf-token" }),
    });
  });
  await mockAgentDashboard(page, caps);
}

const bookingDetailBase = {
  ok: true,
  booking_reference: "BKG-OPS04",
  booking_method: "pay_later",
  payment_method_code: "manual",
  booking_status: status("confirmed", "Confirmed"),
  payment_status: status("paid", "Paid"),
  ticketing_status: status("ticketed", "Ticketed", true),
  pricing: {
    currency: "PKR",
    base_fare: 40000,
    taxes: 5000,
    service_charges: 0,
    total: 45000,
    formatted_total: "Rs. 45,000",
  },
  itinerary: {
    trip_type: "one_way",
    origin: "LHE",
    destination: "KHI",
    depart_date: "2026-08-01",
    airline_name: "PIA",
    cabin: "economy",
    total_formatted: "45,000",
    currency: "PKR",
    route_label: "LHE → KHI",
    segments: [{ origin: "LHE", destination: "KHI", flight_number: "PK301" }],
    return_segments: [],
  },
  passengers: [{ title: "Mr", first_name: "Audit", last_name: "Traveler", passenger_type: "adult" }],
  contact: { name: "Audit Traveler", email: "audit@example.com", phone: "+923001234567", country: "PK" },
  documents_portal: [],
  support: { support_url: "/support", lookup_url: "/lookup-booking" },
  presentation: { heading: "Booking confirmed", subtitle: "Confirmed", tone: "success", show_celebration: false },
  pnr_details: { booking_reference: "ABC123", airline_locator: null, available: true },
  tickets: [{ passenger_name: "Audit Traveler", ticket_number: "1234567890" }],
  poll: { should_poll: false, interval_ms: 4000, max_attempts: 45 },
  refund: { available: false, status: null, label: null },
};

test.describe("JP-OPS-04 agent operational closure", () => {
  test.beforeEach(async ({ page }) => {
    await mockAgentBootstrap(page);
  });

  test("owner dashboard shows authorized nav without fallback links", async ({ page }) => {
    await setSessionFixture(page, "agent");
    await page.goto("/agent/dashboard");
    await expect(page.getByTestId("agent-dashboard-shell")).toBeVisible();
    const nav = page.getByTestId("portal-nav");
    await expect(nav.getByRole("link", { name: "Staff" })).toBeVisible();
    await expect(nav.getByRole("link", { name: "Reports" })).toBeVisible();
    await expect(nav.getByRole("link", { name: "Commissions" })).toBeVisible();
  });

  test("staff permitted dashboard shows bookings only nav", async ({ page }) => {
    await mockAgentDashboard(page, capabilitiesStaffRestricted);
    await setSessionFixture(page, "agent_staff");
    await page.goto("/agent/dashboard");
    await expect(page.getByTestId("agent-dashboard-shell")).toBeVisible();
    const nav = page.getByTestId("portal-nav");
    await expect(nav.getByRole("link", { name: "Bookings" })).toBeVisible();
    await expect(nav.getByRole("link", { name: "Staff" })).toHaveCount(0);
  });

  test("staff direct route to staff page shows permission denied", async ({ page }) => {
    await mockAgentDashboard(page, capabilitiesStaffRestricted);
    await page.route("**/laravel/agent/staff?format=json", async (route) => {
      await route.fulfill({
        status: 403,
        contentType: "application/json",
        body: JSON.stringify({ ok: false, message: "Forbidden" }),
      });
    });
    await setSessionFixture(page, "agent_staff");
    await page.goto("/agent/staff");
    await expect(page.getByTestId("agent-permission-denied")).toBeVisible();
  });

  test("inactive agency denial surfaces on dashboard", async ({ page }) => {
    await page.route("**/laravel/agent?format=json", async (route) => {
      await route.fulfill({
        status: 403,
        contentType: "application/json",
        body: JSON.stringify({ ok: false, code: "agency_inactive", message: "This agency account is inactive." }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/dashboard");
    await expect(page.getByText(/inactive/i)).toBeVisible();
  });

  test("booking list loads from JSON", async ({ page }) => {
    await page.route("**/laravel/agent/bookings?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          bookings: [bookingListItem],
          pagination: { current_page: 1, last_page: 1, per_page: 20, total: 1, from: 1, to: 1 },
          filter: "all",
        }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/bookings");
    await expect(page.getByTestId("agent-bookings-list")).toBeVisible();
    await expect(page.getByText("BKG-OPS04")).toBeVisible();
  });

  test("booking detail shows cancellation pending state", async ({ page }) => {
    const bookingDetail = {
      ...bookingDetailBase,
      actions: [
        {
          code: "request_cancellation",
          label: "Request cancellation",
          available: false,
          reason_unavailable: "A cancellation request is already under review.",
        },
      ],
      cancellation: { eligible: false, request_pending: true, already_cancelled: false, message: "Under review." },
    };
    await page.route("**/laravel/agent/bookings/BKG-OPS04?format=json*", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(bookingDetail) });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/bookings/BKG-OPS04");
    await expect(page.getByTestId("agent-booking-detail")).toBeVisible();
    await expect(page.getByTestId("agent-cancellation-status")).toContainText(/under review/i);
  });

  test("booking-create search handoff", async ({ page }) => {
    await page.route("**/laravel/agent/bookings/create?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          booking_mode_active: true,
          agency_name: "Alpha Travel",
          message: "Continue in flight search to create a booking.",
          search_url: "/flights/search",
          exit_url: "/agent/bookings/exit-mode",
        }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/bookings/create");
    await expect(page.getByTestId("agent-booking-create-entry")).toBeVisible();
    await expect(page.getByRole("button", { name: /search flights/i })).toBeVisible();
  });

  test("staff list renders from JSON", async ({ page }) => {
    await page.route("**/laravel/agent/staff?format=json", async (route) => {
      if (route.request().method() !== "GET") {
        await route.continue();
        return;
      }
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          staff: [
            {
              id: 1,
              name: "Staff A",
              email: "staff@alpha.test",
              status: "active",
              role_label: "Sales",
              permissions_count: 2,
              edit_url: "/agent/staff/1",
            },
          ],
          capabilities: { can_create: true, can_manage_permissions: true },
          permission_labels: {},
        }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/staff");
    await expect(page.getByTestId("agent-staff-list")).toBeVisible();
    await expect(page.getByTestId("agent-staff-list").getByText("Staff A")).toBeVisible();
  });

  test("staff create validation surfaces server message", async ({ page }) => {
    await page.route("**/laravel/agent/staff/create?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          permission_labels: { "agent.bookings.view": "View bookings" },
          default_permissions: [],
          submit_url: "/agent/staff",
        }),
      });
    });
    await page.route("**/laravel/agent/staff?format=json", async (route) => {
      if (route.request().method() === "POST") {
        await route.fulfill({
          status: 422,
          contentType: "application/json",
          body: JSON.stringify({ ok: false, message: "The email has already been taken." }),
        });
        return;
      }
      await route.continue();
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/staff/new");
    await page.getByTestId("agent-staff-create-form").getByLabel(/^name$/i).fill("Duplicate");
    await page.getByTestId("agent-staff-create-form").getByLabel(/^email$/i).fill("staff@alpha.test");
    await page.getByTestId("agent-staff-create-form").getByLabel(/^temporary password$/i).fill("password123");
    await page.getByRole("button", { name: /create staff member/i }).click();
    await expect(page.getByText(/already been taken/i)).toBeVisible();
  });

  test("staff permission mutation denied for unauthorized staff", async ({ page }) => {
    await mockAgentDashboard(page, capabilitiesStaffRestricted);
    await page.route("**/laravel/agent/staff/2/edit?format=json*", async (route) => {
      await route.fulfill({
        status: 403,
        contentType: "application/json",
        body: JSON.stringify({ ok: false, message: "Forbidden" }),
      });
    });
    await setSessionFixture(page, "agent_staff");
    await page.goto("/agent/staff/2");
    await expect(page.getByTestId("agent-permission-denied")).toBeVisible();
  });

  test("agency page loads profile", async ({ page }) => {
    await page.route("**/laravel/agent/agency?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          agency: { name: "Alpha Travel", city: "Lahore", country: "Pakistan", license_number: "LIC-1" },
          capabilities: { can_edit: true },
        }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/agency");
    await expect(page.getByTestId("agent-agency-profile")).toBeVisible();
    await expect(page.getByText("Alpha Travel")).toBeVisible();
  });

  test("wallet state renders balance", async ({ page }) => {
    await page.route("**/laravel/agent/wallet?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          summary: walletSummary,
          recent_ledger_entries: [],
          capabilities: { can_view_ledger: true, can_create_deposit: true },
        }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/wallet");
    await expect(page.getByTestId("agent-wallet-overview")).toBeVisible();
    await expect(page.getByTestId("agent-wallet-overview").getByTestId("wallet-metric-card").first()).toContainText("PKR 25,000");
  });

  test("deposit submission and duplicate conflict", async ({ page }) => {
    await page.route("**/laravel/agent/deposits/create?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          fields: { amount: { min: 1000 } },
          submit_url: "/agent/deposits",
          summary: walletSummary,
        }),
      });
    });
    await page.route("**/laravel/agent/deposits?format=json*", async (route) => {
      if (route.request().method() === "POST") {
        await route.fulfill({
          status: 409,
          contentType: "application/json",
          body: JSON.stringify({ ok: false, message: "A pending deposit request already exists." }),
        });
        return;
      }
      await route.continue();
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/deposits/new");
    await expect(page.getByTestId("agent-new-deposit-form")).toBeVisible();
    await page.getByTestId("agent-new-deposit-form").getByLabel(/^amount$/i).fill("5000");
    await page.getByRole("button", { name: /submit deposit request/i }).click();
    await expect(page.getByText(/pending deposit/i)).toBeVisible();
  });

  test("deposit CTA hidden when can_submit_deposit is false", async ({ page }) => {
    const caps = {
      ...capabilitiesOwner,
      capabilities: { ...capabilitiesOwner.capabilities, can_submit_deposit: false },
    };
    await mockAgentDashboard(page, caps);
    await page.route("**/laravel/agent/deposits?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          deposits: [],
          pagination: { current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null },
          summary: walletSummary,
        }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/deposits");
    await expect(page.getByTestId("deposit-new-cta")).toHaveCount(0);
  });

  test("reports page loads overview", async ({ page }) => {
    await page.route("**/laravel/agent/reports?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          active_tab: "overview",
          has_live_data: true,
          summary: { total_bookings: 2, gross_sales: 90000, ticketed_bookings: 1 },
          monthly_sales: [],
          export_url: "/laravel/agent/finance/statement/export",
          allowed_tabs: ["overview"],
        }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/reports");
    await expect(page.getByTestId("agent-reports-summary")).toBeVisible();
  });

  test("commissions owner-only page loads", async ({ page }) => {
    await page.route("**/laravel/agent/commissions?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          balance: 1200,
          totals: { pending: 500, approved: 700, paid: 0, currency: "PKR" },
          entries: [],
          statements: [],
        }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/commissions");
    await expect(page.getByTestId("agent-commissions-overview")).toBeVisible();
  });

  test("staff commissions route shows owner-only denial", async ({ page }) => {
    await mockAgentDashboard(page, capabilitiesStaffRestricted);
    await page.route("**/laravel/agent/commissions?format=json*", async (route) => {
      await route.fulfill({ status: 403, contentType: "application/json", body: JSON.stringify({ ok: false, message: "Forbidden" }) });
    });
    await setSessionFixture(page, "agent_staff");
    await page.goto("/agent/commissions");
    await expect(page.getByTestId("agent-permission-denied")).toBeVisible();
  });

  test("payment-proof submission remains submitted pending state", async ({ page }) => {
    const bookingDetail = {
      ...bookingDetailBase,
      payment_status: status("pending_proof", "Proof under review"),
      actions: [
        {
          code: "upload_payment_proof",
          label: "Upload payment proof",
          available: false,
          reason_unavailable: "Your payment proof is under review.",
        },
      ],
    };
    await page.route("**/laravel/agent/bookings/BKG-OPS04?format=json*", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(bookingDetail) });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/bookings/BKG-OPS04");
    await expect(page.getByTestId("agent-booking-detail")).toBeVisible();
    await expect(page.getByText(/proof under review/i)).toBeVisible();
  });

  test("support cases list loads", async ({ page }) => {
    await page.route("**/laravel/agent/support/tickets?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          tickets: [
            {
              reference: "TKT-1",
              subject: "Wallet help",
              category: "wallet",
              category_label: "Wallet",
              status: status("open", "Open"),
              detail_url: "/agent/support/TKT-1",
              can_reply: true,
              can_close: false,
              updated_at: "2026-08-01",
            },
          ],
          pagination: { current_page: 1, last_page: 1, per_page: 20, total: 1, from: 1, to: 1 },
        }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/support");
    await expect(page.getByTestId("agent-support-list")).toBeVisible();
    await expect(page.getByText("Wallet help")).toBeVisible();
  });

  test("notifications unavailable state is explicit", async ({ page }) => {
    await page.route("**/laravel/agent/notifications?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, available: false, message: "Notifications are not available yet." }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/notifications");
    await expect(page.getByText(/In-app notifications unavailable/i)).toBeVisible();
  });

  test("session-expired recovery redirects to login", async ({ page }) => {
    await setSessionFixture(page, "expired");
    await page.goto("/agent/dashboard");
    await expect(page).toHaveURL(/\/login/);
  });

  test("removed staff membership denial", async ({ page }) => {
    await page.route("**/laravel/agent?format=json", async (route) => {
      await route.fulfill({
        status: 403,
        contentType: "application/json",
        body: JSON.stringify({ ok: false, code: "permission_required", message: "Agency membership has been removed." }),
      });
    });
    await setSessionFixture(page, "agent_staff");
    await page.goto("/agent/dashboard");
    await expect(page.getByText(/membership has been removed/i)).toBeVisible();
  });

  test("anonymous user does not call private agent API before layout redirect", async ({ page }) => {
    const privateRequests: string[] = [];
    page.on("request", (request) => {
      if (/\/laravel\/agent\?format=json/.test(request.url())) {
        privateRequests.push(request.url());
      }
    });
    await setSessionFixture(page, "anonymous");
    await page.goto("/agent/dashboard");
    await expect(page).toHaveURL(/\/login/);
    expect(privateRequests.length).toBe(0);
  });

  test("staff create mutation sends one POST per submit", async ({ page }) => {
    const posts: string[] = [];
    await page.route("**/laravel/agent/staff/create?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          permission_labels: { "agent.bookings.view": "View bookings" },
          default_permissions: ["agent.bookings.view"],
          submit_url: "/agent/staff",
        }),
      });
    });
    await page.route("**/laravel/agent/staff?format=json", async (route) => {
      if (route.request().method() === "POST") {
        posts.push(route.request().url());
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            ok: true,
            message: "Staff created.",
            staff: { id: 9, name: "New Staff", email: "new@alpha.test", status: "active", role_label: "Staff", permissions_count: 1, edit_url: "/agent/staff/9" },
            redirect_url: "/agent/staff",
          }),
        });
        return;
      }
      await route.continue();
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/staff/new");
    const form = page.getByTestId("agent-staff-create-form");
    await form.getByLabel(/^name$/i).fill("New Staff");
    await form.getByLabel(/^email$/i).fill("new@alpha.test");
    await form.getByLabel(/^temporary password$/i).fill("password123");
    await page.getByRole("button", { name: /create staff member/i }).dblclick();
    await expect.poll(() => posts.length).toBe(1);
  });

  test("deposit submit mutation sends one POST per click", async ({ page }) => {
    const posts: string[] = [];
    await page.route("**/laravel/agent/deposits/create?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, fields: {}, submit_url: "/agent/deposits", summary: walletSummary }),
      });
    });
    await page.route("**/laravel/agent/deposits?format=json*", async (route) => {
      if (route.request().method() === "POST") {
        posts.push(route.request().url());
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({ ok: true, redirect_url: "/agent/deposits", message: "Deposit submitted." }),
        });
        return;
      }
      await route.continue();
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/deposits/new");
    const form = page.getByTestId("agent-new-deposit-form");
    await form.getByLabel(/^amount$/i).fill("5000");
    await page.getByRole("button", { name: /submit deposit request/i }).dblclick();
    await expect.poll(() => posts.length).toBe(1);
  });
});
