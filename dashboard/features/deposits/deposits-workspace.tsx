"use client";

import { useState } from "react";
import type { DepositRecord } from "@/services/deposit-service";
import { formatCurrency } from "@/lib/format";

/**
 * Owner-UAT Wave 2: production deposit review is read-only.
 * Approve/reject remain available in Laravel domain + automated tests — not offered here.
 */
export function DepositsWorkspace({ deposits }: { deposits: DepositRecord[] }) {
  const [rows] = useState(deposits);

  return (
    <div className="space-y-4" data-testid="deposits-workspace">
      <p className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">
        Owner-UAT production mode: deposit list and capabilities are visible for RBAC proof. Approving or
        rejecting deposits (and any manual wallet credit) is blocked here to prevent real money movement.
        Use local/test fixtures for mutation architecture proof.
      </p>
      <div className="overflow-x-auto rounded-2xl border border-jp-border bg-white">
        <table className="min-w-full text-sm">
          <thead>
            <tr className="border-b border-jp-border text-left text-jp-muted">
              <th className="px-4 py-3">Reference</th>
              <th className="px-4 py-3">Agency</th>
              <th className="px-4 py-3">Amount</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">Review eligibility</th>
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
                  <td className="px-4 py-3 text-xs text-jp-muted">
                    {deposit.capabilities?.can_approve || deposit.capabilities?.can_reject
                      ? "Eligible in domain — UI mutation blocked for Owner-UAT"
                      : deposit.capabilities?.already_processed
                        ? "Already processed"
                        : "Not eligible"}
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
