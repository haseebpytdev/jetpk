import type { PaymentsPageResult, PaymentsQuery, TransactionRecord } from "@/types/payment";
import { buildPaymentsPage } from "@/lib/payments-filter";
import { getTransactionById, mockTransactions } from "@/mocks/payment-fixtures";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { createReadOnlyService, ReadOnlyServiceError, type ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { transformPaymentDetail, transformPaymentsPage } from "@/lib/read-only/laravel/transformers/payments";
import type { LaravelPaymentsListPayload } from "@/lib/read-only/laravel/types";

export class PaymentsServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "PaymentsServiceError";
    this.referenceId = referenceId;
  }
}

function mapReadOnlyError(error: unknown): never {
  if (error instanceof ReadOnlyServiceError) {
    throw new PaymentsServiceError(error.envelope.error.message, error.envelope.error.referenceIdSafe);
  }
  throw error;
}

function toLaravelQuery(query: PaymentsQuery): Record<string, string | number> {
  return {
    page: query.page,
    pageSize: query.pageSize,
    q: query.q,
    paymentStatus: query.paymentStatus,
    transactionStatus: query.transactionStatus,
    type: query.type,
    method: query.method,
    channel: query.channel,
    reconciliation: query.reconciliation,
    currency: query.currency,
    dateFrom: query.dateFrom,
    dateTo: query.dateTo,
    sort: query.sort,
    direction: query.direction,
  };
}

const paymentsService = createReadOnlyService<PaymentsQuery, PaymentsPageResult>({
  module: "payments",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(query, options) {
      if (query.previewError) {
        throw new ReadOnlyServiceError({
          error: {
            code: "internal_error",
            message: "Mock payment service returned a recoverable error (preview simulation).",
            referenceIdSafe: "PAY-PREVIEW-SIM-ERR",
          },
          meta: { source: "fixture", schemaVersion: "dash-read-only-v1" },
        });
      }
      await new Promise((r) => setTimeout(r, 80));
      return createReadOnlyEnvelope({ data: buildPaymentsPage(query, mockTransactions), metadata: options?.metadata });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(query, options) {
      const envelope = await fetchDashboardApi<LaravelPaymentsListPayload>(DASHBOARD_API_ROUTES.payments, {
        signal: options?.signal,
        query: toLaravelQuery(query),
      });
      const pagination = envelope.pagination ?? { page: 1, pageSize: 25, total: 0, pageCount: 1 };
      return { ...envelope, data: transformPaymentsPage(envelope.data, pagination) };
    },
  },
});

export async function getPaymentsPage(query: PaymentsQuery, options?: ReadOnlyFetchOptions): Promise<PaymentsPageResult> {
  try {
    const envelope = await paymentsService.fetchReadOnly(query, options);
    return envelope.data;
  } catch (error) {
    mapReadOnlyError(error);
  }
}

export async function getTransactionDetail(id: string, options?: ReadOnlyFetchOptions): Promise<TransactionRecord | null> {
  const { resolveDataSourceMode } = await import("@/lib/read-only/data-source");
  const mode = resolveDataSourceMode();

  if (mode === "fixture") {
    await new Promise((r) => setTimeout(r, 40));
    return getTransactionById(id) ?? null;
  }

  try {
    const envelope = await fetchDashboardApi<TransactionRecord>(DASHBOARD_API_ROUTES.paymentDetail(id), {
      signal: options?.signal,
    });
    return transformPaymentDetail(envelope.data);
  } catch (error) {
    if (error instanceof ReadOnlyServiceError && error.envelope.error.code === "not_found") {
      return null;
    }
    mapReadOnlyError(error);
  }
}

export function listAllMockTransactions(): TransactionRecord[] {
  return mockTransactions;
}
