"use client";

import type { CustomerBookingCapabilities, CustomerRefundSummary } from "../types";

type BookingDocumentsPanelProps = {
  capabilities?: CustomerBookingCapabilities;
};

export function BookingDocumentsPanel({ capabilities }: BookingDocumentsPanelProps) {
  if (!capabilities) return null;

  const invoiceUrl = capabilities.download_urls.invoice;
  const ticketUrl = capabilities.download_urls.ticket;

  return (
    <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="booking-documents-panel">
      <h2 className="text-jp-base font-semibold text-jp-text">Documents</h2>
      <ul className="mt-3 space-y-2">
        <li>
          {capabilities.can_download_invoice && invoiceUrl ? (
            <a href={invoiceUrl} className="text-jp-sm font-semibold text-jp-primary" data-testid="download-invoice-link">
              Download invoice
            </a>
          ) : (
            <p className="text-jp-sm text-jp-muted">Invoice not available yet.</p>
          )}
        </li>
        <li>
          {capabilities.can_download_ticket && ticketUrl ? (
            <a href={ticketUrl} className="text-jp-sm font-semibold text-jp-primary" data-testid="download-ticket-link">
              Download ticket / itinerary
            </a>
          ) : (
            <p className="text-jp-sm text-jp-muted">Ticket not issued yet.</p>
          )}
        </li>
      </ul>
    </section>
  );
}

type BookingRefundPanelProps = {
  refund?: CustomerRefundSummary;
};

export function BookingRefundPanel({ refund }: BookingRefundPanelProps) {
  if (!refund) return null;

  return (
    <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="booking-refund-panel">
      <h2 className="text-jp-base font-semibold text-jp-text">Refund status</h2>
      <p className="mt-2 text-jp-sm text-jp-text">{refund.label}</p>
      <p className="mt-1 text-jp-sm text-jp-muted">{refund.message}</p>
      {refund.request?.amount != null ? (
        <p className="mt-2 text-jp-sm text-jp-muted">
          Amount: {refund.request.currency} {refund.request.amount.toLocaleString()}
        </p>
      ) : null}
    </section>
  );
}
