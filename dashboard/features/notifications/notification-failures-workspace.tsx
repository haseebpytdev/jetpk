"use client";

import { useEffect, useState } from "react";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { Card, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import { notificationFailuresPath } from "@/lib/api/portal-paths";
import { emptyListDescription } from "@/lib/empty-list-copy";
import { DashboardLink } from "@/components/dashboard/dashboard-link";

type FailureRow = {
  id: string;
  timestamp?: string;
  channel?: string;
  event?: string;
  recipient_masked?: string;
  subject?: string;
  classification?: string;
  safe_error?: string;
  provider?: string;
  status?: string;
  booking_id?: string | null;
  booking_reference?: string | null;
  retry_eligible?: boolean;
  operator_disposition?: string;
};

export function NotificationFailuresWorkspace() {
  const isLive = useDashboardLiveMode();
  const [rows, setRows] = useState<FailureRow[]>([]);
  const [kpis, setKpis] = useState<Record<string, number>>({});
  const [page, setPage] = useState(1);
  const [pageCount, setPageCount] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [filter, setFilter] = useState("issues");

  const refresh = async (nextPage = page) => {
    const params = new URLSearchParams();
    params.set("status", filter);
    params.set("page", String(nextPage));
    params.set("pageSize", "25");
    const result = await laravelRequest<{
      failures?: FailureRow[];
      kpis?: Record<string, number>;
      meta?: { page?: number; pageCount?: number };
      safety?: { blind_retry?: boolean };
    }>(notificationFailuresPath(params.toString()), {
      method: "GET",
      headers: { Accept: "application/json" },
      retryCsrfOnce: false,
    });
    if (!result.ok) {
      setError(result.message ?? "Could not load notification failures.");
      return;
    }
    setError(null);
    setRows(result.data.failures ?? []);
    setKpis(result.data.kpis ?? {});
    setPage(result.data.meta?.page ?? nextPage);
    setPageCount(result.data.meta?.pageCount ?? 1);
  };

  useEffect(() => {
    if (!isLive) return;
    void refresh(1);
  }, [isLive, filter]);

  if (!isLive) {
    return (
      <PageContainer>
        <PageHeader title="Notification failures" description="Available in live mode only." />
      </PageContainer>
    );
  }

  return (
    <PageContainer>
      <PageHeader
        title="Notification failures"
        description="Operational view of communication delivery failures. Recipients are masked. Blind retry and log deletion are forbidden."
      />
      <div className="grid gap-3 sm:grid-cols-4">
        {["failed_total", "qa_test_like", "booking_linked", "unlinked"].map((key) => (
          <Card key={key}>
            <div className="text-xs text-jp-muted">{key}</div>
            <CardTitle className="mt-1 text-2xl">{String(kpis[key] ?? 0)}</CardTitle>
          </Card>
        ))}
      </div>
      <div className="flex flex-wrap gap-2">
        <select
          className="rounded-lg border border-jp-border px-3 py-2 text-sm"
          value={filter}
          onChange={(e) => setFilter(e.target.value)}
          data-testid="notif-failure-filter"
        >
          <option value="issues">Issues</option>
          <option value="failed">Failed</option>
          <option value="skipped">Skipped</option>
          <option value="all">All</option>
        </select>
        <Button type="button" size="sm" onClick={() => void refresh(page)}>
          Refresh
        </Button>
      </div>
      {error ? (
        <p className="text-sm text-red-700" role="alert">
          {error}
        </p>
      ) : null}
      {rows.length === 0 && !error ? (
        <p className="text-sm text-jp-muted">{emptyListDescription(true, "Notification failures")}</p>
      ) : null}
      <Card className="overflow-x-auto" data-testid="notification-failures-table">
        <table className="min-w-full text-left text-sm">
          <thead>
            <tr className="border-b text-jp-muted">
              <th className="px-2 py-2">When</th>
              <th className="px-2 py-2">Channel</th>
              <th className="px-2 py-2">Event</th>
              <th className="px-2 py-2">Recipient</th>
              <th className="px-2 py-2">Subject</th>
              <th className="px-2 py-2">Class</th>
              <th className="px-2 py-2">Error</th>
              <th className="px-2 py-2">Booking</th>
              <th className="px-2 py-2">Disposition</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id} className="border-b last:border-0">
                <td className="px-2 py-2 text-xs">{row.timestamp}</td>
                <td className="px-2 py-2">{row.channel}</td>
                <td className="px-2 py-2">{row.event}</td>
                <td className="px-2 py-2 font-mono text-xs">{row.recipient_masked}</td>
                <td className="px-2 py-2">{row.subject}</td>
                <td className="px-2 py-2">{row.classification}</td>
                <td className="px-2 py-2 text-xs">{row.safe_error}</td>
                <td className="px-2 py-2">
                  {row.booking_id ? (
                    <DashboardLink
                      href={`/bookings?selected=${encodeURIComponent(row.booking_id)}`}
                      className="text-jp-accent hover:underline"
                    >
                      {row.booking_reference ?? row.booking_id}
                    </DashboardLink>
                  ) : (
                    "—"
                  )}
                </td>
                <td className="px-2 py-2 text-xs">
                  {row.operator_disposition}
                  {row.retry_eligible ? " · retry not auto" : ""}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
      <div className="flex gap-2">
        <Button
          type="button"
          size="sm"
          variant="secondary"
          disabled={page <= 1}
          onClick={() => void refresh(page - 1)}
        >
          Previous
        </Button>
        <Button
          type="button"
          size="sm"
          variant="secondary"
          disabled={page >= pageCount}
          onClick={() => void refresh(page + 1)}
        >
          Next
        </Button>
        <span className="text-xs text-jp-muted self-center">
          Page {page} / {pageCount}
        </span>
      </div>
    </PageContainer>
  );
}
