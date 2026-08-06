import type { SessionBootstrap } from "@/features/auth/types";

const FIXTURE_COOKIE = "ota_session_fixture";

const BASE_FIXTURE_FIELDS = {
  requires_otp: false,
  requires_password_change: false,
  requires_email_verification: false,
  account_status: "active" as const,
  email_verified: true,
  session_usable: true,
  csrf_ready: true,
  logout: { method: "POST" as const, path: "/logout" },
};

const CUSTOMER_BOOTSTRAP: SessionBootstrap = {
  ...BASE_FIXTURE_FIELDS,
  authenticated: true,
  user: {
    id: "fixture-customer-1",
    name: "Ayesha Khan",
    email: "ayesha.khan@example.com",
    account_type: "customer",
  },
  role: "customer",
  portal_type: "customer",
  agency_id: null,
  agency_role: null,
  permissions: [],
  dashboard_url: "/customer/dashboard",
  landing_route: "/customer/dashboard",
};

const AGENT_BOOTSTRAP: SessionBootstrap = {
  ...BASE_FIXTURE_FIELDS,
  authenticated: true,
  user: {
    id: "fixture-agent-1",
    name: "Agency Owner",
    email: "agent@example.com",
    account_type: "agent",
  },
  role: "agent",
  portal_type: "agent",
  agency_id: "1",
  agency_role: "owner",
  permissions: [],
  dashboard_url: "/agent/dashboard",
  landing_route: "/agent/dashboard",
};

export const AGENT_STAFF_BOOTSTRAP: SessionBootstrap = {
  ...BASE_FIXTURE_FIELDS,
  authenticated: true,
  user: {
    id: "fixture-agent-staff-1",
    name: "Agency Staff",
    email: "staff@example.com",
    account_type: "agent_staff",
  },
  role: "agent_staff",
  portal_type: "agent",
  agency_id: "1",
  agency_role: "staff",
  permissions: ["bookings.view"],
  dashboard_url: "/agent/dashboard",
  landing_route: "/agent/dashboard",
};

const OTP_BOOTSTRAP: SessionBootstrap = {
  authenticated: false,
  requires_otp: true,
  csrf_ready: true,
  logout: { method: "POST", path: "/logout" },
};

const CUSTOMER_DISABLED_BOOTSTRAP: SessionBootstrap = {
  ...CUSTOMER_BOOTSTRAP,
  account_status: "inactive",
  session_usable: false,
};

const AGENT_DISABLED_BOOTSTRAP: SessionBootstrap = {
  ...AGENT_BOOTSTRAP,
  account_status: "suspended",
  session_usable: false,
};

const EXPIRED_SESSION_BOOTSTRAP: SessionBootstrap = {
  authenticated: false,
  session_expired: true,
};

/**
 * Non-production smoke-test fixture selected via `ota_session_fixture` cookie.
 * Allows Playwright to exercise SSR portal guards without a live Laravel process.
 */
export function resolveSessionBootstrapFixture(
  cookies: Array<{ name: string; value: string }>,
): SessionBootstrap | null {
  if (process.env.OTA_ALLOW_SESSION_FIXTURE !== "true") {
    return null;
  }

  const fixture = cookies.find((cookie) => cookie.name === FIXTURE_COOKIE)?.value;
  if (!fixture) {
    return null;
  }

  if (fixture === "customer") {
    return CUSTOMER_BOOTSTRAP;
  }

  if (fixture === "agent") {
    return AGENT_BOOTSTRAP;
  }

  if (fixture === "agent_staff") {
    return AGENT_STAFF_BOOTSTRAP;
  }

  if (fixture === "anonymous") {
    return { authenticated: false };
  }

  if (fixture === "expired") {
    return EXPIRED_SESSION_BOOTSTRAP;
  }

  if (fixture === "customer_disabled") {
    return CUSTOMER_DISABLED_BOOTSTRAP;
  }

  if (fixture === "agent_disabled") {
    return AGENT_DISABLED_BOOTSTRAP;
  }

  if (fixture === "otp") {
    return OTP_BOOTSTRAP;
  }

  if (fixture === "customer_force_password") {
    return {
      ...CUSTOMER_BOOTSTRAP,
      requires_password_change: true,
    };
  }

  if (fixture === "agent_force_password") {
    return {
      ...AGENT_BOOTSTRAP,
      requires_password_change: true,
    };
  }

  return null;
}

export const sessionFixtureCookieName = FIXTURE_COOKIE;
