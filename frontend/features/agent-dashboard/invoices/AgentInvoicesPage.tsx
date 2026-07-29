"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { fetchAgentInvoices } from "../services/agent-dashboard-api";
import {
  AgentDashboardErrorState,
  AgentDashboardShell,
  AgentEmptyState,
  StatusBadge,
} from "../shell/AgentDashboardShell";
import type { AgentInvoice, PaginatedMeta } from "../types";
import type { PublicSession } from "@/types/session";

function PaginationControls({
  pagination,
  onPageChange,
}: {
  pagination: PaginatedMeta;
  onPageChange: (page: number) => void;
}) {
  if (pagination.last_page <= 1) return null;
  return (
    <div className="flex items-center justify-between gap-3 pt-4" data-testid="invoices-pagination">
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

export function AgentInvoicesPage({ session }: { session: PublicSession }) {
  const [invoices, setInvoices] = useState<AgentInvoice[]>([]);
  const [pagination, setPagination] = useState<PaginatedMeta | null>(null);
  const [page, setPage] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = async (nextPage = page) => {
    setLoading(true);
    setError(null);
    const result = await fetchAgentInvoices(nextPage);
    if (!result.ok) {
      setError(result.message);
      setInvoices([]);
    } else {
      setInvoices(result.data.invoices);
      setPagination(result.data.pagination);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, [page]);

  return (
    <AgentDashboardShell session={session} title="Invoices">
      {loading ? <p className="text-jp-sm text-jp-muted">Loading invoices…</p> : null}
      {error ? <AgentDashboardErrorState message={error} onRetry={() => load()} /> : null}

      {!loading && !error && invoices.length === 0 ? (
        <AgentEmptyState title="No invoices yet" description="Invoices appear after bookings are created." />
      ) : null}

      <div className="space-y-3" data-testid="agent-invoices-list">
        {invoices.map((invoice) => (
          <article
            key={`${invoice.invoice_number}-${invoice.booking_reference}`}
            className="rounded-jp-lg border border-jp-border bg-jp-surface p-4"
          >
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p className="font-semibold text-jp-text">{invoice.invoice_number ?? "Invoice"}</p>
                {invoice.booking_reference ? (
                  <Link href={`/agent/bookings/${invoice.booking_reference}`} className="text-jp-sm text-jp-primary">
                    Booking {invoice.booking_reference}
                  </Link>
                ) : null}
                <p className="text-jp-sm text-jp-muted">{invoice.issue_date ?? "—"}</p>
                {invoice.agency_label ? <p className="text-jp-xs text-jp-muted">{invoice.agency_label}</p> : null}
              </div>
              <div className="text-right">
                {invoice.amount != null ? (
                  <p className="font-semibold">
                    {invoice.currency} {invoice.amount.toLocaleString()}
                  </p>
                ) : null}
                {invoice.payment_status ? <StatusBadge status={invoice.payment_status} /> : null}
                <div className="mt-2 flex flex-col gap-1">
                  {invoice.pdf_available && invoice.download_url ? (
                    <a href={invoice.download_url} className="text-jp-sm text-jp-primary">
                      Download PDF
                    </a>
                  ) : null}
                  {invoice.view_url ? (
                    <a href={invoice.view_url} className="text-jp-sm text-jp-primary">
                      View
                    </a>
                  ) : null}
                </div>
              </div>
            </div>
          </article>
        ))}
      </div>

      {pagination ? <PaginationControls pagination={pagination} onPageChange={setPage} /> : null}
    </AgentDashboardShell>
  );
}
