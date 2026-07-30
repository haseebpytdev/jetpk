"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { fetchAgentWallet } from "../services/agent-dashboard-api";
import {
  AgentDashboardErrorState,
  AgentDashboardShell,
  AgentEmptyState,
  PermissionDeniedState,
  StatusBadge,
} from "../shell/AgentDashboardShell";
import type { WalletLedgerEntry, WalletSummary } from "../types";
import type { PublicSession } from "@/types/session";

type WalletData = {
  summary: WalletSummary;
  recent_ledger_entries: WalletLedgerEntry[];
  capabilities: Record<string, boolean>;
  quick_actions?: Array<{ code: string; label: string; available: boolean; url?: string | null }>;
};

function formatMoney(amount: number, currency: string) {
  return `${currency} ${amount.toLocaleString()}`;
}

export function WalletOverviewPage({ session }: { session: PublicSession }) {
  const [data, setData] = useState<WalletData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [errorStatus, setErrorStatus] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    setError(null);
    setErrorStatus(null);
    const result = await fetchAgentWallet();
    if (!result.ok) {
      setError(result.message);
      setErrorStatus(result.status ?? null);
      setData(null);
    } else {
      setData(result.data as WalletData);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  const summary = data?.summary;

  return (
    <AgentDashboardShell session={session} title="Wallet">
      {loading ? <p className="text-jp-sm text-jp-muted">Loading wallet…</p> : null}
      {error && errorStatus === 403 ? <PermissionDeniedState message={error} /> : null}
      {error && errorStatus !== 403 ? <AgentDashboardErrorState message={error} onRetry={load} /> : null}
      {summary ? (
        <div className="space-y-6" data-testid="agent-wallet-overview">
          <div className="grid gap-4 sm:grid-cols-3">
            {[
              { label: "Balance", value: formatMoney(summary.balance, summary.currency) },
              { label: "Available", value: formatMoney(summary.available_balance, summary.currency) },
              { label: "Pending deposits", value: formatMoney(summary.pending_deposits, summary.currency) },
            ].map((card) => (
              <div key={card.label} className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 shadow-jp-sm" data-testid="wallet-metric-card">
                <p className="text-jp-xs uppercase tracking-wide text-jp-muted">{card.label}</p>
                <p className="mt-2 text-jp-h3 font-bold text-jp-text">{card.value}</p>
              </div>
            ))}
          </div>

          {summary.credit_enabled && summary.credit_limit != null ? (
            <p className="text-jp-sm text-jp-muted">
              Credit limit: {formatMoney(summary.credit_limit, summary.currency)} · Status: {summary.wallet_status}
            </p>
          ) : null}

          {data.quick_actions && data.quick_actions.length > 0 ? (
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
          ) : (
            <div className="flex flex-wrap gap-2">
              {data.capabilities.can_view_ledger ? (
                <Link href="/agent/wallet/ledger" className="rounded-jp-button border border-jp-border px-4 py-2 text-jp-sm font-semibold focus-visible:shadow-jp-focus">
                  View ledger
                </Link>
              ) : null}
              {data.capabilities.can_create_deposit ? (
                <Link href="/agent/deposits/new" className="rounded-jp-button border border-jp-border px-4 py-2 text-jp-sm font-semibold focus-visible:shadow-jp-focus">
                  Request deposit
                </Link>
              ) : null}
            </div>
          )}

          {data.recent_ledger_entries.length === 0 ? (
            <AgentEmptyState title="No ledger activity yet" description="Wallet transactions will appear here." />
          ) : (
            <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
              <div className="flex items-center justify-between gap-3">
                <h2 className="text-jp-base font-semibold text-jp-text">Recent ledger</h2>
                {data.capabilities.can_view_ledger ? (
                  <Link href="/agent/wallet/ledger" className="text-jp-sm text-jp-primary focus-visible:shadow-jp-focus">
                    View all
                  </Link>
                ) : null}
              </div>
              <ul className="mt-4 space-y-3" data-testid="agent-wallet-recent-ledger">
                {data.recent_ledger_entries.map((entry) => (
                  <li key={entry.reference} className="flex flex-wrap items-center justify-between gap-3 border-b border-jp-border pb-3 last:border-0">
                    <div>
                      <p className="font-semibold text-jp-text">{entry.reference}</p>
                      <p className="text-jp-sm text-jp-muted">{entry.description}</p>
                      <p className="text-jp-xs text-jp-muted">{entry.date ?? "—"}</p>
                    </div>
                    <div className="text-right">
                      <p className={`font-semibold ${entry.direction === "credit" ? "text-emerald-700" : "text-jp-text"}`}>
                        {entry.direction === "credit" ? "+" : "−"}
                        {formatMoney(entry.amount, entry.currency)}
                      </p>
                      <StatusBadge status={{ label: entry.status, code: entry.status }} />
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
