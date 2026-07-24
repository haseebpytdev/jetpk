"use client";

import Link from "next/link";
import { Divider } from "@/components/ui/divider";
import { PreviewDataBanner } from "@/components/ui/page-layout";
import {
  CancellationEligibilityBadge,
  ChannelBadge,
  FulfilmentStatusBadge,
  LifecycleStatusBadge,
  PnrTicketingStatusBadge,
  ReferenceTypeBadge,
} from "@/components/ui/status-badge";
import { formatCurrency, formatDate } from "@/lib/format";
import type { PnrRecord } from "@/types/pnr";

const GDS_TICKETING_LIMITATION_NOTE =
  "GDS ticketing is not available in this preview environment. Authorized printer designation is pending — ticketing status is informational only and does not imply live issuance capability.";

export function PnrDetailDrawerContent({ pnr }: { pnr: PnrRecord }) {
  const isGdsPnr = pnr.referenceType === "GDS PNR";
  const isNdcOrder = pnr.referenceType === "NDC Order";
  const referenceLabel = isGdsPnr ? "GDS PNR" : isNdcOrder ? "NDC order" : pnr.referenceType;

  return (
    <div className="space-y-5" data-testid="pnr-drawer-content">
      <PreviewDataBanner className="text-xs" />

      <section aria-labelledby="pnr-overview-heading">
        <h3 id="pnr-overview-heading" className="text-sm font-semibold text-gray-900">
          Reference overview
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Internal ID</dt>
            <dd className="font-medium">{pnr.id}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">External reference</dt>
            <dd className="font-mono text-xs">{pnr.externalReference}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Record type</dt>
            <dd>
              <ReferenceTypeBadge status={pnr.referenceType} />
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Channel</dt>
            <dd>
              <ChannelBadge status={pnr.channel} />
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Created</dt>
            <dd>{formatDate(pnr.createdDate)}</dd>
          </div>
        </dl>
        <p className="mt-3 rounded-lg bg-gray-50 p-3 text-xs text-gray-700">
          {isGdsPnr
            ? "This is a traditional GDS passenger name record — distinct from NDC order references."
            : isNdcOrder
              ? "This is an NDC order reference — not a traditional GDS PNR. Fulfilment follows order-document workflow."
              : `This ${referenceLabel.toLowerCase()} is managed outside the GDS PNR workflow.`}
        </p>
      </section>

      <Divider />

      <section aria-labelledby="pnr-supplier-heading">
        <h3 id="pnr-supplier-heading" className="text-sm font-semibold text-gray-900">
          Supplier and airline
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Supplier</dt>
            <dd>
              <Link href={`/suppliers?id=${pnr.supplierId}`} className="text-jp-accent-muted underline">
                {pnr.supplierName}
              </Link>
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Airline</dt>
            <dd>{pnr.airline}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Cabin</dt>
            <dd>{pnr.cabin}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="pnr-relationships-heading">
        <h3 id="pnr-relationships-heading" className="text-sm font-semibold text-gray-900">
          Linked records
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Booking</dt>
            <dd>
              <Link href={`/bookings?id=${pnr.bookingId}`} className="text-jp-accent-muted underline">
                {pnr.bookingId}
              </Link>
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Customer</dt>
            <dd>
              <Link href={`/customers?id=${pnr.customerId}`} className="text-jp-accent-muted underline">
                {pnr.customerName} ({pnr.customerId})
              </Link>
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Agent</dt>
            <dd>
              {pnr.agentId ? (
                <Link href={`/agents?id=${pnr.agentId}`} className="text-jp-accent-muted underline">
                  {pnr.agentName} ({pnr.agentId})
                </Link>
              ) : (
                "Direct / web"
              )}
            </dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="pnr-travellers-heading">
        <h3 id="pnr-travellers-heading" className="text-sm font-semibold text-gray-900">
          Travellers ({pnr.travellerCount})
        </h3>
        <ul className="mt-2 space-y-1 text-sm">
          {pnr.travellerNames.map((name) => (
            <li key={name} className="rounded bg-gray-50 px-3 py-2">
              {name}
            </li>
          ))}
        </ul>
      </section>

      <Divider />

      <section aria-labelledby="pnr-itinerary-heading">
        <h3 id="pnr-itinerary-heading" className="text-sm font-semibold text-gray-900">
          Itinerary
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Route</dt>
            <dd>{pnr.itinerarySummary}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Trip type</dt>
            <dd className="capitalize">{pnr.tripType.replace("_", " ")}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Departure</dt>
            <dd>{formatDate(pnr.departureDate)}</dd>
          </div>
          {pnr.returnDate ? (
            <div className="flex justify-between gap-4">
              <dt className="text-jp-muted">Return</dt>
              <dd>{formatDate(pnr.returnDate)}</dd>
            </div>
          ) : null}
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="pnr-status-heading">
        <h3 id="pnr-status-heading" className="text-sm font-semibold text-gray-900">
          Lifecycle and fulfilment
        </h3>
        <div className="mt-2 flex flex-wrap gap-2">
          <LifecycleStatusBadge status={pnr.lifecycleStatus} />
          <FulfilmentStatusBadge status={pnr.fulfilmentStatus} />
        </div>
        <dl className="mt-3 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Payment</dt>
            <dd>{pnr.paymentStatus}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Queue / review</dt>
            <dd>{pnr.queueReviewStatus}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="pnr-ticketing-heading">
        <h3 id="pnr-ticketing-heading" className="text-sm font-semibold text-gray-900">
          {isGdsPnr ? "GDS ticketing" : "Ticketing / fulfilment"}
        </h3>
        <div className="mt-2 flex flex-wrap gap-2">
          <PnrTicketingStatusBadge status={pnr.ticketingStatus} />
        </div>
        <dl className="mt-3 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Ticketing deadline</dt>
            <dd>{pnr.ticketingDeadline ? formatDate(pnr.ticketingDeadline) : "—"}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Booking value</dt>
            <dd className="font-semibold tabular-nums">
              {formatCurrency(pnr.bookingValue, pnr.currency)}
            </dd>
          </div>
        </dl>
        {pnr.showGdsTicketingLimitation ? (
          <p
            className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-950"
            data-testid="gds-ticketing-limitation-note"
          >
            {GDS_TICKETING_LIMITATION_NOTE}
          </p>
        ) : null}
        {isNdcOrder ? (
          <p className="mt-3 text-xs text-jp-muted">
            NDC orders use order-document fulfilment — GDS ticketing workflows do not apply.
          </p>
        ) : null}
      </section>

      <Divider />

      <section aria-labelledby="pnr-cancellation-heading">
        <h3 id="pnr-cancellation-heading" className="text-sm font-semibold text-gray-900">
          Cancellation eligibility
        </h3>
        <div className="mt-2">
          <CancellationEligibilityBadge status={pnr.cancellationEligibility} />
        </div>
        <p className="mt-2 text-xs text-jp-muted">
          Display-only fixture status — no cancellation actions are available in this module.
        </p>
      </section>

      <Divider />

      <section aria-labelledby="pnr-tickets-heading">
        <h3 id="pnr-tickets-heading" className="text-sm font-semibold text-gray-900">
          Linked tickets / documents
        </h3>
        {pnr.linkedTicketIds.length > 0 ? (
          <ul className="mt-2 space-y-1 text-sm">
            {pnr.linkedTicketIds.map((ticketId) => (
              <li key={ticketId}>
                <Link href={`/tickets?id=${ticketId}`} className="text-jp-accent-muted underline">
                  {ticketId}
                </Link>
              </li>
            ))}
          </ul>
        ) : (
          <p className="mt-2 text-sm text-jp-muted">No linked tickets or fulfilment documents.</p>
        )}
      </section>

      <Divider />

      <section aria-labelledby="pnr-payments-heading">
        <h3 id="pnr-payments-heading" className="text-sm font-semibold text-gray-900">
          Linked payments
        </h3>
        {pnr.linkedTransactionIds.length > 0 ? (
          <ul className="mt-2 space-y-1 text-sm">
            {pnr.linkedTransactionIds.map((txId) => (
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

      <section aria-labelledby="pnr-activity-heading">
        <h3 id="pnr-activity-heading" className="text-sm font-semibold text-gray-900">
          Activity summary
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Last modified</dt>
            <dd>{formatDate(pnr.lastModifiedDate)}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Last supplier activity</dt>
            <dd>{pnr.lastSupplierActivity ? formatDate(pnr.lastSupplierActivity.slice(0, 10)) : "—"}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="pnr-notes-heading">
        <h3 id="pnr-notes-heading" className="text-sm font-semibold text-gray-900">
          Notes
        </h3>
        <p className="mt-2 text-sm text-gray-700">{pnr.notesSummary}</p>
        <p className="mt-2 text-xs text-jp-muted">
          Read-only preview — no retrieve, sync, cancel, or ticketing actions are available.
        </p>
      </section>
    </div>
  );
}
