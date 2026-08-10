"use client";

import { Divider } from "@/components/ui/divider";
import { DetailDrawerSourceNotice } from "@/components/ui/detail-drawer-source-notice";
import { AccessRiskBadge, AccessValidationBadge } from "@/components/ui/status-badge";
import { PERMISSION_GROUP_LABELS } from "@/lib/access-control/permission-catalog";
import { AccessValidationSummary } from "@/features/users/components/access-validation-summary";
import type { AccessValidationIssue, Permission } from "@/types/access-control";

type Props = {
  permission: Permission;
  assignedRoles: { id: string; name: string }[];
  validationIssues: AccessValidationIssue[];
};

function formatActionLabel(action: string): string {
  return action.replace(/([A-Z])/g, " $1").replace(/^./, (c) => c.toUpperCase());
}

function formatScopeLabel(scope: string): string {
  if (scope.startsWith("channel:")) {
    return scope.replace("channel:", "Channel: ");
  }
  return scope.charAt(0).toUpperCase() + scope.slice(1);
}

export function PermissionDetailDrawerContent({ permission, assignedRoles, validationIssues }: Props) {
  return (
    <div className="space-y-5" data-testid="permission-detail-drawer">
      <DetailDrawerSourceNotice className="text-xs" />

      <div role="status" className="rounded-xl border border-amber-200 bg-amber-50/80 px-3 py-2 text-xs text-amber-900">
        This dashboard preview does not enforce production authorization.
      </div>

      <section aria-labelledby="permission-identity-heading">
        <h3 id="permission-identity-heading" className="text-sm font-semibold text-gray-900">Identity</h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Permission ID</dt><dd className="font-medium">{permission.id}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Key</dt><dd className="font-mono text-xs">{permission.key}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Label</dt><dd>{permission.label}</dd></div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="permission-classification-heading">
        <h3 id="permission-classification-heading" className="text-sm font-semibold text-gray-900">Classification</h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Domain</dt><dd>{PERMISSION_GROUP_LABELS[permission.domain]}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Action</dt><dd>{formatActionLabel(permission.action)}</dd></div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Risk</dt>
            <dd className="flex flex-wrap gap-1">
              <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs">{formatActionLabel(permission.risk)}</span>
              {permission.isHighRisk ? <AccessRiskBadge highRisk /> : null}
            </dd>
          </div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Channel aware</dt><dd>{permission.channelAware ? "Yes" : "No"}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Implementation</dt><dd>{formatActionLabel(permission.implementationStatus)}</dd></div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="permission-description-heading">
        <h3 id="permission-description-heading" className="text-sm font-semibold text-gray-900">Description</h3>
        <p className="mt-2 text-sm text-jp-muted">{permission.description}</p>
      </section>

      <Divider />

      <section aria-labelledby="permission-prerequisite-heading">
        <h3 id="permission-prerequisite-heading" className="text-sm font-semibold text-gray-900">Prerequisite</h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Required permission</dt>
            <dd className="font-mono text-xs">{permission.prerequisiteKey ?? "None"}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="permission-scopes-heading">
        <h3 id="permission-scopes-heading" className="text-sm font-semibold text-gray-900">Supported scopes</h3>
        <ul className="mt-2 flex flex-wrap gap-1">
          {permission.supportedScopes.map((scope) => (
            <li key={scope} className="rounded-full bg-gray-100 px-2 py-0.5 text-xs">
              {formatScopeLabel(scope)}
            </li>
          ))}
        </ul>
      </section>

      <Divider />

      <section aria-labelledby="permission-roles-heading">
        <h3 id="permission-roles-heading" className="text-sm font-semibold text-gray-900">Assigned roles (fixture)</h3>
        <ul className="mt-2 space-y-1 text-sm">
          {assignedRoles.length > 0 ? (
            assignedRoles.map((role) => (
              <li key={role.id}>{role.name}</li>
            ))
          ) : (
            <li className="text-jp-muted">Not assigned to any roles</li>
          )}
        </ul>
      </section>

      <Divider />

      <section aria-labelledby="permission-validation-heading">
        <h3 id="permission-validation-heading" className="text-sm font-semibold text-gray-900">Validation</h3>
        <div className="mt-2">
          <AccessValidationBadge status={validationIssues.some((i) => i.blocking) ? "blocked" : validationIssues.some((i) => i.severity === "warning") ? "warning" : "valid"} />
        </div>
        <div className="mt-3">
          <AccessValidationSummary issues={validationIssues} />
        </div>
      </section>

      <Divider />

      <section aria-labelledby="permission-laravel-heading">
        <h3 id="permission-laravel-heading" className="text-sm font-semibold text-gray-900">Future Laravel integration</h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div>
            <dt className="text-jp-muted">Policy hint</dt>
            <dd className="mt-1 font-mono text-xs break-all">{permission.laravelPolicyHint}</dd>
          </div>
        </dl>
        <p className="mt-2 text-xs text-jp-muted">
          Permission catalog entries map to future Laravel Policies and Gates. Server-side authorization remains authoritative in production.
        </p>
      </section>
    </div>
  );
}
