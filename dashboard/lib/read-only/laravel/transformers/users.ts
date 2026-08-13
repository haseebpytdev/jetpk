import type { User, UserType, UserStatus, MfaState, ValidationState } from "@/types/access-control";
import type { UsersModuleResult, UsersQuery, UserTableRow } from "@/types/users";
import type { LaravelUsersListPayload } from "@/lib/read-only/laravel/types";

export function transformUsersModule(
  payload: LaravelUsersListPayload,
  query: UsersQuery,
  pagination: { page: number; pageSize: number; total: number; pageCount: number },
  selectedUser: User | null,
): UsersModuleResult {
  const users = (Array.isArray((payload as { users?: unknown }).users)
    ? (payload as { users: UserTableRow[] }).users
    : Array.isArray((payload as { items?: unknown }).items)
      ? ((payload as { items: UserTableRow[] }).items)
      : []) as UserTableRow[];
  const departments = [...new Set(users.map((u) => u.department).filter((d) => d && d !== "—"))];
  const roles = users.flatMap((u) =>
    (u.assignedRoleNames ?? []).map((name, i) => ({
      id: `role-${i}`,
      name,
    })),
  );
  const uniqueRoles = [...new Map(roles.map((r) => [r.name, r])).values()];
  const payloadFacets = (payload as { facets?: { userTypes?: UserType[]; statuses?: UserStatus[] } }).facets;

  return {
    state: pagination.total === 0 ? "empty" : "ready",
    query,
    summary: payload.summary ?? {
      totalUsers: pagination.total,
      activeUsers: users.filter((u) => u.status === "active").length,
      invitedUsers: users.filter((u) => u.status === "invited").length,
      lockedUsers: users.filter((u) => u.status === "locked").length,
      suspendedUsers: users.filter((u) => u.status === "suspended").length,
      mfaEnabledUsers: users.filter((u) => u.mfaState === "enabled").length,
      usersWithoutRoles: users.filter((u) => !u.assignedRoleNames?.length).length,
      usersRequiringReview: users.filter((u) => u.validationState === "review").length,
    },
    table: {
      rows: users,
      total: pagination.total,
      page: pagination.page,
      pageSize: pagination.pageSize,
      pageCount: pagination.pageCount,
    },
    facets: {
      departments,
      roles: uniqueRoles,
      statuses: (payloadFacets?.statuses as UserStatus[]) ?? (["active", "invited", "suspended", "locked", "disabled"] as UserStatus[]),
      userTypes:
        (payloadFacets?.userTypes as UserType[]) ??
        ([...new Set(users.map((u) => u.userType))] as UserType[]),
    },
    selectedUser,
    validationSummary: {
      valid: users.filter((u) => u.validationState === "valid").length,
      warning: users.filter((u) => u.validationState === "warning").length,
      blocked: users.filter((u) => u.validationState === "blocked").length,
      review: users.filter((u) => u.validationState === "review").length,
    },
  };
}

export function transformUserDetail(payload: Record<string, unknown>): User {
  const base = payload;
  return {
    id: String(base.id ?? ""),
    profile: {
      fullName: String(base.fullName ?? ""),
      displayName: String(base.displayName ?? base.fullName ?? ""),
      department: String(base.department ?? ""),
      jobTitle: String(base.jobTitle ?? ""),
      userType: (base.userType as UserType) ?? "administrator",
    },
    contact: {
      email: String(base.email ?? ""),
      phone: base.phone ? String(base.phone) : null,
      phoneExtension: null,
    },
    assignedRoles: Array.isArray(base.assignedRoles)
      ? (base.assignedRoles as User["assignedRoles"])
      : [],
    effectiveAccess: (base.effectiveAccess as User["effectiveAccess"]) ?? {
      roleLabels: [],
      permissionGroups: [],
      highRiskPermissions: [],
      scope: "allRecords",
      previewOnly: true,
    },
    security: {
      status: (base.status as UserStatus) ?? "active",
      verificationState: (base.verificationState as User["security"]["verificationState"]) ?? "verified",
      mfaState: (base.mfaState as MfaState) ?? "unknown",
      invitationState: "accepted",
      securityState: (base.securityState as User["security"]["securityState"]) ?? "normal",
      failedSignInCount: 0,
      activeSessionCount: Number(base.activeSessionCount ?? 0),
      lastSignInAt: base.lastSignInAt ? String(base.lastSignInAt) : null,
      mfaRequired: Boolean(base.mfaRequired),
    },
    activity: {
      recentActions: [],
      lastViewedModule: null,
      signInCount30d: 0,
      recordViews30d: 0,
    },
    session: {
      activeSessionCount: Number(base.activeSessionCount ?? 0),
      lastSignInAt: base.lastSignInAt ? String(base.lastSignInAt) : null,
      lastSignInMaskedLocation: null,
    },
    validationState: (base.validationState as ValidationState) ?? "valid",
    validationIssues: Array.isArray(base.validationIssues) ? (base.validationIssues as User["validationIssues"]) : [],
    createdAt: String(base.createdAt ?? ""),
    updatedAt: String(base.updatedAt ?? ""),
    createdBy: "system",
    updatedBy: "system",
    notes: null,
  };
}
