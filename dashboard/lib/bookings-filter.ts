import type {
  BookingRecord,
  BookingsPageResult,
  BookingsQuery,
  BookingsSummaryMetrics,
} from "@/types/booking";
import { mockBookings } from "@/mocks/booking-fixtures";

function matchesSearch(booking: BookingRecord, q: string): boolean {
  if (!q) {
    return true;
  }
  const needle = q.toLowerCase();
  const haystack = [
    booking.id,
    booking.pnr,
    booking.customerName,
    booking.customerEmail,
    booking.customerPhone,
    `${booking.origin}-${booking.destination}`,
    booking.origin,
    booking.destination,
    booking.airline,
    booking.supplierReference ?? "",
  ]
    .join(" ")
    .toLowerCase();
  return haystack.includes(needle);
}

function inDateRange(value: string, from: string, to: string): boolean {
  if (from && value < from) {
    return false;
  }
  if (to && value > to) {
    return false;
  }
  return true;
}

export function filterBookings(all: BookingRecord[], query: BookingsQuery): BookingRecord[] {
  return all.filter((b) => {
    if (!matchesSearch(b, query.q)) return false;
    if (query.status !== "all" && b.bookingStatus !== query.status) return false;
    if (query.payment !== "all" && b.paymentStatus !== query.payment) return false;
    if (query.ticketing !== "all" && b.ticketingStatus !== query.ticketing) return false;
    if (query.supplier && b.supplier !== query.supplier) return false;
    if (query.airline && b.airline !== query.airline) return false;
    if (query.tripType !== "all" && b.tripType !== query.tripType) return false;
    if (!inDateRange(b.bookingDate, query.bookingDateFrom, query.bookingDateTo)) return false;
    if (!inDateRange(b.departureDate, query.departureDateFrom, query.departureDateTo)) return false;
    return true;
  });
}

function compareStrings(a: string, b: string): number {
  return a.localeCompare(b, "en", { sensitivity: "base" });
}

export function sortBookings(
  rows: BookingRecord[],
  sort: BookingsQuery["sort"],
  direction: BookingsQuery["direction"],
): BookingRecord[] {
  const sorted = [...rows].sort((a, b) => {
    let cmp = 0;
    switch (sort) {
      case "bookingDate":
        cmp = compareStrings(a.bookingDate, b.bookingDate);
        break;
      case "departureDate":
        cmp = compareStrings(a.departureDate, b.departureDate);
        break;
      case "customer":
        cmp = compareStrings(a.customerName, b.customerName);
        break;
      case "route":
        cmp = compareStrings(`${a.origin}${a.destination}`, `${b.origin}${b.destination}`);
        break;
      case "amount":
        cmp = a.totalAmount - b.totalAmount;
        break;
      case "status":
        cmp = compareStrings(a.bookingStatus, b.bookingStatus);
        break;
      case "lastUpdated":
        cmp = compareStrings(a.lastUpdated, b.lastUpdated);
        break;
      default:
        cmp = 0;
    }
    if (cmp === 0) {
      cmp = compareStrings(a.id, b.id);
    }
    return direction === "asc" ? cmp : -cmp;
  });
  return sorted;
}

export function computeSummary(rows: BookingRecord[]): BookingsSummaryMetrics {
  let confirmed = 0;
  let pending = 0;
  let cancelledOrFailed = 0;
  let paid = 0;
  let outstandingAmount = 0;

  for (const b of rows) {
    if (b.bookingStatus === "confirmed") confirmed += 1;
    if (b.bookingStatus === "pending") pending += 1;
    if (b.bookingStatus === "cancelled" || b.bookingStatus === "failed") cancelledOrFailed += 1;
    if (b.paymentStatus === "paid") paid += 1;
    outstandingAmount += Math.max(0, b.totalAmount - b.amountPaid);
  }

  return {
    totalDisplayed: rows.length,
    confirmed,
    pending,
    cancelledOrFailed,
    paid,
    outstandingAmount,
    currency: "PKR",
  };
}

export function paginateBookings(
  rows: BookingRecord[],
  page: number,
  pageSize: number,
): { page: number; pageCount: number; slice: BookingRecord[] } {
  const pageCount = Math.max(1, Math.ceil(rows.length / pageSize));
  const clampedPage = Math.min(Math.max(1, page), pageCount);
  const start = (clampedPage - 1) * pageSize;
  return {
    page: clampedPage,
    pageCount,
    slice: rows.slice(start, start + pageSize),
  };
}

export function buildBookingsPage(query: BookingsQuery, all: BookingRecord[] = mockBookings): BookingsPageResult {
  const filtered = filterBookings(all, query);
  const sorted = sortBookings(filtered, query.sort, query.direction);
  const { page, pageCount, slice } = paginateBookings(sorted, query.page, query.pageSize);
  const suppliers = [...new Set(all.map((b) => b.supplier))].sort();
  const airlines = [...new Set(all.map((b) => b.airline))].sort();

  return {
    bookings: slice,
    total: filtered.length,
    page,
    pageSize: query.pageSize,
    pageCount,
    summary: computeSummary(filtered),
    facets: { suppliers, airlines },
  };
}

export function countActiveFilters(query: BookingsQuery): number {
  let n = 0;
  if (query.q) n += 1;
  if (query.status !== "all") n += 1;
  if (query.payment !== "all") n += 1;
  if (query.ticketing !== "all") n += 1;
  if (query.supplier) n += 1;
  if (query.airline) n += 1;
  if (query.tripType !== "all") n += 1;
  if (query.bookingDateFrom || query.bookingDateTo) n += 1;
  if (query.departureDateFrom || query.departureDateTo) n += 1;
  return n;
}
