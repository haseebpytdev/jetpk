export type DashboardPortal = "admin" | "staff";

export const DASHBOARD_PORTALS: DashboardPortal[] = ["admin", "staff"];

export function isDashboardPortal(value: string): value is DashboardPortal {
  return value === "admin" || value === "staff";
}

export function dashboardBasePath(portal: DashboardPortal): string {
  return `/${portal}/dashboard`;
}

export function dashboardHref(portal: DashboardPortal, path = ""): string {
  const normalized = path.startsWith("/") ? path : path === "" ? "" : `/${path}`;
  return `${dashboardBasePath(portal)}${normalized}`;
}

export function stripDashboardBasePath(pathname: string): string {
  for (const portal of DASHBOARD_PORTALS) {
    const base = dashboardBasePath(portal);
    if (pathname === base || pathname === `${base}/`) {
      return "/";
    }
    if (pathname.startsWith(`${base}/`)) {
      return pathname.slice(base.length) || "/";
    }
  }

  return pathname;
}

export function detectPortalFromPathname(pathname: string): DashboardPortal | null {
  for (const portal of DASHBOARD_PORTALS) {
    const base = dashboardBasePath(portal);
    if (pathname === base || pathname.startsWith(`${base}/`)) {
      return portal;
    }
  }

  return null;
}
