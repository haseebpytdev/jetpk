import type { PnrsPageResult } from "@/types/pnr";
import type { LaravelPnrsListPayload } from "@/lib/read-only/laravel/types";

export function transformPnrsPage(
  payload: LaravelPnrsListPayload,
  pagination: { page: number; pageSize: number; total: number; pageCount: number },
): PnrsPageResult {
  return {
    pnrs: payload.pnrs,
    total: pagination.total,
    page: pagination.page,
    pageSize: pagination.pageSize,
    pageCount: pagination.pageCount,
    summary: {
      totalRecords: Number(payload.summary?.totalRecords ?? pagination.total),
      activeRecords: Number(payload.summary?.totalRecords ?? 0),
      gdsPnrCount: Number(payload.summary?.gdsPnrCount ?? 0),
      ndcOrderCount: Number(payload.summary?.ndcOrderCount ?? 0),
      awaitingFulfilment: 0,
      reviewRequired: Number(payload.summary?.reviewRequired ?? 0),
      approachingDeadline: 0,
      currency: String(payload.summary?.currency ?? "PKR"),
    },
    facets: {
      suppliers: [],
      airlines: [],
      referenceTypes: (payload.facets?.recordTypes as import("@/types/pnr").PnrReferenceType[]) ?? [],
      channels: (payload.facets?.channels as import("@/types/pnr").PnrChannel[]) ?? [],
    },
  };
}

export function transformPnrDetail(payload: { summary: import("@/types/pnr").PnrRecord } | import("@/types/pnr").PnrRecord) {
  if ("summary" in payload && payload.summary) {
    return payload.summary;
  }
  return payload as import("@/types/pnr").PnrRecord;
}
