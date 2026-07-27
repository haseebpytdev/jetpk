"use client";

import { useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/page-layout";
import { Select } from "@/components/ui/select";
import { buildEffectiveAccessSummary } from "@/lib/access-control/effective-access";
import { PERMISSION_CATALOG } from "@/lib/access-control/permission-catalog";
import { validatePermissionAssignmentPreview } from "@/lib/access-control/permission-preview-validation";
import { AccessValidationSummary } from "@/features/users/components/access-validation-summary";
import { EffectiveAccessSummaryPanel } from "@/features/users/components/effective-access-summary";

type Props = {
  roleId: string;
  fixtureKeys: string[];
};

export function PermissionAssignmentPreview({ roleId, fixtureKeys }: Props) {
  const [previewKeys, setPreviewKeys] = useState<string[]>(fixtureKeys);
  const [selectedPermission, setSelectedPermission] = useState("");

  const previewAccess = useMemo(
    () => {
      const summary = buildEffectiveAccessSummary([roleId]);
      if (previewKeys.length === fixtureKeys.length && previewKeys.every((k, i) => k === fixtureKeys[i])) {
        return summary;
      }
      return {
        ...summary,
        totalPermissions: previewKeys.length,
        highRiskPermissions: previewKeys.filter((k) => PERMISSION_CATALOG.find((p) => p.key === k)?.isHighRisk),
      };
    },
    [roleId, previewKeys, fixtureKeys],
  );

  const previewIssues = useMemo(
    () => validatePermissionAssignmentPreview(roleId, fixtureKeys, previewKeys),
    [roleId, fixtureKeys, previewKeys],
  );

  const availablePermissions = PERMISSION_CATALOG.filter((p) => !previewKeys.includes(p.key));

  const applyToPreview = () => {
    if (!selectedPermission || previewKeys.includes(selectedPermission)) return;
    setPreviewKeys((prev) => [...prev, selectedPermission]);
    setSelectedPermission("");
  };

  const removePreviewPermission = (key: string) => {
    setPreviewKeys((prev) => prev.filter((k) => k !== key));
  };

  const resetPreview = () => {
    setPreviewKeys(fixtureKeys);
    setSelectedPermission("");
  };

  return (
    <section aria-labelledby="permission-preview-heading" data-testid="permission-assignment-preview">
      <h3 id="permission-preview-heading" className="text-sm font-semibold text-gray-900">
        Permission preview only
      </h3>
      <p className="mt-1 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900" role="status">
        Permission preview only — changes are local and not persisted. Refresh restores fixture permissions.
      </p>

      <div className="mt-3 flex flex-wrap gap-2">
        {previewKeys.map((key) => {
          const perm = PERMISSION_CATALOG.find((p) => p.key === key);
          return (
            <span key={key} className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-xs">
              {perm?.label ?? key}
              <button
                type="button"
                className="min-h-6 min-w-6 rounded-full text-jp-muted hover:text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => removePreviewPermission(key)}
                aria-label={`Remove ${perm?.label ?? key} from preview`}
              >
                ×
              </button>
            </span>
          );
        })}
        {previewKeys.length === 0 ? (
          <span className="text-xs text-jp-muted">No permissions in preview</span>
        ) : null}
      </div>

      <div className="mt-3 flex flex-wrap items-end gap-2">
        <div className="min-w-[12rem] flex-1">
          <Label htmlFor="permission-preview-select">Select permission</Label>
          <Select
            id="permission-preview-select"
            value={selectedPermission}
            onChange={(e) => setSelectedPermission(e.target.value)}
          >
            <option value="">Choose a permission…</option>
            {availablePermissions.map((p) => (
              <option key={p.key} value={p.key}>{p.label}</option>
            ))}
          </Select>
        </div>
        <Button type="button" size="sm" variant="secondary" onClick={applyToPreview} disabled={!selectedPermission}>
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
        <EffectiveAccessSummaryPanel summary={previewAccess} testId="permission-preview-effective-access" />
      </div>
    </section>
  );
}
