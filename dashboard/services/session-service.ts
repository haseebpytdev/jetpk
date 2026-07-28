import { createReadOnlyService, ReadOnlyServiceError, type ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { mockUser } from "@/mocks/overview-fixtures";
import type { LaravelSessionPayload } from "@/lib/read-only/laravel/types";

export type DashboardSessionSummary = {
  id: string;
  displayName: string;
  email: string;
  roles: string[];
  permissions: string[];
  accountType: string;
  accountStatus: string;
  initials: string;
};

function toInitials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return "??";
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return `${parts[0][0] ?? ""}${parts[1][0] ?? ""}`.toUpperCase();
}

function fromFixture(): DashboardSessionSummary {
  return {
    id: "fixture-admin",
    displayName: mockUser.name,
    email: mockUser.email,
    roles: [mockUser.role],
    permissions: ["dashboard.view", "bookings.view", "payments.view", "customers.view"],
    accountType: "platform_admin",
    accountStatus: "active",
    initials: mockUser.initials,
  };
}

function fromLaravel(payload: LaravelSessionPayload): DashboardSessionSummary {
  return {
    id: payload.id,
    displayName: payload.displayName,
    email: payload.email ?? "—",
    roles: payload.roles,
    permissions: payload.permissions,
    accountType: payload.accountType,
    accountStatus: payload.accountStatus,
    initials: toInitials(payload.displayName),
  };
}

const sessionService = createReadOnlyService<Record<string, never>, DashboardSessionSummary>({
  module: "session",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(_query, options) {
      await new Promise((r) => setTimeout(r, 40));
      return createReadOnlyEnvelope({ data: fromFixture(), metadata: options?.metadata });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(_query, options) {
      const envelope = await fetchDashboardApi<LaravelSessionPayload>(DASHBOARD_API_ROUTES.session, {
        signal: options?.signal,
      });
      return { ...envelope, data: fromLaravel(envelope.data) };
    },
  },
});

export async function getDashboardSession(options?: ReadOnlyFetchOptions): Promise<DashboardSessionSummary> {
  const envelope = await sessionService.fetchReadOnly({}, options);
  return envelope.data;
}

export { ReadOnlyServiceError as SessionServiceError };
