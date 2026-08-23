/** Legacy `/planned/[slug]` bookmarks → canonical Next paths only (no Blade handoff). */
import { dashboardHref, type DashboardPortal } from "@/lib/portal-path";

type RedirectTarget = { kind: "next"; path: string };

const PLANNED_REDIRECTS: Record<string, RedirectTarget> = {
  bookings: { kind: "next", path: "/bookings" },
  customers: { kind: "next", path: "/customers" },
  agents: { kind: "next", path: "/agents" },
  users: { kind: "next", path: "/users" },
  suppliers: { kind: "next", path: "/suppliers" },
  reports: { kind: "next", path: "/reports" },
  settings: { kind: "next", path: "/settings" },
  "api-connections": { kind: "next", path: "/integrations" },
  "api-settings": { kind: "next", path: "/integrations" },
  integrations: { kind: "next", path: "/integrations" },
  support: { kind: "next", path: "/support" },
  "page-settings": { kind: "next", path: "/cms" },
  markups: { kind: "next", path: "/markups" },
  communications: { kind: "next", path: "/settings/notifications" },
  diagnostics: { kind: "next", path: "/system/health" },
  "group-ticketing": { kind: "next", path: "/group-ticketing" },
  accounting: { kind: "next", path: "/accounting" },
  flights: { kind: "next", path: "/" },
};

export function resolvePlannedRedirect(portal: DashboardPortal, slug: string): string {
  const target = PLANNED_REDIRECTS[slug] ?? { kind: "next", path: "/" };
  return dashboardHref(portal, target.path);
}

export const plannedRedirectSlugs = Object.keys(PLANNED_REDIRECTS);
