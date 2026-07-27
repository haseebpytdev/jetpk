import { PERMISSION_BY_KEY } from "@/lib/access-control/permission-catalog";
import { buildEffectiveAccessSummary } from "@/lib/access-control/effective-access";
import { mockRoles } from "@/mocks/rbac-fixtures";
import type { AccessValidationIssue } from "@/types/access-control";

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

export function validatePermissionAssignmentPreview(
  roleId: string,
  fixtureKeys: string[],
  previewKeys: string[],
): AccessValidationIssue[] {
  const issues: AccessValidationIssue[] = [];
  const role = mockRoles.find((r) => r.id === roleId);

  const unique = new Set(previewKeys);
  if (unique.size !== previewKeys.length) {
    issues.push(
      issue("error", "PREVIEW_DUPLICATE_PERMISSION", "Duplicate permissions in preview.", "previewPermissions", roleId, "Remove duplicate permission keys.", true),
    );
  }

  if (role?.isProtected) {
    const added = previewKeys.filter((k) => !fixtureKeys.includes(k));
    if (added.length > 0) {
      issues.push(
        issue("warning", "PREVIEW_PROTECTED_ROLE", "Modifying protected system role permissions requires review.", "previewPermissions", roleId, "Confirm protected role policy.", false),
      );
    }
  }

  for (const key of previewKeys) {
    const perm = PERMISSION_BY_KEY.get(key);
    if (perm?.prerequisiteKey && !previewKeys.includes(perm.prerequisiteKey)) {
      issues.push(
        issue("warning", "PREVIEW_MISSING_PREREQUISITE", `${key} requires prerequisite ${perm.prerequisiteKey}.`, "previewPermissions", roleId, `Add ${perm.prerequisiteKey}.`, false),
      );
    }
  }

  const effective = buildEffectiveAccessSummary([roleId]);
  const previewHighRisk = previewKeys.filter((k) => PERMISSION_BY_KEY.get(k)?.isHighRisk);
  if (previewHighRisk.length > effective.highRiskPermissions.length + 2) {
    issues.push(
      issue("warning", "PREVIEW_EXCESSIVE_RISK", "Preview grants excessive high-risk permissions.", "previewPermissions", roleId, "Reduce high-risk permissions.", false),
    );
  }

  if (previewHighRisk.length > 0) {
    const newHighRisk = previewHighRisk.filter((k) => !effective.highRiskPermissions.includes(k));
    if (newHighRisk.length > 0) {
      issues.push(
        issue("warning", "PREVIEW_HIGH_RISK_CONFIRMATION", `Adding high-risk permissions: ${newHighRisk.join(", ")}. Preview only — no production action.`, "previewPermissions", roleId, "Review high-risk permissions before any future assignment.", false),
      );
    }
  }

  if (previewKeys.length > 40) {
    issues.push(
      issue("warning", "PREVIEW_EXCESSIVE_ACCESS", "Preview permission count is unusually high.", "previewPermissions", roleId, "Apply least-privilege principle.", false),
    );
  }

  return issues;
}

export function diffPermissionKeys(fixtureKeys: string[], previewKeys: string[]): {
  added: string[];
  removed: string[];
} {
  const fixtureSet = new Set(fixtureKeys);
  const previewSet = new Set(previewKeys);
  return {
    added: previewKeys.filter((k) => !fixtureSet.has(k)),
    removed: fixtureKeys.filter((k) => !previewSet.has(k)),
  };
}
