import type { TicketsPageResult, TicketsQuery, TicketRecord } from "@/types/ticket";
import { buildTicketsPage } from "@/lib/tickets-filter";
import { getTicketById, mockTickets } from "@/mocks/ticket-fixtures";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { createReadOnlyService, ReadOnlyServiceError, type ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { transformTicketDetail, transformTicketsPage } from "@/lib/read-only/laravel/transformers/tickets";
import type { LaravelTicketsListPayload } from "@/lib/read-only/laravel/types";

export class TicketsServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "TicketsServiceError";
    this.referenceId = referenceId;
  }
}

function mapReadOnlyError(error: unknown): never {
  if (error instanceof ReadOnlyServiceError) {
    throw new TicketsServiceError(error.envelope.error.message, error.envelope.error.referenceIdSafe);
  }
  throw error;
}

function toLaravelQuery(query: TicketsQuery): Record<string, string | number> {
  return {
    page: query.page,
    pageSize: query.pageSize,
    q: query.q,
    issueStatus: query.issueStatus,
    documentType: query.documentType,
    channel: query.channel,
    supplier: query.supplier,
    dateFrom: query.issueFrom,
    dateTo: query.issueTo,
    sort: query.sort,
    direction: query.direction,
  };
}

const ticketsService = createReadOnlyService<TicketsQuery, TicketsPageResult>({
  module: "tickets",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(query, options) {
      if (query.previewError) {
        throw new ReadOnlyServiceError({
          error: {
            code: "internal_error",
            message: "Mock ticket service returned a recoverable error (preview simulation).",
            referenceIdSafe: "TK-PREVIEW-SIM-ERR",
          },
          meta: { source: "fixture", schemaVersion: "dash-read-only-v1" },
        });
      }
      await new Promise((r) => setTimeout(r, 80));
      return createReadOnlyEnvelope({ data: buildTicketsPage(query, mockTickets), metadata: options?.metadata });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(query, options) {
      const envelope = await fetchDashboardApi<LaravelTicketsListPayload>(DASHBOARD_API_ROUTES.tickets, {
        signal: options?.signal,
        query: toLaravelQuery(query),
      });
      const pagination = envelope.pagination ?? { page: 1, pageSize: 25, total: 0, pageCount: 1 };
      return { ...envelope, data: transformTicketsPage(envelope.data, pagination) };
    },
  },
});

export async function getTicketsPage(query: TicketsQuery, options?: ReadOnlyFetchOptions): Promise<TicketsPageResult> {
  try {
    const envelope = await ticketsService.fetchReadOnly(query, options);
    return envelope.data;
  } catch (error) {
    mapReadOnlyError(error);
  }
}

export async function getTicketDetail(id: string, options?: ReadOnlyFetchOptions): Promise<TicketRecord | null> {
  const { resolveDataSourceMode } = await import("@/lib/read-only/data-source");
  const mode = resolveDataSourceMode();

  if (mode === "fixture") {
    await new Promise((r) => setTimeout(r, 40));
    return getTicketById(id) ?? null;
  }

  try {
    const envelope = await fetchDashboardApi<{ summary: TicketRecord } | TicketRecord>(
      DASHBOARD_API_ROUTES.ticketDetail(id),
      { signal: options?.signal },
    );
    return transformTicketDetail(envelope.data);
  } catch (error) {
    if (error instanceof ReadOnlyServiceError && error.envelope.error.code === "not_found") {
      return null;
    }
    mapReadOnlyError(error);
  }
}

export function listAllMockTickets(): TicketRecord[] {
  return mockTickets;
}
