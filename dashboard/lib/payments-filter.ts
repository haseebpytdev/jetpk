import type {
  PaymentsPageResult,
  PaymentsQuery,
  PaymentsSummaryMetrics,
  TransactionRecord,
} from "@/types/payment";
import { mockTransactions } from "@/mocks/payment-fixtures";

function matchesSearch(tx: TransactionRecord, q: string): boolean {
  if (!q) {
    return true;
  }
  const needle = q.toLowerCase();
  const haystack = [
    tx.transactionId,
    tx.paymentId,
    tx.bookingId,
    tx.pnr,
    tx.customerName,
    tx.customerEmail,
    tx.customerPhone,
    tx.gatewayReference ?? "",
    tx.bankReference ?? "",
    tx.manualReference ?? "",
    tx.sourceOrAgent,
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

function parseAmount(raw: string): number | null {
  if (!raw.trim()) return null;
  const n = Number.parseFloat(raw);
  return Number.isFinite(n) ? n : null;
}

export function filterTransactions(all: TransactionRecord[], query: PaymentsQuery): TransactionRecord[] {
  const minAmount = parseAmount(query.minAmount);
  const maxAmount = parseAmount(query.maxAmount);

  return all.filter((tx) => {
    if (!matchesSearch(tx, query.q)) return false;
    if (query.paymentStatus !== "all" && tx.paymentStatus !== query.paymentStatus) return false;
    if (query.transactionStatus !== "all" && tx.transactionStatus !== query.transactionStatus) return false;
    if (query.type !== "all" && tx.transactionType !== query.type) return false;
    if (query.method !== "all" && tx.paymentMethod !== query.method) return false;
    if (query.channel !== "all" && tx.paymentChannel !== query.channel) return false;
    if (query.reconciliation !== "all" && tx.reconciliationStatus !== query.reconciliation) return false;
    if (query.currency && tx.currency !== query.currency) return false;
    if (!inDateRange(tx.transactionDate, query.dateFrom, query.dateTo)) return false;
    if (minAmount !== null && tx.grossAmount < minAmount) return false;
    if (maxAmount !== null && tx.grossAmount > maxAmount) return false;
    return true;
  });
}

function compareStrings(a: string, b: string): number {
  return a.localeCompare(b, "en", { sensitivity: "base" });
}

export function sortTransactions(
  rows: TransactionRecord[],
  sort: PaymentsQuery["sort"],
  direction: PaymentsQuery["direction"],
): TransactionRecord[] {
  const sorted = [...rows].sort((a, b) => {
    let cmp = 0;
    switch (sort) {
      case "transactionDate":
        cmp = compareStrings(a.transactionDate, b.transactionDate);
        break;
      case "paymentId":
        cmp = compareStrings(a.paymentId, b.paymentId);
        break;
      case "booking":
        cmp = compareStrings(`${a.bookingId}${a.pnr}`, `${b.bookingId}${b.pnr}`);
        break;
      case "customer":
        cmp = compareStrings(a.customerName, b.customerName);
        break;
      case "grossAmount":
        cmp = a.grossAmount - b.grossAmount;
        break;
      case "netAmount":
        cmp = a.netAmount - b.netAmount;
        break;
      case "outstandingAmount":
        cmp = a.outstandingAmount - b.outstandingAmount;
        break;
      case "paymentStatus":
        cmp = compareStrings(a.paymentStatus, b.paymentStatus);
        break;
      case "reconciliationStatus":
        cmp = compareStrings(a.reconciliationStatus, b.reconciliationStatus);
        break;
      case "lastUpdated":
        cmp = compareStrings(a.updatedAt, b.updatedAt);
        break;
      default:
        cmp = 0;
    }
    if (cmp === 0) {
      cmp = compareStrings(a.transactionId, b.transactionId);
    }
    return direction === "asc" ? cmp : -cmp;
  });
  return sorted;
}

export function computePaymentsSummary(rows: TransactionRecord[]): PaymentsSummaryMetrics {
  let grossCollected = 0;
  let feeTotal = 0;
  let refundedAmount = 0;
  let failedOrPendingCount = 0;
  let unreconciledCount = 0;

  const bookingOutstanding = new Map<string, number>();

  for (const tx of rows) {
    if (tx.transactionType === "payment" && tx.transactionStatus === "succeeded") {
      grossCollected += tx.grossAmount;
      feeTotal += tx.feeAmount;
      bookingOutstanding.set(tx.bookingId, tx.outstandingAmount);
    }
    if (tx.transactionType === "refund" && tx.transactionStatus === "succeeded") {
      refundedAmount += tx.grossAmount;
    }
    if (tx.transactionStatus === "failed" || tx.transactionStatus === "pending") {
      failedOrPendingCount += 1;
    }
    if (tx.reconciliationStatus === "unreconciled" || tx.reconciliationStatus === "pending_review") {
      unreconciledCount += 1;
    }
  }

  let outstandingAmount = 0;
  for (const value of bookingOutstanding.values()) {
    outstandingAmount += value;
  }

  return {
    totalTransactions: rows.length,
    grossCollected,
    netCollected: grossCollected - feeTotal - refundedAmount,
    outstandingAmount,
    refundedAmount,
    failedOrPendingCount,
    unreconciledCount,
    currency: "PKR",
  };
}

export function paginateTransactions(
  rows: TransactionRecord[],
  page: number,
  pageSize: number,
): { page: number; pageCount: number; slice: TransactionRecord[] } {
  const pageCount = Math.max(1, Math.ceil(rows.length / pageSize));
  const clampedPage = Math.min(Math.max(1, page), pageCount);
  const start = (clampedPage - 1) * pageSize;
  return {
    page: clampedPage,
    pageCount,
    slice: rows.slice(start, start + pageSize),
  };
}

export function buildPaymentsPage(
  query: PaymentsQuery,
  all: TransactionRecord[] = mockTransactions,
): PaymentsPageResult {
  const filtered = filterTransactions(all, query);
  const sorted = sortTransactions(filtered, query.sort, query.direction);
  const { page, pageCount, slice } = paginateTransactions(sorted, query.page, query.pageSize);
  const currencies = [...new Set(all.map((t) => t.currency))].sort();
  const methods = [...new Set(all.map((t) => t.paymentMethod))].sort();
  const channels = [...new Set(all.map((t) => t.paymentChannel))].sort();

  return {
    transactions: slice,
    total: filtered.length,
    page,
    pageSize: query.pageSize,
    pageCount,
    summary: computePaymentsSummary(filtered),
    facets: { currencies, methods, channels },
  };
}

export function countActivePaymentFilters(query: PaymentsQuery): number {
  let n = 0;
  if (query.q) n += 1;
  if (query.paymentStatus !== "all") n += 1;
  if (query.transactionStatus !== "all") n += 1;
  if (query.type !== "all") n += 1;
  if (query.method !== "all") n += 1;
  if (query.channel !== "all") n += 1;
  if (query.reconciliation !== "all") n += 1;
  if (query.currency) n += 1;
  if (query.dateFrom || query.dateTo) n += 1;
  if (query.minAmount || query.maxAmount) n += 1;
  return n;
}
