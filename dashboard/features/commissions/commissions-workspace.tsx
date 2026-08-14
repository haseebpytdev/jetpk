"use client";

import { useState } from "react";
import { approveCommissionEntry, rejectCommissionEntry } from "@/services/operational-api";

type PendingEntry = {
  id: string;
  agentName: string;
  amountLabel: string;
  status: string;
};

export function CommissionsWorkspace({
  pendingEntries,
}: {
  pendingEntries: PendingEntry[];
}) {
  const [rows, setRows] = useState(pendingEntries);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function runMutation(entry: PendingEntry, action: "approve" | "reject") {
    if (busyId) {
      return;
    }
    setError(null);
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

  return (
    <section className="space-y-3 rounded-xl border border-jp-border bg-white p-4" data-testid="commissions-workspace">
      <h2 className="text-sm font-semibold">Pending commission entries</h2>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
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
  );
}
