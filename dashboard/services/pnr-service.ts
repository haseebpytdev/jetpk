import type { PnrsPageResult, PnrsQuery, PnrRecord } from "@/types/pnr";
import { buildPnrsPage } from "@/lib/pnrs-filter";
import { getPnrById, mockPnrs } from "@/mocks/pnr-fixtures";
import { useMockData } from "@/lib/preview";

export class PnrsServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "PnrsServiceError";
    this.referenceId = referenceId;
  }
}

export async function getPnrsPage(query: PnrsQuery): Promise<PnrsPageResult> {
  if (!useMockData()) {
    throw new PnrsServiceError(
      "Live PNR data is disabled in preview.",
      "PN-PREVIEW-NO-LIVE",
    );
  }

  if (query.previewError) {
    throw new PnrsServiceError(
      "Mock PNR service returned a recoverable error (preview simulation).",
      "PN-PREVIEW-SIM-ERR",
    );
  }

  await new Promise((r) => setTimeout(r, 80));

  return buildPnrsPage(query, mockPnrs);
}

export async function getPnrDetail(id: string): Promise<PnrRecord | null> {
  if (!useMockData()) {
    return null;
  }
  await new Promise((r) => setTimeout(r, 40));
  return getPnrById(id) ?? null;
}

export function listAllMockPnrs(): PnrRecord[] {
  return mockPnrs;
}
