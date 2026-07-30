import type { Page } from "@playwright/test";
import { sessionFixtureCookieName } from "../../features/auth/server/session-fixture";
import type { JpUi05Scenario } from "./jp-ui-05-scenarios";
import {
  mockAuthRegistrationChallenge,
  mockCsrf,
  mockTurnstileDisabled,
} from "./jp-ui-01-fixtures";

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:3002";

const LOGGED_OUT_SESSION = { authenticated: false };

const CUSTOMER_SESSION = {
  authenticated: true,
  user: {
    id: "fixture-customer-1",
    name: "Ayesha Khan",
    email: "ayesha.khan@example.com",
    account_type: "customer",
  },
  role: "customer",
  permissions: [],
  dashboard_url: "/customer/dashboard",
  requires_otp: false,
  requires_password_change: false,
  requires_email_verification: false,
  account_status: "active",
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
  permissions: [],
  dashboard_url: "/agent/dashboard",
  requires_otp: false,
  requires_password_change: false,
  account_status: "active",
};

const AGENT_STAFF_SESSION = {
  authenticated: true,
  user: {
    id: "fixture-agent-staff-1",
    name: "Agency Staff",
    email: "staff@example.com",
    account_type: "agent_staff",
  },
  role: "agent_staff",
  permissions: [],
  dashboard_url: "/agent/dashboard",
  requires_otp: false,
  requires_password_change: false,
  account_status: "active",
};

const OTP_CHALLENGE_SESSION = {
  authenticated: false,
  requires_otp: true,
  otp_challenge: { masked_email: "a***@example.com", resend_available_in: 0 },
};

const pagination = { current_page: 1, last_page: 1, per_page: 15, total: 1, from: 1, to: 1 };

const customerOverviewPayload = {
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
  recent_bookings: [
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
      ticketing_status: { code: "issued", label: "Issued" },
      booking_type: "standard",
      detail_url: "/customer/bookings/BKG-1001",
    },
  ],
  upcoming_booking: null,
  first_pending_payment_booking: null,
  quick_actions: [{ code: "search_flights", label: "Search flights", available: true, url: "/flights/search" }],
};

const customerBookingsPayload = {
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
      ticketing_status: { code: "issued", label: "Issued" },
      booking_type: "standard",
      detail_url: "/customer/bookings/BKG-1001",
    },
  ],
  pagination,
};

const bookingDetailPayload = {
  ok: true,
  booking_reference: "BKG-1001",
  booking_method: "pay_later",
  payment_method_code: "manual",
  booking_status: { code: "confirmed", label: "Confirmed", terminal: false },
  payment_status: { code: "paid", label: "Paid", terminal: true },
  ticketing_status: { code: "ticketed", label: "Ticketed", terminal: true },
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
    airline_name: "Pakistan International Airlines",
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
  presentation: { heading: "Booking confirmed", subtitle: "Your booking is confirmed.", tone: "success", show_celebration: false },
  pnr_details: { booking_reference: "ABC123", airline_locator: null, available: true },
  tickets: [{ passenger_name: "Audit Traveler", ticket_number: "1234567890" }],
  actions: [
    { code: "view_invoice", label: "View invoice", available: true, url: "/customer/invoices" },
    { code: "lookup_booking", label: "Lookup booking", available: true, url: "/lookup-booking" },
  ],
  poll: { should_poll: false, interval_ms: 4000, max_attempts: 45 },
  cancellation: { eligible: false, request_pending: false, already_cancelled: false, message: "" },
  refund: { available: false, status: null, label: null },
};

const customerPaymentsPayload = {
  ok: true,
  filter: "all",
  payments: [
    {
      reference: "PAY-1001",
      booking_reference: "BKG-1001",
      amount: 45000,
      currency: "PKR",
      payment_status: { code: "paid", label: "Paid" },
      payment_method: "manual",
      payment_method_label: "Manual payment",
      date: "2026-07-01T10:00:00Z",
      retry_available: false,
      receipt_available: true,
      source: "payment_proof",
    },
  ],
  pagination,
};

const customerInvoicesPayload = {
  ok: true,
  invoices: [
    {
      booking_reference: "BKG-1001",
      invoice_number: "INV-1001",
      amount: 45000,
      currency: "PKR",
      issue_date: "2026-07-01",
      pdf_available: true,
      download_url: "/customer/invoices/BKG-1001/download",
    },
  ],
  pagination,
};

const customerProfilePayload = {
  ok: true,
  user: {
    name: "Ayesha Khan",
    email: "ayesha.khan@example.com",
    username: "ayesha.k",
    email_verified: true,
  },
  profile: {
    phone: "+923001234567",
    city: "Lahore",
  },
  countries: [{ code: "PK", name: "Pakistan" }],
  update_url: "/profile",
  password_update_url: "/customer/security",
  supported_fields: ["name", "email", "username", "phone", "city"],
};

const customerSupportPayload = {
  ok: true,
  tickets: [
    {
      reference: "SUP-1001",
      subject: "Seat selection query",
      category: "booking",
      category_label: "Booking",
      status: { code: "open", label: "Open" },
      updated_at: "2026-07-10T12:00:00Z",
      detail_url: "/customer/support/SUP-1001",
      can_reply: true,
      can_close: false,
    },
  ],
  pagination,
};

const agentOverviewPayload = {
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
      { code: "ledger", label: "Ledger", href: "/agent/wallet/ledger", available: true },
      { code: "deposits", label: "Deposits", href: "/agent/deposits", available: true },
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

const agentStaffOverviewPayload = {
  ...agentOverviewPayload,
  capabilities: {
    ...agentOverviewPayload.capabilities,
    identity: {
      display_name: "Agency Staff",
      email: "staff@example.com",
      role: "agent_staff",
      role_label: "Agency staff",
      is_owner: false,
    },
    permissions: {
      bookings_view: true,
      wallet_view: false,
      ledger_view: false,
      payments_upload: false,
      support_manage: false,
    },
    modules: {
      agent_wallet: false,
      agent_deposits: false,
      agent_ledger: false,
      agent_support: false,
    },
    navigation: [
      { code: "overview", label: "Overview", href: "/agent/dashboard", available: true },
      { code: "bookings", label: "Bookings", href: "/agent/bookings", available: true },
      { code: "profile", label: "Profile", href: "/agent/profile", available: true },
    ],
  },
};

const agentBookingsPayload = {
  ok: true,
  filter: "all",
  allowed_filters: ["all", "pending_payment"],
  bookings: [
    {
      booking_reference: "BKG-2001",
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
      detail_url: "/agent/bookings/BKG-2001",
    },
  ],
  pagination: { current_page: 1, last_page: 1, per_page: 20, total: 1, from: 1, to: 1 },
};

const agentWalletPayload = {
  ok: true,
  summary: {
    balance: 25000,
    available_balance: 75000,
    pending_deposits: 0,
    credit_limit: 50000,
    credit_enabled: true,
    currency: "PKR",
    wallet_status: "active",
  },
  recent_ledger_entries: [
    {
      id: 1,
      type: "credit",
      amount: 50000,
      currency: "PKR",
      description: "Deposit approved",
      created_at: "2026-07-01T10:00:00Z",
      balance_after: 75000,
    },
  ],
  capabilities: { deposits: true, ledger: true },
  quick_actions: [{ code: "view_ledger", label: "View ledger", available: true, url: "/agent/wallet/ledger" }],
};

const agentLedgerPayload = {
  ok: true,
  entries: [
    {
      id: 1,
      type: "credit",
      amount: 50000,
      currency: "PKR",
      description: "Deposit approved",
      created_at: "2026-07-01T10:00:00Z",
      balance_after: 75000,
    },
  ],
  pagination: { current_page: 1, last_page: 1, per_page: 20, total: 1, from: 1, to: 1 },
  summary: agentWalletPayload.summary,
};

const agentDepositsPayload = {
  ok: true,
  deposits: [
    {
      deposit_reference: "DEP-1001",
      requested_amount: 50000,
      currency: "PKR",
      date: "2026-07-01",
      method: "bank_transfer",
      proof_status: "uploaded",
      approval_status: { code: "approved", label: "Approved" },
      credited_amount: 50000,
      next_action: { code: "view", label: "View deposit" },
    },
  ],
  pagination: { current_page: 1, last_page: 1, per_page: 20, total: 1, from: 1, to: 1 },
  summary: agentWalletPayload.summary,
};

const agentDepositsPendingPayload = {
  ...agentDepositsPayload,
  deposits: [
    {
      deposit_reference: "DEP-1002",
      requested_amount: 25000,
      currency: "PKR",
      date: "2026-07-02",
      method: "bank_transfer",
      proof_status: "pending",
      approval_status: { code: "pending", label: "Pending review" },
      next_action: { code: "wait", label: "Awaiting review" },
    },
  ],
};

const agentProfilePayload = {
  ok: true,
  user: {
    name: "Agency Owner",
    email: "agent@example.com",
    username: "agencyowner",
    email_verified: true,
    role_label: "Agency owner",
  },
  personal_profile: {
    phone: "+923001234567",
    city: "Lahore",
  },
  agency_profile: {
    name: "Alpha Travel",
    city: "Lahore",
    country: "PK",
  },
  capabilities: {
    can_edit_personal: true,
    can_edit_agency: true,
    can_view_agency: true,
  },
  countries: [{ code: "PK", name: "Pakistan" }],
  personal_update_url: "/agent/profile/personal",
  agency_update_url: "/agent/profile/agency",
  password_update_url: "/agent/security",
  supported_personal_fields: ["name", "email", "username", "phone"],
  supported_agency_fields: ["name", "city", "country"],
};

async function setSessionFixtureCookie(page: Page, fixture?: string): Promise<void> {
  if (!fixture) return;
  const allowed = new Set(["customer", "agent", "agent_staff", "otp", "anonymous"]);
  if (!allowed.has(fixture)) return;
  await page.context().addCookies([
    {
      name: sessionFixtureCookieName,
      value: fixture,
      url: baseURL,
    },
  ]);
}

function sessionBootstrapForFixture(fixtureId: string, role?: string) {
  if (fixtureId === "auth-authenticated-customer") return CUSTOMER_SESSION;
  if (role === "customer") return CUSTOMER_SESSION;
  if (role === "agent_staff") return AGENT_STAFF_SESSION;
  if (role === "agent") return AGENT_SESSION;
  if (fixtureId.startsWith("auth-otp")) return OTP_CHALLENGE_SESSION;
  return LOGGED_OUT_SESSION;
}

async function mockSessionBootstrap(page: Page, fixtureId: string, role?: string): Promise<void> {
  const body = sessionBootstrapForFixture(fixtureId, role);
  await page.route("**/laravel/api/public/auth/session", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(body) });
  });
}

async function mockAuthApis(page: Page, fixtureId: string): Promise<void> {
  await page.route("**/laravel/login", async (route) => {
    if (fixtureId === "auth-login-slow") return;
    if (fixtureId === "auth-login-rate-limit") {
      await route.fulfill({
        status: 429,
        contentType: "application/json",
        body: JSON.stringify({ message: "Too many login attempts. Please wait a moment and try again." }),
      });
      return;
    }
    if (fixtureId === "auth-login-fail") {
      await route.fulfill({
        status: 422,
        contentType: "application/json",
        body: JSON.stringify({
          message: "These credentials do not match our records.",
          errors: { login: ["These credentials do not match our records."] },
        }),
      });
      return;
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ ok: true, redirect: "/customer/dashboard" }),
    });
  });

  await page.route("**/laravel/register", async (route) => {
    if (fixtureId === "auth-register-slow") return;
    if (fixtureId === "auth-register-validation-fail") {
      await route.fulfill({
        status: 422,
        contentType: "application/json",
        body: JSON.stringify({
          message: "The given data was invalid.",
          errors: {
            first_name: ["The first name field is required."],
            last_name: ["The last name field is required."],
            email: ["The email field is required."],
            mobile: ["The mobile field is required."],
            password: ["The password field is required."],
            terms: ["You must accept the terms and conditions."],
          },
        }),
      });
      return;
    }
    if (fixtureId === "auth-register-consent-fail") {
      await route.fulfill({
        status: 422,
        contentType: "application/json",
        body: JSON.stringify({
          message: "The given data was invalid.",
          errors: { terms: ["You must accept the terms and conditions."] },
        }),
      });
      return;
    }
    if (fixtureId === "auth-register-success") {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          redirect: "/verify-email",
          requires_email_verification: true,
          message: "Please verify your email address to continue.",
        }),
      });
      return;
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ ok: true, redirect: "/customer/dashboard" }),
    });
  });

  await page.route("**/laravel/agent/register", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ ok: true, redirect: "/agent/register/submitted", pending: true }),
    });
  });

  await page.route("**/laravel/api/public/auth/otp-challenge", async (route) => {
    if (fixtureId === "auth-otp-resend-cooldown") {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ has_challenge: true, masked_email: "a***@example.com", resend_available_in: 45 }),
      });
      return;
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ has_challenge: true, masked_email: "a***@example.com", resend_available_in: 0 }),
    });
  });

  await page.route("**/laravel/login/otp", async (route) => {
    if (route.request().method() === "POST") {
      if (fixtureId === "auth-otp-invalid") {
        await route.fulfill({
          status: 422,
          contentType: "application/json",
          body: JSON.stringify({
            message: "The verification code is invalid.",
            errors: { otp: ["The verification code is invalid."] },
          }),
        });
        return;
      }
      if (fixtureId === "auth-otp-expired") {
        await route.fulfill({
          status: 422,
          contentType: "application/json",
          body: JSON.stringify({
            message: "This verification code has expired. Please sign in again.",
            errors: { otp: ["This verification code has expired. Please sign in again."] },
          }),
        });
        return;
      }
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, redirect: "/customer/dashboard" }),
      });
      return;
    }
    await route.continue();
  });

  await page.route("**/laravel/login/otp/resend", async (route) => {
    if (fixtureId === "auth-otp-rate-limit") {
      await route.fulfill({
        status: 429,
        contentType: "application/json",
        body: JSON.stringify({ message: "Too many resend attempts. Please wait before trying again." }),
      });
      return;
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ ok: true, resend_available_in: 60 }),
    });
  });

  await page.route("**/laravel/forgot-password", async (route) => {
    if (fixtureId === "auth-recovery-success") {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          message: "If an account exists for that email address, we have emailed password reset instructions.",
        }),
      });
      return;
    }
    await route.continue();
  });

  await page.route("**/laravel/reset-password", async (route) => {
    if (fixtureId === "auth-reset-invalid") {
      await route.fulfill({
        status: 422,
        contentType: "application/json",
        body: JSON.stringify({
          message: "This password reset token is invalid or has expired.",
          errors: { email: ["This password reset token is invalid or has expired."] },
        }),
      });
      return;
    }
    if (fixtureId === "auth-reset-success") {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          redirect: "/login",
          message: "Your password has been reset.",
        }),
      });
      return;
    }
    await route.continue();
  });
}

async function mockTurnstileEnabled(page: Page): Promise<void> {
  await page.route("**/laravel/api/public/content/turnstile-config", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ enabled: true, site_key: "audit-turnstile-site-key", response_field: "cf-turnstile-response" }),
    });
  });
  await page.route("**/challenges.cloudflare.com/turnstile/v0/api.js**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/javascript",
      body: `
        window.turnstile = {
          render: function(container, options) {
            window.__turnstileOptions = options;
            return 'widget-1';
          },
          reset: function() {},
          remove: function() {},
        };
      `,
    });
  });
}

async function mockGuestBookingAccessPage(page: Page): Promise<void> {
  const themeBootstrap = `(function(){try{var k="jp-theme-preference";var s=localStorage.getItem(k);var p=s==="light"||s==="dark"||s==="system"?s:"system";var d=window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches;var t=p==="dark"||(p==="system"&&d)?"dark":"light";document.documentElement.setAttribute("data-theme",t);document.documentElement.style.colorScheme=t;}catch(e){document.documentElement.setAttribute("data-theme","light");}})();`;

  await page.route((url) => /\/guest\/bookings\/\d+\/access\//.test(url.pathname), async (route) => {
    if (route.request().resourceType() !== "document") {
      await route.continue();
      return;
    }

    await route.fulfill({
      status: 200,
      contentType: "text/html",
      body: `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Booking access</title>
  <script>${themeBootstrap}</script>
  <style>
    :root { color-scheme: light; }
    [data-theme="dark"] { color-scheme: dark; }
    body { margin: 0; font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
    [data-theme="dark"] body { background: #0f172a; color: #f1f5f9; }
    main { max-width: 720px; margin: 0 auto; padding: 2rem 1rem; }
    .card { border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; padding: 1.5rem; }
    [data-theme="dark"] .card { background: #1e293b; border-color: #334155; }
    h1 { margin: 0 0 0.5rem; font-size: 1.5rem; }
    p { margin: 0.25rem 0; }
    .muted { opacity: 0.75; font-size: 0.875rem; }
    .status { display: inline-block; margin-top: 1rem; padding: 0.25rem 0.75rem; border-radius: 999px; background: #dcfce7; color: #166534; font-size: 0.75rem; font-weight: 600; }
    [data-theme="dark"] .status { background: #14532d; color: #bbf7d0; }
    .actions { margin-top: 1.5rem; display: flex; flex-wrap: wrap; gap: 0.75rem; }
    a.button { display: inline-block; padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; text-decoration: none; color: inherit; font-size: 0.875rem; font-weight: 600; }
    [data-theme="dark"] a.button { border-color: #475569; }
  </style>
</head>
<body>
  <main id="main-content" data-testid="guest-booking-result">
    <div class="card">
      <p class="muted">Booking reference</p>
      <h1>BKG-FOUND</h1>
      <p class="muted">Route: LHE → KHI · 1 passenger</p>
      <span class="status">Confirmed</span>
      <div class="actions">
        <a class="button" href="/login">Sign in for more actions</a>
        <a class="button" href="/support">Contact support</a>
      </div>
    </div>
  </main>
</body>
</html>`,
    });
  });
}

async function mockLookupApis(page: Page, fixtureId: string): Promise<void> {
  if (fixtureId === "lookup-turnstile-required") {
    await mockTurnstileEnabled(page);
    return;
  }

  if (fixtureId === "lookup-found") {
    await mockGuestBookingAccessPage(page);
  }

  await mockTurnstileDisabled(page);

  await page.route("**/laravel/lookup-booking", async (route) => {
    if (fixtureId === "lookup-rate-limit") {
      await route.fulfill({
        status: 429,
        contentType: "application/json",
        body: JSON.stringify({ message: "Too many lookup attempts. Please try again later." }),
      });
      return;
    }
    if (fixtureId === "lookup-turnstile-fail") {
      await route.fulfill({
        status: 422,
        contentType: "application/json",
        body: JSON.stringify({
          message: "The given data was invalid.",
          errors: { "cf-turnstile-response": ["Security check failed. Please refresh and try again."] },
        }),
      });
      return;
    }
    if (fixtureId === "lookup-not-found") {
      await route.fulfill({
        status: 422,
        contentType: "application/json",
        body: JSON.stringify({
          message: "The given data was invalid.",
          errors: { lookup: ["Booking not found for the provided reference and email."] },
        }),
      });
      return;
    }
    if (fixtureId === "lookup-found") {
      await route.fulfill({
        status: 302,
        headers: { Location: "/guest/bookings/1/access/test-token-123" },
      });
      return;
    }
    await route.continue();
  });
}

async function mockCustomerPortalApis(page: Page, fixtureId: string): Promise<void> {
  const overviewHandler = async (route: import("@playwright/test").Route) => {
    if (fixtureId === "customer-loading") return;
    if (fixtureId === "customer-session-expired") {
      await route.fulfill({ status: 401, contentType: "application/json", body: JSON.stringify({ ok: false, message: "Your session has expired. Please sign in again." }) });
      return;
    }
    if (fixtureId === "customer-api-error") {
      await route.fulfill({ status: 503, contentType: "application/json", body: JSON.stringify({ ok: false, message: "We could not load your dashboard. Please try again." }) });
      return;
    }
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(customerOverviewPayload) });
  };

  await page.route("**/laravel/customer?format=json", overviewHandler);

  await page.route("**/laravel/customer/bookings?format=json*", async (route) => {
    if (fixtureId === "customer-bookings-empty") {
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
      return;
    }
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(customerBookingsPayload) });
  });

  await page.route("**/laravel/customer/bookings/BKG-1001?format=json", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ ...bookingDetailPayload, booking_reference: "BKG-1001" }) });
  });

  await page.route("**/laravel/customer/bookings/BKG-FORBIDDEN?format=json", async (route) => {
    await route.fulfill({ status: 403, contentType: "application/json", body: JSON.stringify({ ok: false, message: "You do not have access to this booking." }) });
  });

  await page.route("**/laravel/customer/payments?format=json*", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(customerPaymentsPayload) });
  });

  await page.route("**/laravel/customer/invoices?format=json*", async (route) => {
    if (fixtureId === "customer-invoices-empty") {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, invoices: [], pagination: { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null } }),
      });
      return;
    }
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(customerInvoicesPayload) });
  });

  await page.route("**/laravel/customer/profile?format=json", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(customerProfilePayload) });
  });

  await page.route("**/laravel/profile", async (route) => {
    if (fixtureId === "customer-profile-validation") {
      await route.fulfill({
        status: 422,
        contentType: "application/json",
        body: JSON.stringify({
          message: "The given data was invalid.",
          errors: { first_name: ["First name is required."] },
        }),
      });
      return;
    }
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ ok: true, message: "Profile updated successfully." }) });
  });

  await page.route("**/laravel/customer/support/tickets?format=json*", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(customerSupportPayload) });
  });
}

async function mockAgentPortalApis(page: Page, fixtureId: string): Promise<void> {
  const overviewBody =
    fixtureId === "agent-staff-permitted" || fixtureId === "agent-staff-forbidden"
      ? agentStaffOverviewPayload
      : agentOverviewPayload;

  await page.route("**/laravel/agent?format=json", async (route) => {
    if (fixtureId === "agent-api-error") {
      await route.fulfill({ status: 503, contentType: "application/json", body: JSON.stringify({ ok: false, message: "We could not load your agent dashboard. Please try again." }) });
      return;
    }
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(overviewBody) });
  });

  await page.route("**/laravel/agent/bookings?format=json*", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(agentBookingsPayload) });
  });

  await page.route("**/laravel/agent/bookings/BKG-2001?format=json", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ ...bookingDetailPayload, booking_reference: "BKG-2001" }),
    });
  });

  await page.route("**/laravel/agent/bookings/BKG-OTHER?format=json", async (route) => {
    await route.fulfill({ status: 404, contentType: "application/json", body: JSON.stringify({ ok: false, message: "Booking not found." }) });
  });

  await page.route("**/laravel/agent/wallet?format=json", async (route) => {
    if (fixtureId === "agent-staff-forbidden" || fixtureId === "agent-wallet-unavailable") {
      await route.fulfill({ status: 403, contentType: "application/json", body: JSON.stringify({ ok: false, message: "You do not have permission to view the wallet." }) });
      return;
    }
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(agentWalletPayload) });
  });

  await page.route("**/laravel/agent/ledger?format=json*", async (route) => {
    if (fixtureId === "agent-ledger-empty") {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          entries: [],
          pagination: { current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null },
          summary: agentWalletPayload.summary,
        }),
      });
      return;
    }
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(agentLedgerPayload) });
  });

  await page.route("**/laravel/agent/deposits?format=json*", async (route) => {
    const body = fixtureId === "agent-deposits-pending" ? agentDepositsPendingPayload : agentDepositsPayload;
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(body) });
  });

  await page.route("**/laravel/agent/profile?format=json", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(agentProfilePayload) });
  });
}

function dashboardQueryForFixture(fixtureId: string): Record<string, string> {
  switch (fixtureId) {
    case "admin-customers-empty":
      return { dataSourcePreview: "fixture", previewEmpty: "1" };
    case "admin-api-error":
      return { dataSourcePreview: "fixture", previewError: "1" };
    case "staff-forbidden":
      return { dataSourcePreview: "forbidden" };
    default:
      return { dataSourcePreview: "fixture" };
  }
}

export function resolveJpUi05Route(scenario: JpUi05Scenario): string {
  if (scenario.application !== "dashboard") {
    return scenario.route;
  }
  const params = dashboardQueryForFixture(scenario.fixtureId);
  const url = new URL(scenario.route, "http://127.0.0.1");
  Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
  url.searchParams.set("jpui05", scenario.id);
  return `${url.pathname}${url.search}`;
}

export async function setupJpUi05Scenario(page: Page, scenario: JpUi05Scenario): Promise<void> {
  await page.unrouteAll({ behavior: "ignoreErrors" });
  await mockCsrf(page);
  await mockSessionBootstrap(page, scenario.fixtureId, scenario.role);
  await mockAuthApis(page, scenario.fixtureId);

  if (scenario.family === "recovery") {
    await setSessionFixtureCookie(page, "otp");
  } else {
    await setSessionFixtureCookie(page, scenario.role);
  }

  const authFamilies = new Set(["login", "signup", "recovery"]);
  const manageFamilies = new Set(["manage"]);
  const customerFamilies = new Set(["customer"]);
  const agentFamilies = new Set(["agent"]);

  if (authFamilies.has(scenario.family)) {
    if (
      scenario.fixtureId === "auth-register" ||
      scenario.fixtureId === "auth-register-validation-fail" ||
      scenario.fixtureId === "auth-register-consent-fail" ||
      scenario.fixtureId === "auth-register-slow" ||
      scenario.fixtureId === "auth-register-success"
    ) {
      await mockAuthRegistrationChallenge(page);
    }
    if (scenario.fixtureId === "auth-authenticated-customer") {
      await setSessionFixtureCookie(page, "customer");
      await mockCustomerPortalApis(page, "customer-overview");
    }
    await mockTurnstileDisabled(page);
  }

  if (manageFamilies.has(scenario.family)) {
    await mockLookupApis(page, scenario.fixtureId);
  }

  if (customerFamilies.has(scenario.family)) {
    await mockCustomerPortalApis(page, scenario.fixtureId);
  }

  if (agentFamilies.has(scenario.family)) {
    await mockAgentPortalApis(page, scenario.fixtureId);
  }

  if (scenario.application === "dashboard") {
    await mockTurnstileDisabled(page);
  }
}
