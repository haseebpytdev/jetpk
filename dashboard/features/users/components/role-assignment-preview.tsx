"use client";

import { useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/page-layout";
import { Select } from "@/components/ui/select";
import { buildEffectiveAccessSummary } from "@/lib/access-control/effective-access";
import { validateRoleAssignmentPreview } from "@/lib/access-control/access-validation";
import { mockRoles } from "@/mocks/rbac-fixtures";
import type { User } from "@/types/access-control";
import { AccessValidationSummary } from "@/features/users/components/access-validation-summary";
import { EffectiveAccessSummaryPanel } from "@/features/users/components/effective-access-summary";

type Props = {
  user: User;
};

export function RoleAssignmentPreview({ user }: Props) {
  const fixtureRoleIds = user.assignedRoles.map((r) => r.roleId);
  const [previewRoleIds, setPreviewRoleIds] = useState<string[]>(fixtureRoleIds);
  const [selectedRole, setSelectedRole] = useState("");

  const previewAccess = useMemo(
    () => buildEffectiveAccessSummary(previewRoleIds),
    [previewRoleIds],
  );

  const previewIssues = useMemo(
    () => validateRoleAssignmentPreview(user.id, fixtureRoleIds, previewRoleIds),
    [user.id, fixtureRoleIds, previewRoleIds],
  );

  const availableRoles = mockRoles.filter((r) => !previewRoleIds.includes(r.id));

  const applyToPreview = () => {
    if (!selectedRole || previewRoleIds.includes(selectedRole)) return;
    setPreviewRoleIds((prev) => [...prev, selectedRole]);
    setSelectedRole("");
  };

  const removePreviewRole = (roleId: string) => {
    setPreviewRoleIds((prev) => prev.filter((id) => id !== roleId));
  };

  const resetPreview = () => {
    setPreviewRoleIds(fixtureRoleIds);
    setSelectedRole("");
  };

  return (
    <section aria-labelledby="role-preview-heading" data-testid="role-assignment-preview">
      <h3 id="role-preview-heading" className="text-sm font-semibold text-gray-900">
        Role assignment preview
      </h3>
      <p className="mt-1 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900" role="status">
        Assignment preview only — changes are local and not persisted. Refresh restores fixture roles.
      </p>

      <div className="mt-3 flex flex-wrap gap-2">
        {previewRoleIds.map((roleId) => {
          const role = mockRoles.find((r) => r.id === roleId);
          return (
            <span key={roleId} className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-xs">
              {role?.name ?? roleId}
              <button
                type="button"
                className="min-h-6 min-w-6 rounded-full text-jp-muted hover:text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => removePreviewRole(roleId)}
                aria-label={`Remove ${role?.name ?? roleId} from preview`}
              >
                ×
              </button>
            </span>
          );
        })}
        {previewRoleIds.length === 0 ? (
          <span className="text-xs text-jp-muted">No roles in preview</span>
        ) : null}
      </div>

      <div className="mt-3 flex flex-wrap items-end gap-2">
        <div className="min-w-[12rem] flex-1">
          <Label htmlFor="role-preview-select">Select role</Label>
          <Select
            id="role-preview-select"
            value={selectedRole}
            onChange={(e) => setSelectedRole(e.target.value)}
          >
            <option value="">Choose a role…</option>
            {availableRoles.map((r) => (
              <option key={r.id} value={r.id}>{r.name}</option>
            ))}
          </Select>
        </div>
        <Button type="button" size="sm" variant="secondary" onClick={applyToPreview} disabled={!selectedRole}>
          Apply to preview
        </Button>
        <Button type="button" size="sm" variant="ghost" onClick={resetPreview}>
          Reset preview
        </Button>
      </div>

      <div className="mt-4">
        <AccessValidationSummary issues={previewIssues} />
      </div>

      <div className="mt-4">
        <EffectiveAccessSummaryPanel summary={previewAccess} testId="role-preview-effective-access" />
      </div>
    </section>
  );
}
