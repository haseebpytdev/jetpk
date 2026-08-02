"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { customerApiErrorMessage, fetchDashboardOverview } from "../services/customer-dashboard-api";
import {
  CustomerDashboardErrorState,
  CustomerDashboardShell,
  CustomerEmptyState,
  StatusBadge,
} from "../shell/CustomerDashboardShell";
import type { CustomerDashboardOverview } from "../types";
import type { PublicSession } from "@/types/session";

export function DashboardOverviewPage({ session }: { session: PublicSession }) {
  const [data, setData] = useState<CustomerDashboardOverview | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    setError(null);
    const result = await fetchDashboardOverview();
    if (!result.ok) {
      setError(customerApiErrorMessage(result));
      setData(null);
    } else {
      setData(result.data);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  return (
    <CustomerDashboardShell
      session={session}
      title="Dashboard overview"
      unreadNotifications={data?.notifications_available ? data.metrics.unread_notifications : 0}
    >
      {loading ? <p className="text-jp-sm text-jp-muted">Loading overview…</p> : null}
      {error ? <CustomerDashboardErrorState message={error} onRetry={load} /> : null}
      {data ? (
        <div className="space-y-6" data-testid="customer-dashboard-overview">
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {[
              { label: "Upcoming trips", value: data.metrics.upcoming_trips },
              { label: "Pending payment", value: data.metrics.pending_payment },
              { label: "Ticketing pending", value: data.metrics.ticketing_pending },
              { label: "Open support cases", value: data.metrics.open_support_cases },
              ...(data.notifications_available
                ? [{ label: "Unread notifications", value: data.metrics.unread_notifications }]
                : []),
            ].map((card) => (
              <div key={card.label} className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 shadow-jp-sm" data-testid="dashboard-metric-card">
                <p className="text-jp-xs uppercase tracking-wide text-jp-muted">{card.label}</p>
                <p className="mt-2 text-jp-h3 font-bold text-jp-text">{card.value}</p>
              </div>
            ))}
          </div>

          <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
            <h2 className="text-jp-base font-semibold text-jp-text">Quick actions</h2>
            <div className="mt-3 flex flex-wrap gap-2">
              {data.quick_actions.map((action) =>
                action.available && action.url ? (
                  <Link key={action.code} href={action.url} className="rounded-jp-button border border-jp-border px-4 py-2 text-jp-sm font-semibold focus-visible:shadow-jp-focus">
                    {action.label}
                  </Link>
                ) : null,
              )}
            </div>
          </section>

          {data.recent_bookings.length === 0 ? (
            <CustomerEmptyState
              title="No bookings yet"
              description="Search flights to create your first booking."
              action={
                <Link
                  href="/flights/search"
                  className="inline-flex min-h-10 items-center rounded-jp-button bg-jp-primary px-4 py-2 text-jp-sm font-semibold text-white focus-visible:shadow-jp-focus"
                >
                  Search flights
                </Link>
              }
            />
          ) : (
            <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
              <h2 className="text-jp-base font-semibold text-jp-text">Recent bookings</h2>
              <ul className="mt-4 space-y-3">
                {data.recent_bookings.map((booking) => (
                  <li key={booking.booking_reference} className="flex flex-wrap items-center justify-between gap-3 border-b border-jp-border pb-3 last:border-0">
                    <div>
                      <Link href={booking.detail_url} className="font-semibold text-jp-primary">
                        {booking.booking_reference}
                      </Link>
                      <p className="text-jp-sm text-jp-muted">{booking.route}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <StatusBadge status={booking.booking_status} />
                      <StatusBadge status={booking.payment_status} />
                    </div>
                  </li>
                ))}
              </ul>
            </section>
          )}
        </div>
      ) : null}
    </CustomerDashboardShell>
  );
}
