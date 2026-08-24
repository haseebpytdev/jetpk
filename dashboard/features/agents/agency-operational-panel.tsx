"use client";

import { useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import {
  applyAgencyUserPermissionTemplate,
  updateAgencyPrefix,
  updateAgencyUserPermissions,
  updateAgencyUserRole,
} from "@/services/operational-api";

const AGENCY_ROLES = [
  "owner",
  "manager",
  "accountant",
  "sales_agent",
  "support_staff",
  "ticketing_staff",
  "viewer",
] as const;

const AGENT_PERMISSIONS = [
  "bookings.view",
  "bookings.create",
  "payments.view",
  "customers.view",
  "reports.view",
  "support.view",
] as const;

type Props = {
  agencyId?: string | null;
  userId?: string | null;
  initialPrefix?: string | null;
  compact?: boolean;
};

export function AgencyOperationalPanel({
  agencyId = null,
  userId = null,
  initialPrefix = "AG",
  compact = false,
}: Props) {
  const isLive = useDashboardLiveMode();
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [prefix, setPrefix] = useState((initialPrefix || "AG").toUpperCase());
  const [agencyRole, setAgencyRole] = useState<(typeof AGENCY_ROLES)[number]>("sales_agent");
  const [permissions, setPermissions] = useState<string[]>(["bookings.view"]);

  if (!isLive) {
    return (
      <p className="text-xs text-jp-muted" data-testid="agency-ops-preview">
        Agency operational actions are available in live dashboard mode only.
      </p>
    );
  }

  if (!agencyId) {
    return (
      <p className="text-xs text-jp-muted" data-testid="agency-ops-select-hint">
        Open an agent detail drawer to update that agency&apos;s prefix and agency-user role/permissions.
      </p>
    );
  }

  async function run(key: string, action: () => Promise<{ ok: boolean; message?: string }>) {
    setBusy(key);
    setError(null);
    setSuccess(null);
    const result = await action();
    setBusy(null);
    if (!result.ok) {
      setError(result.message ?? "Request failed");
      return;
    }
    setSuccess("Saved.");
  }

  return (
    <div
      className={compact ? "space-y-3" : "space-y-3 rounded-xl border border-jp-border p-4"}
      data-testid="agency-operational-panel"
    >
      {!compact ? <h2 className="text-sm font-semibold text-gray-900">Agency administration</h2> : null}
      <p className="text-xs text-jp-muted">
        Prefix and agency-user access write through Laravel AgencyManagementController handlers.
      </p>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}
      <div className="flex flex-wrap gap-2">
        <input
          className="rounded-lg border border-jp-border px-2 py-1 text-sm uppercase"
          value={prefix}
          maxLength={4}
          onChange={(e) => setPrefix(e.target.value.toUpperCase())}
          data-testid="agency-prefix-input"
        />
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
          disabled={busy !== null}
          data-testid="agency-prefix-update"
          onClick={() => run("prefix", () => updateAgencyPrefix(agencyId, prefix))}
        >
          Update agency prefix
        </button>
      </div>
      {userId ? (
        <div className="space-y-2">
          <label className="block text-xs">
            Agency role
            <select
              className="mt-1 w-full rounded-lg border border-jp-border px-2 py-2 text-sm"
              value={agencyRole}
              onChange={(e) => setAgencyRole(e.target.value as (typeof AGENCY_ROLES)[number])}
              data-testid="agency-role-select"
            >
              {AGENCY_ROLES.map((role) => (
                <option key={role} value={role}>
                  {role}
                </option>
              ))}
            </select>
          </label>
          <button
            type="button"
            className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
            disabled={busy !== null}
            data-testid="agency-role-update"
            onClick={() => run("role", () => updateAgencyUserRole(agencyId, userId, agencyRole))}
          >
            Assign agency role
          </button>
          <div className="grid max-h-40 gap-1 overflow-auto rounded-lg border border-jp-border p-2 text-xs">
            {AGENT_PERMISSIONS.map((key) => (
              <label key={key} className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={permissions.includes(key)}
                  onChange={(e) => {
                    setPermissions((current) =>
                      e.target.checked ? [...current, key] : current.filter((item) => item !== key),
                    );
                  }}
                />
                <span>{key}</span>
              </label>
            ))}
          </div>
          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
              disabled={busy !== null}
              data-testid="agency-permissions-update"
              onClick={() => run("perms", () => updateAgencyUserPermissions(agencyId, userId, permissions))}
            >
              Update agent permissions
            </button>
            <button
              type="button"
              className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
              disabled={busy !== null}
              data-testid="agency-permissions-template"
              onClick={() => run("template", () => applyAgencyUserPermissionTemplate(agencyId, userId))}
            >
              Apply permission template
            </button>
          </div>
        </div>
      ) : (
        <p className="text-xs text-jp-muted">Primary user missing — prefix can still be updated.</p>
      )}
    </div>
  );
}
