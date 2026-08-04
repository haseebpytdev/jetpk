"use client";

import { useState } from "react";
import { useDashboardPortal } from "@/lib/portal-context";
import { getDashboardMode } from "@/lib/preview";
import {
  issueTicketExecution,
  markRefundPaidExecution,
  processCancellationExecution,
} from "@/services/operational-api";
import type {
  CancellationExecutionRecord,
  RefundExecutionRecord,
  TicketingExecutionRecord,
} from "@/mocks/execution-fixtures";

type Props = {
  cancellations: CancellationExecutionRecord[];
  refunds: RefundExecutionRecord[];
  ticketing: TicketingExecutionRecord[];
};

export function OperationalExecutionWorkspace({ cancellations, refunds, ticketing }: Props) {
  const portal = useDashboardPortal();
  const isLive = getDashboardMode() === "live";
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [confirmKey, setConfirmKey] = useState<string | null>(null);
  const [cancellationRows, setCancellationRows] = useState(cancellations);
  const [refundRows, setRefundRows] = useState(refunds);
  const [ticketingRows, setTicketingRows] = useState(ticketing);

  if (!isLive) {
    return (
      <p className="text-sm text-jp-muted" data-testid="execution-actions-preview">
        Operational execution controls are available in live dashboard mode only.
      </p>
    );
  }

  async function runMutation(key: string, action: () => Promise<{ ok: boolean; message?: string }>) {
    setBusyKey(key);
    setError(null);
    const result = await action();
    setBusyKey(null);
    setConfirmKey(null);
    if (!result.ok) {
      setError(result.message ?? "Request failed");
      return false;
    }
    return true;
  }

  return (
    <div className="space-y-6" data-testid="operational-execution-workspace">
      {error ? <p className="text-sm text-red-600" data-testid="execution-error">{error}</p> : null}

      <section data-testid="cancellation-execution-section">
        <h2 className="text-sm font-semibold text-gray-900">Cancellation execution</h2>
        <ul className="mt-3 space-y-3">
          {cancellationRows.map((row) => (
            <li key={row.id} className="rounded-xl border border-jp-border p-4 text-sm">
              <p>Request {row.id} · Booking {row.bookingId} · PNR {row.pnr}</p>
              <p className="text-jp-muted">Status: {row.status}</p>
              {row.capabilities?.can_process ? (
                <div className="mt-3 space-y-2">
                  {confirmKey === `cancel-${row.id}` ? (
                    <p className="text-xs text-amber-900">
                      This irreversible action will attempt supplier cancellation for booking {row.bookingId}.
                    </p>
                  ) : null}
                  <button
                    type="button"
                    className="min-h-11 rounded-xl bg-red-700 px-3 py-2 text-white disabled:opacity-60"
                    data-testid={`cancellation-process-${row.id}`}
                    disabled={busyKey !== null}
                    onClick={async () => {
                      if (confirmKey !== `cancel-${row.id}`) {
                        setConfirmKey(`cancel-${row.id}`);
                        return;
                      }
                      const ok = await runMutation(`cancel-${row.id}`, () =>
                        processCancellationExecution(portal, row.id),
                      );
                      if (ok) {
                        setCancellationRows((current) =>
                          current.map((item) =>
                            item.id === row.id
                              ? {
                                  ...item,
                                  status: "processed",
                                  capabilities: { already_processed: true, can_process: false },
                                }
                              : item,
                          ),
                        );
                      }
                    }}
                  >
                    {busyKey === `cancel-${row.id}`
                      ? "Processing…"
                      : confirmKey === `cancel-${row.id}`
                        ? "Confirm supplier cancellation"
                        : "Process cancellation"}
                  </button>
                </div>
              ) : (
                <p className="mt-2 text-xs text-jp-muted" data-testid="cancellation-execution-unavailable">
                  {row.capabilities?.already_processed
                    ? "Cancellation already processed."
                    : row.capabilities?.pending_reconciliation
                      ? "Pending supplier reconciliation."
                      : "Cancellation execution not permitted."}
                </p>
              )}
            </li>
          ))}
        </ul>
      </section>

      <section data-testid="refund-execution-section">
        <h2 className="text-sm font-semibold text-gray-900">Refund settlement</h2>
        <ul className="mt-3 space-y-3">
          {refundRows.map((row) => (
            <li key={row.id} className="rounded-xl border border-jp-border p-4 text-sm">
              <p>Refund {row.id} · Booking {row.bookingId}</p>
              <p className="text-jp-muted">
                {row.amount.toLocaleString()} {row.currency} · {row.status}
              </p>
              {row.capabilities?.can_mark_paid ? (
                <div className="mt-3 space-y-2">
                  {confirmKey === `refund-${row.id}` ? (
                    <p className="text-xs text-amber-900">
                      This irreversible action records refund settlement for {row.amount.toLocaleString()} {row.currency}.
                    </p>
                  ) : null}
                  <button
                    type="button"
                    className="min-h-11 rounded-xl bg-jp-accent px-3 py-2 text-white disabled:opacity-60"
                    data-testid={`refund-mark-paid-${row.id}`}
                    disabled={busyKey !== null}
                    onClick={async () => {
                      if (confirmKey !== `refund-${row.id}`) {
                        setConfirmKey(`refund-${row.id}`);
                        return;
                      }
                      const ok = await runMutation(`refund-${row.id}`, () =>
                        markRefundPaidExecution(portal, row.id),
                      );
                      if (ok) {
                        setRefundRows((current) =>
                          current.map((item) =>
                            item.id === row.id
                              ? {
                                  ...item,
                                  status: "paid",
                                  capabilities: { already_processed: true, can_mark_paid: false },
                                }
                              : item,
                          ),
                        );
                      }
                    }}
                  >
                    {busyKey === `refund-${row.id}`
                      ? "Recording…"
                      : confirmKey === `refund-${row.id}`
                        ? "Confirm refund settlement"
                        : "Mark refund paid"}
                  </button>
                </div>
              ) : (
                <p className="mt-2 text-xs text-jp-muted" data-testid="refund-execution-unavailable">
                  {row.capabilities?.already_processed ? "Refund already settled." : "Refund settlement not permitted."}
                </p>
              )}
            </li>
          ))}
        </ul>
      </section>

      <section data-testid="ticketing-execution-section">
        <h2 className="text-sm font-semibold text-gray-900">Ticket issuance</h2>
        <ul className="mt-3 space-y-3">
          {ticketingRows.map((row) => (
            <li key={row.bookingId} className="rounded-xl border border-jp-border p-4 text-sm">
              <p>Booking {row.bookingId} · PNR {row.pnr}</p>
              <p className="text-jp-muted">Ticketing: {row.ticketingStatus}</p>
              {row.capabilities?.can_issue_ticket ? (
                <div className="mt-3 space-y-2">
                  {confirmKey === `ticket-${row.bookingId}` ? (
                    <p className="text-xs text-amber-900">
                      This irreversible action will request authoritative ticket issuance from the supplier.
                    </p>
                  ) : null}
                  <button
                    type="button"
                    className="min-h-11 rounded-xl bg-jp-accent px-3 py-2 text-white disabled:opacity-60"
                    data-testid={`issue-ticket-${row.bookingId}`}
                    disabled={busyKey !== null}
                    onClick={async () => {
                      if (confirmKey !== `ticket-${row.bookingId}`) {
                        setConfirmKey(`ticket-${row.bookingId}`);
                        return;
                      }
                      const ok = await runMutation(`ticket-${row.bookingId}`, () =>
                        issueTicketExecution(portal, row.bookingId),
                      );
                      if (ok) {
                        setTicketingRows((current) =>
                          current.map((item) =>
                            item.bookingId === row.bookingId
                              ? {
                                  ...item,
                                  ticketingStatus: "ticketed",
                                  capabilities: { already_ticketed: true, can_issue_ticket: false },
                                }
                              : item,
                          ),
                        );
                      }
                    }}
                  >
                    {busyKey === `ticket-${row.bookingId}`
                      ? "Issuing…"
                      : confirmKey === `ticket-${row.bookingId}`
                        ? "Confirm ticket issuance"
                        : "Issue ticket"}
                  </button>
                </div>
              ) : (
                <p className="mt-2 text-xs text-jp-muted" data-testid="ticketing-execution-unavailable">
                  {row.capabilities?.already_ticketed ? "Booking already ticketed." : "Ticket issuance not permitted."}
                </p>
              )}
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}
