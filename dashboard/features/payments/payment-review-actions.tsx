"use client";

import { useState } from "react";
import { useDashboardPortal } from "@/lib/portal-context";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { rejectPaymentReview, verifyPaymentReview } from "@/services/operational-api";
import type { TransactionRecord } from "@/types/payment";

type PaymentCapabilities = {
  can_verify?: boolean;
  can_reject?: boolean;
  already_processed?: boolean;
};

export function PaymentReviewActions({
  transaction,
  onUpdated,
}: {
  transaction: TransactionRecord & { laravelPaymentId?: string; capabilities?: PaymentCapabilities | null };
  onUpdated?: () => void;
}) {
  const portal = useDashboardPortal();
  const [busy, setBusy] = useState<"verify" | "reject" | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [rejectReason, setRejectReason] = useState("");
  const isLive = useDashboardLiveMode();
  const capabilities = transaction.capabilities;
  const paymentId = transaction.laravelPaymentId ?? transaction.paymentId.replace(/^PAY-/, "");

  if (!isLive) {
    return (
      <p className="text-xs text-jp-muted" data-testid="payment-actions-preview">
        Payment review actions are available in live dashboard mode only.
      </p>
    );
  }

  if (!capabilities?.can_verify && !capabilities?.can_reject) {
    return (
      <p className="text-xs text-jp-muted" data-testid="payment-actions-unavailable">
        {capabilities?.already_processed ? "This payment has already been processed." : "Payment review is not permitted."}
      </p>
    );
  }

  async function handleVerify() {
    setBusy("verify");
    setError(null);
    const result = await verifyPaymentReview(portal, paymentId);
    setBusy(null);
    if (!result.ok) {
      setError(result.message ?? "Request failed");
      return;
    }
    onUpdated?.();
  }

  async function handleReject() {
    if (!rejectReason.trim()) {
      setError("A rejection reason is required.");
      return;
    }
    setBusy("reject");
    setError(null);
    const result = await rejectPaymentReview(portal, paymentId, rejectReason.trim());
    setBusy(null);
    if (!result.ok) {
      setError(result.message ?? "Request failed");
      return;
    }
    onUpdated?.();
  }

  return (
    <div className="space-y-3" data-testid="payment-review-actions">
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {capabilities?.can_verify ? (
        <button
          type="button"
          className="min-h-11 w-full rounded-xl bg-jp-accent px-3 py-2 text-sm font-medium text-white disabled:opacity-60"
          disabled={busy !== null}
          onClick={handleVerify}
        >
          {busy === "verify" ? "Verifying…" : "Verify payment"}
        </button>
      ) : null}
      {capabilities?.can_reject ? (
        <div className="space-y-2">
          <textarea
            className="w-full rounded-xl border border-jp-border p-3 text-sm"
            rows={3}
            placeholder="Rejection reason"
            value={rejectReason}
            onChange={(e) => setRejectReason(e.target.value)}
          />
          <button
            type="button"
            className="min-h-11 w-full rounded-xl border border-red-300 px-3 py-2 text-sm font-medium text-red-700 disabled:opacity-60"
            disabled={busy !== null}
            onClick={handleReject}
          >
            {busy === "reject" ? "Rejecting…" : "Reject payment"}
          </button>
        </div>
      ) : null}
    </div>
  );
}
