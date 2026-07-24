import type { PaymentsPageResult, PaymentsQuery, TransactionRecord } from "@/types/payment";
import { buildPaymentsPage } from "@/lib/payments-filter";
import { getTransactionById, mockTransactions } from "@/mocks/payment-fixtures";
import { useMockData } from "@/lib/preview";

export class PaymentsServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "PaymentsServiceError";
    this.referenceId = referenceId;
  }
}

export async function getPaymentsPage(query: PaymentsQuery): Promise<PaymentsPageResult> {
  if (!useMockData()) {
    throw new PaymentsServiceError(
      "Live payment data is disabled in preview.",
      "PAY-PREVIEW-NO-LIVE",
    );
  }

  if (query.previewError) {
    throw new PaymentsServiceError(
      "Mock payment service returned a recoverable error (preview simulation).",
      "PAY-PREVIEW-SIM-ERR",
    );
  }

  await new Promise((r) => setTimeout(r, 80));

  return buildPaymentsPage(query, mockTransactions);
}

export async function getTransactionDetail(id: string): Promise<TransactionRecord | null> {
  if (!useMockData()) {
    return null;
  }
  await new Promise((r) => setTimeout(r, 40));
  return getTransactionById(id) ?? null;
}

export function listAllMockTransactions(): TransactionRecord[] {
  return mockTransactions;
}
