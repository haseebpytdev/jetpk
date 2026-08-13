"use client";

import { useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import {
  agentApplicationNeedsMoreInfo,
  approveAgentApplication,
  rejectAgentApplication,
} from "@/services/operational-api";
import type { AgentApplicationRecord } from "@/services/ops-modules-service";

export function AgentApplicationsWorkspace({ applications }: { applications: AgentApplicationRecord[] }) {
  const isLive = useDashboardLiveMode();
  const [selectedId, setSelectedId] = useState<string | null>(applications[0]?.id ?? null);
  const [note, setNote] = useState("");
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const selected = applications.find((row) => row.id === selectedId) ?? null;

  async function run(key: string, action: () => Promise<{ ok: boolean; message?: string }>) {
    if (!selected) {
      setError("Select an application first.");
      return;
    }
    if (!window.confirm(`Confirm ${key} for ${selected.agencyName}?`)) {
      return;
    }
    setBusy(key);
    setError(null);
    setSuccess(null);
    const result = await action();
    setBusy(null);
    if (!result.ok) {
      setError(result.message ?? "Request failed");
      return;
    }
    setSuccess(`Application ${key} recorded.`);
  }

  return (
    <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]" data-testid="agent-applications-workspace">
      <ul className="divide-y divide-jp-border rounded-xl border border-jp-border bg-white" data-testid="agent-applications-list">
        {applications.map((row) => (
          <li key={row.id}>
            <button
              type="button"
              className={`w-full p-4 text-left text-sm ${selectedId === row.id ? "bg-emerald-50" : ""}`}
              onClick={() => setSelectedId(row.id)}
            >
              <p className="font-medium text-gray-900">{row.agencyName}</p>
              <p className="text-jp-muted">
                {row.contactName} · {row.contactEmail} · {row.status}
              </p>
            </button>
          </li>
        ))}
      </ul>
      <aside className="space-y-3 rounded-xl border border-jp-border bg-white p-4">
        <h2 className="text-sm font-semibold text-gray-900">Review</h2>
        {selected ? (
          <>
            <p className="text-sm">{selected.agencyName}</p>
            <p className="text-xs text-jp-muted">
              {selected.contactName} · {selected.contactEmail}
            </p>
            <label className="block text-xs font-medium text-jp-muted" htmlFor="application-note">
              Operator note
            </label>
            <textarea
              id="application-note"
              className="min-h-24 w-full rounded-lg border border-jp-border p-2 text-sm"
              value={note}
              onChange={(e) => setNote(e.target.value)}
            />
            {error ? <p className="text-sm text-red-600">{error}</p> : null}
            {success ? <p className="text-sm text-emerald-700">{success}</p> : null}
            {isLive ? (
              <div className="flex flex-col gap-2">
                <button
                  type="button"
                  className="min-h-11 rounded-xl bg-jp-accent px-3 text-sm text-white disabled:opacity-60"
                  disabled={busy !== null}
                  data-testid="agent-application-approve"
                  onClick={() => run("approve", () => approveAgentApplication(selected.id, note))}
                >
                  Approve
                </button>
                <button
                  type="button"
                  className="min-h-11 rounded-xl border border-red-300 px-3 text-sm text-red-700 disabled:opacity-60"
                  disabled={busy !== null}
                  data-testid="agent-application-reject"
                  onClick={() => run("reject", () => rejectAgentApplication(selected.id, note || "Rejected"))}
                >
                  Reject
                </button>
                <button
                  type="button"
                  className="min-h-11 rounded-xl border border-jp-border px-3 text-sm disabled:opacity-60"
                  disabled={busy !== null}
                  data-testid="agent-application-needs-more-info"
                  onClick={() =>
                    run("request more info", () => agentApplicationNeedsMoreInfo(selected.id, note || "More information required"))
                  }
                >
                  Request more information
                </button>
              </div>
            ) : (
              <p className="text-xs text-jp-muted">Live review actions require authenticated dashboard mode.</p>
            )}
          </>
        ) : (
          <p className="text-sm text-jp-muted">Select an application to review.</p>
        )}
      </aside>
    </div>
  );
}
