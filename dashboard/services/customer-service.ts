import type { CustomersPageResult, CustomersQuery, CustomerRecord } from "@/types/customer";
import { buildCustomersPage } from "@/lib/customers-filter";
import { getCustomerById, mockCustomers } from "@/mocks/customer-fixtures";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { createReadOnlyService, ReadOnlyServiceError, type ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { transformCustomerDetail, transformCustomersPage } from "@/lib/read-only/laravel/transformers/customers";
import type { LaravelCustomersListPayload } from "@/lib/read-only/laravel/types";

export class CustomersServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "CustomersServiceError";
    this.referenceId = referenceId;
  }
}

function mapReadOnlyError(error: unknown): never {
  if (error instanceof ReadOnlyServiceError) {
    throw new CustomersServiceError(error.envelope.error.message, error.envelope.error.referenceIdSafe);
  }
  throw error;
}

function toLaravelQuery(query: CustomersQuery): Record<string, string | number> {
  return {
    page: query.page,
    pageSize: query.pageSize,
    q: query.q,
    accountStatus: query.accountStatus,
    verificationStatus: query.verificationStatus,
    customerType: query.customerType,
    sort: query.sort,
    direction: query.direction,
  };
}

const customersService = createReadOnlyService<CustomersQuery, CustomersPageResult>({
  module: "customers",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(query, options) {
      if (query.previewError) {
        throw new ReadOnlyServiceError({
          error: {
            code: "internal_error",
            message: "Mock customer service returned a recoverable error (preview simulation).",
            referenceIdSafe: "CU-PREVIEW-SIM-ERR",
          },
          meta: { source: "fixture", schemaVersion: "dash-read-only-v1" },
        });
      }
      await new Promise((r) => setTimeout(r, 80));
      return createReadOnlyEnvelope({ data: buildCustomersPage(query, mockCustomers), metadata: options?.metadata });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(query, options) {
      const envelope = await fetchDashboardApi<LaravelCustomersListPayload>(DASHBOARD_API_ROUTES.customers, {
        signal: options?.signal,
        query: toLaravelQuery(query),
      });
      const pagination = envelope.pagination ?? { page: 1, pageSize: 25, total: 0, pageCount: 1 };
      return { ...envelope, data: transformCustomersPage(envelope.data, pagination) };
    },
  },
});

export async function getCustomersPage(query: CustomersQuery, options?: ReadOnlyFetchOptions): Promise<CustomersPageResult> {
  try {
    const envelope = await customersService.fetchReadOnly(query, options);
    return envelope.data;
  } catch (error) {
    mapReadOnlyError(error);
  }
}

export async function getCustomerDetail(id: string, options?: ReadOnlyFetchOptions): Promise<CustomerRecord | null> {
  const { resolveDataSourceMode } = await import("@/lib/read-only/data-source");
  const mode = resolveDataSourceMode();

  if (mode === "fixture") {
    await new Promise((r) => setTimeout(r, 40));
    return getCustomerById(id) ?? null;
  }

  try {
    const envelope = await fetchDashboardApi<CustomerRecord>(DASHBOARD_API_ROUTES.customerDetail(id), {
      signal: options?.signal,
    });
    return transformCustomerDetail(envelope.data);
  } catch (error) {
    if (error instanceof ReadOnlyServiceError && error.envelope.error.code === "not_found") {
      return null;
    }
    mapReadOnlyError(error);
  }
}

export function listAllMockCustomers(): CustomerRecord[] {
  return mockCustomers;
}
