import type { AuditEvent, AuditTargetType } from "@/types/access-control";

const TARGET_ROUTE_MAP: Partial<Record<AuditTargetType, (id: string) => string | null>> = {
  user: (id) => `/users?selected=${encodeURIComponent(id)}`,
  role: (id) => `/users/roles?selected=${encodeURIComponent(id)}`,
  permission: () => `/users/permissions`,
  booking: (id) => `/bookings?selected=${encodeURIComponent(id)}`,
  payment: (id) => `/payments?selected=${encodeURIComponent(id)}`,
  customer: (id) => `/customers?selected=${encodeURIComponent(id)}`,
  supplier: (id) => `/suppliers?selected=${encodeURIComponent(id)}`,
  agent: (id) => `/agents?selected=${encodeURIComponent(id)}`,
  pnrOrder: (id) => `/pnrs?selected=${encodeURIComponent(id)}`,
  ticketDocument: (id) => `/tickets?selected=${encodeURIComponent(id)}`,
  report: () => `/reports`,
  cmsPage: () => `/cms/pages`,
  cmsSection: () => `/cms/sections`,
  setting: (id) => {
    if (id === "security") return "/settings/security";
    if (id === "general") return "/settings/general";
    if (id === "notifications") return "/settings/notifications";
    if (id === "integrations") return "/api-connections";
    return "/settings";
  },
  integration: () => `/api-connections`,
  auditEvent: (id) => `/audit?selected=${encodeURIComponent(id)}`,
  dashboard: () => `/`,
};

export function getAuditTargetHref(target: AuditEvent["target"]): string | null {
  const resolver = TARGET_ROUTE_MAP[target.type];
  if (!resolver) return null;
  return resolver(target.id);
}

export function getAuditActorHref(actor: AuditEvent["actor"]): string | null {
  if (actor.actorType !== "dashboardUser" || !actor.userId) return null;
  return `/users?selected=${encodeURIComponent(actor.userId)}`;
}
