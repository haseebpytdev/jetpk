import type {
  PnrRecord,
  PnrsPageResult,
  PnrsQuery,
  PnrsSummaryMetrics,
} from "@/types/pnr";
import { mockPnrs } from "@/mocks/pnr-fixtures";

function matchesSearch(pnr: PnrRecord, q: string): boolean {
  if (!q) return true;
  const needle = q.toLowerCase();
  const haystack = [
    pnr.id,
    pnr.externalReference,
    pnr.referenceType,
    pnr.channel,
    pnr.supplierName,
    pnr.airline,
    pnr.bookingId,
    pnr.customerId,
    pnr.customerName,
    pnr.agentId,
    pnr.agentName,
    pnr.itinerarySummary,
    pnr.origin,
    pnr.destination,
    pnr.notesSummary,
    ...pnr.linkedTicketIds,
    ...pnr.linkedTransactionIds,
    ...pnr.travellerNames,
  ]
    .filter(Boolean)
    .join(" ")
    .toLowerCase();
  return haystack.includes(needle);
}

function inDateRange(value: string | null, from: string, to: string): boolean {
  if (!value) return !from && !to;
  if (from && value < from) return false;
  if (to && value > to) return false;
  return true;
}

function isReviewRequired(pnr: PnrRecord): boolean {
  return pnr.lifecycleStatus === "Review Required" || pnr.queueReviewStatus === "Review Required";
}

const STATUS_PRIORITY: Record<string, number> = {
  Active: 0,
  Confirmed: 1,
  "Partially Confirmed": 2,
  "On Hold": 3,
  "Pending Supplier": 4,
  "Review Required": 5,
  Expired: 6,
  Failed: 7,
  Cancelled: 8,
};

export function filterPnrs(all: PnrRecord[], query: PnrsQuery): PnrRecord[] {
  return all.filter((pnr) => {
    if (!matchesSearch(pnr, query.q)) return false;
    if (query.referenceType !== "all" && pnr.referenceType !== query.referenceType) return false;
    if (query.channel !== "all" && pnr.channel !== query.channel) return false;
    if (query.supplier && pnr.supplierName !== query.supplier) return false;
    if (query.airline && pnr.airline !== query.airline) return false;
    if (query.lifecycleStatus !== "all" && pnr.lifecycleStatus !== query.lifecycleStatus) return false;
    if (query.fulfilmentStatus !== "all" && pnr.fulfilmentStatus !== query.fulfilmentStatus)
      return false;
    if (query.ticketingStatus !== "all" && pnr.ticketingStatus !== query.ticketingStatus) return false;
    if (query.paymentStatus !== "all" && pnr.paymentStatus !== query.paymentStatus) return false;
    if (query.tripType !== "all" && pnr.tripType !== query.tripType) return false;
    if (query.hasAgent === "yes" && !pnr.agentId) return false;
    if (query.hasAgent === "no" && pnr.agentId) return false;
    if (query.reviewRequired === "yes" && !isReviewRequired(pnr)) return false;
    if (query.reviewRequired === "no" && isReviewRequired(pnr)) return false;
    if (!inDateRange(pnr.ticketingDeadline, query.deadlineFrom, query.deadlineTo)) return false;
    if (!inDateRange(pnr.departureDate, query.departureFrom, query.departureTo)) return false;
    return true;
  });
}

function compareStrings(a: string, b: string): number {
  return a.localeCompare(b, "en", { sensitivity: "base" });
}

function compareNullableDates(a: string | null, b: string | null): number {
  if (!a && !b) return 0;
  if (!a) return 1;
  if (!b) return -1;
  return compareStrings(a, b);
}

export function sortPnrs(
  rows: PnrRecord[],
  sort: PnrsQuery["sort"],
  direction: PnrsQuery["direction"],
): PnrRecord[] {
  const sorted = [...rows].sort((a, b) => {
    let cmp = 0;
    switch (sort) {
      case "newest":
        cmp = compareStrings(b.createdDate, a.createdDate);
        break;
      case "oldest":
        cmp = compareStrings(a.createdDate, b.createdDate);
        break;
      case "departureDate":
        cmp = compareStrings(a.departureDate, b.departureDate);
        break;
      case "ticketingDeadline":
        cmp = compareNullableDates(a.ticketingDeadline, b.ticketingDeadline);
        break;
      case "lastActivity":
        cmp = compareNullableDates(a.lastSupplierActivity, b.lastSupplierActivity);
        break;
      case "travellerCount":
        cmp = a.travellerCount - b.travellerCount;
        break;
      case "bookingValue":
        cmp = a.bookingValue - b.bookingValue;
        break;
      case "statusPriority":
        cmp =
          (STATUS_PRIORITY[a.lifecycleStatus] ?? 99) - (STATUS_PRIORITY[b.lifecycleStatus] ?? 99);
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

export function computePnrsSummary(rows: PnrRecord[]): PnrsSummaryMetrics {
  const deadlineCutoff = "2026-09-01";
  let activeRecords = 0;
  let gdsPnrCount = 0;
  let ndcOrderCount = 0;
  let awaitingFulfilment = 0;
  let reviewRequired = 0;
  let approachingDeadline = 0;

  for (const pnr of rows) {
    if (pnr.lifecycleStatus === "Active" || pnr.lifecycleStatus === "Confirmed") activeRecords += 1;
    if (pnr.referenceType === "GDS PNR") gdsPnrCount += 1;
    if (pnr.referenceType === "NDC Order") ndcOrderCount += 1;
    if (pnr.fulfilmentStatus === "Pending" || pnr.fulfilmentStatus === "Partially Fulfilled")
      awaitingFulfilment += 1;
    if (isReviewRequired(pnr)) reviewRequired += 1;
    if (pnr.ticketingDeadline && pnr.ticketingDeadline <= deadlineCutoff) approachingDeadline += 1;
  }

  return {
    totalRecords: rows.length,
    activeRecords,
    gdsPnrCount,
    ndcOrderCount,
    awaitingFulfilment,
    reviewRequired,
    approachingDeadline,
    currency: "PKR",
  };
}

export function paginatePnrs(
  rows: PnrRecord[],
  page: number,
  pageSize: number,
): { page: number; pageCount: number; slice: PnrRecord[] } {
  const pageCount = Math.max(1, Math.ceil(rows.length / pageSize));
  const clampedPage = Math.min(Math.max(1, page), pageCount);
  const start = (clampedPage - 1) * pageSize;
  return {
    page: clampedPage,
    pageCount,
    slice: rows.slice(start, start + pageSize),
  };
}

export function buildPnrsPage(query: PnrsQuery, all: PnrRecord[] = mockPnrs): PnrsPageResult {
  const filtered = filterPnrs(all, query);
  const sorted = sortPnrs(filtered, query.sort, query.direction);
  const { page, pageCount, slice } = paginatePnrs(sorted, query.page, query.pageSize);
  const suppliers = [...new Set(all.map((p) => p.supplierName))].sort();
  const airlines = [...new Set(all.map((p) => p.airline))].sort();
  const referenceTypes = [...new Set(all.map((p) => p.referenceType))].sort();
  const channels = [...new Set(all.map((p) => p.channel))].sort();

  return {
    pnrs: slice,
    total: filtered.length,
    page,
    pageSize: query.pageSize,
    pageCount,
    summary: computePnrsSummary(filtered),
    facets: { suppliers, airlines, referenceTypes, channels },
  };
}

export function countActivePnrFilters(query: PnrsQuery): number {
  let n = 0;
  if (query.q) n += 1;
  if (query.referenceType !== "all") n += 1;
  if (query.channel !== "all") n += 1;
  if (query.supplier) n += 1;
  if (query.airline) n += 1;
  if (query.lifecycleStatus !== "all") n += 1;
  if (query.fulfilmentStatus !== "all") n += 1;
  if (query.ticketingStatus !== "all") n += 1;
  if (query.paymentStatus !== "all") n += 1;
  if (query.tripType !== "all") n += 1;
  if (query.hasAgent !== "all") n += 1;
  if (query.reviewRequired !== "all") n += 1;
  if (query.deadlineFrom || query.deadlineTo) n += 1;
  if (query.departureFrom || query.departureTo) n += 1;
  return n;
}
