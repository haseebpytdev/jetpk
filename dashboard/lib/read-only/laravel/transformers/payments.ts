import type { PaymentsPageResult, TransactionRecord } from "@/types/payment";
import type { LaravelPaymentsListPayload } from "@/lib/read-only/laravel/types";

export function transformPaymentsPage(
  payload: LaravelPaymentsListPayload,
  pagination: { page: number; pageSize: number; total: number; pageCount: number },
): PaymentsPageResult {
  const transactions = payload.transactions as TransactionRecord[];
  const currencies = [...new Set(transactions.map((t) => t.currency))];
  const methods = [...new Set(transactions.map((t) => t.paymentMethod))];
  const channels = [...new Set(transactions.map((t) => t.paymentChannel))];

  let grossCollected = 0;
  let feeTotal = 0;
  let refundedAmount = 0;
  let failedOrPendingCount = 0;
  let unreconciledCount = 0;
  let outstandingAmount = 0;

  for (const tx of transactions) {
    if (tx.transactionType === "payment" && tx.transactionStatus === "succeeded") {
      grossCollected += tx.grossAmount;
      feeTotal += tx.feeAmount;
      outstandingAmount += tx.outstandingAmount;
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

  return {
    transactions,
    total: pagination.total,
    page: pagination.page,
    pageSize: pagination.pageSize,
    pageCount: pagination.pageCount,
    summary: {
      totalTransactions: transactions.length,
      grossCollected,
      netCollected: grossCollected - feeTotal,
      outstandingAmount,
      refundedAmount,
      failedOrPendingCount,
      unreconciledCount,
      currency: payload.summary.currency,
    },
    facets: {
      currencies,
      methods,
      channels,
    },
  };
}

export function transformPaymentDetail(payload: TransactionRecord): TransactionRecord {
  return payload;
}
