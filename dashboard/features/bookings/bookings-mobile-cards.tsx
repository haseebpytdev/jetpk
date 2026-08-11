"use client";

import { DashboardLink as Link } from "@/components/dashboard/dashboard-link";
import { Card } from "@/components/ui/card";
import {
  BookingStatusBadge,
  PaymentStatusBadge,
  TicketingStatusBadge,
} from "@/components/ui/status-badge";
import { bookingsQueryToSearchParams } from "@/lib/bookings-query";
import { formatDate } from "@/lib/format";
import { formatMoneyDisplay } from "@/lib/money";
import type { BookingRecord, BookingsQuery } from "@/types/booking";

const viewLinkClassName =
  "inline-flex min-h-11 w-full items-center justify-center rounded-xl font-medium transition-colors duration-ui focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent border border-jp-border bg-white px-3 py-2 text-sm text-gray-900 hover:bg-gray-50";

type Props = {
  bookings: BookingRecord[];
  query: BookingsQuery;
};

export function BookingsMobileCards({ bookings, query }: Props) {
  return (
    <ul className="space-y-3 xl:hidden" data-testid="bookings-mobile-cards">
      {bookings.map((b) => (
        <li key={b.id}>
          <Card className="space-y-3">
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0">
                <p className="truncate font-semibold text-gray-900">{b.id}</p>
                <p className="text-xs text-jp-muted">PNR {b.pnr}</p>
              </div>
              <p className="shrink-0 font-semibold tabular-nums">
                {formatMoneyDisplay(b.totalAmount, b.currency, b.currencyStatus)}
              </p>
            </div>
            <p className="text-sm text-gray-800">{b.customerName}</p>
            <p className="text-sm">
              {b.origin} → {b.destination} · {formatDate(b.departureDate)}
            </p>
            <div className="flex flex-wrap gap-2">
              <BookingStatusBadge status={b.bookingStatus} />
              <PaymentStatusBadge status={b.paymentStatus} />
              <TicketingStatusBadge status={b.ticketingStatus} />
            </div>
            <Link
              href={`/bookings/${encodeURIComponent(b.id)}`}
              className={viewLinkClassName}
              aria-label={`Manage booking ${b.id}`}
              data-testid="booking-manage-button"
            >
              Manage
            </Link>
          </Card>
        </li>
      ))}
    </ul>
  );
}
