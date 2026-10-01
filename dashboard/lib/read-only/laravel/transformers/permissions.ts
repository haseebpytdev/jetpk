import type { ActionType, Permission, PermissionGroup } from "@/types/access-control";
import type { PermissionsModuleResult, PermissionsQuery, PermissionTableRow } from "@/types/permissions";
import type { LaravelPermissionsListPayload } from "@/lib/read-only/laravel/types";
import { PERMISSION_GROUP_LABELS } from "@/lib/access-control/permission-catalog";

function deriveAction(key: string, explicit?: unknown): ActionType {
  if (typeof explicit === "string" && explicit.length > 0) {
    return explicit as ActionType;
  }
  const part = key.includes(".") ? key.split(".").pop() ?? "view" : "view";
  return part as ActionType;
}

function normalizePermissionRow(raw: Record<string, unknown>): PermissionTableRow {
  const key = String(raw.key ?? "");
  const domain = String(raw.domain ?? raw.category ?? "dashboard") as PermissionGroup;
  const risk = (raw.risk as PermissionTableRow["risk"]) ?? (raw.highRisk || raw.isHighRisk ? "high" : "standard");
  const supportedScopes: PermissionTableRow["supportedScopes"] = Array.isArray(raw.supportedScopes)
    ? (raw.supportedScopes as PermissionTableRow["supportedScopes"])
    : raw.scope
      ? ([String(raw.scope)] as unknown as PermissionTableRow["supportedScopes"])
      : ([] as PermissionTableRow["supportedScopes"]);

  return {
    id: String(raw.id ?? key),
    key,
    domain,
    domainLabel: PERMISSION_GROUP_LABELS[domain] ?? String(domain),
    action: deriveAction(key, raw.action),
    label: String(raw.label ?? raw.name ?? key),
    description: String(raw.description ?? ""),
    risk,
    isHighRisk: Boolean(raw.isHighRisk ?? raw.highRisk ?? risk === "high"),
    prerequisiteKey: raw.prerequisiteKey ? String(raw.prerequisiteKey) : null,
    supportedScopes,
    assignedRoleCount: Number(raw.assignedRoleCount ?? 0),
    validationState: (raw.validationState as PermissionTableRow["validationState"]) ?? "valid",
    laravelPolicyHint: String(raw.laravelPolicyHint ?? key),
  };
}

export function transformPermissionsModule(
  payload: LaravelPermissionsListPayload,
  query: PermissionsQuery,
  pagination: { page: number; pageSize: number; total: number; pageCount: number },
  selectedPermission: Permission | null,
): PermissionsModuleResult {
  const rawRows = Array.isArray(payload.permissions) ? payload.permissions : [];
  const permissions = rawRows.map((row) => normalizePermissionRow(row as unknown as Record<string, unknown>));

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
  const key = String(payload.key ?? "");

  return {
    id: String(payload.id ?? key),
    key,
    label: String(payload.label ?? payload.name ?? ""),
    description: String(payload.description ?? ""),
    domain,
    action: deriveAction(key, payload.action),
    risk: (payload.risk as Permission["risk"]) ?? (payload.isHighRisk || payload.highRisk ? "high" : "standard"),
    isHighRisk: Boolean(payload.isHighRisk ?? payload.highRisk ?? payload.risk === "high"),
    prerequisiteKey: payload.prerequisiteKey ? String(payload.prerequisiteKey) : null,
    supportedScopes: Array.isArray(payload.supportedScopes)
      ? (payload.supportedScopes as Permission["supportedScopes"])
      : payload.scope
        ? ([String(payload.scope)] as unknown as Permission["supportedScopes"])
        : [],
    channelAware: Boolean(payload.channelAware ?? false),
    laravelPolicyHint: String(payload.laravelPolicyHint ?? key),
    implementationStatus: "partial",
  };
}
