"use client";

import { useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import {
  applyAgencyUserPermissionTemplate,
  updateAgencyUserPermissions,
  updateAgencyUserRole,
} from "@/services/operational-api";
import type { User } from "@/types/access-control";

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

export function AgencyUserAccessPanel({ user }: { user: User }) {
  const isLive = useDashboardLiveMode();
  const agencyId = user.agencyId;
  const userId = user.laravelUserId ?? user.id;
  const isAgencyUser =
    user.profile.userType === "bookingAgent" || user.profile.userType === "agentStaff";
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [agencyRole, setAgencyRole] = useState<(typeof AGENCY_ROLES)[number]>("sales_agent");
  const [permissions, setPermissions] = useState<string[]>(["bookings.view"]);

  if (!isLive || !isAgencyUser || !agencyId) {
    return null;
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
    <section className="space-y-2" data-testid="agency-user-access-panel">
      <h3 className="text-sm font-semibold text-gray-900">Agency role and permissions</h3>
      <p className="text-xs text-jp-muted">
        Separate from platform staff RBAC. Writes through Laravel agency-user handlers.
      </p>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}
      <label className="block text-xs">
        Agency role
        <select
          className="mt-1 w-full rounded-lg border border-jp-border px-2 py-2 text-sm"
          value={agencyRole}
          onChange={(e) => setAgencyRole(e.target.value as (typeof AGENCY_ROLES)[number])}
          data-testid="user-agency-role-select"
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
        className="min-h-11 rounded-xl border border-jp-border px-3 text-sm disabled:opacity-60"
        disabled={busy !== null}
        data-testid="user-agency-role-update"
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
          className="min-h-11 rounded-xl border border-jp-border px-3 text-sm disabled:opacity-60"
          disabled={busy !== null}
          data-testid="user-agency-permissions-update"
          onClick={() => run("perms", () => updateAgencyUserPermissions(agencyId, userId, permissions))}
        >
          Update agent permissions
        </button>
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-3 text-sm disabled:opacity-60"
          disabled={busy !== null}
          data-testid="user-agency-permissions-template"
          onClick={() => run("template", () => applyAgencyUserPermissionTemplate(agencyId, userId))}
        >
          Apply permission template
        </button>
      </div>
    </section>
  );
}
