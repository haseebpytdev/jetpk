"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { fetchInvoice } from "../services/booking-checkout-api";
import type { InvoicePayload } from "../types/review-payment";
import { MissingBookingSessionState } from "./BookingStateCards";
import { ReviewPriceBreakdown } from "./ReviewPriceBreakdown";

export function InvoicePage() {
  const [invoice, setInvoice] = useState<InvoicePayload | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    void fetchInvoice().then((response) => {
      setLoading(false);
      if (!response.ok) {
        setError(response.message);
        return;
      }
      setInvoice(response.data);
    });
  }, []);

  if (loading) return <p className="p-8 text-jp-sm text-jp-muted">Loading invoice…</p>;
  if (!invoice?.ok) return <div className="mx-auto max-w-3xl p-8"><MissingBookingSessionState /></div>;

  return (
    <div className="mx-auto max-w-3xl px-4 py-8 print:px-0" data-testid="invoice-page">
      <header className="border-b border-jp-border pb-4">
        <h1 className="text-2xl font-semibold text-jp-text">Invoice</h1>
        <p className="text-jp-sm text-jp-muted">{invoice.company.name}</p>
        <p className="text-jp-sm text-jp-muted">{invoice.company.email}</p>
      </header>

      <dl className="mt-4 grid gap-2 text-jp-sm sm:grid-cols-2">
        <div><dt className="text-jp-muted">Invoice number</dt><dd>{invoice.invoice_number ?? "Pending"}</dd></div>
        <div><dt className="text-jp-muted">Booking reference</dt><dd>{invoice.booking_reference}</dd></div>
        <div><dt className="text-jp-muted">Issue date</dt><dd>{invoice.issue_date}</dd></div>
        <div><dt className="text-jp-muted">Customer</dt><dd>{invoice.customer.email}</dd></div>
      </dl>

      <div className="mt-6">
        <ReviewPriceBreakdown pricing={invoice.pricing} />
      </div>

      <p className="mt-4 text-jp-sm">Payment: {invoice.payment_status.label} · Booking: {invoice.booking_status.label}</p>

      <div className="mt-6 flex gap-3 print:hidden">
        <Link href="/booking/payment/manual" className="text-jp-primary">Back to payment</Link>
        <button type="button" className="text-jp-primary" onClick={() => window.print()}>Print</button>
      </div>
      {error ? <p className="mt-4 text-jp-sm text-red-700">{error}</p> : null}
    </div>
  );
}
