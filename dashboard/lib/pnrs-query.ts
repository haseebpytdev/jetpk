import type {
  PnrChannel,
  PnrFulfilmentStatus,
  PnrLifecycleStatus,
  PnrPaymentStatus,
  PnrReferenceType,
  PnrSortField,
  PnrsQuery,
  PnrTicketingStatus,
  SortDirection,
} from "@/types/pnr";
import type { TripType } from "@/types/booking";

const DEFAULT_PAGE_SIZE = 20;

const REFERENCE_TYPES: PnrReferenceType[] = [
  "GDS PNR",
  "NDC Order",
  "One API Order",
  "Manual Reference",
];
const CHANNELS: PnrChannel[] = ["Sabre GDS", "Sabre NDC", "One API", "Manual", "Mock"];
const LIFECYCLE_STATUSES: PnrLifecycleStatus[] = [
  "Active",
  "Confirmed",
  "On Hold",
  "Pending Supplier",
  "Partially Confirmed",
  "Cancelled",
  "Expired",
  "Failed",
  "Review Required",
];
const FULFILMENT_STATUSES: PnrFulfilmentStatus[] = [
  "Not Required",
  "Pending",
  "Partially Fulfilled",
  "Fulfilled",
  "Failed",
  "Refunded",
];
const TICKETING_STATUSES: PnrTicketingStatus[] = [
  "Not Ticketed",
  "Ready for Ticketing",
  "Ticketing Blocked",
  "Partially Ticketed",
  "Ticketed",
  "Failed",
  "Voided",
  "Refunded",
  "Not Applicable",
];
const PAYMENT_STATUSES: PnrPaymentStatus[] = [
  "Unpaid",
  "Partially Paid",
  "Paid",
  "Pending",
  "Refunded",
];
const TRIP_TYPES: TripType[] = ["one_way", "return"];
const SORT_FIELDS: PnrSortField[] = [
  "newest",
  "oldest",
  "departureDate",
  "ticketingDeadline",
  "lastActivity",
  "travellerCount",
  "bookingValue",
  "statusPriority",
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

function parseYesNo(raw: string): "all" | "yes" | "no" {
  if (raw === "yes" || raw === "no") return raw;
  return "all";
}

export function parsePnrsQuery(
  searchParams: Record<string, string | string[] | undefined>,
): PnrsQuery {
  const sortRaw = first(searchParams.sort);
  const directionRaw = first(searchParams.direction);

  return {
    q: first(searchParams.q).trim(),
    referenceType: parseEnum(first(searchParams.referenceType), REFERENCE_TYPES, "all"),
    channel: parseEnum(first(searchParams.channel), CHANNELS, "all"),
    supplier: first(searchParams.supplier),
    airline: first(searchParams.airline),
    lifecycleStatus: parseEnum(first(searchParams.lifecycleStatus), LIFECYCLE_STATUSES, "all"),
    fulfilmentStatus: parseEnum(first(searchParams.fulfilmentStatus), FULFILMENT_STATUSES, "all"),
    ticketingStatus: parseEnum(first(searchParams.ticketingStatus), TICKETING_STATUSES, "all"),
    paymentStatus: parseEnum(first(searchParams.paymentStatus), PAYMENT_STATUSES, "all"),
    tripType: parseEnum(first(searchParams.tripType), TRIP_TYPES, "all"),
    hasAgent: parseYesNo(first(searchParams.hasAgent)),
    reviewRequired: parseYesNo(first(searchParams.reviewRequired)),
    deadlineFrom: first(searchParams.deadlineFrom),
    deadlineTo: first(searchParams.deadlineTo),
    departureFrom: first(searchParams.departureFrom),
    departureTo: first(searchParams.departureTo),
    page: parsePositiveInt(first(searchParams.page), 1),
    pageSize: parsePageSize(first(searchParams.pageSize)),
    sort: SORT_FIELDS.includes(sortRaw as PnrSortField) ? (sortRaw as PnrSortField) : "newest",
    direction: directionRaw === "asc" || directionRaw === "desc" ? directionRaw : "desc",
    selectedId: first(searchParams.id) || null,
    previewError: first(searchParams.previewError) === "1",
    previewLoading: first(searchParams.previewLoading) === "1",
  };
}

export function pnrsQueryToSearchParams(
  query: PnrsQuery,
  overrides?: Partial<PnrsQuery>,
): string {
  const merged = { ...query, ...overrides };
  const params = new URLSearchParams();

  if (merged.q) params.set("q", merged.q);
  if (merged.referenceType !== "all") params.set("referenceType", merged.referenceType);
  if (merged.channel !== "all") params.set("channel", merged.channel);
  if (merged.supplier) params.set("supplier", merged.supplier);
  if (merged.airline) params.set("airline", merged.airline);
  if (merged.lifecycleStatus !== "all") params.set("lifecycleStatus", merged.lifecycleStatus);
  if (merged.fulfilmentStatus !== "all") params.set("fulfilmentStatus", merged.fulfilmentStatus);
  if (merged.ticketingStatus !== "all") params.set("ticketingStatus", merged.ticketingStatus);
  if (merged.paymentStatus !== "all") params.set("paymentStatus", merged.paymentStatus);
  if (merged.tripType !== "all") params.set("tripType", merged.tripType);
  if (merged.hasAgent !== "all") params.set("hasAgent", merged.hasAgent);
  if (merged.reviewRequired !== "all") params.set("reviewRequired", merged.reviewRequired);
  if (merged.deadlineFrom) params.set("deadlineFrom", merged.deadlineFrom);
  if (merged.deadlineTo) params.set("deadlineTo", merged.deadlineTo);
  if (merged.departureFrom) params.set("departureFrom", merged.departureFrom);
  if (merged.departureTo) params.set("departureTo", merged.departureTo);
  if (merged.page > 1) params.set("page", String(merged.page));
  if (merged.pageSize !== DEFAULT_PAGE_SIZE) params.set("pageSize", String(merged.pageSize));
  if (merged.sort !== "newest") params.set("sort", merged.sort);
  if (merged.direction !== "desc") params.set("direction", merged.direction);
  if (merged.selectedId) params.set("id", merged.selectedId);
  if (merged.previewError) params.set("previewError", "1");
  if (merged.previewLoading) params.set("previewLoading", "1");

  const s = params.toString();
  return s ? `?${s}` : "";
}

export function defaultPnrsQuery(): PnrsQuery {
  return parsePnrsQuery({});
}

export type { SortDirection };
