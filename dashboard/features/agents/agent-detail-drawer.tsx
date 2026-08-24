"use client";

import { DashboardLink as Link } from "@/components/dashboard/dashboard-link";
import { Divider } from "@/components/ui/divider";
import { DetailDrawerSourceNotice } from "@/components/ui/detail-drawer-source-notice";
import {
  AccountStatusBadge,
  CommercialStatusBadge,
  SettlementStatusBadge,
  VerificationStatusBadge,
} from "@/components/ui/status-badge";
import { AgencyOperationalPanel } from "@/features/agents/agency-operational-panel";
import { formatCurrency, formatDate } from "@/lib/format";
import type { AgentRecord } from "@/types/agent";

export function AgentDetailDrawerContent({ agent }: { agent: AgentRecord }) {
  const recentBookings = agent.linkedBookingIds.slice(0, 5);
  const recentCustomers = agent.linkedCustomerIds.slice(0, 5);
  const recentTransactions = agent.linkedTransactionIds.slice(0, 5);
  const recentPnrs = agent.linkedPnrIds.slice(0, 5);
  const recentTickets = agent.linkedTicketIds.slice(0, 5);

  return (
    <div className="space-y-5" data-testid="agent-drawer-content">
      <DetailDrawerSourceNotice className="text-xs" />

      <section aria-labelledby="agent-overview-heading">
        <h3 id="agent-overview-heading" className="text-sm font-semibold text-gray-900">
          Overview
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Agent ID</dt>
            <dd className="font-medium">{agent.id}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Agency name</dt>
            <dd>{agent.agencyName}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Trading name</dt>
            <dd>{agent.tradingName}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Agent type</dt>
            <dd>{agent.agentType}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Created</dt>
            <dd>{formatDate(agent.createdDate)}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Support owner</dt>
            <dd>{agent.supportOwner}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="agent-status-heading">
        <h3 id="agent-status-heading" className="text-sm font-semibold text-gray-900">
          Account, verification, commercial, and settlement
        </h3>
        <div className="mt-2 flex flex-wrap gap-2">
          <AccountStatusBadge status={agent.accountStatus} />
          <VerificationStatusBadge status={agent.verificationStatus} />
          <CommercialStatusBadge status={agent.commercialStatus} />
          <SettlementStatusBadge status={agent.settlementStatus} />
        </div>
      </section>

      <Divider />

      <section aria-labelledby="agent-contact-heading">
        <h3 id="agent-contact-heading" className="text-sm font-semibold text-gray-900">
          Contact details
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div>
            <dt className="text-jp-muted">Primary contact</dt>
            <dd>{agent.primaryContact}</dd>
          </div>
          <div>
            <dt className="text-jp-muted">Email</dt>
            <dd>
              <a className="text-jp-accent-muted underline" href={`mailto:${agent.email}`}>
                {agent.email}
              </a>
            </dd>
          </div>
          <div>
            <dt className="text-jp-muted">Phone</dt>
            <dd>{agent.phone}</dd>
          </div>
          <div>
            <dt className="text-jp-muted">Location</dt>
            <dd>
              {agent.city}, {agent.country}
            </dd>
          </div>
          <div>
            <dt className="text-jp-muted">Operating region</dt>
            <dd>{agent.operatingRegion}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="agent-customers-heading">
        <h3 id="agent-customers-heading" className="text-sm font-semibold text-gray-900">
          Customer and traveller summary
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Customers</dt>
            <dd>{agent.customerCount}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Travellers</dt>
            <dd>{agent.travellerCount}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="agent-bookings-heading">
        <h3 id="agent-bookings-heading" className="text-sm font-semibold text-gray-900">
          Booking summary
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Bookings</dt>
            <dd>
              {agent.bookingCount} total · {agent.confirmedBookingCount} confirmed ·{" "}
              {agent.cancelledBookingCount} cancelled · {agent.ticketedBookingCount} ticketed
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Last booking</dt>
            <dd>{agent.lastBookingDate ? formatDate(agent.lastBookingDate) : "—"}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="agent-financial-heading">
        <h3 id="agent-financial-heading" className="text-sm font-semibold text-gray-900">
          Financial summary
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Gross booking value</dt>
            <dd className="font-semibold tabular-nums">
              {formatCurrency(agent.grossBookingValue, agent.currency)}
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Total paid</dt>
            <dd className="tabular-nums">{formatCurrency(agent.totalPaid, agent.currency)}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Outstanding customer balance</dt>
            <dd className="tabular-nums">
              {formatCurrency(agent.outstandingCustomerBalance, agent.currency)}
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Refund exposure</dt>
            <dd className="tabular-nums">{formatCurrency(agent.refundExposure, agent.currency)}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="agent-commission-heading">
        <h3 id="agent-commission-heading" className="text-sm font-semibold text-gray-900">
          Commission summary
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Commission rate</dt>
            <dd>{agent.commissionRatePercent.toFixed(2)}%</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Commission earned</dt>
            <dd className="tabular-nums">
              {formatCurrency(agent.commissionEarned, agent.currency)}
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Commission paid</dt>
            <dd className="tabular-nums">{formatCurrency(agent.commissionPaid, agent.currency)}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Commission pending</dt>
            <dd className="tabular-nums font-semibold">
              {formatCurrency(agent.commissionPending, agent.currency)}
            </dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="agent-agency-admin-heading">
        <h3 id="agent-agency-admin-heading" className="text-sm font-semibold text-gray-900">
          Agency prefix and access
        </h3>
        <div className="mt-2">
          <AgencyOperationalPanel
            compact
            agencyId={agent.agencyId}
            userId={agent.primaryUserId}
            initialPrefix={agent.codePrefix}
          />
        </div>
      </section>

      <Divider />

      <section aria-labelledby="agent-linked-customers-heading">
        <h3 id="agent-linked-customers-heading" className="text-sm font-semibold text-gray-900">
          Linked customers
        </h3>
        {recentCustomers.length > 0 ? (
          <ul className="mt-2 space-y-1 text-sm">
            {recentCustomers.map((customerId) => (
              <li key={customerId}>
                <Link href={`/customers?id=${customerId}`} className="text-jp-accent-muted underline">
                  {customerId}
                </Link>
              </li>
            ))}
          </ul>
        ) : (
          <p className="mt-2 text-sm text-jp-muted">No linked customers.</p>
        )}
      </section>

      <Divider />

      <section aria-labelledby="agent-linked-bookings-heading">
        <h3 id="agent-linked-bookings-heading" className="text-sm font-semibold text-gray-900">
          Recent linked bookings
        </h3>
        {recentBookings.length > 0 ? (
          <ul className="mt-2 space-y-1 text-sm">
            {recentBookings.map((bookingId) => (
              <li key={bookingId}>
                <Link href={`/bookings?id=${bookingId}`} className="text-jp-accent-muted underline">
                  {bookingId}
                </Link>
              </li>
            ))}
          </ul>
        ) : (
          <p className="mt-2 text-sm text-jp-muted">No linked bookings.</p>
        )}
      </section>

      <Divider />

      <section aria-labelledby="agent-linked-payments-heading">
        <h3 id="agent-linked-payments-heading" className="text-sm font-semibold text-gray-900">
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

      <section aria-labelledby="agent-linked-pnrs-heading">
        <h3 id="agent-linked-pnrs-heading" className="text-sm font-semibold text-gray-900">
          Linked PNRs and orders
        </h3>
        {recentPnrs.length > 0 ? (
          <ul className="mt-2 space-y-1 text-sm">
            {recentPnrs.map((pnrId) => (
              <li key={pnrId}>
                <Link href={`/pnrs?id=${pnrId}`} className="text-jp-accent-muted underline">
                  {pnrId}
                </Link>
              </li>
            ))}
          </ul>
        ) : (
          <p className="mt-2 text-sm text-jp-muted">No linked PNRs or orders.</p>
        )}
      </section>

      <Divider />

      <section aria-labelledby="agent-linked-tickets-heading">
        <h3 id="agent-linked-tickets-heading" className="text-sm font-semibold text-gray-900">
          Linked tickets and documents
        </h3>
        {recentTickets.length > 0 ? (
          <ul className="mt-2 space-y-1 text-sm">
            {recentTickets.map((ticketId) => (
              <li key={ticketId}>
                <Link href={`/tickets?id=${ticketId}`} className="text-jp-accent-muted underline">
                  {ticketId}
                </Link>
              </li>
            ))}
          </ul>
        ) : (
          <p className="mt-2 text-sm text-jp-muted">No linked tickets or documents.</p>
        )}
      </section>

      <Divider />

      <section aria-labelledby="agent-activity-heading">
        <h3 id="agent-activity-heading" className="text-sm font-semibold text-gray-900">
          Recent activity
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Last payment</dt>
            <dd>{agent.lastPaymentDate ? formatDate(agent.lastPaymentDate) : "—"}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Last ticket activity</dt>
            <dd>{agent.lastTicketActivity ? formatDate(agent.lastTicketActivity) : "—"}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="agent-notes-heading">
        <h3 id="agent-notes-heading" className="text-sm font-semibold text-gray-900">
          Notes
        </h3>
        <p className="mt-2 text-sm text-gray-700">{agent.notesSummary}</p>
        <p className="mt-2 text-xs text-jp-muted">
          Read-only preview — no agent actions, commission payouts, or settlement mutations are
          available in this module.
        </p>
      </section>
    </div>
  );
}
