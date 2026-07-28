import { buildUsersPage } from "@/lib/users/query-filters";
import { getUserById, mockUsers, USER_FIXTURE_COUNTS } from "@/mocks/user-fixtures";
import { mockRoles, RBAC_FIXTURE_COUNTS } from "@/mocks/rbac-fixtures";
import type { User } from "@/types/access-control";
import type { UsersModuleResult, UsersQuery } from "@/types/users";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { createReadOnlyService, ReadOnlyServiceError, type ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { transformUserDetail, transformUsersModule } from "@/lib/read-only/laravel/transformers/users";
import type { LaravelUsersListPayload } from "@/lib/read-only/laravel/types";

export class UsersServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "UsersServiceError";
    this.referenceId = referenceId;
  }
}

const roleFacets = mockRoles.map((r) => ({ id: r.id, name: r.name }));

function mapReadOnlyError(error: unknown): never {
  if (error instanceof ReadOnlyServiceError) {
    throw new UsersServiceError(error.envelope.error.message, error.envelope.error.referenceIdSafe);
  }
  throw error;
}

function toLaravelQuery(query: UsersQuery): Record<string, string | number> {
  return {
    page: query.page,
    pageSize: query.pageSize,
    q: query.search,
    status: query.status,
    accountType: query.userType,
    role: query.role,
    verificationState: query.verification,
    department: query.department,
    sort: query.sort,
    direction: query.direction,
  };
}

function buildFixtureResult(query: UsersQuery): UsersModuleResult {
  if (query.previewLoading) {
    return {
      state: "loading",
      query,
      summary: {
        totalUsers: 0,
        activeUsers: 0,
        invitedUsers: 0,
        lockedUsers: 0,
        suspendedUsers: 0,
        mfaEnabledUsers: 0,
        usersWithoutRoles: 0,
        usersRequiringReview: 0,
      },
      table: { rows: [], total: 0, page: 1, pageSize: query.pageSize, pageCount: 1 },
      facets: { departments: [], roles: [], statuses: [], userTypes: [] },
      selectedUser: null,
      validationSummary: { valid: 0, warning: 0, blocked: 0, review: 0 },
    };
  }

  const sourceUsers = query.previewEmpty ? [] : mockUsers;
  const page = buildUsersPage(query, sourceUsers, roleFacets);
  const selectedUser = query.selected ? getUserById(query.selected) ?? null : null;

  return {
    state: page.total === 0 ? "empty" : "ready",
    query,
    summary: page.summary,
    table: {
      rows: page.users,
      total: page.total,
      page: page.page,
      pageSize: page.pageSize,
      pageCount: page.pageCount,
    },
    facets: page.facets,
    selectedUser,
    validationSummary: {
      valid: mockUsers.filter((u) => u.validationState === "valid").length,
      warning: mockUsers.filter((u) => u.validationState === "warning").length,
      blocked: mockUsers.filter((u) => u.validationState === "blocked").length,
      review: mockUsers.filter((u) => u.validationState === "review").length,
    },
  };
}

const usersService = createReadOnlyService<UsersQuery, UsersModuleResult>({
  module: "users",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(query, options) {
      if (query.previewError) {
        throw new ReadOnlyServiceError({
          error: {
            code: "internal_error",
            message: "Mock users service returned a recoverable error (preview simulation).",
            referenceIdSafe: "USR-PREVIEW-SIM-ERR",
          },
          meta: { source: "fixture", schemaVersion: "dash-read-only-v1" },
        });
      }
      await new Promise((r) => setTimeout(r, 60));
      return createReadOnlyEnvelope({ data: buildFixtureResult(query), metadata: options?.metadata });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(query, options) {
      const envelope = await fetchDashboardApi<LaravelUsersListPayload>(DASHBOARD_API_ROUTES.users, {
        signal: options?.signal,
        query: toLaravelQuery(query),
      });
      const pagination = envelope.pagination ?? { page: 1, pageSize: 25, total: 0, pageCount: 1 };
      let selectedUser: User | null = null;
      if (query.selected) {
        try {
          const detail = await fetchDashboardApi<Record<string, unknown>>(DASHBOARD_API_ROUTES.userDetail(query.selected), {
            signal: options?.signal,
          });
          selectedUser = transformUserDetail(detail.data);
        } catch (error) {
          if (!(error instanceof ReadOnlyServiceError && error.envelope.error.code === "not_found")) {
            throw error;
          }
        }
      }
      return {
        ...envelope,
        data: transformUsersModule(envelope.data, query, pagination, selectedUser),
      };
    },
  },
});

export async function getUsersModule(query: UsersQuery, options?: ReadOnlyFetchOptions): Promise<UsersModuleResult> {
  try {
    const envelope = await usersService.fetchReadOnly(query, options);
    return envelope.data;
  } catch (error) {
    mapReadOnlyError(error);
  }
}

export async function getUserDetail(id: string, options?: ReadOnlyFetchOptions): Promise<User | null> {
  const { resolveDataSourceMode } = await import("@/lib/read-only/data-source");
  const mode = resolveDataSourceMode();

  if (mode === "fixture") {
    await new Promise((r) => setTimeout(r, 40));
    return getUserById(id) ?? null;
  }

  try {
    const envelope = await fetchDashboardApi<Record<string, unknown>>(DASHBOARD_API_ROUTES.userDetail(id), {
      signal: options?.signal,
    });
    return transformUserDetail(envelope.data);
  } catch (error) {
    if (error instanceof ReadOnlyServiceError && error.envelope.error.code === "not_found") {
      return null;
    }
    mapReadOnlyError(error);
  }
}

export function getFixtureCounts() {
  return {
    users: USER_FIXTURE_COUNTS.users,
    roles: RBAC_FIXTURE_COUNTS.roles,
    permissions: RBAC_FIXTURE_COUNTS.permissions,
  };
}
