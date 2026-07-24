import type {
  BookingSortField,
  BookingsQuery,
  BookingStatus,
  PaymentStatus,
  SortDirection,
  TicketingStatus,
  TripType,
} from "@/types/booking";

const DEFAULT_PAGE_SIZE = 20;

const BOOKING_STATUSES: BookingStatus[] = ["confirmed", "pending", "failed", "cancelled"];
const PAYMENT_STATUSES: PaymentStatus[] = ["paid", "unpaid", "partial", "pending"];
const TICKETING_STATUSES: TicketingStatus[] = ["ticketed", "unticketed", "pending"];
const TRIP_TYPES: TripType[] = ["one_way", "return"];
const SORT_FIELDS: BookingSortField[] = [
  "bookingDate",
  "departureDate",
  "customer",
  "route",
  "amount",
  "status",
  "lastUpdated",
];

function first(value: string | string[] | undefined): string {
  if (Array.isArray(value)) {
    return value[0] ?? "";
  }
  return value ?? "";
}

function parseEnum<T extends string>(raw: string, allowed: readonly T[], fallback: T | "all"): T | "all" {
  if (!raw || raw === "all") {
    return fallback;
  }
  return (allowed as readonly string[]).includes(raw) ? (raw as T) : fallback;
}

function parsePositiveInt(raw: string, fallback: number, max?: number): number {
  const n = Number.parseInt(raw, 10);
  if (!Number.isFinite(n) || n < 1) {
    return fallback;
  }
  if (max !== undefined && n > max) {
    return max;
  }
  return n;
}

function parsePageSize(raw: string): number {
  const n = parsePositiveInt(raw, DEFAULT_PAGE_SIZE);
  if (n === 10 || n === 20 || n === 50) {
    return n;
  }
  return DEFAULT_PAGE_SIZE;
}

export function parseBookingsQuery(
  searchParams: Record<string, string | string[] | undefined>,
): BookingsQuery {
  const sortRaw = first(searchParams.sort);
  const directionRaw = first(searchParams.direction);

  return {
    q: first(searchParams.q).trim(),
    status: parseEnum(first(searchParams.status), BOOKING_STATUSES, "all"),
    payment: parseEnum(first(searchParams.payment), PAYMENT_STATUSES, "all"),
    ticketing: parseEnum(first(searchParams.ticketing), TICKETING_STATUSES, "all"),
    supplier: first(searchParams.supplier),
    airline: first(searchParams.airline),
    tripType: parseEnum(first(searchParams.tripType), TRIP_TYPES, "all"),
    bookingDateFrom: first(searchParams.bookingDateFrom),
    bookingDateTo: first(searchParams.bookingDateTo),
    departureDateFrom: first(searchParams.departureDateFrom),
    departureDateTo: first(searchParams.departureDateTo),
    page: parsePositiveInt(first(searchParams.page), 1),
    pageSize: parsePageSize(first(searchParams.pageSize)),
    sort: SORT_FIELDS.includes(sortRaw as BookingSortField)
      ? (sortRaw as BookingSortField)
      : "bookingDate",
    direction: directionRaw === "asc" || directionRaw === "desc" ? directionRaw : "desc",
    selectedId: first(searchParams.id) || null,
    previewError: first(searchParams.previewError) === "1",
  };
}

export function bookingsQueryToSearchParams(query: BookingsQuery, overrides?: Partial<BookingsQuery>): string {
  const merged = { ...query, ...overrides };
  const params = new URLSearchParams();

  if (merged.q) params.set("q", merged.q);
  if (merged.status !== "all") params.set("status", merged.status);
  if (merged.payment !== "all") params.set("payment", merged.payment);
  if (merged.ticketing !== "all") params.set("ticketing", merged.ticketing);
  if (merged.supplier) params.set("supplier", merged.supplier);
  if (merged.airline) params.set("airline", merged.airline);
  if (merged.tripType !== "all") params.set("tripType", merged.tripType);
  if (merged.bookingDateFrom) params.set("bookingDateFrom", merged.bookingDateFrom);
  if (merged.bookingDateTo) params.set("bookingDateTo", merged.bookingDateTo);
  if (merged.departureDateFrom) params.set("departureDateFrom", merged.departureDateFrom);
  if (merged.departureDateTo) params.set("departureDateTo", merged.departureDateTo);
  if (merged.page > 1) params.set("page", String(merged.page));
  if (merged.pageSize !== DEFAULT_PAGE_SIZE) params.set("pageSize", String(merged.pageSize));
  if (merged.sort !== "bookingDate") params.set("sort", merged.sort);
  if (merged.direction !== "desc") params.set("direction", merged.direction);
  if (merged.selectedId) params.set("id", merged.selectedId);
  if (merged.previewError) params.set("previewError", "1");

  const s = params.toString();
  return s ? `?${s}` : "";
}

export function defaultBookingsQuery(): BookingsQuery {
  return parseBookingsQuery({});
}

export type { SortDirection };
