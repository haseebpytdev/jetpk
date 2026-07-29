"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { fetchAgentBookings } from "../services/agent-dashboard-api";
import {
  AgentDashboardErrorState,
  AgentDashboardShell,
  AgentEmptyState,
  StatusBadge,
} from "../shell/AgentDashboardShell";
import type { AgentBookingListItem } from "../types";
import type { PublicSession } from "@/types/session";

const FILTERS = [
  { value: "all", label: "All" },
  { value: "pending_payment", label: "Pending payment" },
  { value: "pnr_created", label: "PNR created" },
  { value: "needs_action", label: "Needs action" },
  { value: "cancelled", label: "Cancelled" },
] as const;

export function AgentBookingsPage({ session }: { session: PublicSession }) {
  const [bookings, setBookings] = useState<AgentBookingListItem[]>([]);
  const [filter, setFilter] = useState("all");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = async (nextFilter = filter) => {
    setLoading(true);
    setError(null);
    const result = await fetchAgentBookings({ filter: nextFilter });
    if (!result.ok) {
      setError(result.message);
      setBookings([]);
    } else {
      setBookings(result.data.bookings);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, [filter]);

  return (
    <AgentDashboardShell session={session} title="Bookings">
      <div className="mb-4 flex flex-wrap gap-2" data-testid="agent-bookings-filters">
        {FILTERS.map((item) => (
          <button
            key={item.value}
            type="button"
            className={`rounded-jp-button border px-3 py-1.5 text-jp-sm focus-visible:shadow-jp-focus ${
              filter === item.value ? "border-jp-primary bg-jp-primary/10 text-jp-primary" : "border-jp-border"
            }`}
            onClick={() => setFilter(item.value)}
          >
            {item.label}
          </button>
        ))}
      </div>

      {loading ? <p className="text-jp-sm text-jp-muted">Loading bookings…</p> : null}
      {error ? <AgentDashboardErrorState message={error} onRetry={() => load()} /> : null}

      {!loading && !error && bookings.length === 0 ? (
        <AgentEmptyState title="No bookings found" description="Try another filter or search for flights." />
      ) : null}

      <div className="space-y-3" data-testid="agent-bookings-list">
        {bookings.map((booking) => (
          <article key={booking.booking_reference} className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 shadow-jp-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <Link href={booking.detail_url} className="text-jp-base font-semibold text-jp-primary">
                  {booking.booking_reference}
                </Link>
                <p className="mt-1 text-jp-sm text-jp-muted">
                  {booking.route} · {booking.departure_date ?? "Date TBC"}
                </p>
                <p className="text-jp-sm text-jp-muted">
                  {booking.passenger_count} passenger{booking.passenger_count === 1 ? "" : "s"} · {booking.currency}{" "}
                  {booking.total.toLocaleString()}
                </p>
                {booking.creator_name ? <p className="text-jp-xs text-jp-muted">Created by {booking.creator_name}</p> : null}
              </div>
              <div className="flex flex-wrap gap-2">
                <StatusBadge status={booking.booking_status} />
                <StatusBadge status={booking.payment_status} />
                <StatusBadge status={booking.ticketing_status} />
              </div>
            </div>
            {booking.pnr ? <p className="mt-2 text-jp-xs text-jp-muted">PNR: {booking.pnr}</p> : null}
            {booking.commission ? (
              <p className="mt-2 text-jp-xs text-jp-muted">
                Commission: {booking.commission.currency} {booking.commission.amount.toLocaleString()} ({booking.commission.status})
              </p>
            ) : null}
            {booking.next_action?.url ? (
              <Link href={booking.next_action.url} className="mt-2 inline-block text-jp-sm text-jp-primary">
                {booking.next_action.label}
              </Link>
            ) : null}
          </article>
        ))}
      </div>
    </AgentDashboardShell>
  );
}
