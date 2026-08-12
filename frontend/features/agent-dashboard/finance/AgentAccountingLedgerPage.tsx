"use client";

import { useEffect, useState } from "react";
import { agentApiErrorMessage, fetchAgentAccountingLedger } from "../services/agent-dashboard-api";
import {
  AgentDashboardErrorState,
  AgentDashboardShell,
  AgentEmptyState,
  PermissionDeniedState,
  StatusBadge,
} from "../shell/AgentDashboardShell";
import type { AgentAccountingLedgerTransaction, PaginatedMeta } from "../types";
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
    <div className="flex items-center justify-between gap-3 pt-4" data-testid="accounting-ledger-pagination">
      <p className="text-jp-sm text-jp-muted">
        Page {pagination.current_page} of {pagination.last_page}
      </p>
      <div className="flex gap-2">
        <button
          type="button"
          disabled={pagination.current_page <= 1}
          className="rounded-jp-button border border-jp-border px-3 py-1.5 text-jp-sm disabled:opacity-50"
          onClick={() => onPageChange(pagination.current_page - 1)}
        >
          Previous
        </button>
        <button
          type="button"
          disabled={pagination.current_page >= pagination.last_page}
          className="rounded-jp-button border border-jp-border px-3 py-1.5 text-jp-sm disabled:opacity-50"
          onClick={() => onPageChange(pagination.current_page + 1)}
        >
          Next
        </button>
      </div>
    </div>
  );
}

export function AgentAccountingLedgerPage({ session }: { session: PublicSession }) {
  const [transactions, setTransactions] = useState<AgentAccountingLedgerTransaction[]>([]);
  const [pagination, setPagination] = useState<PaginatedMeta | null>(null);
  const [summary, setSummary] = useState<{
    wallet_balance: number;
    ledger_liability: number;
    difference: number;
    reconciliation_status: string;
    currency: string;
  } | null>(null);
  const [page, setPage] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [denied, setDenied] = useState(false);
  const [loading, setLoading] = useState(true);

  const load = async (nextPage = page) => {
    setLoading(true);
    setError(null);
    setDenied(false);
    const result = await fetchAgentAccountingLedger({ page: nextPage });
    if (!result.ok) {
      if (result.status === 403) setDenied(true);
      else setError(agentApiErrorMessage(result));
      setTransactions([]);
    } else {
      setTransactions(result.data.transactions);
      setPagination(result.data.pagination);
      setSummary(result.data.summary);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, [page]);

  if (denied) {
    return (
      <AgentDashboardShell session={session} title="Accounting ledger">
        <PermissionDeniedState message="You do not have permission to view the accounting ledger." />
      </AgentDashboardShell>
    );
  }

  return (
    <AgentDashboardShell session={session} title="Accounting ledger">
      {summary ? (
        <p className="mb-4 text-jp-sm text-jp-muted" data-testid="agent-accounting-ledger-summary">
          Wallet: {formatMoney(summary.wallet_balance, summary.currency)} · Ledger liability:{" "}
          {formatMoney(summary.ledger_liability, summary.currency)} · {summary.reconciliation_status}
        </p>
      ) : null}

      {loading ? <p className="text-jp-sm text-jp-muted">Loading accounting ledger…</p> : null}
      {error ? <AgentDashboardErrorState message={error} onRetry={() => load()} /> : null}

      {!loading && !error && transactions.length === 0 ? (
        <AgentEmptyState title="No ledger transactions" description="Accounting entries will appear here when posted." />
      ) : null}

      <div className="space-y-3" data-testid="agent-accounting-ledger-list">
        {transactions.map((transaction) => (
          <article key={transaction.id} className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p className="font-semibold text-jp-text">{transaction.transaction_ref}</p>
                <p className="text-jp-sm text-jp-muted">
                  {transaction.transaction_type}
                  {transaction.booking_reference ? ` · ${transaction.booking_reference}` : ""}
                </p>
                <p className="text-jp-sm text-jp-muted">{transaction.description}</p>
              </div>
              <div className="text-right">
                <p className="font-semibold">
                  {formatMoney(transaction.amount_total, transaction.currency)}
                </p>
                <StatusBadge status={{ code: transaction.status, label: transaction.status }} />
              </div>
            </div>
          </article>
        ))}
      </div>

      {pagination ? <PaginationControls pagination={pagination} onPageChange={setPage} /> : null}
    </AgentDashboardShell>
  );
}
