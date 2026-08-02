"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { customerApiErrorMessage, fetchCustomerInvoiceDetail } from "../services/customer-dashboard-api";
import { CustomerDashboardErrorState, CustomerDashboardShell } from "../shell/CustomerDashboardShell";
import type { CustomerInvoice } from "../types";
import type { PublicSession } from "@/types/session";

export function CustomerInvoiceDetailPage({ session, reference }: { session: PublicSession; reference: string }) {
  const [invoice, setInvoice] = useState<(CustomerInvoice & { booking_detail_url?: string }) | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    const result = await fetchCustomerInvoiceDetail(reference);
    if (!result.ok) setError(customerApiErrorMessage(result));
    else setInvoice(result.data);
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, [reference]);

  return (
    <CustomerDashboardShell session={session} title={`Invoice ${reference}`}>
      {loading ? <p className="text-jp-sm text-jp-muted">Loading invoice…</p> : null}
      {error ? <CustomerDashboardErrorState message={error} onRetry={load} /> : null}
      {invoice ? (
        <div data-testid="customer-invoice-detail" className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
          <p className="font-semibold text-jp-text">{invoice.invoice_number}</p>
          <p className="text-jp-sm text-jp-muted">Issued {invoice.issue_date}</p>
          {invoice.amount != null ? (
            <p className="mt-2 text-jp-base font-semibold">
              {invoice.currency} {invoice.amount.toLocaleString()}
            </p>
          ) : null}
          {invoice.pdf_available && invoice.download_url ? (
            <a href={invoice.download_url} className="mt-4 inline-block text-jp-sm font-semibold text-jp-primary">
              Download PDF
            </a>
          ) : (
            <p className="mt-4 text-jp-sm text-jp-muted">PDF not available yet.</p>
          )}
          {invoice.booking_detail_url ? (
            <p className="mt-4">
              <Link href={invoice.booking_detail_url} className="text-jp-sm text-jp-primary">
                View booking
              </Link>
            </p>
          ) : null}
        </div>
      ) : null}
    </CustomerDashboardShell>
  );
}
