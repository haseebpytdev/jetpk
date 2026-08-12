"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { fetchAgentDashboardOverview } from "../services/agent-dashboard-api";
import {
  AgentDashboardErrorState,
  AgentDashboardShell,
  AgentEmptyState,
  StatusBadge,
} from "../shell/AgentDashboardShell";
import type { AgentDashboardOverview } from "../types";
import type { PublicSession } from "@/types/session";

function formatMoney(amount: number, currency: string) {
  return `${currency} ${amount.toLocaleString()}`;
}

export function AgentOverviewPage({ session }: { session: PublicSession }) {
  const [data, setData] = useState<AgentDashboardOverview | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    setError(null);
    const result = await fetchAgentDashboardOverview();
    if (!result.ok) {
      setError(result.message);
      setData(null);
    } else {
      setData(result.data);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  const wallet = data?.wallet_summary;
  const metrics = data?.metrics;

  const metricCards = metrics
    ? [
        { label: "Total bookings", value: metrics.total_bookings },
        { label: "Upcoming trips", value: metrics.upcoming_trips },
        { label: "Pending payment", value: metrics.pending_payment },
        { label: "Ticketing pending", value: metrics.ticketing_pending },
        { label: "Open support", value: metrics.open_support_cases },
        ...(data.notifications_available
          ? [{ label: "Unread alerts", value: metrics.unread_notifications }]
          : []),
      ]
    : [];

  const walletCards = wallet
    ? [
        { label: "Balance", value: formatMoney(wallet.balance, wallet.currency) },
        { label: "Available", value: formatMoney(wallet.available_balance, wallet.currency) },
        { label: "Pending deposits", value: formatMoney(wallet.pending_deposits, wallet.currency) },
      ]
    : metrics?.wallet_balance != null
      ? [
          { label: "Balance", value: formatMoney(metrics.wallet_balance, "PKR") },
          ...(metrics.available_balance != null
            ? [{ label: "Available", value: formatMoney(metrics.available_balance, "PKR") }]
            : []),
          ...(metrics.pending_deposits != null
            ? [{ label: "Pending deposits", value: formatMoney(metrics.pending_deposits, "PKR") }]
            : []),
        ]
      : [];

  return (
    <AgentDashboardShell
      session={session}
      title="Agency overview"
      unreadNotifications={metrics?.unread_notifications ?? 0}
      capabilities={data?.capabilities ?? null}
    >
      {loading ? <p className="text-jp-sm text-jp-muted">Loading overview…</p> : null}
      {error ? <AgentDashboardErrorState message={error} onRetry={load} /> : null}
      {data ? (
        <div className="space-y-5" data-testid="agent-dashboard-overview">
          <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            {metricCards.map((card) => (
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

          <div className="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
            <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="agent-wallet-metrics">
              <div className="flex items-center justify-between gap-3">
                <h2 className="text-jp-base font-semibold text-jp-text">Wallet & finance</h2>
                {walletCards.length > 0 ? (
                  <Link href="/agent/wallet" className="text-jp-sm font-medium text-jp-primary focus-visible:shadow-jp-focus">
                    Open wallet
                  </Link>
                ) : null}
              </div>
              {walletCards.length > 0 ? (
                <div className="mt-3 grid gap-3 sm:grid-cols-3">
                  {walletCards.map((card) => (
                    <div key={card.label} className="rounded-jp-md border border-jp-border bg-jp-surface-muted px-3 py-2.5">
                      <p className="text-[0.68rem] uppercase tracking-wide text-jp-muted">{card.label}</p>
                      <p className="mt-1 text-jp-sm font-semibold text-jp-text">{card.value}</p>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="mt-2 text-jp-sm text-jp-muted">Wallet summary is unavailable for this account.</p>
              )}
            </section>

            <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
              <h2 className="text-jp-base font-semibold text-jp-text">Needs attention</h2>
              <ul className="mt-3 space-y-2 text-jp-sm">
                <li className="flex items-center justify-between gap-2">
                  <span className="text-jp-muted">Pending payment</span>
                  <span className="font-semibold text-jp-text">{metrics?.pending_payment ?? 0}</span>
                </li>
                <li className="flex items-center justify-between gap-2">
                  <span className="text-jp-muted">Ticketing pending</span>
                  <span className="font-semibold text-jp-text">{metrics?.ticketing_pending ?? 0}</span>
                </li>
                <li className="flex items-center justify-between gap-2">
                  <span className="text-jp-muted">Open support</span>
                  <span className="font-semibold text-jp-text">{metrics?.open_support_cases ?? 0}</span>
                </li>
              </ul>
              <div className="mt-4 flex flex-wrap gap-2">
                {(data.quick_actions ?? [])
                  .filter((action) => action.available && action.url)
                  .slice(0, 4)
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
            </section>
          </div>

          {(data.recent_bookings ?? []).length === 0 ? (
            <AgentEmptyState
              title="No bookings yet"
              description="Search flights to create your first agency booking."
              action={
                <Link
                  href="/#flight-search"
                  className="inline-flex min-h-10 items-center rounded-jp-button bg-jp-primary px-4 py-2 text-jp-sm font-semibold text-white focus-visible:shadow-jp-focus"
                >
                  Search flights
                </Link>
              }
            />
          ) : (
            <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
              <div className="flex items-center justify-between gap-3">
                <h2 className="text-jp-base font-semibold text-jp-text">Recent bookings</h2>
                <Link href="/agent/bookings" className="text-jp-sm font-medium text-jp-primary focus-visible:shadow-jp-focus">
                  View all
                </Link>
              </div>
              <ul className="mt-3 divide-y divide-jp-border">
                {(data.recent_bookings ?? []).slice(0, 6).map((booking) => (
                  <li key={booking.booking_reference} className="flex flex-wrap items-center justify-between gap-3 py-3">
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
            </section>
          )}
        </div>
      ) : null}
    </AgentDashboardShell>
  );
}
