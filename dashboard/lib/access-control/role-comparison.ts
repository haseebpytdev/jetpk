import { buildEffectiveAccessSummary } from "@/lib/access-control/effective-access";
import { PERMISSION_BY_KEY } from "@/lib/access-control/permission-catalog";
import { getRolePermissionKeys } from "@/lib/roles/query-filters";
import { getRoleById } from "@/mocks/rbac-fixtures";
import type { Role } from "@/types/access-control";
import type { RoleComparisonResult } from "@/types/roles";

export function compareRoles(roleIdA: string, roleIdB: string): RoleComparisonResult | null {
  const roleA = getRoleById(roleIdA);
  const roleB = getRoleById(roleIdB);
  if (!roleA || !roleB) return null;

  const keysA = new Set(getRolePermissionKeys(roleIdA));
  const keysB = new Set(getRolePermissionKeys(roleIdB));
  const accessA = buildEffectiveAccessSummary([roleIdA]);
  const accessB = buildEffectiveAccessSummary([roleIdB]);

  const uniqueToA = [...keysA].filter((k) => !keysB.has(k)).sort();
  const uniqueToB = [...keysB].filter((k) => !keysA.has(k)).sort();
  const shared = [...keysA].filter((k) => keysB.has(k)).sort();

  const countByAction = (keys: Set<string>, action: string) =>
    [...keys].filter((k) => PERMISSION_BY_KEY.get(k)?.action === action).length;

  const manageActions = new Set(["manage", "update", "create", "assign", "suspend", "invite"]);

  return {
    roleA,
    roleB,
    permissionCountA: keysA.size,
    permissionCountB: keysB.size,
    domainCoverageA: accessA.domains.length,
    domainCoverageB: accessB.domains.length,
    viewAccessA: countByAction(keysA, "view"),
    viewAccessB: countByAction(keysB, "view"),
    requestAccessA: countByAction(keysA, "request"),
    requestAccessB: countByAction(keysB, "request"),
    approvalAccessA: countByAction(keysA, "approve"),
    approvalAccessB: countByAction(keysB, "approve"),
    manageAccessA: [...keysA].filter((k) => manageActions.has(PERMISSION_BY_KEY.get(k)?.action ?? "")).length,
    manageAccessB: [...keysB].filter((k) => manageActions.has(PERMISSION_BY_KEY.get(k)?.action ?? "")).length,
    exportAccessA: countByAction(keysA, "export"),
    exportAccessB: countByAction(keysB, "export"),
    highRiskA: accessA.highRiskPermissions,
    highRiskB: accessB.highRiskPermissions,
    channelScopesA: [roleA.scope],
    channelScopesB: [roleB.scope],
    uniqueToA,
    uniqueToB,
    shared,
  };
}

export function formatRoleComparisonNote(result: RoleComparisonResult): string {
  if (result.permissionCountA === result.permissionCountB && result.shared.length === result.permissionCountA) {
    return "Roles share identical permission keys in fixtures — policy equivalence is not implied.";
  }
  if (result.permissionCountA === result.permissionCountB) {
    return "Permission counts match but key sets differ — not policy equivalent.";
  }
  return "Permission counts and coverage differ — review unique permissions before any production assignment.";
}

export function getRoleScopeLabel(role: Role): string {
  const labels: Record<string, string> = {
    allChannels: "All channels",
    gdsOnly: "GDS only",
    ndcOnly: "NDC only",
    specificSupplier: "Specific supplier",
    assignedBranch: "Assigned branch",
    ownRecords: "Own records",
    allRecords: "All records",
  };
  return labels[role.scope] ?? role.scope;
}
