"use client";

import { useEffect, useState } from "react";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { formatDateTime } from "@/lib/format";
import { DashboardLink } from "@/components/dashboard/dashboard-link";

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

type Envelope = {
  data: FailurePayload;
  pagination?: { page: number; pageSize: number; total: number; pageCount: number };
};

export function NotificationFailuresWorkspace() {
  const isLive = useDashboardLiveMode();
  const [payload, setPayload] = useState<FailurePayload | null>(null);
  const [pagination, setPagination] = useState<Envelope["pagination"]>(undefined);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(false);

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
        const envelope = await fetchDashboardApi<FailurePayload>(DASHBOARD_API_ROUTES.communicationsFailures, {
          query: { status: "failed", page, pageSize: 25 },
        });
        if (cancelled) {
          return;
        }
        setPayload(envelope.data);
        setPagination(envelope.pagination ?? undefined);
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
                  <td className="px-3 py-2 text-xs text-jp-muted">{log.operatorAction}</td>
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
        . Resend remains an authorized Laravel-only action and is not offered here.
      </p>
    </div>
  );
}
