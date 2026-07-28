import type { PnrsPageResult, PnrsQuery, PnrRecord } from "@/types/pnr";
import { buildPnrsPage } from "@/lib/pnrs-filter";
import { getPnrById, mockPnrs } from "@/mocks/pnr-fixtures";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { createReadOnlyService, ReadOnlyServiceError, type ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { transformPnrDetail, transformPnrsPage } from "@/lib/read-only/laravel/transformers/pnrs";
import type { LaravelPnrsListPayload } from "@/lib/read-only/laravel/types";

export class PnrsServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "PnrsServiceError";
    this.referenceId = referenceId;
  }
}

function mapReadOnlyError(error: unknown): never {
  if (error instanceof ReadOnlyServiceError) {
    throw new PnrsServiceError(error.envelope.error.message, error.envelope.error.referenceIdSafe);
  }
  throw error;
}

function toLaravelQuery(query: PnrsQuery): Record<string, string | number> {
  return {
    page: query.page,
    pageSize: query.pageSize,
    q: query.q,
    channel: query.channel === "all" ? "" : query.channel,
    recordType: query.referenceType === "all" ? "" : query.referenceType,
    lifecycle: query.lifecycleStatus,
    supplier: query.supplier,
    dateFrom: query.departureFrom,
    dateTo: query.departureTo,
    sort: query.sort,
    direction: query.direction,
  };
}

const pnrsService = createReadOnlyService<PnrsQuery, PnrsPageResult>({
  module: "pnrs",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(query, options) {
      if (query.previewError) {
        throw new ReadOnlyServiceError({
          error: {
            code: "internal_error",
            message: "Mock PNR service returned a recoverable error (preview simulation).",
            referenceIdSafe: "PN-PREVIEW-SIM-ERR",
          },
          meta: { source: "fixture", schemaVersion: "dash-read-only-v1" },
        });
      }
      await new Promise((r) => setTimeout(r, 80));
      return createReadOnlyEnvelope({ data: buildPnrsPage(query, mockPnrs), metadata: options?.metadata });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(query, options) {
      const envelope = await fetchDashboardApi<LaravelPnrsListPayload>(DASHBOARD_API_ROUTES.pnrs, {
        signal: options?.signal,
        query: toLaravelQuery(query),
      });
      const pagination = envelope.pagination ?? { page: 1, pageSize: 25, total: 0, pageCount: 1 };
      return { ...envelope, data: transformPnrsPage(envelope.data, pagination) };
    },
  },
});

export async function getPnrsPage(query: PnrsQuery, options?: ReadOnlyFetchOptions): Promise<PnrsPageResult> {
  try {
    const envelope = await pnrsService.fetchReadOnly(query, options);
    return envelope.data;
  } catch (error) {
    mapReadOnlyError(error);
  }
}

export async function getPnrDetail(id: string, options?: ReadOnlyFetchOptions): Promise<PnrRecord | null> {
  const { resolveDataSourceMode } = await import("@/lib/read-only/data-source");
  const mode = resolveDataSourceMode();

  if (mode === "fixture") {
    await new Promise((r) => setTimeout(r, 40));
    return getPnrById(id) ?? null;
  }

  try {
    const envelope = await fetchDashboardApi<{ summary: PnrRecord } | PnrRecord>(
      DASHBOARD_API_ROUTES.pnrDetail(id),
      { signal: options?.signal },
    );
    return transformPnrDetail(envelope.data);
  } catch (error) {
    if (error instanceof ReadOnlyServiceError && error.envelope.error.code === "not_found") {
      return null;
    }
    mapReadOnlyError(error);
  }
}

export function listAllMockPnrs(): PnrRecord[] {
  return mockPnrs;
}
