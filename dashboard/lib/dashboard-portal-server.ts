import { headers } from "next/headers";
import { detectPortalFromPathname, type DashboardPortal } from "@/lib/portal-path";

export async function resolveDashboardPortalFromRequest(): Promise<DashboardPortal> {
  const headerStore = await headers();
  const portalHeader = headerStore.get("x-dashboard-portal");
  if (portalHeader === "staff") {
    return "staff";
  }
  if (portalHeader === "admin") {
    return "admin";
  }

  const pathname = headerStore.get("x-dashboard-pathname") ?? "";
  const detected = detectPortalFromPathname(pathname);
  return detected ?? "admin";
}
