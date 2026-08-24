"use client";

import { useState } from "react";
import {
  approveCommissionEntry,
  generateCommissionStatement,
  recordCommissionAdjustment,
  recordCommissionPayout,
  rejectCommissionEntry,
} from "@/services/operational-api";

type PendingEntry = {
  id: string;
  agentName: string;
  amountLabel: string;
  status: string;
};

type AgentRow = {
  id: string;
  code: string;
  name: string;
  balance: Record<string, unknown>;
};

export function CommissionsWorkspace({
  pendingEntries,
  agents = [],
}: {
  pendingEntries: PendingEntry[];
  agents?: AgentRow[];
}) {
  const [rows, setRows] = useState(pendingEntries);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [selectedAgentId, setSelectedAgentId] = useState(agents[0]?.id ?? "");
  const [amount, setAmount] = useState("100");
  const [description, setDescription] = useState("");

  async function runMutation(entry: PendingEntry, action: "approve" | "reject") {
    if (busyId) {
      return;
    }
    setError(null);
    setSuccess(null);
    setBusyId(entry.id);
    const result =
      action === "approve"
        ? await approveCommissionEntry(entry.id)
        : await rejectCommissionEntry(entry.id, "Rejected from admin commissions review.");
    setBusyId(null);
    if (!result.ok) {
      setError(result.message ?? "Commission review action failed.");
      return;
    }
    setRows((current) => current.filter((row) => row.id !== entry.id));
  }

  async function runAgentAction(action: "adjust" | "payout" | "statement") {
    if (!selectedAgentId || busyId) {
      return;
    }
    setError(null);
    setSuccess(null);
    setBusyId(action);
    const parsed = Number(amount);
    const result =
      action === "adjust"
        ? await recordCommissionAdjustment(selectedAgentId, parsed, description)
        : action === "payout"
          ? await recordCommissionPayout(selectedAgentId, parsed, description)
          : await generateCommissionStatement(selectedAgentId);
    setBusyId(null);
    if (!result.ok) {
      setError(result.message ?? "Commission action failed.");
      return;
    }
    setSuccess(
      action === "adjust"
        ? "Adjustment recorded."
        : action === "payout"
          ? "Payout recorded."
          : "Statement generated.",
    );
  }

  return (
    <div className="space-y-4">
      <section className="space-y-3 rounded-xl border border-jp-border bg-white p-4" data-testid="commissions-workspace">
        <h2 className="text-sm font-semibold">Pending commission entries</h2>
        {error ? <p className="text-sm text-red-600">{error}</p> : null}
        {success ? <p className="text-sm text-emerald-700">{success}</p> : null}
        {rows.length === 0 ? (
          <p className="text-sm text-jp-muted">No pending commission entries require review.</p>
        ) : (
          <ul className="divide-y divide-jp-border">
            {rows.map((entry) => (
              <li key={entry.id} className="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                <div>
                  <p className="font-medium">{entry.agentName}</p>
                  <p className="text-jp-muted">{entry.amountLabel}</p>
                </div>
                <div className="flex gap-2">
                  <button
                    type="button"
                    className="rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs"
                    data-testid="commission-approve"
                    disabled={busyId === entry.id}
                    onClick={() => void runMutation(entry, "approve")}
                  >
                    Approve
                  </button>
                  <button
                    type="button"
                    className="rounded-lg border border-red-200 bg-red-50 px-2 py-1 text-xs"
                    data-testid="commission-reject"
                    disabled={busyId === entry.id}
                    onClick={() => void runMutation(entry, "reject")}
                  >
                    Reject
                  </button>
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="space-y-3 rounded-xl border border-jp-border bg-white p-4" data-testid="commissions-agent-actions">
        <h2 className="text-sm font-semibold">Adjustments, payouts, and statements</h2>
        <p className="text-xs text-jp-muted">
          Posts through Laravel AgentCommissionController. Requires finance commission permissions.
        </p>
        {agents.length === 0 ? (
          <p className="text-sm text-jp-muted">No agents available for commission actions.</p>
        ) : (
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label className="text-xs">
              Agent
              <select
                className="mt-1 w-full rounded-lg border border-jp-border px-2 py-2 text-sm"
                value={selectedAgentId}
                onChange={(e) => setSelectedAgentId(e.target.value)}
                data-testid="commission-agent-select"
              >
                {agents.map((agent) => (
                  <option key={agent.id} value={agent.id}>
                    {agent.name} ({agent.code || agent.id})
                  </option>
                ))}
              </select>
            </label>
            <label className="text-xs">
              Amount
              <input
                className="mt-1 w-full rounded-lg border border-jp-border px-2 py-2 text-sm"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                data-testid="commission-amount-input"
              />
            </label>
            <label className="text-xs sm:col-span-2">
              Description
              <input
                className="mt-1 w-full rounded-lg border border-jp-border px-2 py-2 text-sm"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                data-testid="commission-description-input"
              />
            </label>
            <button
              type="button"
              className="min-h-11 rounded-xl border border-jp-border px-3 text-sm disabled:opacity-60"
              disabled={!selectedAgentId || busyId !== null}
              data-testid="commission-adjustment"
              onClick={() => void runAgentAction("adjust")}
            >
              Record adjustment
            </button>
            <button
              type="button"
              className="min-h-11 rounded-xl border border-jp-border px-3 text-sm disabled:opacity-60"
              disabled={!selectedAgentId || busyId !== null}
              data-testid="commission-payout"
              onClick={() => void runAgentAction("payout")}
            >
              Record payout
            </button>
            <button
              type="button"
              className="min-h-11 rounded-xl border border-jp-border px-3 text-sm disabled:opacity-60"
              disabled={!selectedAgentId || busyId !== null}
              data-testid="commission-statement"
              onClick={() => void runAgentAction("statement")}
            >
              Generate statement
            </button>
          </div>
        )}
      </section>
    </div>
  );
}
