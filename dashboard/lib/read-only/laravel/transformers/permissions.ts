import type { Permission, PermissionGroup } from "@/types/access-control";
import type { PermissionsModuleResult, PermissionsQuery, PermissionTableRow } from "@/types/permissions";
import type { LaravelPermissionsListPayload } from "@/lib/read-only/laravel/types";

export function transformPermissionsModule(
  payload: LaravelPermissionsListPayload,
  query: PermissionsQuery,
  pagination: { page: number; pageSize: number; total: number; pageCount: number },
  selectedPermission: Permission | null,
): PermissionsModuleResult {
  const permissions = payload.permissions as PermissionTableRow[];

  return {
    state: pagination.total === 0 ? "empty" : "ready",
    query,
    summary: payload.summary ?? {
      totalPermissions: pagination.total,
      viewPermissions: permissions.filter((p) => p.action === "view").length,
      requestPermissions: permissions.filter((p) => p.action === "request").length,
      approvalPermissions: permissions.filter((p) => p.action === "approve").length,
      managePermissions: permissions.filter((p) => p.action === "manage").length,
      exportPermissions: permissions.filter((p) => p.action === "export").length,
      highRiskPermissions: permissions.filter((p) => p.risk === "high").length,
      permissionsRequiringPrerequisiteReview: permissions.filter((p) => p.validationState === "review").length,
    },
    table: {
      rows: permissions,
      total: pagination.total,
      page: pagination.page,
      pageSize: pagination.pageSize,
      pageCount: pagination.pageCount,
    },
    facets: {
      domains: [...new Set(permissions.map((p) => p.domain))],
      actions: [...new Set(permissions.map((p) => p.action))],
      risks: [...new Set(permissions.map((p) => p.risk))],
      scopes: [...new Set(permissions.flatMap((p) => p.supportedScopes ?? []))],
    },
    selectedPermission,
    assignedRoles: [],
    validationIssues: [],
  };
}

export function transformPermissionDetail(payload: Record<string, unknown>): Permission {
  const domain = String(payload.domain ?? payload.category ?? "dashboard") as PermissionGroup;

  return {
    id: String(payload.id ?? payload.key ?? ""),
    key: String(payload.key ?? ""),
    label: String(payload.label ?? payload.name ?? ""),
    description: String(payload.description ?? ""),
    domain,
    action: (payload.action as Permission["action"]) ?? "view",
    risk: (payload.risk as Permission["risk"]) ?? (payload.isHighRisk ? "high" : "standard"),
    isHighRisk: Boolean(payload.isHighRisk ?? payload.risk === "high"),
    prerequisiteKey: payload.prerequisiteKey ? String(payload.prerequisiteKey) : null,
    supportedScopes: Array.isArray(payload.supportedScopes)
      ? (payload.supportedScopes as Permission["supportedScopes"])
      : [(payload.scope as Permission["supportedScopes"][number]) ?? "allRecords"],
    channelAware: Boolean(payload.channelAware ?? false),
    laravelPolicyHint: String(payload.laravelPolicyHint ?? payload.key ?? ""),
    implementationStatus: "partial",
  };
}
