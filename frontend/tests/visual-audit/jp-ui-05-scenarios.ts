import type { Page } from "@playwright/test";
import type { ThemeMode } from "./jp-ui-02-scenarios";

export type JpUi05Application = "frontend" | "dashboard";

export type JpUi05Family = "login" | "signup" | "recovery" | "manage" | "customer" | "agent" | "admin";

export type JpUi05Scenario = {
  id: string;
  application: JpUi05Application;
  family: JpUi05Family;
  route: string;
  theme: ThemeMode;
  viewport: { name: string; width: number; height: number };
  zoom: number;
  state: string;
  fixtureId: string;
  role?: string;
  fullPage?: boolean;
  waitForTestId?: string;
  forbiddenTestIds?: string[];
  action?: (page: Page) => Promise<void>;
};

type Viewport = { name: string; width: number; height: number };

const VP = {
  d1440: { name: "1440x900", width: 1440, height: 900 },
  d1024: { name: "1024x900", width: 1024, height: 900 },
  m390: { name: "390x844", width: 390, height: 844 },
  m320: { name: "320x700", width: 320, height: 700 },
} as const;

function s(
  application: JpUi05Application,
  family: JpUi05Family,
  id: string,
  route: string,
  theme: ThemeMode,
  viewport: Viewport,
  state: string,
  fixtureId: string,
  extra?: Partial<JpUi05Scenario>,
): JpUi05Scenario {
  return {
    id,
    application,
    family,
    route,
    theme,
    viewport,
    zoom: 1,
    state,
    fixtureId,
    fullPage: true,
    ...extra,
  };
}

function layoutFamily(
  application: JpUi05Application,
  family: JpUi05Family,
  prefix: string,
  route: string,
  fixtureId: string,
  waitForTestId: string,
): JpUi05Scenario[] {
  return [
    s(application, family, `${prefix}-light-1440`, route, "light", VP.d1440, "layout", fixtureId, { waitForTestId }),
    s(application, family, `${prefix}-dark-1440`, route, "dark", VP.d1440, "layout", fixtureId, { waitForTestId }),
    s(application, family, `${prefix}-system-light-1440`, route, "system-light", VP.d1440, "layout", fixtureId, { waitForTestId }),
    s(application, family, `${prefix}-system-dark-1440`, route, "system-dark", VP.d1440, "layout", fixtureId, { waitForTestId }),
    s(application, family, `${prefix}-light-1024`, route, "light", VP.d1024, "layout", fixtureId, { waitForTestId }),
    s(application, family, `${prefix}-dark-1024`, route, "dark", VP.d1024, "layout", fixtureId, { waitForTestId }),
    s(application, family, `${prefix}-light-390`, route, "light", VP.m390, "layout", fixtureId, { waitForTestId }),
    s(application, family, `${prefix}-dark-390`, route, "dark", VP.m390, "layout", fixtureId, { waitForTestId }),
    s(application, family, `${prefix}-light-320`, route, "light", VP.m320, "layout", fixtureId, { waitForTestId }),
    s(application, family, `${prefix}-dark-320`, route, "dark", VP.m320, "layout", fixtureId, { waitForTestId }),
    { ...s(application, family, `${prefix}-light-150-zoom`, route, "light", VP.d1024, "zoom-150", fixtureId, { waitForTestId }), zoom: 1.5 },
    { ...s(application, family, `${prefix}-dark-150-zoom`, route, "dark", VP.d1024, "zoom-150", fixtureId, { waitForTestId }), zoom: 1.5 },
  ];
}

function buildLogin(): JpUi05Scenario[] {
  return [
    ...layoutFamily("frontend", "login", "login", "/login", "auth-logged-out", "auth-page-shell"),
    s("frontend", "login", "login-password-visible", "/login", "light", VP.d1440, "password-visible", "auth-logged-out", {
      waitForTestId: "auth-form-card",
      action: async (page) => {
        await page.getByLabel(/^password/i).fill("audit-password");
        await page.getByRole("button", { name: /show password/i }).click();
      },
    }),
    s("frontend", "login", "login-invalid-credentials", "/login", "light", VP.d1440, "invalid-credentials", "auth-login-fail", {
      waitForTestId: "auth-form-card",
      action: async (page) => {
        await page.getByLabel(/email or username/i).fill("audit@example.com");
        await page.getByLabel(/^password/i).fill("wrong-password");
        await page.getByRole("button", { name: /sign in/i }).click();
        await page.getByRole("alert").first().waitFor({ state: "visible", timeout: 10_000 });
      },
    }),
    s("frontend", "login", "login-validation-errors", "/login", "light", VP.d1440, "validation-errors", "auth-logged-out", {
      waitForTestId: "auth-form-card",
      action: async (page) => {
        await page.getByRole("button", { name: /sign in/i }).click();
      },
    }),
    s("frontend", "login", "login-loading", "/login", "light", VP.d1440, "loading", "auth-login-slow", {
      waitForTestId: "auth-form-card",
      action: async (page) => {
        await page.getByLabel(/email or username/i).fill("audit@example.com");
        await page.getByLabel(/^password/i).fill("audit-password");
        await page.getByRole("button", { name: /sign in/i }).click();
        await page.getByRole("button", { name: /signing in/i }).waitFor({ state: "visible", timeout: 5000 }).catch(() => undefined);
      },
    }),
    s("frontend", "login", "login-session-expired", "/login?reason=session-expired", "light", VP.d1440, "session-expired", "auth-session-expired", {
      waitForTestId: "auth-form-card",
    }),
    s("frontend", "login", "login-rate-limited", "/login", "light", VP.d1440, "rate-limited", "auth-login-rate-limit", {
      waitForTestId: "auth-form-card",
      action: async (page) => {
        await page.getByLabel(/email or username/i).fill("audit@example.com");
        await page.getByLabel(/^password/i).fill("wrong");
        await page.getByRole("button", { name: /sign in/i }).click();
        await page.getByRole("alert").first().waitFor({ state: "visible", timeout: 10_000 });
      },
    }),
    s("frontend", "login", "login-already-authenticated", "/login", "light", VP.d1440, "already-authenticated", "auth-authenticated-customer", {
      waitForTestId: "customer-dashboard-shell",
      role: "customer",
    }),
    s("frontend", "login", "login-social-providers-hidden-or-authoritative", "/login", "light", VP.d1440, "social-hidden", "auth-logged-out", {
      waitForTestId: "auth-page-shell",
      forbiddenTestIds: ["oauth-google", "oauth-apple", "oauth-facebook", "social-login-row"],
    }),
  ];
}

function buildSignup(): JpUi05Scenario[] {
  return [
    ...layoutFamily("frontend", "signup", "signup", "/register", "auth-register", "auth-page-shell"),
    s("frontend", "signup", "signup-customer", "/register", "light", VP.d1440, "customer", "auth-register", { waitForTestId: "auth-form-card" }),
    s("frontend", "signup", "signup-agent", "/agent/register", "light", VP.d1440, "agent", "auth-agent-register", { waitForTestId: "auth-form-card" }),
    s("frontend", "signup", "signup-validation-errors", "/register", "light", VP.d1440, "validation-errors", "auth-register", {
      waitForTestId: "auth-form-card",
      action: async (page) => {
        await page.getByRole("button", { name: /create account/i }).click();
      },
    }),
    s("frontend", "signup", "signup-password-rules", "/register", "light", VP.d1440, "password-rules", "auth-register", {
      waitForTestId: "auth-form-card",
      action: async (page) => {
        await page.locator('input[name="password"]').fill("short");
      },
    }),
    s("frontend", "signup", "signup-consent-error", "/register", "light", VP.d1440, "consent-error", "auth-register-consent-fail", {
      waitForTestId: "auth-form-card",
      action: async (page) => {
        await page.getByLabel(/first name/i).fill("Audit");
        await page.getByLabel(/last name/i).fill("User");
        await page.getByLabel(/^email/i).fill("audit@example.com");
        await page.getByRole("button", { name: /create account/i }).click();
        await page.getByRole("alert").first().waitFor({ state: "visible", timeout: 10_000 });
      },
    }),
    s("frontend", "signup", "signup-submitting", "/register", "light", VP.d1440, "submitting", "auth-register-slow", {
      waitForTestId: "auth-form-card",
      action: async (page) => {
        await page.getByLabel(/first name/i).fill("Audit");
        await page.getByLabel(/last name/i).fill("User");
        await page.getByLabel(/^email/i).fill("audit@example.com");
        await page.locator('input[name="password"]').fill("AuditPass123!");
        await page.locator('input[name="password_confirmation"]').fill("AuditPass123!");
        await page.getByRole("checkbox", { name: /terms/i }).check();
        await page.getByRole("button", { name: /create account/i }).click();
      },
    }),
    s("frontend", "signup", "signup-success-verification-required", "/register", "light", VP.d1440, "success-verification", "auth-register-success", {
      waitForTestId: "auth-form-card",
      action: async (page) => {
        await page.getByLabel(/first name/i).fill("Audit");
        await page.getByLabel(/last name/i).fill("User");
        await page.getByLabel(/^email/i).fill("audit@example.com");
        await page.locator('input[name="password"]').fill("AuditPass123!");
        await page.locator('input[name="password_confirmation"]').fill("AuditPass123!");
        await page.getByRole("checkbox", { name: /terms/i }).check();
        await page.getByRole("button", { name: /create account/i }).click();
        await page.waitForTimeout(400);
      },
    }),
    s("frontend", "signup", "signup-unsupported-account-types-hidden", "/register", "light", VP.d1440, "unsupported-hidden", "auth-register", {
      waitForTestId: "auth-form-card",
      forbiddenTestIds: ["account-type-family-manager", "account-type-business-traveler", "oauth-google"],
    }),
  ];
}

function buildRecovery(): JpUi05Scenario[] {
  return [
    s("frontend", "recovery", "otp-light-desktop", "/login/otp", "light", VP.d1440, "otp-desktop", "auth-otp-challenge", { waitForTestId: "auth-form-card" }),
    s("frontend", "recovery", "otp-dark-desktop", "/login/otp", "dark", VP.d1440, "otp-desktop", "auth-otp-challenge", { waitForTestId: "auth-form-card" }),
    s("frontend", "recovery", "otp-light-mobile", "/login/otp", "light", VP.m390, "otp-mobile", "auth-otp-challenge", { waitForTestId: "auth-form-card" }),
    s("frontend", "recovery", "otp-dark-mobile", "/login/otp", "dark", VP.m390, "otp-mobile", "auth-otp-challenge", { waitForTestId: "auth-form-card" }),
    s("frontend", "recovery", "otp-invalid", "/login/otp", "light", VP.d1440, "otp-invalid", "auth-otp-invalid", {
      waitForTestId: "auth-form-card",
      action: async (page) => {
        await page.locator('input[name="otp"]').fill("000000");
        await page.getByRole("button", { name: /verify and continue/i }).click();
        await page.getByRole("alert").first().waitFor({ state: "visible", timeout: 10_000 });
      },
    }),
    s("frontend", "recovery", "otp-expired", "/login/otp", "light", VP.d1440, "otp-expired", "auth-otp-expired", {
      waitForTestId: "auth-form-card",
      action: async (page) => {
        await page.locator('input[name="otp"]').fill("111111");
        await page.getByRole("button", { name: /verify and continue/i }).click();
        await page.getByRole("alert").first().waitFor({ state: "visible", timeout: 10_000 });
      },
    }),
    s("frontend", "recovery", "otp-rate-limited", "/login/otp", "light", VP.d1440, "otp-rate-limited", "auth-otp-rate-limit", {
      waitForTestId: "auth-form-card",
      action: async (page) => {
        await page.getByRole("button", { name: /resend/i }).click();
        await page.getByRole("alert").first().waitFor({ state: "visible", timeout: 10_000 });
      },
    }),
    s("frontend", "recovery", "otp-resend-state", "/login/otp", "light", VP.d1440, "otp-resend", "auth-otp-resend-cooldown", { waitForTestId: "auth-form-card" }),
    s("frontend", "recovery", "recovery-initial", "/forgot-password", "light", VP.d1440, "recovery-initial", "auth-logged-out", { waitForTestId: "auth-page-shell" }),
    s("frontend", "recovery", "recovery-generic-success", "/forgot-password", "light", VP.d1440, "recovery-success", "auth-recovery-success", {
      waitForTestId: "auth-form-card",
      action: async (page) => {
        await page.getByLabel(/^email/i).fill("audit@example.com");
        await page.getByRole("button", { name: /send reset link/i }).click();
        await page.getByRole("status").waitFor({ state: "visible", timeout: 10_000 });
      },
    }),
    s("frontend", "recovery", "reset-invalid-or-expired-token", "/reset-password/expired-token?email=audit@example.com", "light", VP.d1440, "reset-invalid", "auth-reset-invalid", { waitForTestId: "auth-form-card" }),
    s("frontend", "recovery", "reset-success", "/reset-password/valid-token?email=audit@example.com", "light", VP.d1440, "reset-success", "auth-reset-success", {
      waitForTestId: "auth-form-card",
    }),
  ];
}

function buildManage(): JpUi05Scenario[] {
  return [
    ...layoutFamily("frontend", "manage", "manage", "/lookup-booking", "lookup-default", "booking-lookup-page"),
    s("frontend", "manage", "manage-turnstile-required", "/lookup-booking", "light", VP.d1440, "turnstile-required", "lookup-turnstile-required", { waitForTestId: "lookup-turnstile" }),
    s("frontend", "manage", "manage-turnstile-failure", "/lookup-booking", "light", VP.d1440, "turnstile-failure", "lookup-turnstile-fail", {
      waitForTestId: "booking-lookup-form",
      action: async (page) => {
        await page.getByLabel(/booking reference/i).fill("BKG-AUDIT");
        await page.getByLabel(/email address/i).fill("audit@example.com");
        await page.getByTestId("lookup-submit").click();
        await page.getByTestId("lookup-error").waitFor({ state: "visible", timeout: 10_000 });
      },
    }),
    s("frontend", "manage", "manage-validation-errors", "/lookup-booking", "light", VP.d1440, "validation-errors", "lookup-default", {
      waitForTestId: "booking-lookup-form",
      action: async (page) => {
        await page.getByTestId("lookup-submit").click();
        await page.getByTestId("lookup-error").waitFor({ state: "visible" });
      },
    }),
    s("frontend", "manage", "manage-rate-limited", "/lookup-booking", "light", VP.d1440, "rate-limited", "lookup-rate-limit", {
      waitForTestId: "booking-lookup-form",
      action: async (page) => {
        await page.getByLabel(/booking reference/i).fill("BKG-AUDIT");
        await page.getByLabel(/email address/i).fill("audit@example.com");
        await page.getByTestId("lookup-submit").click();
        await page.getByTestId("lookup-error").waitFor({ state: "visible", timeout: 10_000 });
      },
    }),
    s("frontend", "manage", "manage-not-found", "/lookup-booking", "light", VP.d1440, "not-found", "lookup-not-found", {
      waitForTestId: "booking-lookup-form",
      action: async (page) => {
        await page.getByLabel(/booking reference/i).fill("BKG-NOTFOUND");
        await page.getByLabel(/email address/i).fill("audit@example.com");
        await page.getByTestId("lookup-submit").click();
        await page.getByTestId("lookup-error").waitFor({ state: "visible", timeout: 10_000 });
      },
    }),
    s("frontend", "manage", "manage-booking-found", "/guest/bookings/1/access/test-token-123", "light", VP.d1440, "booking-found", "lookup-found", {
      waitForTestId: "guest-booking-result",
    }),
    s("frontend", "manage", "manage-restricted-actions-hidden", "/lookup-booking", "light", VP.d1440, "restricted-hidden", "lookup-default", {
      waitForTestId: "booking-lookup-page",
      forbiddenTestIds: ["lookup-change-flight", "lookup-add-baggage", "lookup-live-status"],
    }),
    s("frontend", "manage", "manage-action-requires-login", "/lookup-booking", "light", VP.d1440, "requires-login", "lookup-found", {
      waitForTestId: "booking-lookup-page",
      forbiddenTestIds: ["lookup-refund-action"],
    }),
  ];
}

function buildCustomer(): JpUi05Scenario[] {
  return [
    s("frontend", "customer", "customer-overview-light", "/customer/dashboard", "light", VP.d1440, "overview", "customer-overview", { waitForTestId: "customer-dashboard-overview", role: "customer" }),
    s("frontend", "customer", "customer-overview-dark", "/customer/dashboard", "dark", VP.d1440, "overview", "customer-overview", { waitForTestId: "customer-dashboard-overview", role: "customer" }),
    s("frontend", "customer", "customer-overview-system-light", "/customer/dashboard", "system-light", VP.d1440, "overview", "customer-overview", { waitForTestId: "customer-dashboard-overview", role: "customer" }),
    s("frontend", "customer", "customer-overview-system-dark", "/customer/dashboard", "system-dark", VP.d1440, "overview", "customer-overview", { waitForTestId: "customer-dashboard-overview", role: "customer" }),
    s("frontend", "customer", "customer-overview-mobile-light", "/customer/dashboard", "light", VP.m390, "overview-mobile", "customer-overview", { waitForTestId: "customer-dashboard-shell", role: "customer" }),
    s("frontend", "customer", "customer-overview-mobile-dark", "/customer/dashboard", "dark", VP.m390, "overview-mobile", "customer-overview", { waitForTestId: "customer-dashboard-shell", role: "customer" }),
    { ...s("frontend", "customer", "customer-overview-150-zoom", "/customer/dashboard", "light", VP.d1024, "zoom-150", "customer-overview", { waitForTestId: "customer-dashboard-overview", role: "customer" }), zoom: 1.5 },
    s("frontend", "customer", "customer-bookings-list", "/customer/bookings", "light", VP.d1440, "bookings-list", "customer-bookings", { waitForTestId: "customer-bookings-list", role: "customer" }),
    s("frontend", "customer", "customer-bookings-empty", "/customer/bookings", "light", VP.d1440, "bookings-empty", "customer-bookings-empty", { waitForTestId: "customer-empty-state", role: "customer" }),
    s("frontend", "customer", "customer-booking-detail", "/customer/bookings/BKG-1001", "light", VP.d1440, "booking-detail", "customer-booking-detail", { waitForTestId: "customer-booking-detail", role: "customer" }),
    s("frontend", "customer", "customer-booking-forbidden", "/customer/bookings/BKG-FORBIDDEN", "light", VP.d1440, "forbidden", "customer-booking-forbidden", { waitForTestId: "customer-dashboard-error", role: "customer" }),
    s("frontend", "customer", "customer-payment-detail", "/customer/payments", "light", VP.d1440, "payments", "customer-payments", { waitForTestId: "customer-payments-list", role: "customer" }),
    s("frontend", "customer", "customer-invoice-available", "/customer/invoices", "light", VP.d1440, "invoice-available", "customer-invoices", { waitForTestId: "customer-invoices-list", role: "customer" }),
    s("frontend", "customer", "customer-invoice-unavailable", "/customer/invoices", "light", VP.d1440, "invoice-unavailable", "customer-invoices-empty", { waitForTestId: "customer-empty-state", role: "customer" }),
    s("frontend", "customer", "customer-profile", "/customer/profile", "light", VP.d1440, "profile", "customer-profile", { waitForTestId: "customer-profile-form", role: "customer" }),
    s("frontend", "customer", "customer-profile-validation", "/customer/profile", "light", VP.d1440, "profile-validation", "customer-profile-validation", {
      waitForTestId: "customer-profile-form",
      role: "customer",
      action: async (page) => {
        await page.getByRole("button", { name: /save/i }).click();
      },
    }),
    s("frontend", "customer", "customer-support", "/customer/support", "light", VP.d1440, "support", "customer-support", { waitForTestId: "customer-support-list", role: "customer" }),
    s("frontend", "customer", "customer-session-expired", "/customer/dashboard", "light", VP.d1440, "session-expired", "customer-session-expired", { waitForTestId: "customer-dashboard-error", role: "customer" }),
    s("frontend", "customer", "customer-loading", "/customer/dashboard", "light", VP.d1440, "loading", "customer-loading", { waitForTestId: "customer-dashboard-shell", role: "customer" }),
    s("frontend", "customer", "customer-api-error", "/customer/dashboard", "light", VP.d1440, "api-error", "customer-api-error", { waitForTestId: "customer-dashboard-error", role: "customer" }),
  ];
}

function buildAgent(): JpUi05Scenario[] {
  return [
    s("frontend", "agent", "agent-overview-light", "/agent/dashboard", "light", VP.d1440, "overview", "agent-overview", { waitForTestId: "agent-dashboard-overview", role: "agent" }),
    s("frontend", "agent", "agent-overview-dark", "/agent/dashboard", "dark", VP.d1440, "overview", "agent-overview", { waitForTestId: "agent-dashboard-overview", role: "agent" }),
    s("frontend", "agent", "agent-overview-system-light", "/agent/dashboard", "system-light", VP.d1440, "overview", "agent-overview", { waitForTestId: "agent-dashboard-overview", role: "agent" }),
    s("frontend", "agent", "agent-overview-system-dark", "/agent/dashboard", "system-dark", VP.d1440, "overview", "agent-overview", { waitForTestId: "agent-dashboard-overview", role: "agent" }),
    s("frontend", "agent", "agent-overview-mobile-light", "/agent/dashboard", "light", VP.m390, "overview-mobile", "agent-overview", { waitForTestId: "agent-dashboard-shell", role: "agent" }),
    s("frontend", "agent", "agent-overview-mobile-dark", "/agent/dashboard", "dark", VP.m390, "overview-mobile", "agent-overview", { waitForTestId: "agent-dashboard-shell", role: "agent" }),
    { ...s("frontend", "agent", "agent-overview-150-zoom", "/agent/dashboard", "light", VP.d1024, "zoom-150", "agent-overview", { waitForTestId: "agent-dashboard-overview", role: "agent" }), zoom: 1.5 },
    s("frontend", "agent", "agent-bookings-list", "/agent/bookings", "light", VP.d1440, "bookings", "agent-bookings", { waitForTestId: "agent-bookings-list", role: "agent" }),
    s("frontend", "agent", "agent-booking-detail", "/agent/bookings/BKG-2001", "light", VP.d1440, "booking-detail", "agent-booking-detail", { waitForTestId: "agent-booking-detail", role: "agent" }),
    s("frontend", "agent", "agent-wallet", "/agent/wallet", "light", VP.d1440, "wallet", "agent-wallet", { waitForTestId: "agent-wallet-overview", role: "agent" }),
    s("frontend", "agent", "agent-wallet-unavailable", "/agent/wallet", "light", VP.d1440, "wallet-unavailable", "agent-wallet-unavailable", { waitForTestId: "agent-permission-denied", role: "agent" }),
    s("frontend", "agent", "agent-ledger", "/agent/wallet/ledger", "light", VP.d1440, "ledger", "agent-ledger", { waitForTestId: "agent-ledger-list", role: "agent" }),
    s("frontend", "agent", "agent-ledger-empty", "/agent/wallet/ledger", "light", VP.d1440, "ledger-empty", "agent-ledger-empty", { waitForTestId: "agent-dashboard-empty", role: "agent" }),
    s("frontend", "agent", "agent-deposits", "/agent/deposits", "light", VP.d1440, "deposits", "agent-deposits", { waitForTestId: "agent-deposits-list", role: "agent" }),
    s("frontend", "agent", "agent-deposit-pending", "/agent/deposits", "light", VP.d1440, "deposit-pending", "agent-deposits-pending", { waitForTestId: "agent-deposits-list", role: "agent" }),
    s("frontend", "agent", "agent-profile", "/agent/profile", "light", VP.d1440, "profile", "agent-profile", { waitForTestId: "agent-profile-form", role: "agent" }),
    s("frontend", "agent", "agent-staff-permitted", "/agent/bookings", "light", VP.d1440, "staff-permitted", "agent-staff-permitted", { waitForTestId: "agent-bookings-list", role: "agent_staff" }),
    s("frontend", "agent", "agent-staff-owner-route-forbidden", "/agent/wallet", "light", VP.d1440, "staff-forbidden", "agent-staff-forbidden", { waitForTestId: "agent-permission-denied", role: "agent_staff" }),
    s("frontend", "agent", "agent-cross-agency-not-found", "/agent/bookings/BKG-OTHER", "light", VP.d1440, "cross-agency", "agent-cross-agency", { waitForTestId: "agent-dashboard-error", role: "agent" }),
    s("frontend", "agent", "agent-api-error", "/agent/dashboard", "light", VP.d1440, "api-error", "agent-api-error", { waitForTestId: "agent-dashboard-error", role: "agent" }),
  ];
}

function buildAdmin(): JpUi05Scenario[] {
  return [
    s("dashboard", "admin", "admin-overview-light", "/admin/dashboard", "light", VP.d1440, "overview", "admin-overview", { waitForTestId: "dashboard-shell", role: "admin" }),
    s("dashboard", "admin", "admin-overview-dark", "/admin/dashboard", "dark", VP.d1440, "overview", "admin-overview", { waitForTestId: "dashboard-shell", role: "admin" }),
    s("dashboard", "admin", "admin-overview-system-light", "/admin/dashboard", "system-light", VP.d1440, "overview", "admin-overview", { waitForTestId: "dashboard-shell", role: "admin" }),
    s("dashboard", "admin", "admin-overview-system-dark", "/admin/dashboard", "system-dark", VP.d1440, "overview", "admin-overview", { waitForTestId: "dashboard-shell", role: "admin" }),
    s("dashboard", "admin", "admin-overview-mobile-light", "/admin/dashboard", "light", VP.m390, "overview-mobile", "admin-overview", { waitForTestId: "dashboard-shell", role: "admin" }),
    s("dashboard", "admin", "admin-overview-mobile-dark", "/admin/dashboard", "dark", VP.m390, "overview-mobile", "admin-overview", { waitForTestId: "dashboard-shell", role: "admin" }),
    { ...s("dashboard", "admin", "admin-overview-150-zoom", "/admin/dashboard", "light", VP.d1024, "zoom-150", "admin-overview", { waitForTestId: "dashboard-shell", role: "admin" }), zoom: 1.5 },
    s("dashboard", "admin", "admin-action-kpis", "/admin/dashboard", "light", VP.d1440, "action-kpis", "admin-overview", { waitForTestId: "dashboard-shell", role: "admin" }),
    s("dashboard", "admin", "admin-bookings-list", "/admin/dashboard/bookings", "light", VP.d1440, "bookings", "admin-bookings", { waitForTestId: "bookings-filters", role: "admin" }),
    s("dashboard", "admin", "admin-booking-detail-or-stub", "/admin/dashboard/bookings", "light", VP.d1440, "booking-detail", "admin-bookings", { waitForTestId: "bookings-filters", role: "admin" }),
    s("dashboard", "admin", "admin-deposits", "/admin/dashboard/payments", "light", VP.d1440, "deposits", "admin-payments", { waitForTestId: "payments-filters", role: "admin" }),
    s("dashboard", "admin", "admin-payments", "/admin/dashboard/payments", "light", VP.d1440, "payments", "admin-payments", { waitForTestId: "payments-filters", role: "admin" }),
    s("dashboard", "admin", "admin-agencies", "/admin/dashboard/agents", "light", VP.d1440, "agencies", "admin-agents", { waitForTestId: "dashboard-shell", role: "admin" }),
    s("dashboard", "admin", "admin-staff", "/admin/dashboard/users", "light", VP.d1440, "staff", "admin-users", { waitForTestId: "users-workspace", role: "admin" }),
    s("dashboard", "admin", "admin-supplier-pnr-queue", "/admin/dashboard/pnrs", "light", VP.d1440, "pnr-queue", "admin-pnrs", { waitForTestId: "pnrs-filters", role: "admin" }),
    s("dashboard", "admin", "admin-cancellations-refunds", "/admin/dashboard/planned/bookings?queue=cancellations", "light", VP.d1440, "cancellations", "admin-planned", { waitForTestId: "dashboard-shell", role: "admin" }),
    s("dashboard", "admin", "admin-empty-state", "/admin/dashboard/customers", "light", VP.d1440, "empty", "admin-customers-empty", { waitForTestId: "dashboard-shell", role: "admin" }),
    s("dashboard", "admin", "platform-staff-permitted-route", "/staff/dashboard/bookings", "light", VP.d1440, "staff-permitted", "staff-bookings", { waitForTestId: "bookings-filters", role: "platform_staff" }),
    s("dashboard", "admin", "platform-staff-forbidden-route", "/staff/dashboard/users", "light", VP.d1440, "staff-forbidden", "staff-forbidden", { waitForTestId: "dashboard-shell", role: "platform_staff" }),
    s("dashboard", "admin", "dashboard-api-or-preview-error", "/admin/dashboard", "light", VP.d1440, "api-error", "admin-api-error", { waitForTestId: "dashboard-shell", role: "admin" }),
  ];
}

export const JP_UI_05_SCENARIOS: JpUi05Scenario[] = [
  ...buildLogin(),
  ...buildSignup(),
  ...buildRecovery(),
  ...buildManage(),
  ...buildCustomer(),
  ...buildAgent(),
  ...buildAdmin(),
];

export const EXPECTED_SCENARIO_COUNT = 132;
export const FRONTEND_SCENARIOS = JP_UI_05_SCENARIOS.filter((scenario) => scenario.application === "frontend");
export const DASHBOARD_SCENARIOS = JP_UI_05_SCENARIOS.filter((scenario) => scenario.application === "dashboard");

if (JP_UI_05_SCENARIOS.length !== EXPECTED_SCENARIO_COUNT) {
  throw new Error(`JP-UI-05 scenario registry must contain exactly ${EXPECTED_SCENARIO_COUNT} scenarios (found ${JP_UI_05_SCENARIOS.length})`);
}

const uniqueIds = new Set(JP_UI_05_SCENARIOS.map((scenario) => scenario.id));
if (uniqueIds.size !== JP_UI_05_SCENARIOS.length) {
  throw new Error("JP-UI-05 scenario registry contains duplicate ids");
}
