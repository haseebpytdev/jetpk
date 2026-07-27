import { PERMISSION_BY_KEY } from "@/lib/access-control/permission-catalog";
import { validateRole } from "@/lib/access-control/access-validation";
import { getRolePermissions } from "@/mocks/rbac-fixtures";
import { mockUsers } from "@/mocks/user-fixtures";
import type { Role, RoleCategory, RoleScope } from "@/types/access-control";
import type {
  RoleSortField,
  RolesPageResult,
  RolesQuery,
  RolesSummaryMetrics,
  RoleTableRow,
} from "@/types/roles";

const CATEGORY_LABELS: Record<RoleCategory, string> = {
  system: "System",
  operations: "Operations",
  finance: "Finance",
  content: "Content",
  analytics: "Analytics",
  audit: "Audit",
  custom: "Custom",
};

const SCOPE_LABELS: Record<RoleScope, string> = {
  allChannels: "All channels",
  gdsOnly: "GDS only",
  ndcOnly: "NDC only",
  specificSupplier: "Specific supplier",
  assignedBranch: "Assigned branch",
  ownRecords: "Own records",
  allRecords: "All records",
};

export function countActiveRoleFilters(query: RolesQuery): number {
  let count = 0;
  if (query.search) count += 1;
  if (query.category !== "all") count += 1;
  if (query.status !== "all") count += 1;
  if (query.roleType !== "all") count += 1;
  if (query.protected !== "all") count += 1;
  if (query.risk !== "all") count += 1;
  if (query.validationState !== "all") count += 1;
  if (query.channelScope !== "all") count += 1;
  if (query.assignedState !== "all") count += 1;
  return count;
}

function getHighRiskCount(roleId: string): number {
  return getRolePermissions(roleId).filter((rp) => PERMISSION_BY_KEY.get(rp.permissionKey)?.isHighRisk).length;
}

function toTableRow(role: Role): RoleTableRow {
  return {
    id: role.id,
    name: role.name,
    description: role.description,
    category: role.category,
    categoryLabel: CATEGORY_LABELS[role.category],
    isSystem: role.isSystem,
    isProtected: role.isProtected,
    assignedUserCount: role.assignedUserCount,
    permissionCount: role.permissionCount,
    highRiskPermissionCount: getHighRiskCount(role.id),
    scope: role.scope,
    scopeLabel: SCOPE_LABELS[role.scope],
    status: role.status,
    validationState: role.validationState,
    updatedAt: role.updatedAt,
  };
}

function matchesSearch(role: Role, search: string): boolean {
  if (!search) return true;
  const q = search.toLowerCase();
  return (
    role.id.toLowerCase().includes(q) ||
    role.name.toLowerCase().includes(q) ||
    role.key.toLowerCase().includes(q) ||
    role.description.toLowerCase().includes(q)
  );
}

function filterRoles(roles: Role[], query: RolesQuery): Role[] {
  return roles.filter((role) => {
    if (!matchesSearch(role, query.search)) return false;
    if (query.category !== "all" && role.category !== query.category) return false;
    if (query.status !== "all" && role.status !== query.status) return false;
    if (query.roleType === "system" && !role.isSystem) return false;
    if (query.roleType === "custom" && role.isSystem) return false;
    if (query.protected === "protected" && !role.isProtected) return false;
    if (query.protected === "unprotected" && role.isProtected) return false;
    const highRisk = getHighRiskCount(role.id);
    if (query.risk === "highRisk" && highRisk === 0) return false;
    if (query.risk === "noHighRisk" && highRisk > 0) return false;
    if (query.validationState !== "all" && role.validationState !== query.validationState) return false;
    if (query.channelScope !== "all" && role.scope !== query.channelScope) return false;
    if (query.assignedState === "assigned" && role.assignedUserCount === 0) return false;
    if (query.assignedState === "unassigned" && role.assignedUserCount > 0) return false;
    if (query.assignedState === "unused" && !(role.assignedUserCount === 0 && role.status === "active")) return false;
    return true;
  });
}

function sortRoles(roles: Role[], sort: RoleSortField, direction: "asc" | "desc"): Role[] {
  const dir = direction === "asc" ? 1 : -1;
  return [...roles].sort((a, b) => {
    let cmp = 0;
    switch (sort) {
      case "name":
        cmp = a.name.localeCompare(b.name);
        break;
      case "category":
        cmp = a.category.localeCompare(b.category);
        break;
      case "status":
        cmp = a.status.localeCompare(b.status);
        break;
      case "assignedUserCount":
        cmp = a.assignedUserCount - b.assignedUserCount;
        break;
      case "permissionCount":
        cmp = a.permissionCount - b.permissionCount;
        break;
      case "highRiskPermissionCount":
        cmp = getHighRiskCount(a.id) - getHighRiskCount(b.id);
        break;
      case "updatedAt":
        cmp = new Date(a.updatedAt).getTime() - new Date(b.updatedAt).getTime();
        break;
      case "validationState":
        cmp = a.validationState.localeCompare(b.validationState);
        break;
      default:
        cmp = a.id.localeCompare(b.id);
    }
    return cmp * dir;
  });
}

export function buildRolesSummary(roles: Role[]): RolesSummaryMetrics {
  return {
    totalRoles: roles.length,
    activeRoles: roles.filter((r) => r.status === "active").length,
    protectedSystemRoles: roles.filter((r) => r.isProtected && r.isSystem).length,
    customRoles: roles.filter((r) => !r.isSystem).length,
    rolesWithHighRiskPermissions: roles.filter((r) => getHighRiskCount(r.id) > 0).length,
    rolesRequiringReview: roles.filter((r) => r.validationState !== "valid").length,
    unusedRoles: roles.filter((r) => r.assignedUserCount === 0 && r.status === "active").length,
    incompleteRoles: roles.filter((r) => r.status === "draft" || (r.validationState === "warning" && r.permissionCount <= 1)).length,
  };
}

export function getAssignedUsersForRole(roleId: string): { id: string; name: string }[] {
  return mockUsers
    .filter((u) => u.assignedRoles.some((r) => r.roleId === roleId))
    .map((u) => ({ id: u.id, name: u.profile.fullName }));
}

export function buildRolesPage(query: RolesQuery, sourceRoles: Role[]): RolesPageResult {
  const filtered = filterRoles(sourceRoles, query);
  const sorted = sortRoles(filtered, query.sort, query.direction);
  const pageCount = Math.max(1, Math.ceil(sorted.length / query.pageSize));
  const page = Math.min(query.page, pageCount);
  const start = (page - 1) * query.pageSize;
  const pageRoles = sorted.slice(start, start + query.pageSize);

  const categories = [...new Set(sourceRoles.map((r) => r.category))].sort();
  const statuses = [...new Set(sourceRoles.map((r) => r.status))].sort();
  const scopes = [...new Set(sourceRoles.map((r) => r.scope))].sort();
  const validationStates = [...new Set(sourceRoles.map((r) => r.validationState))].sort();

  return {
    roles: pageRoles.map(toTableRow),
    total: filtered.length,
    page,
    pageSize: query.pageSize,
    pageCount,
    summary: buildRolesSummary(sourceRoles),
    facets: { categories, statuses, scopes, validationStates },
  };
}

export function getRolePermissionKeys(roleId: string): string[] {
  return getRolePermissions(roleId).map((rp) => rp.permissionKey);
}

export function getRoleValidationIssues(role: Role) {
  const keys = getRolePermissionKeys(role.id);
  return validateRole(role, keys).issues;
}

export { CATEGORY_LABELS, SCOPE_LABELS };
