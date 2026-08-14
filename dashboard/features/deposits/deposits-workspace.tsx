"use client";

import { useState } from "react";
import type { DepositRecord } from "@/services/deposit-service";
import { approveDepositReview, rejectDepositReview } from "@/services/operational-api";
import { formatCurrency } from "@/lib/format";

export function DepositsWorkspace({ deposits }: { deposits: DepositRecord[] }) {
  const [rows, setRows] = useState(deposits);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function runMutation(
    deposit: DepositRecord,
    action: "approve" | "reject",
  ) {
    if (busyId) {
      return;
    }
    setError(null);
    setBusyId(deposit.id);
    const result =
      action === "approve"
        ? await approveDepositReview(deposit.id)
        : await rejectDepositReview(deposit.id, "Rejected from admin dashboard review.");
    setBusyId(null);
    if (!result.ok) {
      setError(result.message ?? "Deposit review action failed.");
      return;
    }
    setRows((current) =>
      current.map((row) =>
        row.id === deposit.id
          ? {
              ...row,
              status: action === "approve" ? "approved" : "rejected",
              capabilities: { ...row.capabilities, can_approve: false, can_reject: false, already_processed: true },
            }
          : row,
      ),
    );
  }

  return (
    <div className="space-y-4" data-testid="deposits-workspace">
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
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
            {rows.length === 0 ? (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-jp-muted">
                  No deposit requests for the current filters.
                </td>
              </tr>
            ) : (
              rows.map((deposit) => (
                <tr key={deposit.id} className="border-b border-jp-border last:border-0">
                  <td className="px-4 py-3 font-medium">{deposit.reference}</td>
                  <td className="px-4 py-3">{deposit.agencyName}</td>
                  <td className="px-4 py-3 tabular-nums">
                    {formatCurrency(deposit.amount, deposit.currency)}
                  </td>
                  <td className="px-4 py-3 capitalize">{deposit.status}</td>
                  <td className="px-4 py-3">
                    <div className="flex flex-wrap gap-2">
                      {deposit.capabilities?.can_approve ? (
                        <button
                          type="button"
                          className="rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs text-emerald-900 disabled:opacity-60"
                          data-testid={`deposit-approve-${deposit.id}`}
                          disabled={busyId === deposit.id}
                          onClick={() => void runMutation(deposit, "approve")}
                        >
                          Approve
                        </button>
                      ) : null}
                      {deposit.capabilities?.can_reject ? (
                        <button
                          type="button"
                          className="rounded-lg border border-red-200 bg-red-50 px-2 py-1 text-xs text-red-900 disabled:opacity-60"
                          data-testid={`deposit-reject-${deposit.id}`}
                          disabled={busyId === deposit.id}
                          onClick={() => void runMutation(deposit, "reject")}
                        >
                          Reject
                        </button>
                      ) : null}
                      {!deposit.capabilities?.can_approve && !deposit.capabilities?.can_reject ? (
                        <span className="text-xs text-jp-muted">
                          {deposit.capabilities?.already_processed ? "Processed" : "Not eligible"}
                        </span>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
