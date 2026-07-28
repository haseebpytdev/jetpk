import type { SuppliersPageResult } from "@/types/supplier";
import type { LaravelSuppliersListPayload } from "@/lib/read-only/laravel/types";

export function transformSuppliersPage(
  payload: LaravelSuppliersListPayload,
  pagination: { page: number; pageSize: number; total: number; pageCount: number },
): SuppliersPageResult {
  return {
    suppliers: payload.suppliers,
    total: pagination.total,
    page: pagination.page,
    pageSize: pagination.pageSize,
    pageCount: pagination.pageCount,
    summary: payload.summary,
    facets: payload.facets,
  };
}

export function transformSupplierDetail(payload: { summary: import("@/types/supplier").SupplierRecord } | import("@/types/supplier").SupplierRecord) {
  if ("summary" in payload && payload.summary) {
    return payload.summary;
  }
  return payload as import("@/types/supplier").SupplierRecord;
}
