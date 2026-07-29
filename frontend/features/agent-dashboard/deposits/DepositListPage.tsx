"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { fetchAgentDeposits } from "../services/agent-dashboard-api";
import {
  AgentDashboardErrorState,
  AgentDashboardShell,
  AgentEmptyState,
  StatusBadge,
} from "../shell/AgentDashboardShell";
import type { DepositRequest, PaginatedMeta, WalletSummary } from "../types";
import type { PublicSession } from "@/types/session";

function formatMoney(amount: number, currency: string) {
  return `${currency} ${amount.toLocaleString()}`;
}

function PaginationControls({
  pagination,
  onPageChange,
}: {
  pagination: PaginatedMeta;
  onPageChange: (page: number) => void;
}) {
  if (pagination.last_page <= 1) return null;
  return (
    <div className="flex items-center justify-between gap-3 pt-4" data-testid="deposits-pagination">
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

export function DepositListPage({ session }: { session: PublicSession }) {
  const [deposits, setDeposits] = useState<DepositRequest[]>([]);
  const [summary, setSummary] = useState<WalletSummary | null>(null);
  const [pagination, setPagination] = useState<PaginatedMeta | null>(null);
  const [page, setPage] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = async (nextPage = page) => {
    setLoading(true);
    setError(null);
    const result = await fetchAgentDeposits(nextPage);
    if (!result.ok) {
      setError(result.message);
      setDeposits([]);
    } else {
      setDeposits(result.data.deposits);
      setSummary(result.data.summary);
      setPagination(result.data.pagination);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, [page]);

  return (
    <AgentDashboardShell session={session} title="Deposit requests">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <Link href="/agent/wallet" className="text-jp-sm text-jp-primary focus-visible:shadow-jp-focus">
          Back to wallet
        </Link>
        <Link
          href="/agent/deposits/new"
          className="rounded-jp-button border border-jp-border px-4 py-2 text-jp-sm font-semibold focus-visible:shadow-jp-focus"
        >
          New deposit request
        </Link>
      </div>

      {summary ? (
        <p className="mb-4 text-jp-sm text-jp-muted">
          Balance: {formatMoney(summary.balance, summary.currency)}
          {summary.pending_deposits > 0 ? ` · Pending: ${formatMoney(summary.pending_deposits, summary.currency)}` : ""}
        </p>
      ) : null}

      {loading ? <p className="text-jp-sm text-jp-muted">Loading deposits…</p> : null}
      {error ? <AgentDashboardErrorState message={error} onRetry={() => load()} /> : null}

      {!loading && !error && deposits.length === 0 ? (
        <AgentEmptyState
          title="No deposit requests"
          description="Submit a deposit request to top up your wallet."
          action={
            <Link
              href="/agent/deposits/new"
              className="inline-flex min-h-10 items-center rounded-jp-button bg-jp-primary px-4 py-2 text-jp-sm font-semibold text-white focus-visible:shadow-jp-focus"
            >
              New deposit request
            </Link>
          }
        />
      ) : null}

      <div className="space-y-3" data-testid="agent-deposits-list">
        {deposits.map((deposit) => (
          <article key={deposit.deposit_reference} className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 shadow-jp-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p className="font-semibold text-jp-text">{deposit.deposit_reference}</p>
                <p className="text-jp-sm text-jp-muted">
                  {formatMoney(deposit.requested_amount, deposit.currency)} · {deposit.method}
                </p>
                <p className="text-jp-xs text-jp-muted">{deposit.date ?? "—"} · Proof: {deposit.proof_status}</p>
                {deposit.rejection_reason ? (
                  <p className="mt-1 text-jp-xs text-red-700">{deposit.rejection_reason}</p>
                ) : null}
              </div>
              <div className="text-right">
                <StatusBadge status={deposit.approval_status} />
                {deposit.credited_amount != null ? (
                  <p className="mt-1 text-jp-sm text-jp-muted">
                    Credited: {formatMoney(deposit.credited_amount, deposit.currency)}
                  </p>
                ) : null}
              </div>
            </div>
          </article>
        ))}
      </div>

      {pagination ? <PaginationControls pagination={pagination} onPageChange={setPage} /> : null}
    </AgentDashboardShell>
  );
}
