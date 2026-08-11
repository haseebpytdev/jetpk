"use client";

import Link from "next/link";
import { useCallback, useEffect, useRef, useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { useDashboardPortal } from "@/lib/portal-context";
import { dashboardHref } from "@/lib/portal-path";
import {
  fetchOpsEvents,
  fetchOpsInbox,
  fetchOpsWorkQueue,
  markOpsInboxRead,
  type OpsActivityEvent,
  type OpsInboxItem,
  type OpsWorkQueuePayload,
} from "@/services/ops-service";

const POLL_MS = 1500;

export function LiveOperationsPanel() {
  const isLive = useDashboardLiveMode();
  const portal = useDashboardPortal();
  const [events, setEvents] = useState<OpsActivityEvent[]>([]);
  const [inbox, setInbox] = useState<OpsInboxItem[]>([]);
  const [unread, setUnread] = useState(0);
  const [queue, setQueue] = useState<OpsWorkQueuePayload | null>(null);
  const [error, setError] = useState<string | null>(null);
  const cursorRef = useRef(0);
  const seenIds = useRef<Set<number>>(new Set());

  const toHref = (deepLink: string) => {
    if (deepLink.startsWith("/")) {
      return deepLink;
    }
    return dashboardHref(portal, `/${deepLink.replace(/^\//, "")}`);
  };

  const refreshStatic = useCallback(async () => {
    if (!isLive) return;
    try {
      const [inboxResult, workQueue] = await Promise.all([fetchOpsInbox(), fetchOpsWorkQueue()]);
      setInbox(inboxResult.items.slice(0, 8));
      setUnread(inboxResult.unreadCount);
      setQueue(workQueue);
      setError(null);
    } catch {
      setError("Operational feed temporarily unavailable.");
    }
  }, [isLive]);

  const pollEvents = useCallback(async () => {
    if (!isLive) return;
    try {
      const result = await fetchOpsEvents(cursorRef.current);
      if (result.items.length > 0) {
        const fresh = result.items.filter((item) => !seenIds.current.has(item.id));
        for (const item of fresh) {
          seenIds.current.add(item.id);
        }
        if (fresh.length > 0) {
          setEvents((prev) => [...fresh, ...prev].slice(0, 40));
        }
      }
      cursorRef.current = Math.max(cursorRef.current, result.cursor);
      setError(null);
    } catch {
      setError("Polling delayed — reconnecting.");
    }
  }, [isLive]);

  useEffect(() => {
    if (!isLive) return;
    void refreshStatic();
    void pollEvents();
    const pollTimer = window.setInterval(() => {
      void pollEvents();
    }, POLL_MS);
    const inboxTimer = window.setInterval(() => {
      void refreshStatic();
    }, POLL_MS * 2);
    return () => {
      window.clearInterval(pollTimer);
      window.clearInterval(inboxTimer);
    };
  }, [isLive, pollEvents, refreshStatic]);

  const onMarkRead = async (id: string) => {
    try {
      const next = await markOpsInboxRead([id]);
      setUnread(next);
      setInbox((prev) =>
        prev.map((item) => (item.id === id ? { ...item, unread: false, readAt: new Date().toISOString() } : item)),
      );
    } catch {
      setError("Could not mark notification read.");
    }
  };

  if (!isLive) {
    return (
      <section
        className="mt-4 rounded-xl border border-jp-border bg-white p-4"
        data-testid="live-operations-panel-fixture"
        aria-label="Live operations unavailable in fixture mode"
      >
        <h2 className="text-sm font-semibold text-gray-900">Live operations</h2>
        <p className="mt-1 text-sm text-gray-600">Connect live Laravel mode to monitor operational events.</p>
      </section>
    );
  }

  return (
    <section
      className="mt-4 grid gap-4 lg:grid-cols-3"
      data-testid="live-operations-panel"
      data-transport="EVENT_POLLING"
      aria-label="Live operations"
    >
      <div className="rounded-xl border border-jp-border bg-white p-4 lg:col-span-2">
        <div className="flex items-center justify-between gap-2">
          <h2 className="text-sm font-semibold text-gray-900">Live operations</h2>
          <span className="text-xs text-gray-500" data-testid="ops-transport-label">
            EVENT_POLLING · {POLL_MS}ms
          </span>
        </div>
        {error ? (
          <p className="mt-2 text-sm text-amber-700" role="status">
            {error}
          </p>
        ) : null}
        <ul className="mt-3 max-h-80 space-y-2 overflow-y-auto" data-testid="ops-activity-feed">
          {events.length === 0 ? (
            <li className="text-sm text-gray-500">Waiting for operational events…</li>
          ) : (
            events.map((event) => (
              <li
                key={event.id}
                className="rounded-lg border border-jp-border/70 px-3 py-2 text-sm"
                data-testid="ops-activity-item"
                data-event-id={event.id}
              >
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                  <span className="font-medium text-gray-900">{event.summary}</span>
                  <time className="text-xs text-gray-500">{event.occurredAt ?? ""}</time>
                </div>
                <div className="mt-1 text-xs text-gray-600">
                  {event.actorName}
                  {event.actorRole ? ` · ${event.actorRole}` : ""} · {event.eventType}
                  {event.entityRef ? ` · ${event.entityRef}` : ""}
                </div>
                {event.deepLink ? (
                  <Link className="mt-1 inline-block text-xs font-medium text-jp-primary underline" href={event.deepLink}>
                    Open
                  </Link>
                ) : null}
              </li>
            ))
          )}
        </ul>
      </div>

      <div className="space-y-4">
        <div className="rounded-xl border border-jp-border bg-white p-4">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-semibold text-gray-900">Notifications</h3>
            <span
              className="rounded-full bg-jp-primary/10 px-2 py-0.5 text-xs font-semibold text-jp-primary"
              data-testid="ops-unread-badge"
              aria-label={`${unread} unread notifications`}
            >
              {unread}
            </span>
          </div>
          <ul className="mt-3 max-h-48 space-y-2 overflow-y-auto" data-testid="ops-inbox-list">
            {inbox.length === 0 ? (
              <li className="text-sm text-gray-500">No notifications yet.</li>
            ) : (
              inbox.map((item) => (
                <li key={item.id} className="rounded-lg border border-jp-border/70 px-3 py-2 text-sm">
                  <p className={item.unread ? "font-semibold text-gray-900" : "text-gray-700"}>{item.summary}</p>
                  <div className="mt-1 flex flex-wrap gap-2">
                    {item.deepLink ? (
                      <Link className="text-xs font-medium text-jp-primary underline" href={item.deepLink}>
                        Open
                      </Link>
                    ) : null}
                    {item.unread ? (
                      <button
                        type="button"
                        className="text-xs font-medium text-gray-600 underline"
                        data-testid="ops-mark-read"
                        onClick={() => void onMarkRead(item.id)}
                      >
                        Mark read
                      </button>
                    ) : null}
                  </div>
                </li>
              ))
            )}
          </ul>
        </div>

        <div className="rounded-xl border border-jp-border bg-white p-4" data-testid="ops-work-queue">
          <h3 className="text-sm font-semibold text-gray-900">Assigned work</h3>
          <ul className="mt-3 max-h-48 space-y-2 overflow-y-auto">
            {(queue?.bookings ?? []).slice(0, 5).map((booking) => (
              <li key={`b-${booking.id}`} className="text-sm">
                <Link className="font-medium text-jp-primary underline" href={toHref(booking.deepLink)}>
                  Booking {booking.reference ?? booking.id}
                </Link>
                <span className="ml-2 text-xs text-gray-500">{booking.status}</span>
              </li>
            ))}
            {(queue?.supportTickets ?? []).slice(0, 5).map((ticket) => (
              <li key={`t-${ticket.id}`} className="text-sm">
                <Link className="font-medium text-jp-primary underline" href={toHref(ticket.deepLink)}>
                  Ticket {ticket.reference ?? ticket.id}
                </Link>
                <span className="ml-2 text-xs text-gray-500">{ticket.status}</span>
              </li>
            ))}
            {(queue?.bookings?.length ?? 0) === 0 && (queue?.supportTickets?.length ?? 0) === 0 ? (
              <li className="text-sm text-gray-500">No assigned work items.</li>
            ) : null}
          </ul>
        </div>
      </div>
    </section>
  );
}
