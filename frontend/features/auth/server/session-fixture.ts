import type { SessionBootstrap } from "@/features/auth/types";

const FIXTURE_COOKIE = "ota_session_fixture";

const CUSTOMER_BOOTSTRAP: SessionBootstrap = {
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

const AGENT_BOOTSTRAP: SessionBootstrap = {
  authenticated: true,
  user: {
    id: "fixture-agent-1",
    name: "Agency Owner",
    email: "agent@example.com",
    account_type: "agent",
  },
  role: "agent",
  permissions: [],
  dashboard_url: "/agent",
  requires_otp: false,
  requires_password_change: false,
  account_status: "active",
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

  if (fixture === "anonymous") {
    return { authenticated: false };
  }

  return null;
}

export const sessionFixtureCookieName = FIXTURE_COOKIE;
