"use client";

import { Divider } from "@/components/ui/divider";
import { PreviewDataBanner } from "@/components/ui/page-layout";
import { AccessRiskBadge, AccessValidationBadge, UserStatusBadge } from "@/components/ui/status-badge";
import { buildEffectiveAccessSummary } from "@/lib/access-control/effective-access";
import { PERMISSION_GROUP_LABELS } from "@/lib/access-control/permission-catalog";
import { CATEGORY_LABELS, SCOPE_LABELS } from "@/lib/roles/query-filters";
import { formatDate } from "@/lib/format";
import { getUserById } from "@/mocks/user-fixtures";
import type { AccessValidationIssue, Role } from "@/types/access-control";
import { AccessValidationSummary } from "@/features/users/components/access-validation-summary";
import { EffectiveAccessSummaryPanel } from "@/features/users/components/effective-access-summary";
import { AccessDecisionExplainer } from "@/features/roles/components/access-decision-explainer";
import { PermissionAssignmentPreview } from "@/features/roles/components/permission-assignment-preview";
import { RoleComparisonPanel } from "@/features/roles/components/role-comparison-panel";

type Props = {
  role: Role;
  permissionKeys: string[];
  assignedUsers: { id: string; name: string }[];
  validationIssues: AccessValidationIssue[];
  compareA: string | null;
  compareB: string | null;
};

export function RoleDetailDrawerContent({
  role,
  permissionKeys,
  assignedUsers,
  validationIssues,
  compareA,
  compareB,
}: Props) {
  const effectiveAccess = buildEffectiveAccessSummary([role.id]);
  const sampleUser = getUserById(assignedUsers[0]?.id ?? "JP-USR-0001");
  const explainerPermission = permissionKeys[0] ?? "dashboard.view";

  return (
    <div className="space-y-5" data-testid="role-detail-drawer">
      <PreviewDataBanner className="text-xs" />

      <section aria-labelledby="role-identity-heading">
        <h3 id="role-identity-heading" className="text-sm font-semibold text-gray-900">Identity</h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Role ID</dt><dd className="font-medium">{role.id}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Key</dt><dd className="break-all">{role.key}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Name</dt><dd>{role.name}</dd></div>
          <div><dt className="text-jp-muted">Description</dt><dd className="mt-1">{role.description}</dd></div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="role-classification-heading">
        <h3 id="role-classification-heading" className="text-sm font-semibold text-gray-900">Classification</h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Category</dt><dd>{CATEGORY_LABELS[role.category]}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Channel scope</dt><dd>{SCOPE_LABELS[role.scope]}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">System role</dt><dd>{role.isSystem ? "Yes" : "No"}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Protected</dt><dd>{role.isProtected ? "Yes" : "No"}</dd></div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="role-status-heading">
        <h3 id="role-status-heading" className="text-sm font-semibold text-gray-900">Lifecycle</h3>
        <div className="mt-2 flex flex-wrap gap-2">
          <UserStatusBadge status={role.status} />
          <AccessValidationBadge status={role.validationState} />
          {effectiveAccess.highRiskPermissions.length > 0 ? (
            <AccessRiskBadge highRisk label={`${effectiveAccess.highRiskPermissions.length} high risk`} />
          ) : null}
        </div>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Revision</dt><dd className="tabular-nums">{role.revision}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Last editor</dt><dd>{role.lastEditor}</dd></div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="role-counts-heading">
        <h3 id="role-counts-heading" className="text-sm font-semibold text-gray-900">Access counts</h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Assigned users</dt><dd className="tabular-nums">{role.assignedUserCount}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Permission count</dt><dd className="tabular-nums">{role.permissionCount}</dd></div>
        </dl>
        <h4 className="mt-3 text-xs font-semibold text-gray-900">Permission groups</h4>
        <div className="mt-1 flex flex-wrap gap-1">
          {role.permissionGroups.map((g) => (
            <span key={g} className="rounded-full bg-gray-100 px-2 py-0.5 text-xs">
              {PERMISSION_GROUP_LABELS[g]}
            </span>
          ))}
        </div>
      </section>

      <Divider />

      <section aria-labelledby="role-assigned-users-heading">
        <h3 id="role-assigned-users-heading" className="text-sm font-semibold text-gray-900">Assigned users (fixture)</h3>
        <ul className="mt-2 space-y-1 text-sm">
          {assignedUsers.length > 0 ? (
            assignedUsers.map((u) => (
              <li key={u.id}>{u.name} <span className="text-jp-muted">({u.id})</span></li>
            ))
          ) : (
            <li className="text-jp-muted">No users assigned</li>
          )}
        </ul>
      </section>

      <Divider />

      <section aria-labelledby="role-permissions-heading">
        <h3 id="role-permissions-heading" className="text-sm font-semibold text-gray-900">Permission keys (fixture)</h3>
        <ul className="mt-2 max-h-32 overflow-y-auto text-xs break-all">
          {permissionKeys.length > 0 ? (
            permissionKeys.map((key) => <li key={key}>{key}</li>)
          ) : (
            <li className="text-jp-muted">No permissions assigned</li>
          )}
        </ul>
      </section>

      <Divider />

      <EffectiveAccessSummaryPanel summary={effectiveAccess} testId="role-effective-access-summary" />

      {validationIssues.length > 0 ? (
        <>
          <Divider />
          <section aria-labelledby="role-validation-heading">
            <h3 id="role-validation-heading" className="text-sm font-semibold text-gray-900">Validation issues</h3>
            <div className="mt-2">
              <AccessValidationSummary issues={validationIssues} />
            </div>
          </section>
        </>
      ) : null}

      <Divider />

      <section aria-labelledby="role-meta-heading">
        <h3 id="role-meta-heading" className="text-sm font-semibold text-gray-900">Record metadata</h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Created</dt><dd>{formatDate(role.createdAt)}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Updated</dt><dd>{formatDate(role.updatedAt)}</dd></div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="laravel-integration-heading">
        <h3 id="laravel-integration-heading" className="text-sm font-semibold text-gray-900">Future Laravel integration</h3>
        <p className="mt-1 text-xs text-jp-muted">
          Role definitions and permission assignments will be sourced from Laravel authorization APIs.
          This preview does not connect to live auth. Server-side policies and gates remain authoritative.
        </p>
      </section>

      <PermissionAssignmentPreview roleId={role.id} fixtureKeys={permissionKeys} />

      <Divider />

      <RoleComparisonPanel compareA={compareA} compareB={compareB} />

      {sampleUser ? (
        <>
          <Divider />
          <AccessDecisionExplainer user={sampleUser} permissionKey={explainerPermission} />
        </>
      ) : null}
    </div>
  );
}
