import { Card, CardDescription, CardTitle } from "@/components/ui/card";
import { AccessRiskBadge, AccessValidationBadge } from "@/components/ui/status-badge";
import type { PermissionTableRow } from "@/types/permissions";

type Props = {
  permissions: PermissionTableRow[];
  onView: (id: string) => void;
};

function formatActionLabel(action: string): string {
  return action.replace(/([A-Z])/g, " $1").replace(/^./, (c) => c.toUpperCase());
}

function formatScopeLabel(scope: string): string {
  if (scope.startsWith("channel:")) {
    return scope.replace("channel:", "");
  }
  return scope;
}

export function PermissionMobileCard({ permissions, onView }: Props) {
  return (
    <div className="space-y-3 md:hidden" data-testid="permissions-mobile-cards">
      {permissions.map((permission) => (
        <Card key={permission.id} className="p-4">
          <CardTitle className="text-base">{permission.label}</CardTitle>
          <CardDescription className="mt-2 space-y-2 text-sm">
            <div className="font-mono text-xs text-jp-muted">{permission.key}</div>
            <div className="flex flex-wrap gap-2">
              <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs">{formatActionLabel(permission.risk)}</span>
              {permission.isHighRisk ? <AccessRiskBadge highRisk /> : null}
              <AccessValidationBadge status={permission.validationState} />
            </div>
            <p className="text-xs text-jp-muted">{permission.description}</p>
            <dl className="grid gap-1">
              <div className="flex justify-between gap-3">
                <dt className="text-jp-muted">Permission ID</dt>
                <dd className="font-medium">{permission.id}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-jp-muted">Domain</dt>
                <dd>{permission.domainLabel}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-jp-muted">Action</dt>
                <dd>{formatActionLabel(permission.action)}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-jp-muted">Prerequisite</dt>
                <dd className="font-mono text-xs">{permission.prerequisiteKey ?? "—"}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-jp-muted">Assigned roles</dt>
                <dd className="tabular-nums">{permission.assignedRoleCount}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-jp-muted">Laravel hint</dt>
                <dd className="max-w-[12rem] truncate font-mono text-xs">{permission.laravelPolicyHint}</dd>
              </div>
            </dl>
            <div className="flex flex-wrap gap-1">
              {permission.supportedScopes.map((scope) => (
                <span key={scope} className="rounded-full bg-gray-100 px-2 py-0.5 text-xs">
                  {formatScopeLabel(scope)}
                </span>
              ))}
            </div>
          </CardDescription>
          <button
            type="button"
            className="mt-3 min-h-11 w-full rounded-xl border border-jp-border bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
            onClick={() => onView(permission.id)}
          >
            View details
          </button>
        </Card>
      ))}
    </div>
  );
}
