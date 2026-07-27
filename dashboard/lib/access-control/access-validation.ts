import { PERMISSION_BY_KEY, PERMISSION_CATALOG } from "@/lib/access-control/permission-catalog";
import { buildEffectiveAccessSummary } from "@/lib/access-control/effective-access";
import { mockRoles } from "@/mocks/rbac-fixtures";
import type {
  AccessValidationIssue,
  AccessValidationResult,
  Permission,
  Role,
  RoleId,
  User,
} from "@/types/access-control";

function issue(
  severity: AccessValidationIssue["severity"],
  code: string,
  message: string,
  fieldPath: string,
  entityId: string,
  suggestedResolution: string,
  blocking: boolean,
): AccessValidationIssue {
  return { severity, code, message, fieldPath, entityId, suggestedResolution, blocking };
}

export function validateUser(user: User): AccessValidationResult {
  const issues: AccessValidationIssue[] = [];
  const roleIds = user.assignedRoles.map((r) => r.roleId);

  if (roleIds.length === 0) {
    issues.push(
      issue(
        "warning",
        "USER_NO_ROLE",
        "User has no assigned roles.",
        "assignedRoles",
        user.id,
        "Assign at least one operational role.",
        false,
      ),
    );
  }

  const uniqueRoles = new Set(roleIds);
  if (uniqueRoles.size !== roleIds.length) {
    issues.push(
      issue(
        "error",
        "USER_DUPLICATE_ROLE",
        "Duplicate role assignments detected.",
        "assignedRoles",
        user.id,
        "Remove duplicate role entries.",
        true,
      ),
    );
  }

  if (user.security.status === "suspended" && user.session.activeSessionCount > 0) {
    issues.push(
      issue(
        "error",
        "USER_SUSPENDED_ACTIVE_SESSION",
        "Suspended account still has active sessions.",
        "session.activeSessionCount",
        user.id,
        "Revoke active sessions when suspending (future Laravel enforcement).",
        true,
      ),
    );
  }

  if (user.security.status === "locked" && user.security.lastSignInAt) {
    const lastSignIn = new Date(user.security.lastSignInAt).getTime();
    const ref = new Date("2026-07-01T00:00:00.000Z").getTime();
    if (lastSignIn > ref - 24 * 60 * 60 * 1000) {
      issues.push(
        issue(
          "warning",
          "USER_LOCKED_RECENT_SIGNIN",
          "Locked account has a recent successful sign-in.",
          "security.lastSignInAt",
          user.id,
          "Review lockout policy consistency.",
          false,
        ),
      );
    }
  }

  if (user.security.mfaRequired && user.security.mfaState !== "enabled") {
    issues.push(
      issue(
        "error",
        "USER_MFA_REQUIRED_DISABLED",
        "MFA is required but not enabled.",
        "security.mfaState",
        user.id,
        "Enable MFA for this user.",
        true,
      ),
    );
  }

  if (user.security.invitationState === "pending" && user.security.status === "invited") {
    const created = new Date(user.createdAt).getTime();
    const ref = new Date("2026-07-01T00:00:00.000Z").getTime();
    if (ref - created > 14 * 24 * 60 * 60 * 1000) {
      issues.push(
        issue(
          "warning",
          "USER_STALE_INVITATION",
          "Invitation has been pending for more than 14 days.",
          "security.invitationState",
          user.id,
          "Resend or revoke the invitation.",
          false,
        ),
      );
    }
  }

  if (user.security.status === "active" && user.security.verificationState === "unverified") {
    issues.push(
      issue(
        "warning",
        "USER_INVALID_STATUS_COMBO",
        "Active status with unverified email is inconsistent.",
        "security.status",
        user.id,
        "Complete verification or change status.",
        false,
      ),
    );
  }

  if (!user.profile.department.trim()) {
    issues.push(
      issue(
        "warning",
        "USER_MISSING_DEPARTMENT",
        "Department is required for operational users.",
        "profile.department",
        user.id,
        "Set a department value.",
        false,
      ),
    );
  }

  if (user.security.failedSignInCount >= 5) {
    issues.push(
      issue(
        "warning",
        "USER_HIGH_FAILED_LOGINS",
        "Failed sign-in count exceeds warning threshold.",
        "security.failedSignInCount",
        user.id,
        "Review account lockout policy.",
        false,
      ),
    );
  }

  const effective = buildEffectiveAccessSummary(roleIds);
  if (effective.highRiskPermissions.length >= 6) {
    issues.push(
      issue(
        "warning",
        "USER_EXCESSIVE_HIGH_RISK",
        "User has excessive high-risk permission access.",
        "effectiveAccess",
        user.id,
        "Review role assignments for least privilege.",
        false,
      ),
    );
  }

  return { valid: !issues.some((i) => i.blocking), issues };
}

export function validateRole(role: Role, permissionKeys: string[]): AccessValidationResult {
  const issues: AccessValidationIssue[] = [];

  if (permissionKeys.length === 0) {
    issues.push(
      issue(
        "error",
        "ROLE_EMPTY_PERMISSIONS",
        "Role has no permissions assigned.",
        "permissions",
        role.id,
        "Assign at least one permission.",
        true,
      ),
    );
  }

  const unique = new Set(permissionKeys);
  if (unique.size !== permissionKeys.length) {
    issues.push(
      issue(
        "error",
        "ROLE_DUPLICATE_PERMISSION",
        "Duplicate permissions in role definition.",
        "permissions",
        role.id,
        "Remove duplicate permission keys.",
        true,
      ),
    );
  }

  if (role.isProtected && role.revision > 1) {
    issues.push(
      issue(
        "warning",
        "ROLE_PROTECTED_MODIFIED",
        "Protected system role has revision changes.",
        "revision",
        role.id,
        "System roles should not be modified in production.",
        false,
      ),
    );
  }

  const domains = new Set(permissionKeys.map((k) => PERMISSION_BY_KEY.get(k)?.domain).filter(Boolean));
  if (domains.size >= 10) {
    issues.push(
      issue(
        "warning",
        "ROLE_EXCESSIVE_DOMAIN_ACCESS",
        "Role spans too many domains.",
        "permissionGroups",
        role.id,
        "Split into narrower operational roles.",
        false,
      ),
    );
  }

  for (const key of permissionKeys) {
    const perm = PERMISSION_BY_KEY.get(key);
    if (perm?.isHighRisk && perm.prerequisiteKey && !permissionKeys.includes(perm.prerequisiteKey)) {
      issues.push(
        issue(
          "warning",
          "ROLE_HIGH_RISK_NO_PREREQUISITE",
          `High-risk permission ${key} missing prerequisite ${perm.prerequisiteKey}.`,
          "permissions",
          role.id,
          `Add prerequisite ${perm.prerequisiteKey}.`,
          false,
        ),
      );
    }
  }

  if (!role.isSystem && role.assignedUserCount === 0 && role.status === "active") {
    issues.push(
      issue(
        "info",
        "ROLE_UNUSED_CUSTOM",
        "Custom role has no assigned users.",
        "assignedUserCount",
        role.id,
        "Assign users or deactivate the role.",
        false,
      ),
    );
  }

  const nameConflict = mockRoles.filter((r) => r.name === role.name && r.id !== role.id);
  if (nameConflict.length > 0) {
    issues.push(
      issue(
        "error",
        "ROLE_NAME_CONFLICT",
        "Another role uses the same name.",
        "name",
        role.id,
        "Use a unique role name.",
        true,
      ),
    );
  }

  return { valid: !issues.some((i) => i.blocking), issues };
}

export function validatePermission(permission: Permission): AccessValidationResult {
  const issues: AccessValidationIssue[] = [];
  const validDomains = new Set(PERMISSION_CATALOG.map((p) => p.domain));

  if (!validDomains.has(permission.domain)) {
    issues.push(
      issue(
        "error",
        "PERMISSION_UNKNOWN_DOMAIN",
        "Permission domain is not recognized.",
        "domain",
        permission.id,
        "Use a valid permission domain.",
        true,
      ),
    );
  }

  const validActions = new Set(["view", "create", "update", "request", "approve", "manage", "export", "invite", "suspend", "assign"]);
  if (!validActions.has(permission.action)) {
    issues.push(
      issue(
        "error",
        "PERMISSION_UNKNOWN_ACTION",
        "Permission action is not recognized.",
        "action",
        permission.id,
        "Use a valid action type.",
        true,
      ),
    );
  }

  if (permission.supportedScopes.length === 0) {
    issues.push(
      issue(
        "error",
        "PERMISSION_INVALID_SCOPE",
        "Permission must support at least one scope.",
        "supportedScopes",
        permission.id,
        "Add supported scope metadata.",
        true,
      ),
    );
  }

  if (permission.isHighRisk && permission.action === "approve" && !permission.prerequisiteKey) {
    issues.push(
      issue(
        "warning",
        "PERMISSION_HIGH_RISK_NO_APPROVAL_META",
        "High-risk approval permission should declare a prerequisite.",
        "prerequisiteKey",
        permission.id,
        "Set prerequisite request permission.",
        false,
      ),
    );
  }

  const duplicate = PERMISSION_CATALOG.filter((p) => p.key === permission.key && p.id !== permission.id);
  if (duplicate.length > 0) {
    issues.push(
      issue(
        "error",
        "PERMISSION_DUPLICATE_KEY",
        "Duplicate permission key in catalog.",
        "key",
        permission.id,
        "Use a unique permission key.",
        true,
      ),
    );
  }

  return { valid: !issues.some((i) => i.blocking), issues };
}

export function validateRoleAssignmentPreview(
  userId: string,
  currentRoleIds: RoleId[],
  previewRoleIds: RoleId[],
): AccessValidationIssue[] {
  const issues: AccessValidationIssue[] = [];

  if (previewRoleIds.length === 0) {
    issues.push(
      issue(
        "warning",
        "PREVIEW_NO_ROLE",
        "Preview has no roles assigned.",
        "previewRoles",
        userId,
        "Select at least one role for preview.",
        false,
      ),
    );
  }

  const unique = new Set(previewRoleIds);
  if (unique.size !== previewRoleIds.length) {
    issues.push(
      issue(
        "error",
        "PREVIEW_DUPLICATE_ROLE",
        "Duplicate roles in preview assignment.",
        "previewRoles",
        userId,
        "Remove duplicate role selection.",
        true,
      ),
    );
  }

  const protectedRoles = previewRoleIds
    .map((id) => mockRoles.find((r) => r.id === id))
    .filter((r) => r?.isProtected);
  if (protectedRoles.length > 0 && currentRoleIds.length === 0) {
    issues.push(
      issue(
        "warning",
        "PREVIEW_PROTECTED_ROLE",
        "Assigning protected system role requires review.",
        "previewRoles",
        userId,
        "Confirm protected role assignment policy.",
        false,
      ),
    );
  }

  const effective = buildEffectiveAccessSummary(previewRoleIds);
  if (effective.highRiskPermissions.length >= 6) {
    issues.push(
      issue(
        "warning",
        "PREVIEW_EXCESSIVE_HIGH_RISK",
        "Preview assignment grants excessive high-risk access.",
        "previewRoles",
        userId,
        "Reduce high-risk permissions.",
        false,
      ),
    );
  }

  const hasOps = previewRoleIds.some((id) => mockRoles.find((r) => r.id === id)?.category === "operations");
  const hasAudit = previewRoleIds.some((id) => mockRoles.find((r) => r.id === id)?.category === "audit");
  if (hasOps && hasAudit && previewRoleIds.length > 2) {
    issues.push(
      issue(
        "warning",
        "PREVIEW_CONFLICTING_ROLES",
        "Operations and audit roles may conflict.",
        "previewRoles",
        userId,
        "Separate operational and audit responsibilities.",
        false,
      ),
    );
  }

  return issues;
}
