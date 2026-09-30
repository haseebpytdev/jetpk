"use client";

import { useEffect, useState } from "react";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { Card, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import { operationalInboxPath } from "@/lib/api/portal-paths";
import { DashboardLink } from "@/components/dashboard/dashboard-link";
import { emptyListDescription } from "@/lib/empty-list-copy";

type InboxEvent = {
  id: string;
  type?: string;
  title?: string;
  status?: string;
  unread?: boolean;
  deep_link?: string;
  created_at?: string | null;
};

export function LiveOperationsWorkspace() {
  const isLive = useDashboardLiveMode();
  const [kpis, setKpis] = useState<Record<string, number>>({});
  const [events, setEvents] = useState<InboxEvent[]>([]);
  const [assignedBookings, setAssignedBookings] = useState<
    Array<{ id: string; booking_reference?: string; deep_link?: string; status?: string }>
  >([]);
  const [assignedSupport, setAssignedSupport] = useState<
    Array<{ id: string; subject?: string; deep_link?: string; status?: string }>
  >([]);
  const [error, setError] = useState<string | null>(null);
  const [note, setNote] = useState<string | null>(null);

  const refresh = async () => {
    const result = await laravelRequest<{
      kpis?: Record<string, number>;
      events?: InboxEvent[];
      inbox?: InboxEvent[];
      assigned_bookings?: Array<{
        id: string;
        booking_reference?: string;
        deep_link?: string;
        status?: string;
      }>;
      assigned_support?: Array<{ id: string; subject?: string; deep_link?: string; status?: string }>;
      fixture_rows?: number;
      note?: string;
    }>(operationalInboxPath(), {
      method: "GET",
      headers: { Accept: "application/json" },
      retryCsrfOnce: false,
    });
    if (!result.ok) {
      setError(result.message ?? "Could not load live operations.");
      return;
    }
    setError(null);
    setKpis(result.data.kpis ?? {});
    setEvents(result.data.events ?? result.data.inbox ?? []);
    setAssignedBookings(result.data.assigned_bookings ?? []);
    setAssignedSupport(result.data.assigned_support ?? []);
    setNote(result.data.note ?? null);
  };

  useEffect(() => {
    if (!isLive) return;
    void refresh();
  }, [isLive]);

  if (!isLive) {
    return (
      <PageContainer>
        <PageHeader title="Live operations" description="Available in live mode only." />
      </PageContainer>
    );
  }

  return (
    <PageContainer>
      <PageHeader
        title="Live operations"
        description="Operational inbox and assigned work from current Laravel contracts. No fixture rows in live mode."
        actions={
          <div className="flex items-center gap-2">
            <span
              className="rounded-full bg-jp-accent px-2 py-0.5 text-xs font-semibold text-white"
              data-testid="ops-unread-badge"
            >
              {kpis.unread ?? 0} unread
            </span>
            <Button type="button" size="sm" onClick={() => void refresh()}>
              Refresh
            </Button>
          </div>
        }
      />
      <div className="grid gap-3 sm:grid-cols-5">
        {[
          "failed_notifications",
          "pending_cancellations",
          "pending_refunds",
          "assigned_bookings",
          "assigned_support",
        ].map((key) => (
          <Card key={key}>
            <div className="text-xs text-jp-muted">{key}</div>
            <CardTitle className="mt-1 text-2xl">{String(kpis[key] ?? 0)}</CardTitle>
          </Card>
        ))}
      </div>
      {error ? (
        <p className="text-sm text-red-700" role="alert">
          {error}
        </p>
      ) : null}
      {note ? <p className="text-xs text-jp-muted">{note}</p> : null}

      <Card data-testid="ops-event-feed">
        <CardTitle>Event feed / inbox</CardTitle>
        {events.length === 0 ? (
          <p className="mt-2 text-sm text-jp-muted">{emptyListDescription(true, "Ops events")}</p>
        ) : (
          <ul className="mt-3 space-y-2 text-sm">
            {events.map((event) => (
              <li key={event.id} className="flex flex-wrap items-center justify-between gap-2 border-b py-2 last:border-0">
                <div>
                  <p className="font-medium">{event.title}</p>
                  <p className="text-xs text-jp-muted">
                    {event.type} · {event.status}
                    {event.unread ? " · unread" : ""}
                  </p>
                </div>
                {event.deep_link ? (
                  <DashboardLink href={event.deep_link} className="text-sm text-jp-accent hover:underline">
                    Open
                  </DashboardLink>
                ) : null}
              </li>
            ))}
          </ul>
        )}
      </Card>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card data-testid="ops-assigned-bookings">
          <CardTitle>Assigned bookings</CardTitle>
          {assignedBookings.length === 0 ? (
            <p className="mt-2 text-sm text-jp-muted">No assigned bookings.</p>
          ) : (
            <ul className="mt-2 space-y-1 text-sm">
              {assignedBookings.map((row) => (
                <li key={row.id}>
                  <DashboardLink
                    href={row.deep_link ?? `/bookings?selected=${row.id}`}
                    className="text-jp-accent hover:underline"
                  >
                    {row.booking_reference ?? row.id}
                  </DashboardLink>{" "}
                  <span className="text-xs text-jp-muted">{row.status}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>
        <Card data-testid="ops-assigned-support">
          <CardTitle>Assigned support</CardTitle>
          {assignedSupport.length === 0 ? (
            <p className="mt-2 text-sm text-jp-muted">No assigned support tickets.</p>
          ) : (
            <ul className="mt-2 space-y-1 text-sm">
              {assignedSupport.map((row) => (
                <li key={row.id}>
                  <DashboardLink
                    href={row.deep_link ?? `/support?ticket=${row.id}`}
                    className="text-jp-accent hover:underline"
                  >
                    {row.subject ?? row.id}
                  </DashboardLink>{" "}
                  <span className="text-xs text-jp-muted">{row.status}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>
    </PageContainer>
  );
}
