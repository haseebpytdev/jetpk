"use client";

import { DashboardLink as Link } from "@/components/dashboard/dashboard-link";
import { Divider } from "@/components/ui/divider";
import { PreviewDataBanner } from "@/components/ui/page-layout";
import {
  DocumentTypeBadge,
  ExchangeEligibilityBadge,
  IssueStatusBadge,
  RefundEligibilityBadge,
  VoidStatusBadge,
} from "@/components/ui/status-badge";
import { formatCurrency, formatDate } from "@/lib/format";
import type { TicketRecord } from "@/types/ticket";

export function TicketDetailDrawerContent({ ticket }: { ticket: TicketRecord }) {
  const recentTransactions = ticket.linkedTransactionIds.slice(0, 5);

  return (
    <div className="space-y-5" data-testid="ticket-drawer-content">
      <PreviewDataBanner className="text-xs" />

      <section aria-labelledby="ticket-overview-heading">
        <h3 id="ticket-overview-heading" className="text-sm font-semibold text-gray-900">
          Document overview
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Document ID</dt>
            <dd className="font-medium">{ticket.id}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Masked identifier</dt>
            <dd className="font-mono text-sm">{ticket.maskedExternalId}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Document type</dt>
            <dd>
              <DocumentTypeBadge type={ticket.documentType} />
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Channel</dt>
            <dd>{ticket.channel}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Created</dt>
            <dd>{formatDate(ticket.createdDate)}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="ticket-relationships-heading">
        <h3 id="ticket-relationships-heading" className="text-sm font-semibold text-gray-900">
          Relationships
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div>
            <dt className="text-jp-muted">Booking</dt>
            <dd>
              <Link href={`/bookings?id=${ticket.bookingId}`} className="text-jp-accent-muted underline">
                {ticket.bookingId}
              </Link>
            </dd>
          </div>
          <div>
            <dt className="text-jp-muted">PNR / order</dt>
            <dd>
              <Link href={`/pnrs?id=${ticket.pnrOrderId}`} className="text-jp-accent-muted underline">
                {ticket.pnrOrderId}
              </Link>
            </dd>
          </div>
          <div>
            <dt className="text-jp-muted">Customer</dt>
            <dd>
              <Link href={`/customers?id=${ticket.customerId}`} className="text-jp-accent-muted underline">
                {ticket.customerId}
              </Link>
            </dd>
          </div>
          <div>
            <dt className="text-jp-muted">Traveller</dt>
            <dd>{ticket.travellerName}</dd>
          </div>
          {ticket.agentId ? (
            <div>
              <dt className="text-jp-muted">Agent</dt>
              <dd>
                <Link href={`/agents?id=${ticket.agentId}`} className="text-jp-accent-muted underline">
                  {ticket.agentId}
                </Link>
              </dd>
            </div>
          ) : (
            <div>
              <dt className="text-jp-muted">Agent</dt>
              <dd className="text-jp-muted">Direct / web booking</dd>
            </div>
          )}
          <div>
            <dt className="text-jp-muted">Supplier</dt>
            <dd>
              <Link
                href={`/suppliers?id=${ticket.supplierId}`}
                className="text-jp-accent-muted underline"
              >
                {ticket.supplier} ({ticket.supplierId})
              </Link>
            </dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="ticket-itinerary-heading">
        <h3 id="ticket-itinerary-heading" className="text-sm font-semibold text-gray-900">
          Itinerary
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Route</dt>
            <dd>
              {ticket.origin} → {ticket.destination}
            </dd>
          </div>
          <div>
            <dt className="text-jp-muted">Summary</dt>
            <dd>{ticket.itinerarySummary}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Airline</dt>
            <dd>{ticket.airline}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Travel date</dt>
            <dd>{formatDate(ticket.travelDate)}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="ticket-status-heading">
        <h3 id="ticket-status-heading" className="text-sm font-semibold text-gray-900">
          Issue and fulfilment
        </h3>
        <div className="mt-2 flex flex-wrap gap-2">
          <IssueStatusBadge status={ticket.issueStatus} />
          <span className="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-800 ring-1 ring-inset ring-gray-500/20">
            {ticket.fulfilmentStatus}
          </span>
        </div>
        <dl className="mt-3 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Issue date</dt>
            <dd>{ticket.issueDate ? formatDate(ticket.issueDate) : "—"}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Payment status</dt>
            <dd>{ticket.paymentStatus}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Refund status</dt>
            <dd>{ticket.refundStatus}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="ticket-financial-heading">
        <h3 id="ticket-financial-heading" className="text-sm font-semibold text-gray-900">
          Financial breakdown
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Fare</dt>
            <dd className="tabular-nums">{formatCurrency(ticket.fare, ticket.currency)}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Tax</dt>
            <dd className="tabular-nums">{formatCurrency(ticket.tax, ticket.currency)}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Total</dt>
            <dd className="font-semibold tabular-nums">
              {formatCurrency(ticket.total, ticket.currency)}
            </dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="ticket-eligibility-heading">
        <h3 id="ticket-eligibility-heading" className="text-sm font-semibold text-gray-900">
          Eligibility (informational)
        </h3>
        <div className="mt-2 flex flex-wrap gap-2">
          <RefundEligibilityBadge status={ticket.refundEligibility} />
          <ExchangeEligibilityBadge status={ticket.exchangeEligibility} />
          <VoidStatusBadge status={ticket.voidStatus} />
        </div>
        {ticket.voidDeadline ? (
          <p className="mt-2 text-xs text-jp-muted">
            Void deadline (fixture): {formatDate(ticket.voidDeadline)}
          </p>
        ) : null}
      </section>

      <Divider />

      <section aria-labelledby="ticket-payments-heading">
        <h3 id="ticket-payments-heading" className="text-sm font-semibold text-gray-900">
          Linked payments
        </h3>
        {recentTransactions.length > 0 ? (
          <ul className="mt-2 space-y-1 text-sm">
            {recentTransactions.map((txId) => (
              <li key={txId}>
                <Link
                  href={`/payments?transactionId=${txId}`}
                  className="text-jp-accent-muted underline"
                >
                  {txId}
                </Link>
              </li>
            ))}
          </ul>
        ) : (
          <p className="mt-2 text-sm text-jp-muted">No linked transactions.</p>
        )}
      </section>

      <Divider />

      <section aria-labelledby="ticket-activity-heading">
        <h3 id="ticket-activity-heading" className="text-sm font-semibold text-gray-900">
          Activity summary
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Last activity</dt>
            <dd>{formatDate(ticket.lastActivity)}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="ticket-notes-heading">
        <h3 id="ticket-notes-heading" className="text-sm font-semibold text-gray-900">
          Notes
        </h3>
        <p className="mt-2 text-sm text-gray-700">{ticket.notesSummary}</p>
        <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
          Informational preview only — issue, reissue, exchange, void, and refund actions are not
          available in this module. Eligibility values are synthetic fixture states.
        </p>
      </section>
    </div>
  );
}
