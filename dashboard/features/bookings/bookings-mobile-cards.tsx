"use client";

import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import {
  BookingStatusBadge,
  PaymentStatusBadge,
  TicketingStatusBadge,
} from "@/components/ui/status-badge";
import { formatCurrency, formatDate } from "@/lib/format";
import type { BookingRecord } from "@/types/booking";

type Props = {
  bookings: BookingRecord[];
  onView: (id: string) => void;
};

export function BookingsMobileCards({ bookings, onView }: Props) {
  return (
    <ul className="space-y-3 md:hidden" data-testid="bookings-mobile-cards">
      {bookings.map((b) => (
        <li key={b.id}>
          <Card className="space-y-3">
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0">
                <p className="truncate font-semibold text-gray-900">{b.id}</p>
                <p className="text-xs text-jp-muted">PNR {b.pnr}</p>
              </div>
              <p className="shrink-0 font-semibold tabular-nums">
                {formatCurrency(b.totalAmount, b.currency)}
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
            <Button variant="secondary" size="sm" className="w-full" onClick={() => onView(b.id)}>
              View details
            </Button>
          </Card>
        </li>
      ))}
    </ul>
  );
}
