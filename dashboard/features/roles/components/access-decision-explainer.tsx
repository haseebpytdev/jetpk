"use client";

import { useMemo, useState } from "react";
import { Label } from "@/components/ui/page-layout";
import { Select } from "@/components/ui/select";
import { AccessRiskBadge } from "@/components/ui/status-badge";
import { evaluateAccessDecision } from "@/lib/access-control/access-decision";
import { PERMISSION_CATALOG } from "@/lib/access-control/permission-catalog";
import type { User } from "@/types/access-control";

type Props = {
  user: User;
  permissionKey: string;
};

function formatReason(reason: string): string {
  return reason.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
}

export function AccessDecisionExplainer({ user, permissionKey: initialKey }: Props) {
  const [permissionKey, setPermissionKey] = useState(initialKey);

  const decision = useMemo(
    () =>
      evaluateAccessDecision({
        user,
        roleIds: user.assignedRoles.map((r) => r.roleId),
        permissionKey,
      }),
    [user, permissionKey],
  );

  const permission = PERMISSION_CATALOG.find((p) => p.key === permissionKey);

  return (
    <section aria-labelledby="access-decision-heading" data-testid="access-decision-explainer">
      <h3 id="access-decision-heading" className="text-sm font-semibold text-gray-900">
        Authorization explanation preview
      </h3>
      <p className="mt-1 text-xs text-jp-muted">
        Fixture-only decision for {user.profile.fullName} ({user.id}). Laravel policies remain authoritative.
      </p>

      <div className="mt-3">
        <Label htmlFor="access-decision-permission">Permission</Label>
        <Select
          id="access-decision-permission"
          value={permissionKey}
          onChange={(e) => setPermissionKey(e.target.value)}
        >
          {PERMISSION_CATALOG.map((p) => (
            <option key={p.key} value={p.key}>{p.label}</option>
          ))}
        </Select>
      </div>

      <dl className="mt-3 grid gap-2 rounded-xl border border-jp-border p-3 text-sm">
        <div className="flex justify-between gap-4">
          <dt className="text-jp-muted">Permission</dt>
          <dd className="text-right break-all">{permission?.label ?? permissionKey}</dd>
        </div>
        <div className="flex justify-between gap-4">
          <dt className="text-jp-muted">Allowed</dt>
          <dd className="font-medium">{decision.allowed ? "Yes" : "No"}</dd>
        </div>
        <div className="flex justify-between gap-4">
          <dt className="text-jp-muted">Reason</dt>
          <dd>{formatReason(decision.reason)}</dd>
        </div>
        {decision.sourceRoleId ? (
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Source role</dt>
            <dd>{decision.sourceRoleId}</dd>
          </div>
        ) : null}
        {decision.scope ? (
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Scope</dt>
            <dd>{decision.scope}</dd>
          </div>
        ) : null}
        <div className="flex justify-between gap-4">
          <dt className="text-jp-muted">Requires approval</dt>
          <dd>{decision.requiresApproval ? "Yes" : "No"}</dd>
        </div>
        <div className="flex items-center justify-between gap-4">
          <dt className="text-jp-muted">Risk</dt>
          <dd><AccessRiskBadge highRisk={decision.highRisk} /></dd>
        </div>
      </dl>
    </section>
  );
}
