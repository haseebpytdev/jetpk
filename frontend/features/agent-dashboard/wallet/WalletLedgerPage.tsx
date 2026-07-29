"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { fetchAgentLedger } from "../services/agent-dashboard-api";
import {
  AgentDashboardErrorState,
  AgentDashboardShell,
  AgentEmptyState,
  StatusBadge,
} from "../shell/AgentDashboardShell";
import type { PaginatedMeta, WalletLedgerEntry, WalletSummary } from "../types";
import type { PublicSession } from "@/types/session";

const TYPE_FILTERS = [
  { value: "", label: "All types" },
  { value: "credit", label: "Credit" },
  { value: "debit", label: "Debit" },
  { value: "deposit", label: "Deposit" },
  { value: "booking", label: "Booking" },
  { value: "adjustment", label: "Adjustment" },
] as const;

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
    <div className="flex items-center justify-between gap-3 pt-4" data-testid="ledger-pagination">
      <p className="text-jp-sm text-jp-muted">
        Page {pagination.current_page} of {pagination.last_page} ({pagination.total} entries)
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

function LedgerEntryRow({ entry }: { entry: WalletLedgerEntry }) {
  return (
    <article className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 shadow-jp-sm">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="font-semibold text-jp-text">{entry.reference}</p>
          <p className="text-jp-sm text-jp-muted">{entry.description}</p>
          <p className="text-jp-xs text-jp-muted">
            {entry.date ?? "—"} · {entry.type}
            {entry.booking_reference ? (
              <>
                {" · "}
                <Link href={`/agent/bookings/${entry.booking_reference}`} className="text-jp-primary">
                  {entry.booking_reference}
                </Link>
              </>
            ) : null}
          </p>
        </div>
        <div className="text-right">
          <p className={`font-semibold ${entry.direction === "credit" ? "text-emerald-700" : "text-jp-text"}`}>
            {entry.direction === "credit" ? "+" : "−"}
            {formatMoney(entry.amount, entry.currency)}
          </p>
          <p className="text-jp-xs text-jp-muted">Balance: {formatMoney(entry.balance_after, entry.currency)}</p>
          <StatusBadge status={{ label: entry.status, code: entry.status }} />
        </div>
      </div>
    </article>
  );
}

export function WalletLedgerPage({ session }: { session: PublicSession }) {
  const [entries, setEntries] = useState<WalletLedgerEntry[]>([]);
  const [summary, setSummary] = useState<WalletSummary | null>(null);
  const [pagination, setPagination] = useState<PaginatedMeta | null>(null);
  const [page, setPage] = useState(1);
  const [type, setType] = useState("");
  const [query, setQuery] = useState("");
  const [searchInput, setSearchInput] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = async (nextPage = page, nextType = type, nextQuery = query) => {
    setLoading(true);
    setError(null);
    const result = await fetchAgentLedger({
      page: nextPage,
      type: nextType || undefined,
      q: nextQuery || undefined,
    });
    if (!result.ok) {
      setError(result.message);
      setEntries([]);
    } else {
      setEntries(result.data.entries);
      setSummary(result.data.summary);
      setPagination(result.data.pagination);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, [page, type, query]);

  const handleSearch = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setPage(1);
    setQuery(searchInput.trim());
  };

  return (
    <AgentDashboardShell session={session} title="Wallet ledger">
      <div className="mb-4">
        <Link href="/agent/wallet" className="text-jp-sm text-jp-primary focus-visible:shadow-jp-focus">
          Back to wallet
        </Link>
      </div>

      {summary ? (
        <p className="mb-4 text-jp-sm text-jp-muted">
          Balance: {formatMoney(summary.balance, summary.currency)} · Available:{" "}
          {formatMoney(summary.available_balance, summary.currency)}
        </p>
      ) : null}

      <div className="mb-4 flex flex-wrap gap-2" data-testid="agent-ledger-filters">
        {TYPE_FILTERS.map((item) => (
          <button
            key={item.value || "all"}
            type="button"
            className={`rounded-jp-button border px-3 py-1.5 text-jp-sm focus-visible:shadow-jp-focus ${
              type === item.value ? "border-jp-primary bg-jp-primary/10 text-jp-primary" : "border-jp-border"
            }`}
            onClick={() => {
              setPage(1);
              setType(item.value);
            }}
          >
            {item.label}
          </button>
        ))}
      </div>

      <form onSubmit={handleSearch} className="mb-4 flex flex-wrap gap-2" data-testid="agent-ledger-search">
        <input
          type="search"
          value={searchInput}
          onChange={(event) => setSearchInput(event.target.value)}
          placeholder="Search reference or description"
          className="min-w-0 flex-1 rounded-jp-md border border-jp-border px-3 py-2 text-jp-sm focus-visible:shadow-jp-focus"
        />
        <button type="submit" className="rounded-jp-button border border-jp-border px-4 py-2 text-jp-sm font-semibold focus-visible:shadow-jp-focus">
          Search
        </button>
      </form>

      {loading ? <p className="text-jp-sm text-jp-muted">Loading ledger…</p> : null}
      {error ? <AgentDashboardErrorState message={error} onRetry={() => load()} /> : null}

      {!loading && !error && entries.length === 0 ? (
        <AgentEmptyState title="No ledger entries" description="Try another filter or search term." />
      ) : null}

      <div className="space-y-3" data-testid="agent-ledger-list">
        {entries.map((entry) => (
          <LedgerEntryRow key={`${entry.reference}-${entry.date}`} entry={entry} />
        ))}
      </div>

      {pagination ? <PaginationControls pagination={pagination} onPageChange={setPage} /> : null}
    </AgentDashboardShell>
  );
}
