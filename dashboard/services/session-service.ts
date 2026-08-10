import { createReadOnlyService, ReadOnlyServiceError, type ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { getDashboardMode } from "@/lib/preview";
import { mockUser } from "@/mocks/overview-fixtures";
import type { LaravelSessionPayload } from "@/lib/read-only/laravel/types";
import type { DashboardPortal } from "@/lib/portal-path";

export type DashboardNavItem = {
  label: string;
  href: string;
  key: string;
  target?: "dashboard" | "laravel";
};

export type DashboardSessionSummary = {
  id: string;
  displayName: string;
  email: string;
  roles: string[];
  permissions: string[];
  accountType: string;
  accountStatus: string;
  portalType: DashboardPortal;
  platformRole: string;
  sessionUsable: boolean;
  denialReason: string | null;
  requiresPasswordChange: boolean;
  requiresEmailVerification: boolean;
  landingRoute: string;
  navigation: DashboardNavItem[];
  capabilities: Record<string, boolean>;
  initials: string;
  unavailable?: boolean;
};

function toInitials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return "??";
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return `${parts[0][0] ?? ""}${parts[1][0] ?? ""}`.toUpperCase();
}

function unavailableSession(): DashboardSessionSummary {
  return {
    id: "unavailable",
    displayName: "Session unavailable",
    email: "—",
    roles: [],
    permissions: [],
    accountType: "unknown",
    accountStatus: "unknown",
    portalType: "admin",
    platformRole: "unknown",
    sessionUsable: false,
    denialReason: "session_unavailable",
    requiresPasswordChange: false,
    requiresEmailVerification: false,
    landingRoute: "/admin/dashboard",
    navigation: [],
    capabilities: {},
    initials: "??",
    unavailable: true,
  };
}

function fromFixture(portal: DashboardPortal = "admin"): DashboardSessionSummary {
  const isStaff = portal === "staff";
  return {
    id: isStaff ? "fixture-staff" : "fixture-admin",
    displayName: isStaff ? "Platform Staff" : mockUser.name,
    email: isStaff ? "staff@jetpakistan.test" : mockUser.email,
    roles: [isStaff ? "Platform Staff" : mockUser.role],
    permissions: isStaff
      ? ["dashboard.view", "bookings.view"]
      : ["dashboard.view", "bookings.view", "payments.view", "customers.view"],
    accountType: isStaff ? "platform_staff" : "platform_admin",
    accountStatus: "active",
    portalType: portal,
    platformRole: isStaff ? "platform_staff" : "platform_admin",
    sessionUsable: true,
    denialReason: null,
    requiresPasswordChange: false,
    requiresEmailVerification: false,
    landingRoute: `/${portal}/dashboard`,
    navigation: isStaff
      ? [
          { label: "Dashboard", href: "/", key: "dashboard" },
          { label: "Bookings", href: "/bookings", key: "bookings" },
          { label: "Support & Help", href: "/support", key: "support" },
        ]
      : [
          { label: "Dashboard", href: "/", key: "dashboard" },
          { label: "Bookings", href: "/bookings", key: "bookings" },
          { label: "Payments", href: "/payments", key: "payments" },
          { label: "Cancellations", href: "/operations/review", key: "cancellations" },
          { label: "Execution", href: "/operations/execution", key: "execution" },
          { label: "Reports", href: "/reports", key: "reports" },
          { label: "Support & Help", href: "/support", key: "support" },
        ],
    capabilities: {
      can_review_payment: !isStaff,
      can_review_deposit: portal === "admin",
      can_review_cancellation: true,
      can_review_refund: portal === "admin",
    },
    initials: isStaff ? "PS" : mockUser.initials,
  };
}

function fromLaravel(payload: LaravelSessionPayload, portal: DashboardPortal): DashboardSessionSummary {
  return {
    id: payload.id,
    displayName: payload.displayName,
    email: payload.email ?? "—",
    roles: payload.roles,
    permissions: payload.permissions,
    accountType: payload.accountType,
    accountStatus: payload.accountStatus,
    portalType: (payload.portalType as DashboardPortal) ?? portal,
    platformRole: payload.platformRole ?? payload.accountType,
    sessionUsable: payload.sessionUsable ?? true,
    denialReason: payload.denialReason ?? null,
    requiresPasswordChange: payload.requiresPasswordChange ?? false,
    requiresEmailVerification: payload.requiresEmailVerification ?? false,
    landingRoute: payload.landingRoute ?? `/${portal}/dashboard`,
    navigation: (payload.navigation as DashboardNavItem[]) ?? [],
    capabilities: (payload.capabilities as Record<string, boolean>) ?? {},
    initials: toInitials(payload.displayName),
  };
}

const sessionService = createReadOnlyService<
  { portal?: DashboardPortal },
  DashboardSessionSummary
>({
  module: "session",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(query, options) {
      await new Promise((r) => setTimeout(r, 40));
      return createReadOnlyEnvelope({
        data: fromFixture(query.portal ?? "admin"),
        metadata: options?.metadata,
      });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(query, options) {
      const portal = query.portal ?? "admin";
      const envelope = await fetchDashboardApi<LaravelSessionPayload>(DASHBOARD_API_ROUTES.session, {
        signal: options?.signal,
        query: { portal },
      });
      return { ...envelope, data: fromLaravel(envelope.data, portal) };
    },
  },
});

export async function getDashboardSession(
  options?: ReadOnlyFetchOptions & { portal?: DashboardPortal },
): Promise<DashboardSessionSummary> {
  if (getDashboardMode() === "live") {
    try {
      const envelope = await sessionService.fetchReadOnly({ portal: options?.portal }, options);
      return envelope.data;
    } catch {
      return unavailableSession();
    }
  }

  const envelope = await sessionService.fetchReadOnly({ portal: options?.portal }, options);
  return envelope.data;
}

export { ReadOnlyServiceError as SessionServiceError };
