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

type SupportListQuery = Record<string, never>;

type SupportListPayload = {
  tickets: SupportTicketRecord[];
};

function mapReadOnlyError(error: unknown): never {
  if (error instanceof ReadOnlyServiceError) {
    throw new SupportServiceError(error.envelope.error.message, error.envelope.error.referenceIdSafe);
  }
  throw error;
}

const supportService = createReadOnlyService<SupportListQuery, SupportTicketRecord[]>({
  module: "support",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(_query, options) {
      await new Promise((r) => setTimeout(r, 40));
      return createReadOnlyEnvelope({ data: mockSupportTickets, metadata: options?.metadata });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(_query, options) {
      const envelope = await fetchDashboardApi<SupportListPayload>(DASHBOARD_API_ROUTES.supportTickets, {
        signal: options?.signal,
      });
      const tickets = (envelope.data?.tickets ?? []).map((row) => ({
        id: String(row.id),
        subject: String(row.subject ?? "Support ticket"),
        status: String(row.status ?? "open"),
        assignedTo: row.assignedTo ?? null,
      }));
      return { ...envelope, data: tickets };
    },
  },
});

export async function getSupportTickets(options?: ReadOnlyFetchOptions): Promise<SupportTicketRecord[]> {
  try {
    const envelope = await supportService.fetchReadOnly({}, options);
    return envelope.data;
  } catch (error) {
    mapReadOnlyError(error);
  }
}
