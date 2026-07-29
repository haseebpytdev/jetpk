"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { fetchCustomerInvoices } from "../services/customer-dashboard-api";
import { CustomerDashboardErrorState, CustomerDashboardShell, CustomerEmptyState } from "../shell/CustomerDashboardShell";
import type { CustomerInvoice } from "../types";
import type { PublicSession } from "@/types/session";

export function CustomerInvoicesPage({ session }: { session: PublicSession }) {
  const [invoices, setInvoices] = useState<CustomerInvoice[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    void (async () => {
      const result = await fetchCustomerInvoices();
      if (!result.ok) setError(result.message);
      else setInvoices(result.data.invoices);
      setLoading(false);
    })();
  }, []);

  return (
    <CustomerDashboardShell session={session} title="Invoices">
      {loading ? <p className="text-jp-sm text-jp-muted">Loading invoices…</p> : null}
      {error ? <CustomerDashboardErrorState message={error} /> : null}
      {!loading && !error && invoices.length === 0 ? (
        <CustomerEmptyState title="No invoices yet" description="Invoices appear after a booking is created." />
      ) : null}
      <div className="space-y-3" data-testid="customer-invoices-list">
        {invoices.map((invoice) => (
          <article key={`${invoice.invoice_number}-${invoice.booking_reference}`} className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p className="font-semibold text-jp-text">{invoice.invoice_number}</p>
                {invoice.booking_reference ? (
                  <Link href={`/customer/invoices/${invoice.booking_reference}`} className="text-jp-sm text-jp-primary">
                    Booking {invoice.booking_reference}
                  </Link>
                ) : null}
                <p className="text-jp-sm text-jp-muted">{invoice.issue_date}</p>
              </div>
              <div className="text-right">
                {invoice.amount != null ? (
                  <p className="font-semibold">
                    {invoice.currency} {invoice.amount.toLocaleString()}
                  </p>
                ) : null}
                {invoice.pdf_available && invoice.download_url ? (
                  <a href={invoice.download_url} className="text-jp-sm text-jp-primary">
                    Download PDF
                  </a>
                ) : null}
              </div>
            </div>
          </article>
        ))}
      </div>
    </CustomerDashboardShell>
  );
}
