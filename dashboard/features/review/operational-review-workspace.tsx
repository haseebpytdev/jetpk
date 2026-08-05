"use client";

import { useState } from "react";
import { useDashboardPortal } from "@/lib/portal-context";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import {
  approveCancellationReview,
  approveRefundReview,
  rejectCancellationReview,
  rejectRefundReview,
} from "@/services/operational-api";
import type { CancellationReviewRecord, RefundReviewRecord } from "@/mocks/review-fixtures";

type Props = {
  cancellations: CancellationReviewRecord[];
  refunds: RefundReviewRecord[];
};

export function OperationalReviewWorkspace({ cancellations, refunds }: Props) {
  const portal = useDashboardPortal();
  const isLive = useDashboardLiveMode();
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [rejectReason, setRejectReason] = useState<Record<string, string>>({});
  const [cancellationRows, setCancellationRows] = useState(cancellations);
  const [refundRows, setRefundRows] = useState(refunds);

  if (!isLive) {
    return (
      <p className="text-sm text-jp-muted" data-testid="review-actions-preview">
        Cancellation and refund review actions are available in live dashboard mode only.
      </p>
    );
  }

  async function runMutation(
    key: string,
    action: () => Promise<{
      ok: boolean;
      message?: string;
      cancellation_request?: { status?: string };
      refund?: { status?: string };
      capabilities?: Record<string, unknown>;
    }>,
    onSuccess: (result: Awaited<ReturnType<typeof action>>) => void,
  ) {
    setBusyKey(key);
    setError(null);
    const result = await action();
    setBusyKey(null);
    if (!result.ok) {
      setError(result.message ?? "Request failed");
      return;
    }
    onSuccess(result);
  }

  return (
    <div className="space-y-6" data-testid="operational-review-workspace">
      {error ? <p className="text-sm text-red-600" data-testid="review-error">{error}</p> : null}

      <section data-testid="cancellation-review-section">
        <h2 className="text-sm font-semibold text-gray-900">Cancellation review</h2>
        <ul className="mt-3 space-y-3">
          {cancellationRows.map((row) => (
            <li key={row.id} className="rounded-xl border border-jp-border p-4 text-sm">
              <p>Request {row.id} · Booking {row.bookingId} · PNR {row.pnr}</p>
              <p className="text-jp-muted">Status: {row.status}</p>
              {row.capabilities?.can_approve || row.capabilities?.can_reject ? (
                <div className="mt-3 space-y-2">
                  {row.capabilities?.can_approve ? (
                    <button
                      type="button"
                      className="min-h-11 rounded-xl bg-jp-accent px-3 py-2 text-white disabled:opacity-60"
                      data-testid={`cancellation-approve-${row.id}`}
                      disabled={busyKey !== null}
                      onClick={() =>
                        runMutation(
                          `cancel-approve-${row.id}`,
                          () => approveCancellationReview(portal, row.id),
                          (result) => {
                            setCancellationRows((current) =>
                              current.map((item) =>
                                item.id === row.id
                                  ? {
                                      ...item,
                                      status: result.cancellation_request?.status ?? "approved",
                                      capabilities: {
                                        can_approve: false,
                                        can_reject: (result.capabilities?.can_reject as boolean) ?? false,
                                        already_processed: false,
                                      },
                                    }
                                  : item,
                              ),
                            );
                          },
                        )
                      }
                    >
                      {busyKey === `cancel-approve-${row.id}` ? "Approving…" : "Approve cancellation"}
                    </button>
                  ) : null}
                  {row.capabilities?.can_reject ? (
                    <div className="space-y-2">
                      <textarea
                        className="w-full rounded-lg border border-jp-border p-2 text-sm"
                        placeholder="Rejection reason (required)"
                        value={rejectReason[`cancel-${row.id}`] ?? ""}
                        onChange={(e) =>
                          setRejectReason((current) => ({ ...current, [`cancel-${row.id}`]: e.target.value }))
                        }
                        data-testid={`cancellation-reject-reason-${row.id}`}
                      />
                      <button
                        type="button"
                        className="min-h-11 rounded-xl border border-red-300 px-3 py-2 text-red-700 disabled:opacity-60"
                        data-testid={`cancellation-reject-${row.id}`}
                        disabled={busyKey !== null}
                        onClick={() => {
                          const reason = rejectReason[`cancel-${row.id}`]?.trim();
                          if (!reason) {
                            setError("A rejection reason is required.");
                            return;
                          }
                          runMutation(
                            `cancel-reject-${row.id}`,
                            () => rejectCancellationReview(portal, row.id, reason),
                            (result) => {
                              setCancellationRows((current) =>
                                current.map((item) =>
                                  item.id === row.id
                                    ? {
                                        ...item,
                                        status: result.cancellation_request?.status ?? "rejected",
                                        capabilities: { already_processed: true, can_approve: false, can_reject: false },
                                      }
                                    : item,
                                ),
                              );
                            },
                          );
                        }}
                      >
                        {busyKey === `cancel-reject-${row.id}` ? "Rejecting…" : "Reject cancellation"}
                      </button>
                    </div>
                  ) : null}
                </div>
              ) : (
                <p className="mt-2 text-xs text-jp-muted" data-testid="cancellation-review-unavailable">
                  {row.capabilities?.already_processed
                    ? "Cancellation review already completed."
                    : "Cancellation review not permitted."}
                </p>
              )}
            </li>
          ))}
        </ul>
      </section>

      <section data-testid="refund-review-section">
        <h2 className="text-sm font-semibold text-gray-900">Refund review</h2>
        <ul className="mt-3 space-y-3">
          {refundRows.map((row) => (
            <li key={row.id} className="rounded-xl border border-jp-border p-4 text-sm">
              <p>Refund {row.id} · Booking {row.bookingId}</p>
              <p className="text-jp-muted">
                Status: {row.status} · {row.amount} {row.currency}
              </p>
              {row.capabilities?.can_approve || row.capabilities?.can_reject ? (
                <div className="mt-3 space-y-2">
                  {row.capabilities?.can_approve ? (
                    <button
                      type="button"
                      className="min-h-11 rounded-xl bg-jp-accent px-3 py-2 text-white disabled:opacity-60"
                      data-testid={`refund-approve-${row.id}`}
                      disabled={busyKey !== null}
                      onClick={() =>
                        runMutation(
                          `refund-approve-${row.id}`,
                          () => approveRefundReview(portal, row.id),
                          (result) => {
                            setRefundRows((current) =>
                              current.map((item) =>
                                item.id === row.id
                                  ? {
                                      ...item,
                                      status: result.refund?.status ?? "approved",
                                      capabilities: {
                                        can_approve: false,
                                        can_reject: (result.capabilities?.can_reject as boolean) ?? true,
                                        already_processed: false,
                                      },
                                    }
                                  : item,
                              ),
                            );
                          },
                        )
                      }
                    >
                      {busyKey === `refund-approve-${row.id}` ? "Approving…" : "Approve refund"}
                    </button>
                  ) : null}
                  {row.capabilities?.can_reject ? (
                    <div className="space-y-2">
                      <textarea
                        className="w-full rounded-lg border border-jp-border p-2 text-sm"
                        placeholder="Rejection reason (required)"
                        value={rejectReason[`refund-${row.id}`] ?? ""}
                        onChange={(e) =>
                          setRejectReason((current) => ({ ...current, [`refund-${row.id}`]: e.target.value }))
                        }
                        data-testid={`refund-reject-reason-${row.id}`}
                      />
                      <button
                        type="button"
                        className="min-h-11 rounded-xl border border-red-300 px-3 py-2 text-red-700 disabled:opacity-60"
                        data-testid={`refund-reject-${row.id}`}
                        disabled={busyKey !== null}
                        onClick={() => {
                          const reason = rejectReason[`refund-${row.id}`]?.trim();
                          if (!reason) {
                            setError("A rejection reason is required.");
                            return;
                          }
                          runMutation(
                            `refund-reject-${row.id}`,
                            () => rejectRefundReview(portal, row.id, reason),
                            (result) => {
                              setRefundRows((current) =>
                                current.map((item) =>
                                  item.id === row.id
                                    ? {
                                        ...item,
                                        status: result.refund?.status ?? "rejected",
                                        capabilities: { already_processed: true, can_approve: false, can_reject: false },
                                      }
                                    : item,
                                ),
                              );
                            },
                          );
                        }}
                      >
                        {busyKey === `refund-reject-${row.id}` ? "Rejecting…" : "Reject refund"}
                      </button>
                    </div>
                  ) : null}
                </div>
              ) : (
                <p className="mt-2 text-xs text-jp-muted" data-testid="refund-review-unavailable">
                  {row.capabilities?.already_processed
                    ? "Refund review already completed."
                    : "Refund review not permitted."}
                </p>
              )}
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}
