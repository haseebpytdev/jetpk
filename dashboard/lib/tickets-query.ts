import type {
  DocumentType,
  FulfilmentStatus,
  IssueStatus,
  RefundEligibility,
  SortDirection,
  TicketChannel,
  TicketPaymentStatus,
  TicketsQuery,
  TicketSortField,
  VoidStatus,
} from "@/types/ticket";

const DEFAULT_PAGE_SIZE = 20;

const DOCUMENT_TYPES: DocumentType[] = [
  "E-Ticket",
  "NDC Fulfilment Document",
  "EMD",
  "Manual Ticket Record",
  "Refund Document",
  "Void Record",
];

const CHANNELS: TicketChannel[] = ["Sabre GDS", "Sabre NDC", "One API", "Manual", "Mock"];

const ISSUE_STATUSES: IssueStatus[] = [
  "Pending",
  "Issued",
  "Partially Issued",
  "Blocked",
  "Failed",
  "Voided",
  "Refunded",
  "Not Applicable",
];

const FULFILMENT_STATUSES: FulfilmentStatus[] = [
  "Pending",
  "Fulfilled",
  "Partially Fulfilled",
  "Failed",
  "Cancelled",
  "Refunded",
];

const PAYMENT_STATUSES: TicketPaymentStatus[] = [
  "Unpaid",
  "Partially Paid",
  "Paid",
  "Refunded",
  "Partially Refunded",
  "Reconciliation Required",
];

const REFUND_ELIGIBILITIES: RefundEligibility[] = [
  "Eligible",
  "Not Eligible",
  "Airline Review Required",
  "Fare Rules Required",
  "Already Refunded",
  "Unknown",
  "Not Applicable",
];

const VOID_STATUSES: VoidStatus[] = [
  "Within Window",
  "Window Expired",
  "Voided",
  "Not Applicable",
  "Unknown",
];

const SORT_FIELDS: TicketSortField[] = [
  "newest",
  "oldest",
  "travelDate",
  "issueDate",
  "totalValue",
  "airline",
  "statusPriority",
  "lastActivity",
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

export function parseTicketsQuery(
  searchParams: Record<string, string | string[] | undefined>,
): TicketsQuery {
  const sortRaw = first(searchParams.sort);
  const directionRaw = first(searchParams.direction);

  return {
    q: first(searchParams.q).trim(),
    documentType: parseEnum(first(searchParams.documentType), DOCUMENT_TYPES, "all"),
    channel: parseEnum(first(searchParams.channel), CHANNELS, "all"),
    airline: first(searchParams.airline),
    supplier: first(searchParams.supplier),
    issueStatus: parseEnum(first(searchParams.issueStatus), ISSUE_STATUSES, "all"),
    fulfilmentStatus: parseEnum(first(searchParams.fulfilmentStatus), FULFILMENT_STATUSES, "all"),
    paymentStatus: parseEnum(first(searchParams.paymentStatus), PAYMENT_STATUSES, "all"),
    refundEligibility: parseEnum(first(searchParams.refundEligibility), REFUND_ELIGIBILITIES, "all"),
    voidStatus: parseEnum(first(searchParams.voidStatus), VOID_STATUSES, "all"),
    hasAgent: parseYesNo(first(searchParams.hasAgent)),
    travelFrom: first(searchParams.travelFrom),
    travelTo: first(searchParams.travelTo),
    issueFrom: first(searchParams.issueFrom),
    issueTo: first(searchParams.issueTo),
    page: parsePositiveInt(first(searchParams.page), 1),
    pageSize: parsePageSize(first(searchParams.pageSize)),
    sort: SORT_FIELDS.includes(sortRaw as TicketSortField)
      ? (sortRaw as TicketSortField)
      : "newest",
    direction: directionRaw === "asc" || directionRaw === "desc" ? directionRaw : "desc",
    selectedId: first(searchParams.id) || null,
    previewError: first(searchParams.previewError) === "1",
    previewLoading: first(searchParams.previewLoading) === "1",
  };
}

export function ticketsQueryToSearchParams(
  query: TicketsQuery,
  overrides?: Partial<TicketsQuery>,
): string {
  const merged = { ...query, ...overrides };
  const params = new URLSearchParams();

  if (merged.q) params.set("q", merged.q);
  if (merged.documentType !== "all") params.set("documentType", merged.documentType);
  if (merged.channel !== "all") params.set("channel", merged.channel);
  if (merged.airline) params.set("airline", merged.airline);
  if (merged.supplier) params.set("supplier", merged.supplier);
  if (merged.issueStatus !== "all") params.set("issueStatus", merged.issueStatus);
  if (merged.fulfilmentStatus !== "all") params.set("fulfilmentStatus", merged.fulfilmentStatus);
  if (merged.paymentStatus !== "all") params.set("paymentStatus", merged.paymentStatus);
  if (merged.refundEligibility !== "all") params.set("refundEligibility", merged.refundEligibility);
  if (merged.voidStatus !== "all") params.set("voidStatus", merged.voidStatus);
  if (merged.hasAgent !== "all") params.set("hasAgent", merged.hasAgent);
  if (merged.travelFrom) params.set("travelFrom", merged.travelFrom);
  if (merged.travelTo) params.set("travelTo", merged.travelTo);
  if (merged.issueFrom) params.set("issueFrom", merged.issueFrom);
  if (merged.issueTo) params.set("issueTo", merged.issueTo);
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

export function defaultTicketsQuery(): TicketsQuery {
  return parseTicketsQuery({});
}

export type { SortDirection };
