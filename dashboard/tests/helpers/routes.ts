export const ADMIN_DASHBOARD_BASE = "/admin/dashboard";
export const STAFF_DASHBOARD_BASE = "/staff/dashboard";

export function adminDashboardPath(path = ""): string {
  const normalized = path.startsWith("/") ? path : path === "" ? "" : `/${path}`;
  return `${ADMIN_DASHBOARD_BASE}${normalized}`;
}

export function staffDashboardPath(path = ""): string {
  const normalized = path.startsWith("/") ? path : path === "" ? "" : `/${path}`;
  return `${STAFF_DASHBOARD_BASE}${normalized}`;
}

/** @deprecated Use adminDashboardPath — retained for incremental test migration */
export const LEGACY_TESTDASH_BASE = ADMIN_DASHBOARD_BASE;
