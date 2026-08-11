import { dashboardHref, type DashboardPortal } from "@/lib/portal-path";

type OverviewLinkInput = {
  laravelRoute?: string | null;
  queue?: string | null;
  href?: string | null;
};

/**
 * Map legacy overview laravelRoute metadata to Next dashboard paths.
 * Never hand operators to Blade presentation shells.
 */
export function resolveOverviewDashboardHref(
  portal: DashboardPortal,
  input: OverviewLinkInput,
): string {
  if (input.href && input.href.startsWith(`/${portal}/dashboard`)) {
    return input.href;
  }

  const route = input.laravelRoute ?? "";
  const queue = input.queue ?? "";

  if (route.includes("support")) {
    return dashboardHref(portal, "/support");
  }
  if (route.includes("agent-deposit") || route.includes("deposits")) {
    return dashboardHref(portal, "/deposits");
  }
  if (route.includes("payment") && !route.includes("booking")) {
    return dashboardHref(portal, "/payments");
  }
  if (route.includes("system-health") || route.includes("deployment")) {
    return dashboardHref(portal, "/system/health");
  }
  if (route.includes("go-live")) {
    return dashboardHref(portal, "/system/go-live");
  }

  if (queue === "needs_action") {
    return dashboardHref(portal, "/operations/execution");
  }
  if (queue === "cancellations") {
    return dashboardHref(portal, "/operations/review");
  }
  if (queue && queue !== "all") {
    return dashboardHref(portal, `/bookings?queue=${encodeURIComponent(queue)}`);
  }

  if (route.includes("booking")) {
    return dashboardHref(portal, "/bookings");
  }

  return dashboardHref(portal, "/");
}
