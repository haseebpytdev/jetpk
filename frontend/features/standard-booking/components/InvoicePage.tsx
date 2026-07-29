"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { fetchInvoice } from "../services/booking-checkout-api";
import type { InvoicePayload } from "../types/review-payment";
import { MissingBookingSessionState } from "./BookingStateCards";
import { ReviewPriceBreakdown } from "./ReviewPriceBreakdown";
import { ItineraryTimeline } from "../itinerary/ItineraryTimeline";
import type { SelectedFlightSummary } from "../types";

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

  const itinerarySummary: SelectedFlightSummary = {
    trip_type: "one_way",
    origin: (invoice.itinerary_summary.route ?? "").split("→")[0]?.trim() ?? "",
    destination: (invoice.itinerary_summary.route ?? "").split("→")[1]?.trim() ?? "",
    route_label: invoice.itinerary_summary.route,
    depart_date: invoice.itinerary_summary.depart_date,
    return_date: invoice.itinerary_summary.return_date,
    cabin: "economy",
    currency: invoice.pricing.currency,
    segments: [],
    return_segments: [],
  };

  return (
    <div className="mx-auto max-w-3xl px-4 py-8 print:px-0" data-testid="invoice-page">
      <header className="border-b border-jp-border pb-4">
        <h1 className="text-2xl font-semibold text-jp-text">Invoice</h1>
        <p className="text-jp-sm text-jp-muted">{invoice.company.name}</p>
        <p className="text-jp-sm text-jp-muted">{invoice.company.email}</p>
        {invoice.company.phone ? <p className="text-jp-sm text-jp-muted">{invoice.company.phone}</p> : null}
      </header>

      <dl className="mt-4 grid gap-2 text-jp-sm sm:grid-cols-2">
        <div><dt className="text-jp-muted">Invoice number</dt><dd>{invoice.invoice_number ?? "Pending"}</dd></div>
        <div><dt className="text-jp-muted">Booking reference</dt><dd>{invoice.booking_reference}</dd></div>
        <div><dt className="text-jp-muted">Issue date</dt><dd>{invoice.issue_date}</dd></div>
        <div><dt className="text-jp-muted">Customer</dt><dd>{invoice.customer.name || invoice.customer.email}</dd></div>
        <div><dt className="text-jp-muted">Passengers</dt><dd>{invoice.passenger_count}</dd></div>
      </dl>

      <div className="mt-6">
        <ItineraryTimeline itinerary={itinerarySummary} />
      </div>

      <div className="mt-6 overflow-x-auto">
        <table className="min-w-full border-collapse text-jp-sm">
          <caption className="sr-only">Invoice line items</caption>
          <thead>
            <tr className="border-b border-jp-border text-left">
              <th className="py-2 pr-4">Description</th>
              <th className="py-2">Amount</th>
            </tr>
          </thead>
          <tbody>
            {invoice.line_items.map((item, index) => (
              <tr key={index} className="border-b border-jp-border">
                <td className="py-2 pr-4">{(item.label as string) ?? (item.description as string) ?? "Item"}</td>
                <td className="py-2">{(item.formatted as string) ?? (item.amount as string) ?? "—"}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="mt-6">
        <ReviewPriceBreakdown pricing={invoice.pricing} />
      </div>

      <p className="mt-4 text-jp-sm">
        Payment: {invoice.payment_status.label} · Booking: {invoice.booking_status.label}
      </p>

      <div className="mt-6 flex flex-wrap gap-3 print:hidden">
        <Link href="/booking/confirmation" className="text-jp-primary">Back to confirmation</Link>
        <button type="button" className="text-jp-primary" onClick={() => window.print()}>Print</button>
        {invoice.pdf_available && invoice.pdf_download_path ? (
          <a href={invoice.pdf_download_path} className="text-jp-primary" data-testid="invoice-pdf-download">
            Download PDF
          </a>
        ) : (
          <span className="text-jp-muted" data-testid="invoice-pdf-unavailable">PDF not available yet</span>
        )}
      </div>
      {error ? <p className="mt-4 text-jp-sm text-red-700">{error}</p> : null}
    </div>
  );
}
