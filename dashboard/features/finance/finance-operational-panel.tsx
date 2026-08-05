"use client";

import { useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import {
  approveCommissionEntry,
  rejectCommissionEntry,
  rejectGroupBookingPayment,
  reverseFinanceAdjustment,
  storeFinanceAdjustment,
  verifyGroupBookingPayment,
} from "@/services/operational-api";

export function FinanceOperationalPanel() {
  const isLive = useDashboardLiveMode();
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  if (!isLive) {
    return (
      <p className="text-xs text-jp-muted" data-testid="finance-ops-preview">
        Finance operational actions are available in live dashboard mode only.
      </p>
    );
  }

  async function run(key: string, action: () => Promise<{ ok: boolean; message?: string }>) {
    setBusy(key);
    setError(null);
    setMessage(null);
    const result = await action();
    setBusy(null);
    if (!result.ok) {
      setError(result.message ?? "Request failed");
      return;
    }
    setMessage("Action completed.");
  }

  return (
    <div className="space-y-3 rounded-xl border border-jp-border p-4" data-testid="finance-operational-panel">
      <h2 className="text-sm font-semibold text-gray-900">Finance operations</h2>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {message ? <p className="text-sm text-green-700">{message}</p> : null}
      <div className="flex flex-wrap gap-2">
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
          disabled={busy !== null}
          data-testid="commission-approve"
          onClick={() => run("commission-approve", () => approveCommissionEntry("1"))}
        >
          Approve commission entry
        </button>
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
          disabled={busy !== null}
          data-testid="commission-reject"
          onClick={() => run("commission-reject", () => rejectCommissionEntry("1", "Review rejected"))}
        >
          Reject commission entry
        </button>
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
          disabled={busy !== null}
          data-testid="group-verify-payment"
          onClick={() => run("group-verify", () => verifyGroupBookingPayment("1"))}
        >
          Verify group payment
        </button>
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
          disabled={busy !== null}
          data-testid="group-reject-payment"
          onClick={() => run("group-reject", () => rejectGroupBookingPayment("1", "Rejected"))}
        >
          Reject group payment
        </button>
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
          disabled={busy !== null}
          data-testid="finance-adjustment-store"
          onClick={() =>
            run("finance-store", () =>
              storeFinanceAdjustment({
                agent_id: 1,
                amount: 100,
                currency: "PKR",
                reason: "ops-07-test",
                direction: "credit",
              }),
            )
          }
        >
          Post wallet adjustment
        </button>
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
          disabled={busy !== null}
          data-testid="finance-adjustment-reverse"
          onClick={() => run("finance-reverse", () => reverseFinanceAdjustment("1"))}
        >
          Reverse adjustment
        </button>
      </div>
    </div>
  );
}
