import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { createReadOnlyService, ReadOnlyServiceError, type ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { mockSupportTickets, type SupportTicketRecord } from "@/mocks/support-fixtures";

export class SupportServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "SupportServiceError";
    this.referenceId = referenceId;
  }
}

export type SupportListQuery = {
  page?: number;
  pageSize?: number;
};

type SupportListPayload = {
  tickets: SupportTicketRecord[];
};

export type SupportTicketsPage = {
  tickets: SupportTicketRecord[];
  pagination: {
    page: number;
    pageSize: number;
    total: number;
    pageCount: number;
  };
};

function mapReadOnlyError(error: unknown): never {
  if (error instanceof ReadOnlyServiceError) {
    throw new SupportServiceError(error.envelope.error.message, error.envelope.error.referenceIdSafe);
  }
  throw error;
}

export const SUPPORT_DEFAULT_PAGE_SIZE = 10;

const supportService = createReadOnlyService<SupportListQuery, SupportTicketsPage>({
  module: "support",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(query, options) {
      await new Promise((r) => setTimeout(r, 40));
      const page = Math.max(1, query.page ?? 1);
      const pageSize = Math.max(5, Math.min(50, query.pageSize ?? SUPPORT_DEFAULT_PAGE_SIZE));
      const total = mockSupportTickets.length;
      const pageCount = Math.max(1, Math.ceil(total / pageSize));
      const start = (page - 1) * pageSize;
      const tickets = mockSupportTickets.slice(start, start + pageSize);
      return createReadOnlyEnvelope({
        data: {
          tickets,
          pagination: { page, pageSize, total, pageCount },
        },
        metadata: options?.metadata,
      });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(query, options) {
      const page = Math.max(1, query.page ?? 1);
      const pageSize = Math.max(5, Math.min(50, query.pageSize ?? SUPPORT_DEFAULT_PAGE_SIZE));
      const envelope = await fetchDashboardApi<SupportListPayload>(DASHBOARD_API_ROUTES.supportTickets, {
        signal: options?.signal,
        query: { page, pageSize },
      });
      const tickets = (envelope.data?.tickets ?? []).map((row) => ({
        id: String(row.id),
        subject: String(row.subject ?? "Support ticket"),
        status: String(row.status ?? "open"),
        assignedTo: row.assignedTo ?? null,
      }));
      const pagination = envelope.pagination ?? {
        page,
        pageSize,
        total: tickets.length,
        pageCount: 1,
      };
      return {
        ...envelope,
        data: {
          tickets,
          pagination: {
            page: pagination.page,
            pageSize: pagination.pageSize,
            total: pagination.total,
            pageCount: pagination.pageCount,
          },
        },
      };
    },
  },
});

export async function getSupportTicketsPage(
  query: SupportListQuery = {},
  options?: ReadOnlyFetchOptions,
): Promise<SupportTicketsPage> {
  try {
    const envelope = await supportService.fetchReadOnly(
      { page: query.page ?? 1, pageSize: query.pageSize ?? SUPPORT_DEFAULT_PAGE_SIZE },
      options,
    );
    return envelope.data;
  } catch (error) {
    mapReadOnlyError(error);
  }
}

/** @deprecated Prefer getSupportTicketsPage for pagination. */
export async function getSupportTickets(options?: ReadOnlyFetchOptions): Promise<SupportTicketRecord[]> {
  const page = await getSupportTicketsPage({ page: 1, pageSize: SUPPORT_DEFAULT_PAGE_SIZE }, options);
  return page.tickets;
}
