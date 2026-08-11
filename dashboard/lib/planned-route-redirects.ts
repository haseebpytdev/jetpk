import { dashboardHref, type DashboardPortal } from "@/lib/portal-path";
import { sanitizePublicHref } from "@/lib/sanitize-public-href";

type RedirectTarget =
  | { kind: "next"; path: string }
  | { kind: "laravel"; path: string };

/** Legacy `/planned/[slug]` bookmarks → canonical Next or Laravel handoff. */
const PLANNED_REDIRECTS: Record<string, RedirectTarget> = {
  bookings: { kind: "next", path: "/bookings" },
  customers: { kind: "next", path: "/customers" },
  agents: { kind: "next", path: "/agents" },
  users: { kind: "next", path: "/users" },
  suppliers: { kind: "next", path: "/suppliers" },
  reports: { kind: "next", path: "/reports" },
  settings: { kind: "next", path: "/settings" },
  support: { kind: "next", path: "/support" },
  "page-settings": { kind: "next", path: "/cms" },
  markups: { kind: "laravel", path: "/admin/markups" },
  flights: { kind: "laravel", path: "/flights/search" },
  communications: { kind: "laravel", path: "/admin/settings/communications" },
  diagnostics: { kind: "laravel", path: "/admin/system-health" },
  "group-ticketing": { kind: "laravel", path: "/admin/group-ticketing" },
  accounting: { kind: "laravel", path: "/admin/ledger" },
};

export function resolvePlannedRedirect(portal: DashboardPortal, slug: string): string {
  const target = PLANNED_REDIRECTS[slug] ?? { kind: "next", path: "/" };
  if (target.kind === "next") {
    return dashboardHref(portal, target.path);
  }
  return sanitizePublicHref(target.path);
}

export const plannedRedirectSlugs = Object.keys(PLANNED_REDIRECTS);
