import type { TicketsPageResult, TicketsQuery, TicketRecord } from "@/types/ticket";
import { buildTicketsPage } from "@/lib/tickets-filter";
import { getTicketById, mockTickets } from "@/mocks/ticket-fixtures";
import { useMockData } from "@/lib/preview";

export class TicketsServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "TicketsServiceError";
    this.referenceId = referenceId;
  }
}

export async function getTicketsPage(query: TicketsQuery): Promise<TicketsPageResult> {
  if (!useMockData()) {
    throw new TicketsServiceError(
      "Live ticket data is disabled in preview.",
      "TK-PREVIEW-NO-LIVE",
    );
  }

  if (query.previewError) {
    throw new TicketsServiceError(
      "Mock ticket service returned a recoverable error (preview simulation).",
      "TK-PREVIEW-SIM-ERR",
    );
  }

  await new Promise((r) => setTimeout(r, 80));

  return buildTicketsPage(query, mockTickets);
}

export async function getTicketDetail(id: string): Promise<TicketRecord | null> {
  if (!useMockData()) {
    return null;
  }
  await new Promise((r) => setTimeout(r, 40));
  return getTicketById(id) ?? null;
}

export function listAllMockTickets(): TicketRecord[] {
  return mockTickets;
}
