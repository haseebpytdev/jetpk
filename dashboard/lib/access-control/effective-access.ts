import { PERMISSION_BY_KEY, PERMISSION_CATALOG, PERMISSION_GROUP_LABELS } from "@/lib/access-control/permission-catalog";
import type {
  EffectiveAccessDomainSummary,
  EffectiveAccessSummary,
  PermissionGroup,
  RoleId,
  RolePermission,
} from "@/types/access-control";
import { mockRolePermissions, mockRoles } from "@/mocks/rbac-fixtures";

const DOMAIN_ORDER: PermissionGroup[] = [
  "dashboard",
  "bookings",
  "payments",
  "customers",
  "suppliers",
  "agents",
  "pnrs",
  "tickets",
  "reports",
  "cms",
  "users",
  "roles",
  "settings",
  "audit",
];

function collectRolePermissions(roleIds: RoleId[]): RolePermission[] {
  const roleIdSet = new Set(roleIds);
  return mockRolePermissions.filter((rp) => roleIdSet.has(rp.roleId));
}

export function buildEffectiveAccessSummary(roleIds: RoleId[]): EffectiveAccessSummary {
  const rolePermissions = collectRolePermissions(roleIds);
  const permissionKeys = new Set<string>();
  const highRiskPermissions: string[] = [];

  for (const rp of rolePermissions) {
    permissionKeys.add(rp.permissionKey);
    const perm = PERMISSION_BY_KEY.get(rp.permissionKey);
    if (perm?.isHighRisk) {
      highRiskPermissions.push(rp.permissionKey);
    }
  }

  const domains: EffectiveAccessDomainSummary[] = DOMAIN_ORDER.map((domain) => {
    const domainPerms = PERMISSION_CATALOG.filter(
      (p) => p.domain === domain && permissionKeys.has(p.key),
    );
    const actions = domainPerms.map((p) => p.action);
    return {
      domain,
      label: PERMISSION_GROUP_LABELS[domain],
      permissionCount: domainPerms.length,
      viewAccess: actions.some((a) => a === "view"),
      requestAccess: actions.some((a) => a === "request"),
      approvalAccess: actions.some((a) => a === "approve"),
      manageAccess: actions.some((a) => a === "manage" || a === "update" || a === "create" || a === "assign" || a === "suspend" || a === "invite"),
      exportAccess: actions.some((a) => a === "export"),
      highRiskCount: domainPerms.filter((p) => p.isHighRisk).length,
    };
  }).filter((d) => d.permissionCount > 0);

  return {
    domains,
    totalPermissions: permissionKeys.size,
    highRiskPermissions: [...new Set(highRiskPermissions)].sort(),
    roleIds: [...roleIds],
  };
}

export function getRoleName(roleId: RoleId): string {
  return mockRoles.find((r) => r.id === roleId)?.name ?? roleId;
}

export function getRoleNames(roleIds: RoleId[]): string[] {
  return roleIds.map(getRoleName);
}
