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
        { label: "Open support cases", value: metrics.open_support_cases },
        ...(data.notifications_available
          ? [{ label: "Unread notifications", value: metrics.unread_notifications }]
          : []),
      ]
    : [];

  const walletCards = wallet
    ? [
        { label: "Wallet balance", value: formatMoney(wallet.balance, wallet.currency) },
        { label: "Available balance", value: formatMoney(wallet.available_balance, wallet.currency) },
        { label: "Pending deposits", value: formatMoney(wallet.pending_deposits, wallet.currency) },
      ]
    : metrics?.wallet_balance != null
      ? [
          { label: "Wallet balance", value: formatMoney(metrics.wallet_balance, "PKR") },
          ...(metrics.available_balance != null
            ? [{ label: "Available balance", value: formatMoney(metrics.available_balance, "PKR") }]
            : []),
          ...(metrics.pending_deposits != null
            ? [{ label: "Pending deposits", value: formatMoney(metrics.pending_deposits, "PKR") }]
            : []),
        ]
      : [];

  return (
    <AgentDashboardShell
      session={session}
      title="Dashboard overview"
      unreadNotifications={metrics?.unread_notifications ?? 0}
      capabilities={data?.capabilities ?? null}
    >
      {loading ? <p className="text-jp-sm text-jp-muted">Loading overview…</p> : null}
      {error ? <AgentDashboardErrorState message={error} onRetry={load} /> : null}
      {data ? (
        <div className="space-y-6" data-testid="agent-dashboard-overview">
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {metricCards.map((card) => (
              <div
                key={card.label}
                className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 shadow-jp-sm"
                data-testid="dashboard-metric-card"
              >
                <p className="text-jp-xs uppercase tracking-wide text-jp-muted">{card.label}</p>
                <p className="mt-2 text-jp-h3 font-bold text-jp-text">{card.value}</p>
              </div>
            ))}
          </div>

          {walletCards.length > 0 ? (
            <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="agent-wallet-metrics">
              <h2 className="text-jp-base font-semibold text-jp-text">Wallet</h2>
              <div className="mt-3 grid gap-4 sm:grid-cols-3">
                {walletCards.map((card) => (
                  <div key={card.label} className="rounded-jp-md border border-jp-border bg-jp-surface-muted p-3">
                    <p className="text-jp-xs uppercase tracking-wide text-jp-muted">{card.label}</p>
                    <p className="mt-1 text-jp-base font-semibold text-jp-text">{card.value}</p>
                  </div>
                ))}
              </div>
              <div className="mt-3">
                <Link href="/agent/wallet" className="text-jp-sm text-jp-primary focus-visible:shadow-jp-focus">
                  View wallet
                </Link>
              </div>
            </section>
          ) : null}

          <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
            <h2 className="text-jp-base font-semibold text-jp-text">Quick actions</h2>
            <div className="mt-3 flex flex-wrap gap-2">
              {data.quick_actions.map((action) =>
                action.available && action.url ? (
                  <Link
                    key={action.code}
                    href={action.url}
                    className="rounded-jp-button border border-jp-border px-4 py-2 text-jp-sm font-semibold focus-visible:shadow-jp-focus"
                  >
                    {action.label}
                  </Link>
                ) : null,
              )}
            </div>
          </section>

          {data.recent_bookings.length === 0 ? (
            <AgentEmptyState
              title="No bookings yet"
              description="Search flights to create your first agency booking."
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
                  <li
                    key={booking.booking_reference}
                    className="flex flex-wrap items-center justify-between gap-3 border-b border-jp-border pb-3 last:border-0"
                  >
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
    </AgentDashboardShell>
  );
}
