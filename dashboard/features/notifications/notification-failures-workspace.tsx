"use client";

import { useEffect, useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { formatDateTime } from "@/lib/format";
import { DashboardLink } from "@/components/dashboard/dashboard-link";
import { resendDeliveryLog } from "@/services/operational-api";

type FailureLog = {
  id: string;
  status: string;
  channel: string;
  event: string;
  provider: string;
  recipientMasked: string;
  subject: string;
  errorMessage: string;
  bookingId: string | null;
  bookingReference?: string | null;
  createdAt: string | null;
  classificationHint: string;
  retryEligible: boolean;
  operatorAction: string;
};

type FailurePayload = {
  logs: FailureLog[];
  summary: {
    failedTotal: number;
    qaOrTestLike: number;
    linkedToBooking: number;
    unlinked: number;
    note: string;
  };
};

type Pagination = { page: number; pageSize: number; total: number; pageCount: number };

async function fetchFailures(page: number): Promise<{ data: FailurePayload; pagination?: Pagination }> {
  const params = new URLSearchParams({
    status: "failed",
    page: String(page),
    pageSize: "25",
  });
  const response = await fetch(`/api/dashboard/communications/failures?${params.toString()}`, {
    method: "GET",
    credentials: "same-origin",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  const payload = (await response.json()) as {
    data?: FailurePayload;
    pagination?: Pagination;
    error?: { message?: string };
    message?: string;
  };
  if (!response.ok) {
    throw new Error(payload.error?.message ?? payload.message ?? "Unable to load failed notifications.");
  }
  if (!payload.data) {
    throw new Error("Unexpected empty failures payload.");
  }
  return { data: payload.data, pagination: payload.pagination };
}

export function NotificationFailuresWorkspace() {
  const isLive = useDashboardLiveMode();
  const [payload, setPayload] = useState<FailurePayload | null>(null);
  const [pagination, setPagination] = useState<Pagination | undefined>(undefined);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(false);
  const [busyId, setBusyId] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      if (!isLive) {
        setPayload({
          logs: [],
          summary: {
            failedTotal: 0,
            qaOrTestLike: 0,
            linkedToBooking: 0,
            unlinked: 0,
            note: "Live dashboard mode is required to inspect production communication failures.",
          },
        });
        return;
      }

      setLoading(true);
      setError(null);
      try {
        const result = await fetchFailures(page);
        if (cancelled) {
          return;
        }
        setPayload(result.data);
        setPagination(result.pagination);
      } catch (err) {
        if (!cancelled) {
          setError(err instanceof Error ? err.message : "Unable to load failed notifications.");
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, [isLive, page]);

  async function onResend(log: FailureLog) {
    if (!log.retryEligible || busyId) {
      return;
    }
    setBusyId(log.id);
    setActionError(null);
    setActionSuccess(null);
    const result = await resendDeliveryLog(log.id);
    setBusyId(null);
    if (!result.ok) {
      setActionError(result.message ?? "Resend failed.");
      return;
    }
    setActionSuccess(`Resend queued for log ${log.id}.`);
    setPayload((current) =>
      current
        ? {
            ...current,
            logs: current.logs.map((row) =>
              row.id === log.id
                ? { ...row, operatorAction: "Resend queued", retryEligible: false, status: "queued" }
                : row,
            ),
          }
        : current,
    );
  }

  return (
    <div className="space-y-4" data-testid="notification-failures-workspace">
      <section className="rounded-2xl border border-jp-border bg-white p-4 shadow-sm">
        <h2 className="text-sm font-semibold text-gray-900">Classification summary</h2>
        <p className="mt-1 text-xs text-jp-muted">{payload?.summary.note}</p>
        <dl className="mt-3 grid gap-2 text-sm sm:grid-cols-4">
          <div>
            <dt className="text-jp-muted">Failed total</dt>
            <dd className="font-semibold tabular-nums">{payload?.summary.failedTotal ?? "—"}</dd>
          </div>
          <div>
            <dt className="text-jp-muted">QA / test-like</dt>
            <dd className="font-semibold tabular-nums">{payload?.summary.qaOrTestLike ?? "—"}</dd>
          </div>
          <div>
            <dt className="text-jp-muted">Booking-linked</dt>
            <dd className="font-semibold tabular-nums">{payload?.summary.linkedToBooking ?? "—"}</dd>
          </div>
          <div>
            <dt className="text-jp-muted">Unlinked</dt>
            <dd className="font-semibold tabular-nums">{payload?.summary.unlinked ?? "—"}</dd>
          </div>
        </dl>
      </section>

      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {actionError ? <p className="text-sm text-red-600">{actionError}</p> : null}
      {actionSuccess ? <p className="text-sm text-emerald-700">{actionSuccess}</p> : null}
      {loading ? <p className="text-sm text-jp-muted">Loading failures…</p> : null}

      <div className="overflow-x-auto rounded-2xl border border-jp-border bg-white shadow-sm">
        <table className="min-w-full text-left text-sm">
          <thead className="border-b border-jp-border bg-gray-50 text-xs uppercase tracking-wide text-jp-muted">
            <tr>
              <th className="px-3 py-2">When</th>
              <th className="px-3 py-2">Channel</th>
              <th className="px-3 py-2">Event</th>
              <th className="px-3 py-2">Recipient</th>
              <th className="px-3 py-2">Hint</th>
              <th className="px-3 py-2">Error</th>
              <th className="px-3 py-2">Action</th>
            </tr>
          </thead>
          <tbody>
            {(payload?.logs ?? []).length === 0 ? (
              <tr>
                <td colSpan={7} className="px-3 py-6 text-jp-muted">
                  No failed notification rows for the current filter.
                </td>
              </tr>
            ) : (
              (payload?.logs ?? []).map((log) => (
                <tr key={log.id} className="border-t border-jp-border align-top">
                  <td className="px-3 py-2 whitespace-nowrap text-xs">
                    {log.createdAt ? formatDateTime(log.createdAt) : "—"}
                  </td>
                  <td className="px-3 py-2">{log.channel || "—"}</td>
                  <td className="px-3 py-2">
                    <div className="font-medium">{log.event || "—"}</div>
                    {log.bookingReference ? (
                      <div className="font-mono text-xs text-jp-muted">{log.bookingReference}</div>
                    ) : null}
                  </td>
                  <td className="px-3 py-2">{log.recipientMasked}</td>
                  <td className="px-3 py-2 capitalize">{log.classificationHint.replaceAll("_", " ")}</td>
                  <td className="px-3 py-2 max-w-xs text-xs text-jp-muted">{log.errorMessage || "—"}</td>
                  <td className="px-3 py-2 text-xs">
                    {log.retryEligible ? (
                      <button
                        type="button"
                        className="rounded-lg border border-jp-border px-2 py-1 text-xs disabled:opacity-60"
                        disabled={busyId === log.id}
                        data-testid={`delivery-log-resend-${log.id}`}
                        onClick={() => void onResend(log)}
                      >
                        {busyId === log.id ? "Resending…" : "Resend"}
                      </button>
                    ) : (
                      <span className="text-jp-muted">{log.operatorAction || "No retry"}</span>
                    )}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {pagination && pagination.pageCount > 1 ? (
        <div className="flex items-center justify-between gap-3">
          <p className="text-xs text-jp-muted">
            Page {pagination.page} of {pagination.pageCount} · {pagination.total} rows
          </p>
          <div className="flex gap-2">
            <button
              type="button"
              className="min-h-11 rounded-xl border border-jp-border px-3 text-sm disabled:opacity-50"
              disabled={page <= 1}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
            >
              Previous
            </button>
            <button
              type="button"
              className="min-h-11 rounded-xl border border-jp-border px-3 text-sm disabled:opacity-50"
              disabled={page >= pagination.pageCount}
              onClick={() => setPage((p) => p + 1)}
            >
              Next
            </button>
          </div>
        </div>
      ) : null}

      <p className="text-xs text-jp-muted">
        Related settings:{" "}
        <DashboardLink href="/settings/notifications" className="underline">
          Notification policy
        </DashboardLink>
        . Resend uses Laravel CommunicationDeliveryLogController and remains platform-admin gated.
      </p>
    </div>
  );
}
