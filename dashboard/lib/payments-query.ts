import type {
  LedgerPaymentStatus,
  PaymentChannel,
  PaymentMethod,
  PaymentSortField,
  PaymentsQuery,
  ReconciliationState,
  SortDirection,
  TransactionStatus,
  TransactionType,
} from "@/types/payment";

const DEFAULT_PAGE_SIZE = 20;

const PAYMENT_STATUSES: LedgerPaymentStatus[] = [
  "paid",
  "unpaid",
  "partial",
  "pending",
  "failed",
  "reversed",
  "refunded",
  "partially_refunded",
];
const TRANSACTION_STATUSES: TransactionStatus[] = ["succeeded", "failed", "pending", "cancelled"];
const TRANSACTION_TYPES: TransactionType[] = ["payment", "refund", "reversal", "fee", "adjustment"];
const PAYMENT_METHODS: PaymentMethod[] = ["card", "bank_transfer", "cash", "wallet", "office"];
const PAYMENT_CHANNELS: PaymentChannel[] = ["web", "agent", "mobile", "api"];
const RECONCILIATION_STATES: ReconciliationState[] = [
  "reconciled",
  "unreconciled",
  "disputed",
  "pending_review",
];
const SORT_FIELDS: PaymentSortField[] = [
  "transactionDate",
  "paymentId",
  "booking",
  "customer",
  "grossAmount",
  "netAmount",
  "outstandingAmount",
  "paymentStatus",
  "reconciliationStatus",
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

export function parsePaymentsQuery(
  searchParams: Record<string, string | string[] | undefined>,
): PaymentsQuery {
  const sortRaw = first(searchParams.sort);
  const directionRaw = first(searchParams.direction);

  return {
    q: first(searchParams.q).trim(),
    paymentStatus: parseEnum(first(searchParams.paymentStatus), PAYMENT_STATUSES, "all"),
    transactionStatus: parseEnum(first(searchParams.transactionStatus), TRANSACTION_STATUSES, "all"),
    type: parseEnum(first(searchParams.type), TRANSACTION_TYPES, "all"),
    method: parseEnum(first(searchParams.method), PAYMENT_METHODS, "all"),
    channel: parseEnum(first(searchParams.channel), PAYMENT_CHANNELS, "all"),
    reconciliation: parseEnum(first(searchParams.reconciliation), RECONCILIATION_STATES, "all"),
    currency: first(searchParams.currency),
    dateFrom: first(searchParams.dateFrom),
    dateTo: first(searchParams.dateTo),
    minAmount: first(searchParams.minAmount),
    maxAmount: first(searchParams.maxAmount),
    page: parsePositiveInt(first(searchParams.page), 1),
    pageSize: parsePageSize(first(searchParams.pageSize)),
    sort: SORT_FIELDS.includes(sortRaw as PaymentSortField)
      ? (sortRaw as PaymentSortField)
      : "transactionDate",
    direction: directionRaw === "asc" || directionRaw === "desc" ? directionRaw : "desc",
    selectedTransactionId: first(searchParams.transactionId) || null,
    previewError: first(searchParams.previewError) === "1",
  };
}

export function paymentsQueryToSearchParams(
  query: PaymentsQuery,
  overrides?: Partial<PaymentsQuery>,
): string {
  const merged = { ...query, ...overrides };
  const params = new URLSearchParams();

  if (merged.q) params.set("q", merged.q);
  if (merged.paymentStatus !== "all") params.set("paymentStatus", merged.paymentStatus);
  if (merged.transactionStatus !== "all") params.set("transactionStatus", merged.transactionStatus);
  if (merged.type !== "all") params.set("type", merged.type);
  if (merged.method !== "all") params.set("method", merged.method);
  if (merged.channel !== "all") params.set("channel", merged.channel);
  if (merged.reconciliation !== "all") params.set("reconciliation", merged.reconciliation);
  if (merged.currency) params.set("currency", merged.currency);
  if (merged.dateFrom) params.set("dateFrom", merged.dateFrom);
  if (merged.dateTo) params.set("dateTo", merged.dateTo);
  if (merged.minAmount) params.set("minAmount", merged.minAmount);
  if (merged.maxAmount) params.set("maxAmount", merged.maxAmount);
  if (merged.page > 1) params.set("page", String(merged.page));
  if (merged.pageSize !== DEFAULT_PAGE_SIZE) params.set("pageSize", String(merged.pageSize));
  if (merged.sort !== "transactionDate") params.set("sort", merged.sort);
  if (merged.direction !== "desc") params.set("direction", merged.direction);
  if (merged.selectedTransactionId) params.set("transactionId", merged.selectedTransactionId);
  if (merged.previewError) params.set("previewError", "1");

  const s = params.toString();
  return s ? `?${s}` : "";
}

export function defaultPaymentsQuery(): PaymentsQuery {
  return parsePaymentsQuery({});
}

export type { SortDirection };
