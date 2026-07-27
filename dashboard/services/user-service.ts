import { useMockData } from "@/lib/preview";
import { buildUsersPage } from "@/lib/users/query-filters";
import { getUserById, mockUsers, USER_FIXTURE_COUNTS } from "@/mocks/user-fixtures";
import { mockRoles, RBAC_FIXTURE_COUNTS } from "@/mocks/rbac-fixtures";
import type { UsersModuleResult, UsersQuery } from "@/types/users";

export class UsersServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "UsersServiceError";
    this.referenceId = referenceId;
  }
}

const roleFacets = mockRoles.map((r) => ({ id: r.id, name: r.name }));

export async function getUsersModule(query: UsersQuery): Promise<UsersModuleResult> {
  if (!useMockData()) {
    throw new UsersServiceError("Live user data is disabled in preview.", "USR-PREVIEW-NO-LIVE");
  }

  if (query.previewError) {
    throw new UsersServiceError(
      "Mock users service returned a recoverable error (preview simulation).",
      "USR-PREVIEW-SIM-ERR",
    );
  }

  await new Promise((r) => setTimeout(r, 60));

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

  const validationSummary = {
    valid: mockUsers.filter((u) => u.validationState === "valid").length,
    warning: mockUsers.filter((u) => u.validationState === "warning").length,
    blocked: mockUsers.filter((u) => u.validationState === "blocked").length,
    review: mockUsers.filter((u) => u.validationState === "review").length,
  };

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
    validationSummary,
  };
}

export async function getUserDetail(id: string) {
  if (!useMockData()) return null;
  await new Promise((r) => setTimeout(r, 40));
  return getUserById(id) ?? null;
}

export function getFixtureCounts() {
  return {
    users: USER_FIXTURE_COUNTS.users,
    roles: RBAC_FIXTURE_COUNTS.roles,
    permissions: RBAC_FIXTURE_COUNTS.permissions,
  };
}
