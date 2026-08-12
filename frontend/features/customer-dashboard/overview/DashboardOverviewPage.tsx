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
      title="Travel overview"
      unreadNotifications={data?.notifications_available ? data.metrics.unread_notifications : 0}
    >
      {loading ? <p className="text-jp-sm text-jp-muted">Loading overview…</p> : null}
      {error ? <CustomerDashboardErrorState message={error} onRetry={load} /> : null}
      {data ? (
        <div className="space-y-5" data-testid="customer-dashboard-overview">
          <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
            {[
              { label: "Upcoming trips", value: data.metrics.upcoming_trips },
              { label: "Pending payment", value: data.metrics.pending_payment },
              { label: "Ticketing pending", value: data.metrics.ticketing_pending },
              { label: "Open support", value: data.metrics.open_support_cases },
              ...(data.notifications_available
                ? [{ label: "Unread alerts", value: data.metrics.unread_notifications }]
                : []),
            ].map((card) => (
              <div
                key={card.label}
                className="rounded-jp-md border border-jp-border bg-jp-surface px-3 py-3 shadow-jp-sm"
                data-testid="dashboard-metric-card"
              >
                <p className="text-[0.68rem] font-medium uppercase tracking-wide text-jp-muted">{card.label}</p>
                <p className="mt-1 text-jp-xl font-semibold text-jp-text">{card.value}</p>
              </div>
            ))}
          </div>

          <div className="grid gap-4 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
            <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
              <div className="flex items-center justify-between gap-3">
                <h2 className="text-jp-base font-semibold text-jp-text">Recent bookings</h2>
                <Link href="/customer/bookings" className="text-jp-sm font-medium text-jp-primary focus-visible:shadow-jp-focus">
                  View all
                </Link>
              </div>
              {data.recent_bookings.length === 0 ? (
                <div className="mt-3">
                  <CustomerEmptyState
                    title="No bookings yet"
                    description="Search flights to create your first booking."
                    action={
                      <Link
                        href="/#flight-search"
                        className="inline-flex min-h-10 items-center rounded-jp-button bg-jp-primary px-4 py-2 text-jp-sm font-semibold text-white focus-visible:shadow-jp-focus"
                      >
                        Search flights
                      </Link>
                    }
                  />
                </div>
              ) : (
                <ul className="mt-3 divide-y divide-jp-border">
                  {data.recent_bookings.slice(0, 6).map((booking) => (
                    <li
                      key={booking.booking_reference}
                      className="flex flex-wrap items-center justify-between gap-3 py-3"
                    >
                      <div className="min-w-0">
                        <Link href={booking.detail_url} className="font-semibold text-jp-primary">
                          {booking.booking_reference}
                        </Link>
                        <p className="truncate text-jp-sm text-jp-muted">{booking.route}</p>
                      </div>
                      <div className="flex flex-wrap gap-2">
                        <StatusBadge status={booking.booking_status} />
                        <StatusBadge status={booking.payment_status} />
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </section>

            <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
              <h2 className="text-jp-base font-semibold text-jp-text">Quick actions</h2>
              <div className="mt-3 flex flex-wrap gap-2">
                {data.quick_actions
                  .filter((action) => action.available && action.url)
                  .map((action) => (
                    <Link
                      key={action.code}
                      href={action.url!}
                      className="rounded-jp-button border border-jp-border px-3 py-1.5 text-jp-sm font-semibold focus-visible:shadow-jp-focus"
                    >
                      {action.label}
                    </Link>
                  ))}
              </div>
              <div className="mt-5 space-y-2 text-jp-sm">
                <Link href="/customer/travelers" className="block font-medium text-jp-primary focus-visible:shadow-jp-focus">
                  Manage travelers
                </Link>
                <Link href="/customer/support" className="block font-medium text-jp-primary focus-visible:shadow-jp-focus">
                  Contact support
                </Link>
                <Link href="/customer/profile" className="block font-medium text-jp-primary focus-visible:shadow-jp-focus">
                  Update profile
                </Link>
              </div>
            </section>
          </div>
        </div>
      ) : null}
    </CustomerDashboardShell>
  );
}
