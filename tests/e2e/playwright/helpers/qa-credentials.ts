/**
 * Load JP-DASH-03 QA passwords from process env only.
 * Never log or serialize password values.
 */
export type QaRole = "admin" | "staff" | "agent" | "customer";

const ENV_KEYS: Record<QaRole, string> = {
  admin: "JP_DASH_03_QA_ADMIN_PASSWORD",
  staff: "JP_DASH_03_QA_STAFF_PASSWORD",
  agent: "JP_DASH_03_QA_AGENT_PASSWORD",
  customer: "JP_DASH_03_QA_CUSTOMER_PASSWORD",
};

export const QA_EMAILS: Record<QaRole, string> = {
  admin: "jp-dash-03-qa-admin@jetpakistan.pk",
  staff: "jp-dash-03-qa-staff@jetpakistan.pk",
  agent: "jp-dash-03-qa-agent@jetpakistan.pk",
  customer: "jp-dash-03-qa-customer@jetpakistan.pk",
};

/** Preferred Next dashboard entry when the role uses the Next shell. */
export const QA_DASHBOARD_PATHS: Record<QaRole, string> = {
  admin: "/admin/dashboard",
  staff: "/staff/dashboard",
  agent: "/agent/dashboard",
  customer: "/customer/dashboard",
};

/**
 * Accepted post-login pathname prefixes from ClientRedirectResolver / current product.
 * Agent/Customer currently land on Blade portal roots; Admin/Staff on Next dashboard.
 */
export const QA_LOGIN_LANDING_PREFIXES: Record<QaRole, string[]> = {
  admin: ["/admin/dashboard"],
  staff: ["/staff/dashboard", "/staff"],
  agent: ["/agent", "/agent/dashboard"],
  customer: ["/customer/bookings", "/customer", "/customer/dashboard", "/dashboard"],
};

export function loadQaPassword(role: QaRole): string | null {
  const value = process.env[ENV_KEYS[role]]?.trim() ?? "";
  return value !== "" ? value : null;
}

export function requireQaPassword(role: QaRole): string {
  const password = loadQaPassword(role);
  if (!password) {
    throw new Error(`${ENV_KEYS[role]} must be set in the process environment (never commit).`);
  }
  return password;
}

export function qaPasswordEnvKey(role: QaRole): string {
  return ENV_KEYS[role];
}

export function pathMatchesQaLanding(role: QaRole, pathname: string): boolean {
  return QA_LOGIN_LANDING_PREFIXES[role].some(
    (prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`) || pathname.startsWith(prefix),
  );
}
