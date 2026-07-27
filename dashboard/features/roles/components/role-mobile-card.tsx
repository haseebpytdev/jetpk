import { Card, CardDescription, CardTitle } from "@/components/ui/card";
import { AccessRiskBadge, AccessValidationBadge, UserStatusBadge } from "@/components/ui/status-badge";
import { formatDate } from "@/lib/format";
import type { RoleTableRow } from "@/types/roles";

type Props = {
  roles: RoleTableRow[];
  onView: (id: string) => void;
};

export function RoleMobileCard({ roles, onView }: Props) {
  return (
    <div className="space-y-3 md:hidden" data-testid="roles-mobile-cards">
      {roles.map((role) => (
        <Card key={role.id} className="p-4">
          <CardTitle className="text-base">{role.name}</CardTitle>
          <CardDescription className="mt-2 space-y-2 text-sm">
            <div className="text-jp-muted">{role.description}</div>
            <div className="flex flex-wrap gap-2">
              <UserStatusBadge status={role.status} />
              <AccessValidationBadge status={role.validationState} />
              {role.highRiskPermissionCount > 0 ? (
                <AccessRiskBadge highRisk label={`${role.highRiskPermissionCount} high risk`} />
              ) : null}
            </div>
            <dl className="grid gap-1">
              <div className="flex justify-between gap-3">
                <dt className="text-jp-muted">Role ID</dt>
                <dd className="font-medium">{role.id}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-jp-muted">Category</dt>
                <dd>{role.categoryLabel}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-jp-muted">Type</dt>
                <dd>{role.isSystem ? "System" : "Custom"}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-jp-muted">Protected</dt>
                <dd>{role.isProtected ? "Yes" : "No"}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-jp-muted">Assigned users</dt>
                <dd className="tabular-nums">{role.assignedUserCount}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-jp-muted">Permissions</dt>
                <dd className="tabular-nums">{role.permissionCount}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-jp-muted">Channel scope</dt>
                <dd>{role.scopeLabel}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-jp-muted">Last updated</dt>
                <dd>{formatDate(role.updatedAt)}</dd>
              </div>
            </dl>
          </CardDescription>
          <button
            type="button"
            className="mt-3 min-h-11 w-full rounded-xl border border-jp-border bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
            onClick={() => onView(role.id)}
          >
            View details
          </button>
        </Card>
      ))}
    </div>
  );
}
