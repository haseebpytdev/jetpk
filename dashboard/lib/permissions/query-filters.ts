import { PERMISSION_CATALOG, PERMISSION_GROUP_LABELS } from "@/lib/access-control/permission-catalog";
import { validatePermission } from "@/lib/access-control/access-validation";
import { mockRolePermissions } from "@/mocks/rbac-fixtures";
import { mockRoles } from "@/mocks/rbac-fixtures";
import type { Permission, PermissionGroup } from "@/types/access-control";
import type {
  PermissionSortField,
  PermissionsPageResult,
  PermissionsQuery,
  PermissionsSummaryMetrics,
  PermissionTableRow,
} from "@/types/permissions";

export function countActivePermissionFilters(query: PermissionsQuery): number {
  let count = 0;
  if (query.search) count += 1;
  if (query.domain !== "all") count += 1;
  if (query.action !== "all") count += 1;
  if (query.risk !== "all") count += 1;
  if (query.effect !== "all") count += 1;
  if (query.scope !== "all") count += 1;
  if (query.prerequisite !== "all") count += 1;
  if (query.assignedState !== "all") count += 1;
  if (query.validationState !== "all") count += 1;
  return count;
}

function getAssignedRoleCount(permissionKey: string): number {
  const roleIds = new Set(
    mockRolePermissions.filter((rp) => rp.permissionKey === permissionKey).map((rp) => rp.roleId),
  );
  return roleIds.size;
}

function getPermissionValidationState(permission: Permission): PermissionTableRow["validationState"] {
  const result = validatePermission(permission);
  if (result.issues.some((i) => i.blocking)) return "blocked";
  if (result.issues.some((i) => i.severity === "warning")) return "warning";
  return "valid";
}

function toTableRow(permission: Permission): PermissionTableRow {
  return {
    id: permission.id,
    key: permission.key,
    domain: permission.domain,
    domainLabel: PERMISSION_GROUP_LABELS[permission.domain],
    action: permission.action,
    label: permission.label,
    description: permission.description,
    risk: permission.risk,
    isHighRisk: permission.isHighRisk,
    prerequisiteKey: permission.prerequisiteKey,
    supportedScopes: permission.supportedScopes,
    assignedRoleCount: getAssignedRoleCount(permission.key),
    validationState: getPermissionValidationState(permission),
    laravelPolicyHint: permission.laravelPolicyHint,
  };
}

function matchesSearch(permission: Permission, search: string): boolean {
  if (!search) return true;
  const q = search.toLowerCase();
  return (
    permission.id.toLowerCase().includes(q) ||
    permission.key.toLowerCase().includes(q) ||
    permission.label.toLowerCase().includes(q) ||
    permission.description.toLowerCase().includes(q)
  );
}

function hasMissingPrerequisite(permission: Permission, roleKeySets: string[][]): boolean {
  if (!permission.isHighRisk || !permission.prerequisiteKey) return false;
  return roleKeySets.some((keys) => keys.includes(permission.key) && !keys.includes(permission.prerequisiteKey!));
}

function filterPermissions(permissions: Permission[], query: PermissionsQuery): Permission[] {
  return permissions.filter((permission) => {
    if (!matchesSearch(permission, query.search)) return false;
    if (query.domain !== "all" && permission.domain !== query.domain) return false;
    if (query.action !== "all" && permission.action !== query.action) return false;
    if (query.risk !== "all" && permission.risk !== query.risk) return false;
    if (query.scope !== "all" && !permission.supportedScopes.includes(query.scope)) return false;
    const assignedCount = getAssignedRoleCount(permission.key);
    if (query.assignedState === "assigned" && assignedCount === 0) return false;
    if (query.assignedState === "unassigned" && assignedCount > 0) return false;
    if (query.prerequisite === "hasPrerequisite" && !permission.prerequisiteKey) return false;
    if (query.prerequisite === "noPrerequisite" && permission.prerequisiteKey) return false;
    if (query.prerequisite === "missingPrerequisite") {
      const rolesWithPerm = mockRolePermissions
        .filter((rp) => rp.permissionKey === permission.key)
        .map((rp) => getRolePermissionKeys(rp.roleId));
      if (!hasMissingPrerequisite(permission, rolesWithPerm)) return false;
    }
    const validationState = getPermissionValidationState(permission);
    if (query.validationState !== "all" && validationState !== query.validationState) return false;
    return true;
  });
}

function getRolePermissionKeys(roleId: string): string[] {
  return mockRolePermissions.filter((rp) => rp.roleId === roleId).map((rp) => rp.permissionKey);
}

function sortPermissions(
  permissions: Permission[],
  sort: PermissionSortField,
  direction: "asc" | "desc",
): Permission[] {
  const dir = direction === "asc" ? 1 : -1;
  return [...permissions].sort((a, b) => {
    let cmp = 0;
    switch (sort) {
      case "key":
        cmp = a.key.localeCompare(b.key);
        break;
      case "domain":
        cmp = a.domain.localeCompare(b.domain) || a.key.localeCompare(b.key);
        break;
      case "action":
        cmp = a.action.localeCompare(b.action);
        break;
      case "risk":
        cmp = a.risk.localeCompare(b.risk);
        break;
      case "assignedRoleCount":
        cmp = getAssignedRoleCount(a.key) - getAssignedRoleCount(b.key);
        break;
      case "validationState":
        cmp = getPermissionValidationState(a).localeCompare(getPermissionValidationState(b));
        break;
      default:
        cmp = a.id.localeCompare(b.id);
    }
    return cmp * dir;
  });
}

export function buildPermissionsSummary(permissions: Permission[]): PermissionsSummaryMetrics {
  const manageActions = new Set(["manage", "update", "create", "assign", "suspend", "invite"]);
  return {
    totalPermissions: permissions.length,
    viewPermissions: permissions.filter((p) => p.action === "view").length,
    requestPermissions: permissions.filter((p) => p.action === "request").length,
    approvalPermissions: permissions.filter((p) => p.action === "approve").length,
    managePermissions: permissions.filter((p) => manageActions.has(p.action)).length,
    exportPermissions: permissions.filter((p) => p.action === "export").length,
    highRiskPermissions: permissions.filter((p) => p.isHighRisk).length,
    permissionsRequiringPrerequisiteReview: permissions.filter(
      (p) => p.isHighRisk && p.prerequisiteKey,
    ).length,
  };
}

export function getAssignedRolesForPermission(permissionKey: string): { id: string; name: string }[] {
  const roleIds = [...new Set(
    mockRolePermissions.filter((rp) => rp.permissionKey === permissionKey).map((rp) => rp.roleId),
  )];
  return roleIds.map((id) => {
    const role = mockRoles.find((r) => r.id === id);
    return { id, name: role?.name ?? id };
  });
}

export function buildPermissionsPage(query: PermissionsQuery, source: Permission[]): PermissionsPageResult {
  const filtered = filterPermissions(source, query);
  const sorted = sortPermissions(filtered, query.sort, query.direction);
  const pageCount = Math.max(1, Math.ceil(sorted.length / query.pageSize));
  const page = Math.min(query.page, pageCount);
  const start = (page - 1) * query.pageSize;
  const pagePerms = sorted.slice(start, start + query.pageSize);

  const domains = [...new Set(source.map((p) => p.domain))].sort() as PermissionGroup[];
  const actions = [...new Set(source.map((p) => p.action))].sort();
  const risks = [...new Set(source.map((p) => p.risk))].sort();
  const scopes = [...new Set(source.flatMap((p) => p.supportedScopes))].sort();

  return {
    permissions: pagePerms.map(toTableRow),
    total: filtered.length,
    page,
    pageSize: query.pageSize,
    pageCount,
    summary: buildPermissionsSummary(source),
    facets: { domains, actions, risks, scopes },
  };
}

export function getPermissionValidationIssues(permission: Permission) {
  return validatePermission(permission).issues;
}

export { PERMISSION_CATALOG };
