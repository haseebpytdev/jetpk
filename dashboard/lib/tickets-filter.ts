import type {
  TicketRecord,
  TicketsPageResult,
  TicketsQuery,
  TicketsSummaryMetrics,
} from "@/types/ticket";
import { mockTickets } from "@/mocks/ticket-fixtures";

function matchesSearch(ticket: TicketRecord, q: string): boolean {
  if (!q) return true;
  const needle = q.toLowerCase();
  const haystack = [
    ticket.id,
    ticket.maskedExternalId,
    ticket.documentType,
    ticket.channel,
    ticket.airline,
    ticket.supplier,
    ticket.bookingId,
    ticket.pnrOrderId,
    ticket.customerId,
    ticket.travellerName,
    ticket.agentId ?? "",
    ticket.origin,
    ticket.destination,
    ticket.itinerarySummary,
    ticket.notesSummary,
    ...ticket.linkedTransactionIds,
  ]
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

function statusPriority(ticket: TicketRecord): number {
  const issueRank: Record<string, number> = {
    Failed: 0,
    Blocked: 1,
    Pending: 2,
    "Partially Issued": 3,
    Issued: 4,
    Voided: 5,
    Refunded: 6,
    "Not Applicable": 7,
  };
  return issueRank[ticket.issueStatus] ?? 8;
}

export function filterTickets(all: TicketRecord[], query: TicketsQuery): TicketRecord[] {
  return all.filter((ticket) => {
    if (!matchesSearch(ticket, query.q)) return false;
    if (query.documentType !== "all" && ticket.documentType !== query.documentType) return false;
    if (query.channel !== "all" && ticket.channel !== query.channel) return false;
    if (query.airline && ticket.airline !== query.airline) return false;
    if (query.supplier && ticket.supplier !== query.supplier) return false;
    if (query.issueStatus !== "all" && ticket.issueStatus !== query.issueStatus) return false;
    if (query.fulfilmentStatus !== "all" && ticket.fulfilmentStatus !== query.fulfilmentStatus)
      return false;
    if (query.paymentStatus !== "all" && ticket.paymentStatus !== query.paymentStatus) return false;
    if (query.refundEligibility !== "all" && ticket.refundEligibility !== query.refundEligibility)
      return false;
    if (query.voidStatus !== "all" && ticket.voidStatus !== query.voidStatus) return false;
    if (query.hasAgent === "yes" && !ticket.agentId) return false;
    if (query.hasAgent === "no" && ticket.agentId) return false;
    if (!inDateRange(ticket.travelDate, query.travelFrom, query.travelTo)) return false;
    if (!inDateRange(ticket.issueDate, query.issueFrom, query.issueTo)) return false;
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

export function sortTickets(
  rows: TicketRecord[],
  sort: TicketsQuery["sort"],
  direction: TicketsQuery["direction"],
): TicketRecord[] {
  const sorted = [...rows].sort((a, b) => {
    let cmp = 0;
    switch (sort) {
      case "newest":
        cmp = compareStrings(b.createdDate, a.createdDate);
        break;
      case "oldest":
        cmp = compareStrings(a.createdDate, b.createdDate);
        break;
      case "travelDate":
        cmp = compareStrings(a.travelDate, b.travelDate);
        break;
      case "issueDate":
        cmp = compareNullableDates(a.issueDate, b.issueDate);
        break;
      case "totalValue":
        cmp = a.total - b.total;
        break;
      case "airline":
        cmp = compareStrings(a.airline, b.airline);
        break;
      case "statusPriority":
        cmp = statusPriority(a) - statusPriority(b);
        break;
      case "lastActivity":
        cmp = compareStrings(a.lastActivity, b.lastActivity);
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

export function computeTicketsSummary(rows: TicketRecord[]): TicketsSummaryMetrics {
  const upcomingCutoff = "2026-03-01";
  let issued = 0;
  let pending = 0;
  let blockedOrFailed = 0;
  let refunded = 0;
  let totalDocumentValue = 0;
  let upcomingTravel = 0;

  for (const ticket of rows) {
    if (ticket.issueStatus === "Issued" || ticket.issueStatus === "Partially Issued") issued += 1;
    if (ticket.issueStatus === "Pending") pending += 1;
    if (ticket.issueStatus === "Blocked" || ticket.issueStatus === "Failed") blockedOrFailed += 1;
    if (
      ticket.issueStatus === "Refunded" ||
      ticket.refundStatus === "Refunded" ||
      ticket.documentType === "Refund Document"
    ) {
      refunded += 1;
    }
    totalDocumentValue += ticket.total;
    if (ticket.travelDate >= upcomingCutoff) upcomingTravel += 1;
  }

  return {
    totalDocuments: rows.length,
    issued,
    pending,
    blockedOrFailed,
    refunded,
    totalDocumentValue,
    upcomingTravel,
    currency: "PKR",
  };
}

export function paginateTickets(
  rows: TicketRecord[],
  page: number,
  pageSize: number,
): { page: number; pageCount: number; slice: TicketRecord[] } {
  const pageCount = Math.max(1, Math.ceil(rows.length / pageSize));
  const clampedPage = Math.min(Math.max(1, page), pageCount);
  const start = (clampedPage - 1) * pageSize;
  return {
    page: clampedPage,
    pageCount,
    slice: rows.slice(start, start + pageSize),
  };
}

export function buildTicketsPage(
  query: TicketsQuery,
  all: TicketRecord[] = mockTickets,
): TicketsPageResult {
  const filtered = filterTickets(all, query);
  const sorted = sortTickets(filtered, query.sort, query.direction);
  const { page, pageCount, slice } = paginateTickets(sorted, query.page, query.pageSize);
  const airlines = [...new Set(all.map((t) => t.airline))].sort();
  const suppliers = [...new Set(all.map((t) => t.supplier))].sort();
  const documentTypes = [...new Set(all.map((t) => t.documentType))].sort();
  const channels = [...new Set(all.map((t) => t.channel))].sort();

  return {
    tickets: slice,
    total: filtered.length,
    page,
    pageSize: query.pageSize,
    pageCount,
    summary: computeTicketsSummary(filtered),
    facets: { airlines, suppliers, documentTypes, channels },
  };
}

export function countActiveTicketFilters(query: TicketsQuery): number {
  let n = 0;
  if (query.q) n += 1;
  if (query.documentType !== "all") n += 1;
  if (query.channel !== "all") n += 1;
  if (query.airline) n += 1;
  if (query.supplier) n += 1;
  if (query.issueStatus !== "all") n += 1;
  if (query.fulfilmentStatus !== "all") n += 1;
  if (query.paymentStatus !== "all") n += 1;
  if (query.refundEligibility !== "all") n += 1;
  if (query.voidStatus !== "all") n += 1;
  if (query.hasAgent !== "all") n += 1;
  if (query.travelFrom || query.travelTo) n += 1;
  if (query.issueFrom || query.issueTo) n += 1;
  return n;
}
