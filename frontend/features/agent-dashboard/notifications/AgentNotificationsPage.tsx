"use client";

import { useEffect, useState } from "react";
import { fetchAgentNotifications } from "../services/agent-dashboard-api";
import { AgentDashboardErrorState, AgentDashboardShell, AgentEmptyState } from "../shell/AgentDashboardShell";
import type { PaginatedMeta } from "../types";
import type { PublicSession } from "@/types/session";

function PaginationControls({
  pagination,
  onPageChange,
}: {
  pagination: PaginatedMeta;
  onPageChange: (page: number) => void;
}) {
  if (pagination.last_page <= 1) return null;
  return (
    <div className="flex items-center justify-between gap-3 pt-4" data-testid="notifications-pagination">
      <p className="text-jp-sm text-jp-muted">
        Page {pagination.current_page} of {pagination.last_page}
      </p>
      <div className="flex gap-2">
        <button
          type="button"
          disabled={pagination.current_page <= 1}
          className="rounded-jp-button border border-jp-border px-3 py-1.5 text-jp-sm disabled:opacity-50 focus-visible:shadow-jp-focus"
          onClick={() => onPageChange(pagination.current_page - 1)}
        >
          Previous
        </button>
        <button
          type="button"
          disabled={pagination.current_page >= pagination.last_page}
          className="rounded-jp-button border border-jp-border px-3 py-1.5 text-jp-sm disabled:opacity-50 focus-visible:shadow-jp-focus"
          onClick={() => onPageChange(pagination.current_page + 1)}
        >
          Next
        </button>
      </div>
    </div>
  );
}

export function AgentNotificationsPage({ session }: { session: PublicSession }) {
  const [available, setAvailable] = useState<boolean | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [notifications, setNotifications] = useState<Array<Record<string, unknown>>>([]);
  const [pagination, setPagination] = useState<PaginatedMeta | null>(null);
  const [page, setPage] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = async (nextPage = page) => {
    setLoading(true);
    setError(null);
    const result = await fetchAgentNotifications(nextPage);
    if (!result.ok) {
      setError(result.message);
    } else {
      setAvailable(result.data.available);
      setMessage(result.data.message ?? null);
      setNotifications(result.data.notifications);
      setPagination(result.data.pagination);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, [page]);

  return (
    <AgentDashboardShell session={session} title="Notifications">
      {loading ? <p className="text-jp-sm text-jp-muted">Loading notifications…</p> : null}
      {error ? <AgentDashboardErrorState message={error} onRetry={() => load()} /> : null}

      {!loading && !error && available === false ? (
        <AgentEmptyState
          title="In-app notifications unavailable"
          description={
            message ?? "Booking and wallet updates are sent to your registered email address."
          }
        />
      ) : null}

      {!loading && !error && available && notifications.length === 0 ? (
        <AgentEmptyState title="No notifications" description="You are all caught up." />
      ) : null}

      {available && notifications.length > 0 ? (
        <div className="space-y-3" data-testid="agent-notifications-list">
          {notifications.map((notification, index) => (
            <article key={String(notification.id ?? index)} className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
              <p className="font-semibold text-jp-text">{String(notification.title ?? "Notification")}</p>
              {notification.body ? <p className="mt-1 text-jp-sm text-jp-muted">{String(notification.body)}</p> : null}
              {notification.created_at ? <p className="mt-1 text-jp-xs text-jp-muted">{String(notification.created_at)}</p> : null}
            </article>
          ))}
        </div>
      ) : null}

      {pagination && available ? <PaginationControls pagination={pagination} onPageChange={setPage} /> : null}
    </AgentDashboardShell>
  );
}
