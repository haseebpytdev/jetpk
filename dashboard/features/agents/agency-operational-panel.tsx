"use client";

import { useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import {
  agentApplicationNeedsMoreInfo,
  approveAgentApplication,
  applyAgencyUserPermissionTemplate,
  rejectAgentApplication,
  updateAgencyPrefix,
  updateAgencyUserPermissions,
  updateAgencyUserRole,
} from "@/services/operational-api";

export function AgencyOperationalPanel() {
  const isLive = useDashboardLiveMode();
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [prefix, setPrefix] = useState("AG");

  if (!isLive) {
    return (
      <p className="text-xs text-jp-muted" data-testid="agency-ops-preview">
        Agency operational actions are available in live dashboard mode only.
      </p>
    );
  }

  async function run(key: string, action: () => Promise<{ ok: boolean; message?: string }>) {
    setBusy(key);
    setError(null);
    const result = await action();
    setBusy(null);
    if (!result.ok) {
      setError(result.message ?? "Request failed");
    }
  }

  return (
    <div className="space-y-3 rounded-xl border border-jp-border p-4" data-testid="agency-operational-panel">
      <h2 className="text-sm font-semibold text-gray-900">Agency administration</h2>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      <div className="flex flex-wrap gap-2">
        <input
          className="rounded-lg border border-jp-border px-2 py-1 text-sm"
          value={prefix}
          onChange={(e) => setPrefix(e.target.value)}
          data-testid="agency-prefix-input"
        />
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
          disabled={busy !== null}
          data-testid="agency-prefix-update"
          onClick={() => run("prefix", () => updateAgencyPrefix("1", prefix))}
        >
          Update agency prefix
        </button>
        <button
          type="button"
          className="min-h-11 rounded-xl bg-jp-accent px-3 py-2 text-sm text-white disabled:opacity-60"
          disabled={busy !== null}
          data-testid="agent-application-approve"
          onClick={() => run("approve", () => approveAgentApplication("1"))}
        >
          Approve application
        </button>
        <button
          type="button"
          className="min-h-11 rounded-xl border border-red-300 px-3 py-2 text-sm text-red-700 disabled:opacity-60"
          disabled={busy !== null}
          data-testid="agent-application-reject"
          onClick={() => run("reject", () => rejectAgentApplication("1", "Incomplete docs"))}
        >
          Reject application
        </button>
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
          disabled={busy !== null}
          data-testid="agent-application-needs-more-info"
          onClick={() => run("needs-info", () => agentApplicationNeedsMoreInfo("1", "Upload NTN"))}
        >
          Request more info
        </button>
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
          disabled={busy !== null}
          data-testid="agency-role-update"
          onClick={() => run("role", () => updateAgencyUserRole("1", "1", "agent_staff"))}
        >
          Assign agency role
        </button>
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
          disabled={busy !== null}
          data-testid="agency-permissions-update"
          onClick={() => run("perms", () => updateAgencyUserPermissions("1", "1", ["bookings.view"]))}
        >
          Update agent permissions
        </button>
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
          disabled={busy !== null}
          data-testid="agency-permissions-template"
          onClick={() => run("template", () => applyAgencyUserPermissionTemplate("1", "1", "default"))}
        >
          Apply permission template
        </button>
      </div>
    </div>
  );
}
