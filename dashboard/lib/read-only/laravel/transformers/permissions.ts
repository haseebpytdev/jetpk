import type { Permission, PermissionGroup } from "@/types/access-control";
import type { PermissionsModuleResult, PermissionsQuery, PermissionTableRow } from "@/types/permissions";
import type { LaravelPermissionsListPayload } from "@/lib/read-only/laravel/types";

function mapPermissionTableRow(raw: Record<string, unknown> | PermissionTableRow): PermissionTableRow {
  const row = raw as Record<string, unknown>;
  const key = String(row.key ?? row.id ?? "");
  const domain = String(row.domain ?? row.category ?? "dashboard") as PermissionGroup;
  const action = String(row.action ?? (key.includes(".") ? key.slice(key.lastIndexOf(".") + 1) : "view")) as PermissionTableRow["action"];
  return {
    id: String(row.id ?? key),
    key,
    domain,
    domainLabel: String(row.domainLabel ?? domain),
    action,
    label: String(row.label ?? key),
    description: String(row.description ?? ""),
    risk: (row.risk as PermissionTableRow["risk"]) ?? (row.highRisk || row.isHighRisk ? "high" : "standard"),
    isHighRisk: Boolean(row.isHighRisk ?? row.highRisk ?? false),
    prerequisiteKey: row.prerequisiteKey ? String(row.prerequisiteKey) : null,
    supportedScopes: Array.isArray(row.supportedScopes)
      ? (row.supportedScopes as PermissionTableRow["supportedScopes"])
      : [((row.scope as PermissionTableRow["supportedScopes"][number]) ?? "allRecords")],
    assignedRoleCount: Number(row.assignedRoleCount ?? 0),
    validationState: (row.validationState as PermissionTableRow["validationState"]) ?? "valid",
    laravelPolicyHint: String(row.laravelPolicyHint ?? key),
  };
}

export function transformPermissionsModule(
  payload: LaravelPermissionsListPayload,
  query: PermissionsQuery,
  pagination: { page: number; pageSize: number; total: number; pageCount: number },
  selectedPermission: Permission | null,
): PermissionsModuleResult {
  const raw = (payload as { permissions?: unknown; items?: unknown }).permissions
    ?? (payload as { items?: unknown }).items
    ?? [];
  const permissions = (Array.isArray(raw) ? raw : []).map(mapPermissionTableRow);

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
