import type { PermissionGroup, Role, RoleCategory } from "@/types/access-control";
import type { RolesModuleResult, RolesQuery, RoleTableRow } from "@/types/roles";
import type { LaravelRolesListPayload } from "@/lib/read-only/laravel/types";

export function transformRolesModule(
  payload: LaravelRolesListPayload,
  query: RolesQuery,
  pagination: { page: number; pageSize: number; total: number; pageCount: number },
  selectedRole: Role | null,
): RolesModuleResult {
  const roles = payload.roles as RoleTableRow[];

  return {
    state: pagination.total === 0 ? "empty" : "ready",
    query,
    summary: payload.summary ?? {
      totalRoles: pagination.total,
      activeRoles: roles.filter((r) => r.status === "active").length,
      protectedSystemRoles: roles.filter((r) => r.isProtected).length,
      customRoles: roles.filter((r) => !r.isSystem).length,
      rolesWithHighRiskPermissions: roles.filter((r) => r.highRiskPermissionCount > 0).length,
      rolesRequiringReview: roles.filter((r) => r.validationState === "review").length,
      unusedRoles: roles.filter((r) => r.assignedUserCount === 0).length,
      incompleteRoles: roles.filter((r) => r.validationState === "blocked").length,
    },
    table: {
      rows: roles,
      total: pagination.total,
      page: pagination.page,
      pageSize: pagination.pageSize,
      pageCount: pagination.pageCount,
    },
    facets: {
      categories: [...new Set(roles.map((r) => r.category))],
      statuses: [...new Set(roles.map((r) => r.status))],
      scopes: [...new Set(roles.map((r) => r.scope))],
      validationStates: [...new Set(roles.map((r) => r.validationState))],
    },
    selectedRole,
    selectedRolePermissionKeys: Array.isArray((selectedRole as { permissionKeys?: string[] } | null)?.permissionKeys)
      ? ((selectedRole as { permissionKeys?: string[] }).permissionKeys ?? [])
      : [],
    selectedRoleAssignedUsers: [],
    validationIssues: [],
  };
}

export function transformRoleDetail(payload: Record<string, unknown>): Role {
  const groups = Array.isArray(payload.permissionGroups)
    ? (payload.permissionGroups as PermissionGroup[])
    : [((payload.category as RoleCategory) ?? "operations") as PermissionGroup];

  return {
    id: String(payload.id ?? ""),
    key: String(payload.key ?? ""),
    name: String(payload.name ?? ""),
    description: String(payload.description ?? ""),
    category: (payload.category as Role["category"]) ?? "operations",
    isSystem: Boolean(payload.isSystem),
    isProtected: Boolean(payload.isProtected),
    assignedUserCount: Number(payload.assignedUserCount ?? 0),
    permissionCount: Number(payload.permissionCount ?? 0),
    permissionGroups: groups,
    scope: (payload.scope as Role["scope"]) ?? "allRecords",
    status: (payload.status as Role["status"]) ?? "active",
    validationState: (payload.validationState as Role["validationState"]) ?? "valid",
    createdAt: String(payload.createdAt ?? ""),
    updatedAt: String(payload.updatedAt ?? ""),
    revision: 1,
    lastEditor: "system",
  };
}
