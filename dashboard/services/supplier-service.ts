import type { SuppliersPageResult, SuppliersQuery, SupplierRecord } from "@/types/supplier";
import { buildSuppliersPage } from "@/lib/suppliers-filter";
import { getSupplierById, mockSuppliers } from "@/mocks/supplier-fixtures";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { createReadOnlyService, ReadOnlyServiceError, type ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { transformSupplierDetail, transformSuppliersPage } from "@/lib/read-only/laravel/transformers/suppliers";
import type { LaravelSuppliersListPayload } from "@/lib/read-only/laravel/types";

export class SuppliersServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "SuppliersServiceError";
    this.referenceId = referenceId;
  }
}

function mapReadOnlyError(error: unknown): never {
  if (error instanceof ReadOnlyServiceError) {
    throw new SuppliersServiceError(error.envelope.error.message, error.envelope.error.referenceIdSafe);
  }
  throw error;
}

function toLaravelQuery(query: SuppliersQuery): Record<string, string | number> {
  return {
    page: query.page,
    pageSize: query.pageSize,
    q: query.q,
    category: query.category,
    operationalStatus: query.operationalStatus,
    integrationStatus: query.integrationStatus,
    credentialStatus: query.credentialStatus,
    settlementStatus: query.settlementStatus,
    operatingRegion: query.operatingRegion,
    hasOutstandingSettlement: query.hasOutstandingSettlement,
    activityFrom: query.activityFrom,
    activityTo: query.activityTo,
    sort: query.sort,
    direction: query.direction,
  };
}

const suppliersService = createReadOnlyService<SuppliersQuery, SuppliersPageResult>({
  module: "suppliers",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(query, options) {
      if (query.previewError) {
        throw new ReadOnlyServiceError({
          error: {
            code: "internal_error",
            message: "Mock supplier service returned a recoverable error (preview simulation).",
            referenceIdSafe: "SU-PREVIEW-SIM-ERR",
          },
          meta: { source: "fixture", schemaVersion: "dash-read-only-v1" },
        });
      }
      await new Promise((r) => setTimeout(r, 80));
      return createReadOnlyEnvelope({ data: buildSuppliersPage(query, mockSuppliers), metadata: options?.metadata });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(query, options) {
      const envelope = await fetchDashboardApi<LaravelSuppliersListPayload>(DASHBOARD_API_ROUTES.suppliers, {
        signal: options?.signal,
        query: toLaravelQuery(query),
      });
      const pagination = envelope.pagination ?? { page: 1, pageSize: 25, total: 0, pageCount: 1 };
      return { ...envelope, data: transformSuppliersPage(envelope.data, pagination) };
    },
  },
});

export async function getSuppliersPage(query: SuppliersQuery, options?: ReadOnlyFetchOptions): Promise<SuppliersPageResult> {
  try {
    const envelope = await suppliersService.fetchReadOnly(query, options);
    return envelope.data;
  } catch (error) {
    mapReadOnlyError(error);
  }
}

export async function getSupplierDetail(id: string, options?: ReadOnlyFetchOptions): Promise<SupplierRecord | null> {
  const { resolveDataSourceMode } = await import("@/lib/read-only/data-source");
  const mode = resolveDataSourceMode();

  if (mode === "fixture") {
    await new Promise((r) => setTimeout(r, 40));
    return getSupplierById(id) ?? null;
  }

  try {
    const envelope = await fetchDashboardApi<{ summary: SupplierRecord } | SupplierRecord>(
      DASHBOARD_API_ROUTES.supplierDetail(id),
      { signal: options?.signal },
    );
    return transformSupplierDetail(envelope.data);
  } catch (error) {
    if (error instanceof ReadOnlyServiceError && error.envelope.error.code === "not_found") {
      return null;
    }
    mapReadOnlyError(error);
  }
}

export function listAllMockSuppliers(): SupplierRecord[] {
  return mockSuppliers;
}
