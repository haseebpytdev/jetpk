"use client";

import { Button } from "@/components/ui/button";
import {
  BookingStatusBadge,
  PaymentStatusBadge,
  TicketingStatusBadge,
} from "@/components/ui/status-badge";
import { Table, TableBody, TableHead, TableRow, Td, Th } from "@/components/ui/table";
import { formatCurrency, formatDate, formatDateTime } from "@/lib/format";
import type { BookingRecord, BookingSortField, BookingsQuery } from "@/types/booking";

type Props = {
  bookings: BookingRecord[];
  query: BookingsQuery;
  onSort: (field: BookingSortField) => void;
  onView: (id: string) => void;
};

function sortIndicator(active: boolean, direction: BookingsQuery["direction"]) {
  if (!active) return " ↕";
  return direction === "asc" ? " ↑" : " ↓";
}

export function BookingsTable({ bookings, query, onSort, onView }: Props) {
  return (
    <div className="hidden md:block" data-testid="bookings-table">
      <Table>
        <TableHead>
          <TableRow>
            <Th scope="col">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("bookingDate")}
              >
                Booking / PNR{sortIndicator(query.sort === "bookingDate", query.direction)}
              </button>
            </Th>
            <Th scope="col">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("customer")}
              >
                Customer{sortIndicator(query.sort === "customer", query.direction)}
              </button>
            </Th>
            <Th scope="col">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("route")}
              >
                Route{sortIndicator(query.sort === "route", query.direction)}
              </button>
            </Th>
            <Th scope="col">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("departureDate")}
              >
                Travel{sortIndicator(query.sort === "departureDate", query.direction)}
              </button>
            </Th>
            <Th scope="col">Airline / Supplier</Th>
            <Th scope="col" className="text-right">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("amount")}
              >
                Amount{sortIndicator(query.sort === "amount", query.direction)}
              </button>
            </Th>
            <Th scope="col">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("status")}
              >
                Status{sortIndicator(query.sort === "status", query.direction)}
              </button>
            </Th>
            <Th scope="col">Payment</Th>
            <Th scope="col">Ticketing</Th>
            <Th scope="col">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("lastUpdated")}
              >
                Updated{sortIndicator(query.sort === "lastUpdated", query.direction)}
              </button>
            </Th>
            <Th scope="col" className="w-24">
              <span className="sr-only">Actions</span>
            </Th>
          </TableRow>
        </TableHead>
        <TableBody>
          {bookings.map((b) => (
            <TableRow key={b.id}>
              <Td>
                <div className="font-medium text-gray-900">{b.id}</div>
                <div className="text-xs text-jp-muted">PNR {b.pnr}</div>
              </Td>
              <Td>
                <div>{b.customerName}</div>
                <div className="text-xs text-jp-muted">{b.customerEmail}</div>
              </Td>
              <Td>
                {b.origin} → {b.destination}
                <div className="text-xs text-jp-muted">{b.passengerCount} pax</div>
              </Td>
              <Td>{formatDate(b.departureDate)}</Td>
              <Td>
                <div>{b.airline}</div>
                <div className="text-xs text-jp-muted">{b.supplier}</div>
              </Td>
              <Td className="text-right tabular-nums font-medium">
                {formatCurrency(b.totalAmount, b.currency)}
              </Td>
              <Td>
                <BookingStatusBadge status={b.bookingStatus} />
              </Td>
              <Td>
                <PaymentStatusBadge status={b.paymentStatus} />
              </Td>
              <Td>
                <TicketingStatusBadge status={b.ticketingStatus} />
              </Td>
              <Td className="text-xs text-jp-muted">{formatDateTime(b.lastUpdated)}</Td>
              <Td>
                <Button variant="secondary" size="sm" onClick={() => onView(b.id)}>
                  View
                </Button>
              </Td>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}
