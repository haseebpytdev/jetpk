import type { TicketsPageResult } from "@/types/ticket";
import type { LaravelTicketsListPayload } from "@/lib/read-only/laravel/types";

export function transformTicketsPage(
  payload: LaravelTicketsListPayload,
  pagination: { page: number; pageSize: number; total: number; pageCount: number },
): TicketsPageResult {
  return {
    tickets: payload.tickets,
    total: pagination.total,
    page: pagination.page,
    pageSize: pagination.pageSize,
    pageCount: pagination.pageCount,
    summary: {
      totalDocuments: Number(payload.summary?.totalTickets ?? pagination.total),
      issued: Number(payload.summary?.issuedCount ?? 0),
      pending: Number(payload.summary?.pendingCount ?? 0),
      blockedOrFailed: 0,
      refunded: 0,
      totalDocumentValue: 0,
      upcomingTravel: 0,
      currency: String(payload.summary?.currency ?? "PKR"),
    },
    facets: {
      airlines: [],
      suppliers: [],
      documentTypes: (payload.facets?.documentTypes as import("@/types/ticket").DocumentType[]) ?? [],
      channels: [],
    },
  };
}

export function transformTicketDetail(payload: { summary: import("@/types/ticket").TicketRecord } | import("@/types/ticket").TicketRecord) {
  if ("summary" in payload && payload.summary) {
    return payload.summary;
  }
  return payload as import("@/types/ticket").TicketRecord;
}
