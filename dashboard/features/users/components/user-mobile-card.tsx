import { Card, CardTitle } from "@/components/ui/card";
import { AccessValidationBadge, MfaStatusBadge, UserStatusBadge } from "@/components/ui/status-badge";
import { formatDate } from "@/lib/format";
import type { UserTableRow } from "@/types/users";

type Props = {
  users: UserTableRow[];
  onView: (id: string) => void;
};

export function UserMobileCard({ users, onView }: Props) {
  return (
    <div className="space-y-3 md:hidden" data-testid="users-mobile-cards">
      {users.map((user) => (
        <Card key={user.id} className="p-4">
          <CardTitle className="text-base">{user.fullName}</CardTitle>
          <div className="mt-2 space-y-2 text-sm text-jp-muted">
            <div className="break-all text-gray-900">{user.email}</div>
            <div className="flex flex-wrap gap-2">
              <UserStatusBadge status={user.status} />
              <MfaStatusBadge status={user.mfaState} />
              <AccessValidationBadge status={user.validationState} />
            </div>
            <dl className="grid gap-1">
              <div className="flex justify-between gap-3">
                <dt className="text-jp-muted">User ID</dt>
                <dd className="font-medium text-gray-900">{user.id}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-jp-muted">Department</dt>
                <dd className="text-gray-900">{user.department}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-jp-muted">Job title</dt>
                <dd className="text-gray-900">{user.jobTitle}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-jp-muted">User type</dt>
                <dd className="text-gray-900">{user.userTypeLabel}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-jp-muted">Last sign-in</dt>
                <dd className="text-gray-900">{user.lastSignInAt ? formatDate(user.lastSignInAt) : "—"}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-jp-muted">Sessions</dt>
                <dd className="tabular-nums text-gray-900">{user.activeSessionCount}</dd>
              </div>
            </dl>
            {user.assignedRoleNames.length > 0 ? (
              <div className="flex flex-wrap gap-1">
                {user.assignedRoleNames.map((name) => (
                  <span key={name} className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-900">{name}</span>
                ))}
              </div>
            ) : (
              <p className="text-xs text-jp-muted">No roles assigned</p>
            )}
          </div>
          <button
            type="button"
            className="mt-3 min-h-11 w-full rounded-xl border border-jp-border bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
            onClick={() => onView(user.id)}
          >
            View details
          </button>
        </Card>
      ))}
    </div>
  );
}
