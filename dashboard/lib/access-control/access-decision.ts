import { PERMISSION_BY_KEY } from "@/lib/access-control/permission-catalog";
import { mockRolePermissions } from "@/mocks/rbac-fixtures";
import type {
  AccessDecision,
  AccessDecisionReason,
  PermissionScope,
  RoleId,
  User,
} from "@/types/access-control";

export type AccessDecisionContext = {
  user: User | null;
  roleIds: RoleId[];
  permissionKey: string;
  resourceType?: string;
  action?: string;
  scopeContext?: PermissionScope;
  channelContext?: "gds" | "ndc" | "oneApi" | "manual" | "mock" | null;
};

/**
 * Pure fixture-based access decision helper.
 * Laravel remains the future authoritative enforcement layer.
 */
export function evaluateAccessDecision(ctx: AccessDecisionContext): AccessDecision {
  const permission = PERMISSION_BY_KEY.get(ctx.permissionKey);

  if (!permission) {
    return denied(ctx.permissionKey, "denied_no_permission", null, false);
  }

  if (!ctx.user || ctx.roleIds.length === 0) {
    return denied(ctx.permissionKey, "denied_no_role", null, permission.isHighRisk);
  }

  const matching = mockRolePermissions.filter(
    (rp) => ctx.roleIds.includes(rp.roleId) && rp.permissionKey === ctx.permissionKey,
  );

  if (matching.length === 0) {
    if (permission.action === "approve") {
      return {
        allowed: false,
        denied: true,
        requiresApproval: true,
        unavailable: false,
        reason: "requires_approval",
        sourceRoleId: null,
        scope: null,
        highRisk: permission.isHighRisk,
        permissionKey: ctx.permissionKey,
      };
    }
    return denied(ctx.permissionKey, "denied_no_permission", null, permission.isHighRisk);
  }

  const sourceRoleId = matching[0]?.roleId ?? null;
  const scope = matching[0]?.scope ?? "all";

  if (permission.action === "approve") {
    return {
      allowed: true,
      denied: false,
      requiresApproval: true,
      unavailable: false,
      reason: "requires_approval",
      sourceRoleId,
      scope,
      highRisk: permission.isHighRisk,
      permissionKey: ctx.permissionKey,
    };
  }

  if (ctx.channelContext && permission.channelAware) {
    const channelScope = `channel:${ctx.channelContext}` as PermissionScope;
    const channelMatch = matching.some((m) => m.scope === "all" || m.scope === channelScope);
    if (!channelMatch) {
      return denied(ctx.permissionKey, "denied_channel", sourceRoleId, permission.isHighRisk);
    }
  }

  return {
    allowed: true,
    denied: false,
    requiresApproval: false,
    unavailable: false,
    reason: "granted",
    sourceRoleId,
    scope,
    highRisk: permission.isHighRisk,
    permissionKey: ctx.permissionKey,
  };
}

function denied(
  permissionKey: string,
  reason: AccessDecisionReason,
  sourceRoleId: RoleId | null,
  highRisk: boolean,
): AccessDecision {
  return {
    allowed: false,
    denied: true,
    requiresApproval: false,
    unavailable: reason === "unavailable_preview",
    reason,
    sourceRoleId,
    scope: null,
    highRisk,
    permissionKey,
  };
}

export function combineMultiRoleDecisions(decisions: AccessDecision[]): AccessDecision {
  const granted = decisions.find((d) => d.allowed);
  if (granted) return granted;
  const approval = decisions.find((d) => d.requiresApproval);
  if (approval) return approval;
  return decisions[0] ?? denied("unknown", "denied_no_permission", null, false);
}

export function evaluateMultiRoleAccess(
  user: User | null,
  roleIds: RoleId[],
  permissionKey: string,
): AccessDecision {
  const perRole = roleIds.map((roleId) =>
    evaluateAccessDecision({ user, roleIds: [roleId], permissionKey }),
  );
  return combineMultiRoleDecisions(perRole);
}
