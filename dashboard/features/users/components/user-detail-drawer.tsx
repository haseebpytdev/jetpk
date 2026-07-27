"use client";

import { Divider } from "@/components/ui/divider";
import { PreviewDataBanner } from "@/components/ui/page-layout";
import { AccessValidationBadge, MfaStatusBadge, UserStatusBadge } from "@/components/ui/status-badge";
import { getRoleName } from "@/lib/access-control/effective-access";
import { formatDate } from "@/lib/format";
import { USER_TYPE_LABELS } from "@/types/access-control";
import type { User } from "@/types/access-control";
import { AccessValidationSummary } from "@/features/users/components/access-validation-summary";
import { EffectiveAccessSummaryPanel } from "@/features/users/components/effective-access-summary";
import { RoleAssignmentPreview } from "@/features/users/components/role-assignment-preview";
import { UserSecuritySummary } from "@/features/users/components/user-security-summary";

export function UserDetailDrawerContent({ user }: { user: User }) {
  return (
    <div className="space-y-5" data-testid="user-detail-drawer">
      <PreviewDataBanner className="text-xs" />

      <section aria-labelledby="user-identity-heading">
        <h3 id="user-identity-heading" className="text-sm font-semibold text-gray-900">Identity</h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">User ID</dt><dd className="font-medium">{user.id}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Full name</dt><dd>{user.profile.fullName}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Display name</dt><dd>{user.profile.displayName}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">User type</dt><dd>{USER_TYPE_LABELS[user.profile.userType]}</dd></div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="user-contact-heading">
        <h3 id="user-contact-heading" className="text-sm font-semibold text-gray-900">Contact</h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div><dt className="text-jp-muted">Email</dt><dd className="break-all">{user.contact.email}</dd></div>
          {user.contact.phone ? <div><dt className="text-jp-muted">Phone</dt><dd>{user.contact.phone}</dd></div> : null}
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="user-org-heading">
        <h3 id="user-org-heading" className="text-sm font-semibold text-gray-900">Organization</h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Department</dt><dd>{user.profile.department || "—"}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Job title</dt><dd>{user.profile.jobTitle}</dd></div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="user-status-heading">
        <h3 id="user-status-heading" className="text-sm font-semibold text-gray-900">Account state</h3>
        <div className="mt-2 flex flex-wrap gap-2">
          <UserStatusBadge status={user.security.status} />
          <MfaStatusBadge status={user.security.mfaState} />
          <AccessValidationBadge status={user.validationState} />
        </div>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Verification</dt><dd>{user.security.verificationState}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Invitation</dt><dd>{user.security.invitationState}</dd></div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="user-roles-heading">
        <h3 id="user-roles-heading" className="text-sm font-semibold text-gray-900">Assigned roles (fixture)</h3>
        <ul className="mt-2 space-y-1 text-sm">
          {user.assignedRoles.length > 0 ? (
            user.assignedRoles.map((r) => (
              <li key={r.roleId}>{getRoleName(r.roleId)}</li>
            ))
          ) : (
            <li className="text-jp-muted">No roles assigned</li>
          )}
        </ul>
      </section>

      <Divider />

      <UserSecuritySummary user={user} />
      <EffectiveAccessSummaryPanel summary={user.effectiveAccess} testId="user-effective-access-summary" />

      <Divider />

      <section aria-labelledby="user-session-heading">
        <h3 id="user-session-heading" className="text-sm font-semibold text-gray-900">Session and activity</h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Last sign-in</dt><dd>{user.security.lastSignInAt ? formatDate(user.security.lastSignInAt) : "—"}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Failed sign-ins</dt><dd className="tabular-nums">{user.security.failedSignInCount}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Active sessions</dt><dd className="tabular-nums">{user.session.activeSessionCount}</dd></div>
          {user.session.lastSignInMaskedLocation ? (
            <div><dt className="text-jp-muted">Last location</dt><dd>{user.session.lastSignInMaskedLocation}</dd></div>
          ) : null}
        </dl>
        <h4 className="mt-3 text-xs font-semibold text-gray-900">Recent activity</h4>
        <ul className="mt-1 list-disc pl-5 text-xs text-jp-muted">
          {user.activity.recentActions.map((action) => (
            <li key={action}>{action}</li>
          ))}
        </ul>
      </section>

      <Divider />

      <section aria-labelledby="user-meta-heading">
        <h3 id="user-meta-heading" className="text-sm font-semibold text-gray-900">Record metadata</h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Created</dt><dd>{formatDate(user.createdAt)}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Updated</dt><dd>{formatDate(user.updatedAt)}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Created by</dt><dd>{user.createdBy}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Updated by</dt><dd>{user.updatedBy}</dd></div>
        </dl>
      </section>

      {user.validationIssues.length > 0 ? (
        <>
          <Divider />
          <section aria-labelledby="user-validation-heading">
            <h3 id="user-validation-heading" className="text-sm font-semibold text-gray-900">Validation issues</h3>
            <div className="mt-2">
              <AccessValidationSummary issues={user.validationIssues} />
            </div>
          </section>
        </>
      ) : null}

      <Divider />

      <section aria-labelledby="laravel-integration-heading">
        <h3 id="laravel-integration-heading" className="text-sm font-semibold text-gray-900">Future Laravel integration</h3>
        <p className="mt-1 text-xs text-jp-muted">
          User records, roles, and permissions will be sourced from Laravel authentication and authorization APIs.
          This preview does not connect to live auth. Server-side policies and gates remain authoritative.
        </p>
      </section>

      <RoleAssignmentPreview user={user} />
    </div>
  );
}
