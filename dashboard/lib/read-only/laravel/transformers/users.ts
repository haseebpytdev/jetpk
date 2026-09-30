import type { User, UserType, UserStatus, MfaState, ValidationState } from "@/types/access-control";
import type { UsersModuleResult, UsersQuery, UserTableRow } from "@/types/users";
import type { LaravelUsersListPayload } from "@/lib/read-only/laravel/types";

export function transformUsersModule(
  payload: LaravelUsersListPayload,
  query: UsersQuery,
  pagination: { page: number; pageSize: number; total: number; pageCount: number },
  selectedUser: User | null,
): UsersModuleResult {
  const users = payload.users as UserTableRow[];
  const departments = [...new Set(users.map((u) => u.department).filter(Boolean))];
  const roles = users.flatMap((u) =>
    (u.assignedRoleNames ?? []).map((name, i) => ({
      id: `role-${i}`,
      name,
    })),
  );
  const uniqueRoles = [...new Map(roles.map((r) => [r.name, r])).values()];

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
      statuses: ["active", "invited", "suspended", "locked", "disabled"] as UserStatus[],
      userTypes: [...new Set(users.map((u) => u.userType))] as UserType[],
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

function normalizeEffectiveAccess(raw: unknown, roleIds: string[] = []): User["effectiveAccess"] {
  const ea = (raw && typeof raw === "object" ? raw : {}) as Record<string, unknown>;
  const highRiskPermissions = Array.isArray(ea.highRiskPermissions)
    ? (ea.highRiskPermissions as string[])
    : [];
  const domains = Array.isArray(ea.domains)
    ? (ea.domains as User["effectiveAccess"]["domains"])
    : [];
  const totalPermissions =
    typeof ea.totalPermissions === "number"
      ? ea.totalPermissions
      : Array.isArray(ea.permissionGroups)
        ? ea.permissionGroups.length
        : highRiskPermissions.length;

  return {
    domains,
    totalPermissions,
    highRiskPermissions,
    roleIds: Array.isArray(ea.roleIds) ? (ea.roleIds as string[]) : roleIds,
  };
}

export function transformUserDetail(payload: Record<string, unknown>): User {
  const base = payload;
  const assignedRolesRaw = Array.isArray(base.assignedRoles) ? base.assignedRoles : [];
  const assignedRoles = assignedRolesRaw.map((entry) => {
    const row = (entry && typeof entry === "object" ? entry : {}) as Record<string, unknown>;
    return {
      roleId: String(row.roleId ?? row.id ?? ""),
      assignedAt: String(row.assignedAt ?? ""),
      assignedBy: String(row.assignedBy ?? "system"),
      source: (row.source as User["assignedRoles"][number]["source"]) ?? "system",
    };
  });

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
    assignedRoles,
    effectiveAccess: normalizeEffectiveAccess(
      base.effectiveAccess,
      assignedRoles.map((r) => r.roleId).filter(Boolean),
    ),
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
