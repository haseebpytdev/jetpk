"use client";

import { useEffect, useState } from "react";
import { useDashboardPortal } from "@/lib/portal-context";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import { cancellationsIndexPath, refundsIndexPath } from "@/lib/api/portal-paths";
import {
  approveCancellationReview,
  approveRefundReview,
  rejectCancellationReview,
  rejectRefundReview,
} from "@/services/operational-api";
import type { CancellationReviewRecord, RefundReviewRecord } from "@/mocks/review-fixtures";
import { emptyListDescription } from "@/lib/empty-list-copy";

type Props = {
  cancellations: CancellationReviewRecord[];
  refunds: RefundReviewRecord[];
};

function mapCancellation(row: Record<string, unknown>): CancellationReviewRecord {
  return {
    id: String(row.id ?? ""),
    bookingId: String(row.booking_id ?? ""),
    status: String(row.status ?? ""),
    pnr: String(row.pnr ?? row.booking_reference ?? "—"),
    capabilities: (row.capabilities as CancellationReviewRecord["capabilities"]) ?? null,
  };
}

function mapRefund(row: Record<string, unknown>): RefundReviewRecord {
  return {
    id: String(row.id ?? ""),
    bookingId: String(row.booking_id ?? ""),
    status: String(row.status ?? ""),
    amount: Number(row.amount ?? 0),
    currency: String(row.currency ?? "PKR"),
    capabilities: (row.capabilities as RefundReviewRecord["capabilities"]) ?? null,
  };
}

export function OperationalReviewWorkspace({ cancellations, refunds }: Props) {
  const portal = useDashboardPortal();
  const isLive = useDashboardLiveMode();
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [rejectReason, setRejectReason] = useState<Record<string, string>>({});
  const [cancellationRows, setCancellationRows] = useState(cancellations);
  const [refundRows, setRefundRows] = useState(refunds);
  const [loading, setLoading] = useState(false);

  const refreshLive = async () => {
    setLoading(true);
    setError(null);
    const [cancelResult, refundResult] = await Promise.all([
      laravelRequest<{ cancellations?: Record<string, unknown>[] }>(
        cancellationsIndexPath("queue=review"),
        { method: "GET", headers: { Accept: "application/json" }, retryCsrfOnce: false },
      ),
      laravelRequest<{ refunds?: Record<string, unknown>[] }>(refundsIndexPath("queue=review"), {
        method: "GET",
        headers: { Accept: "application/json" },
        retryCsrfOnce: false,
      }),
    ]);
    setLoading(false);
    if (!cancelResult.ok || !refundResult.ok) {
      setError(
        (!cancelResult.ok ? cancelResult.message : null) ??
          (!refundResult.ok ? refundResult.message : null) ??
          "Could not load review queues.",
      );
      return;
    }
    setCancellationRows((cancelResult.data.cancellations ?? []).map(mapCancellation).filter((r) => r.id));
    setRefundRows((refundResult.data.refunds ?? []).map(mapRefund).filter((r) => r.id));
  };

  useEffect(() => {
    if (!isLive) return;
    void refreshLive();
  }, [isLive]);

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
      <div className="flex flex-wrap items-center gap-2">
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm"
          onClick={() => void refreshLive()}
          disabled={loading || busyKey !== null}
          data-testid="review-refresh"
        >
          {loading ? "Loading…" : "Refresh live queues"}
        </button>
        <p className="text-xs text-amber-800">
          Production UAT: open/read only. Do not approve/reject real production bookings.
        </p>
      </div>
      {error ? <p className="text-sm text-red-600" data-testid="review-error">{error}</p> : null}

      <section data-testid="cancellation-review-section">
        <h2 className="text-sm font-semibold text-gray-900">Cancellation review</h2>
        {cancellationRows.length === 0 ? (
          <p className="mt-2 text-sm text-jp-muted">{emptyListDescription(true, "Cancellation requests")}</p>
        ) : null}
        <ul className="mt-3 space-y-3">
          {cancellationRows.map((row) => (
            <li key={row.id} className="rounded-xl border border-jp-border p-4 text-sm">
              <p>
                Request {row.id} · Booking {row.bookingId} · PNR {row.pnr}
              </p>
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
                      Approve
                    </button>
                  ) : null}
                  {row.capabilities?.can_reject ? (
                    <div className="space-y-2">
                      <input
                        className="w-full rounded-lg border border-jp-border px-3 py-2 text-sm"
                        placeholder="Reject reason"
                        value={rejectReason[`c-${row.id}`] ?? ""}
                        onChange={(e) =>
                          setRejectReason((current) => ({ ...current, [`c-${row.id}`]: e.target.value }))
                        }
                      />
                      <button
                        type="button"
                        className="min-h-11 rounded-xl border border-jp-border px-3 py-2 disabled:opacity-60"
                        data-testid={`cancellation-reject-${row.id}`}
                        disabled={busyKey !== null}
                        onClick={() => {
                          const reason = rejectReason[`c-${row.id}`]?.trim();
                          if (!reason) {
                            setError("Reject reason is required.");
                            return;
                          }
                          void runMutation(
                            `cancel-reject-${row.id}`,
                            () => rejectCancellationReview(portal, row.id, reason),
                            (result) => {
                              setCancellationRows((current) =>
                                current.map((item) =>
                                  item.id === row.id
                                    ? {
                                        ...item,
                                        status: result.cancellation_request?.status ?? "rejected",
                                        capabilities: {
                                          can_approve: false,
                                          can_reject: false,
                                          already_processed: true,
                                        },
                                      }
                                    : item,
                                ),
                              );
                            },
                          );
                        }}
                      >
                        Reject
                      </button>
                    </div>
                  ) : null}
                </div>
              ) : (
                <p className="mt-2 text-xs text-jp-muted">No mutation allowed for this lifecycle state.</p>
              )}
            </li>
          ))}
        </ul>
      </section>

      <section data-testid="refund-review-section">
        <h2 className="text-sm font-semibold text-gray-900">Refund review</h2>
        {refundRows.length === 0 ? (
          <p className="mt-2 text-sm text-jp-muted">{emptyListDescription(true, "Refund requests")}</p>
        ) : null}
        <ul className="mt-3 space-y-3">
          {refundRows.map((row) => (
            <li key={row.id} className="rounded-xl border border-jp-border p-4 text-sm">
              <p>
                Refund {row.id} · Booking {row.bookingId} · {row.currency} {row.amount}
              </p>
              <p className="text-jp-muted">Status: {row.status}</p>
              {row.capabilities?.can_approve || row.capabilities?.can_reject ? (
                <div className="mt-3 flex flex-wrap gap-2">
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
                                        can_reject: (result.capabilities?.can_reject as boolean) ?? false,
                                      },
                                    }
                                  : item,
                              ),
                            );
                          },
                        )
                      }
                    >
                      Approve
                    </button>
                  ) : null}
                  {row.capabilities?.can_reject ? (
                    <button
                      type="button"
                      className="min-h-11 rounded-xl border border-jp-border px-3 py-2 disabled:opacity-60"
                      data-testid={`refund-reject-${row.id}`}
                      disabled={busyKey !== null}
                      onClick={() => {
                        const reason = rejectReason[`r-${row.id}`]?.trim() || "Rejected in review";
                        void runMutation(
                          `refund-reject-${row.id}`,
                          () => rejectRefundReview(portal, row.id, reason),
                          (result) => {
                            setRefundRows((current) =>
                              current.map((item) =>
                                item.id === row.id
                                  ? {
                                      ...item,
                                      status: result.refund?.status ?? "rejected",
                                      capabilities: { can_approve: false, can_reject: false },
                                    }
                                  : item,
                              ),
                            );
                          },
                        );
                      }}
                    >
                      Reject
                    </button>
                  ) : null}
                </div>
              ) : (
                <p className="mt-2 text-xs text-jp-muted">No mutation allowed for this lifecycle state.</p>
              )}
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}
