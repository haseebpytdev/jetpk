"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { fetchAgentPayments } from "../services/agent-dashboard-api";
import {
  AgentDashboardErrorState,
  AgentDashboardShell,
  AgentEmptyState,
  StatusBadge,
} from "../shell/AgentDashboardShell";
import type { AgentPayment, PaginatedMeta } from "../types";
import type { PublicSession } from "@/types/session";

const FILTERS = [
  { value: "all", label: "All" },
  { value: "pending", label: "Pending" },
  { value: "paid", label: "Paid" },
  { value: "failed", label: "Failed" },
] as const;

function PaginationControls({
  pagination,
  onPageChange,
}: {
  pagination: PaginatedMeta;
  onPageChange: (page: number) => void;
}) {
  if (pagination.last_page <= 1) return null;
  return (
    <div className="flex items-center justify-between gap-3 pt-4" data-testid="payments-pagination">
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

export function AgentPaymentsPage({ session }: { session: PublicSession }) {
  const [payments, setPayments] = useState<AgentPayment[]>([]);
  const [pagination, setPagination] = useState<PaginatedMeta | null>(null);
  const [filter, setFilter] = useState("all");
  const [page, setPage] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = async (nextFilter = filter, nextPage = page) => {
    setLoading(true);
    setError(null);
    const result = await fetchAgentPayments({ filter: nextFilter, page: nextPage });
    if (!result.ok) {
      setError(result.message);
      setPayments([]);
    } else {
      setPayments(result.data.payments);
      setPagination(result.data.pagination);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, [filter, page]);

  return (
    <AgentDashboardShell session={session} title="Payments">
      <div className="mb-4 flex flex-wrap gap-2" data-testid="agent-payments-filters">
        {FILTERS.map((item) => (
          <button
            key={item.value}
            type="button"
            className={`rounded-jp-button border px-3 py-1.5 text-jp-sm focus-visible:shadow-jp-focus ${
              filter === item.value ? "border-jp-primary bg-jp-primary/10 text-jp-primary" : "border-jp-border"
            }`}
            onClick={() => {
              setPage(1);
              setFilter(item.value);
            }}
          >
            {item.label}
          </button>
        ))}
      </div>

      {loading ? <p className="text-jp-sm text-jp-muted">Loading payments…</p> : null}
      {error ? <AgentDashboardErrorState message={error} onRetry={() => load()} /> : null}

      {!loading && !error && payments.length === 0 ? (
        <AgentEmptyState title="No payments found" description="Payment activity will appear here." />
      ) : null}

      <div className="space-y-3" data-testid="agent-payments-list">
        {payments.map((payment) => (
          <article key={`${payment.source}-${payment.reference}`} className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p className="font-semibold text-jp-text">{payment.reference}</p>
                <p className="text-jp-sm text-jp-muted">
                  {payment.booking_reference ? (
                    <Link href={`/agent/bookings/${payment.booking_reference}`} className="text-jp-primary">
                      {payment.booking_reference}
                    </Link>
                  ) : payment.deposit_reference ? (
                    <Link href="/agent/deposits" className="text-jp-primary">
                      Deposit {payment.deposit_reference}
                    </Link>
                  ) : (
                    "No linked booking"
                  )}
                </p>
                <p className="text-jp-sm text-jp-muted">
                  {payment.method_label} · {payment.source} · {payment.date ?? "—"}
                </p>
              </div>
              <div className="text-right">
                <p className="font-semibold">
                  {payment.currency} {payment.amount.toLocaleString()}
                </p>
                <StatusBadge status={payment.payment_status} />
                {payment.detail_url ? (
                  <Link href={payment.detail_url} className="mt-1 block text-jp-sm text-jp-primary">
                    View details
                  </Link>
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
