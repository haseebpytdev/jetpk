"use client";

import { useState } from "react";
import { approveDepositReview, rejectDepositReview } from "@/services/operational-api";
import { getDashboardMode } from "@/lib/preview";
import type { DepositRecord } from "@/services/deposit-service";

export function DepositsWorkspace({ deposits }: { deposits: DepositRecord[] }) {
  const [rows, setRows] = useState(deposits);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const isLive = getDashboardMode() === "live";

  async function approve(id: string) {
    setBusyId(id);
    setError(null);
    const result = await approveDepositReview(id);
    setBusyId(null);
    if (!result.ok) {
      setError(result.message ?? "Request failed");
      return;
    }
    setRows((current) =>
      current.map((row) =>
        row.id === id ? { ...row, status: "approved", capabilities: { already_processed: true } } : row,
      ),
    );
  }

  async function reject(id: string) {
    const note = window.prompt("Rejection note");
    if (!note?.trim()) return;
    setBusyId(id);
    setError(null);
    const result = await rejectDepositReview(id, note.trim());
    setBusyId(null);
    if (!result.ok) {
      setError(result.message ?? "Request failed");
      return;
    }
    setRows((current) =>
      current.map((row) =>
        row.id === id ? { ...row, status: "rejected", capabilities: { already_processed: true } } : row,
      ),
    );
  }

  return (
    <div className="space-y-4" data-testid="deposits-workspace">
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {!isLive ? (
        <p className="text-sm text-jp-muted">Deposit review mutations require live dashboard mode.</p>
      ) : null}
      <div className="overflow-x-auto rounded-2xl border border-jp-border bg-white">
        <table className="min-w-full text-sm">
          <thead>
            <tr className="border-b border-jp-border text-left text-jp-muted">
              <th className="px-4 py-3">Reference</th>
              <th className="px-4 py-3">Agency</th>
              <th className="px-4 py-3">Amount</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((deposit) => (
              <tr key={deposit.id} className="border-b border-jp-border last:border-0">
                <td className="px-4 py-3 font-medium">{deposit.reference}</td>
                <td className="px-4 py-3">{deposit.agencyName}</td>
                <td className="px-4 py-3 tabular-nums">
                  {deposit.amount.toLocaleString()} {deposit.currency}
                </td>
                <td className="px-4 py-3 capitalize">{deposit.status}</td>
                <td className="px-4 py-3">
                  {deposit.capabilities?.can_approve ? (
                    <button
                      type="button"
                      className="mr-2 rounded-lg bg-jp-accent px-3 py-1.5 text-white disabled:opacity-60"
                      disabled={busyId === deposit.id}
                      onClick={() => approve(deposit.id)}
                    >
                      Approve
                    </button>
                  ) : null}
                  {deposit.capabilities?.can_reject ? (
                    <button
                      type="button"
                      className="rounded-lg border border-red-300 px-3 py-1.5 text-red-700 disabled:opacity-60"
                      disabled={busyId === deposit.id}
                      onClick={() => reject(deposit.id)}
                    >
                      Reject
                    </button>
                  ) : null}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
