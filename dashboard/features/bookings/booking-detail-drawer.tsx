"use client";

import { Divider } from "@/components/ui/divider";
import { PreviewDataBanner } from "@/components/ui/page-layout";
import {
  BookingStatusBadge,
  PaymentStatusBadge,
  TicketingStatusBadge,
} from "@/components/ui/status-badge";
import { formatCurrency, formatDate, formatDateTime, tripTypeLabel } from "@/lib/format";
import type { BookingRecord } from "@/types/booking";

export function BookingDetailDrawerContent({ booking }: { booking: BookingRecord }) {
  return (
    <div className="space-y-5" data-testid="booking-drawer-content">
      <PreviewDataBanner className="text-xs" />

      <section aria-labelledby="booking-id-heading">
        <h3 id="booking-id-heading" className="text-sm font-semibold text-gray-900">
          Identification
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Booking ID</dt>
            <dd className="font-medium">{booking.id}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">PNR</dt>
            <dd>{booking.pnr}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Supplier ref</dt>
            <dd>{booking.supplierReference ?? "—"}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Booked</dt>
            <dd>{formatDate(booking.bookingDate)}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Last updated</dt>
            <dd>{formatDateTime(booking.lastUpdated)}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="customer-heading">
        <h3 id="customer-heading" className="text-sm font-semibold text-gray-900">
          Customer
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div>
            <dt className="text-jp-muted">Name</dt>
            <dd>{booking.customerName}</dd>
          </div>
          <div>
            <dt className="text-jp-muted">Email</dt>
            <dd>
              <a className="text-jp-accent-muted underline" href={`mailto:${booking.customerEmail}`}>
                {booking.customerEmail}
              </a>
            </dd>
          </div>
          <div>
            <dt className="text-jp-muted">Phone</dt>
            <dd>{booking.customerPhone}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="itinerary-heading">
        <h3 id="itinerary-heading" className="text-sm font-semibold text-gray-900">
          Itinerary
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Route</dt>
            <dd>
              {booking.origin} → {booking.destination}
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Trip</dt>
            <dd>{tripTypeLabel(booking.tripType)}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Departure</dt>
            <dd>{formatDate(booking.departureDate)}</dd>
          </div>
          {booking.returnDate ? (
            <div className="flex justify-between gap-4">
              <dt className="text-jp-muted">Return</dt>
              <dd>{formatDate(booking.returnDate)}</dd>
            </div>
          ) : null}
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Passengers</dt>
            <dd>{booking.passengerCount}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Airline</dt>
            <dd>{booking.airline}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Supplier</dt>
            <dd>{booking.supplier}</dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="financial-heading">
        <h3 id="financial-heading" className="text-sm font-semibold text-gray-900">
          Financial
        </h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Total</dt>
            <dd className="font-semibold tabular-nums">
              {formatCurrency(booking.totalAmount, booking.currency)}
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Paid</dt>
            <dd className="tabular-nums">{formatCurrency(booking.amountPaid, booking.currency)}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Balance</dt>
            <dd className="tabular-nums">
              {formatCurrency(Math.max(0, booking.totalAmount - booking.amountPaid), booking.currency)}
            </dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="status-heading">
        <h3 id="status-heading" className="text-sm font-semibold text-gray-900">
          Status
        </h3>
        <div className="mt-2 flex flex-wrap gap-2">
          <BookingStatusBadge status={booking.bookingStatus} />
          <PaymentStatusBadge status={booking.paymentStatus} />
          <TicketingStatusBadge status={booking.ticketingStatus} />
        </div>
        <p className="mt-3 text-sm text-jp-muted">Source: {booking.agentOrSource}</p>
      </section>
    </div>
  );
}
