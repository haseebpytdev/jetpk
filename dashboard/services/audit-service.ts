import { buildAuditExportManifest } from "@/lib/audit/export-preview";
import { buildAuditPage } from "@/lib/audit/query-filters";
import { useMockData } from "@/lib/preview";
import { AUDIT_FIXTURE_COUNT, getAuditEventById, mockAuditEvents } from "@/mocks/audit-fixtures";
import type { AuditModuleResult, AuditQuery } from "@/types/audit";

export class AuditServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "AuditServiceError";
    this.referenceId = referenceId;
  }
}

const emptySummary = {
  totalEvents: 0,
  securityEvents: 0,
  warningCriticalEvents: 0,
  successfulOutcomes: 0,
  deniedOutcomes: 0,
  previewOnlyEvents: 0,
  highRiskEvents: 0,
  eventsRequiringReview: 0,
};

export async function getAuditModule(query: AuditQuery): Promise<AuditModuleResult> {
  if (!useMockData()) {
    throw new AuditServiceError("Live audit data is disabled in preview.", "AUD-PREVIEW-NO-LIVE");
  }

  if (query.previewError) {
    throw new AuditServiceError(
      "Mock audit service returned a recoverable error (preview simulation).",
      "AUD-PREVIEW-SIM-ERR",
    );
  }

  await new Promise((r) => setTimeout(r, 60));

  if (query.previewLoading) {
    return {
      state: "loading",
      query,
      dateRange: { preset: query.datePreset, startDate: query.startDate, endDate: query.endDate, valid: true, error: null },
      summary: emptySummary,
      table: { rows: [], total: 0, page: 1, pageSize: query.pageSize, pageCount: 1 },
      facets: {
        categories: [],
        eventTypes: [],
        severities: [],
        outcomes: [],
        actorTypes: [],
        actors: [],
        targetTypes: [],
        sourceModules: [],
        riskStates: [],
        authorizationOutcomes: [],
        channels: [],
        validationStates: [],
      },
      selectedEvent: null,
      exportEvents: [],
      exportManifest: buildAuditExportManifest([]),
      securityEventCount: 0,
    };
  }

  const sourceEvents = query.previewEmpty ? [] : mockAuditEvents;
  const page = buildAuditPage(query, sourceEvents);
  const selectedEvent = query.selected ? getAuditEventById(query.selected) ?? null : null;

  return {
    state: !page.dateRange.valid ? "ready" : page.total === 0 ? "empty" : "ready",
    query,
    dateRange: page.dateRange,
    summary: page.summary,
    table: {
      rows: page.rows,
      total: page.total,
      page: page.page,
      pageSize: page.pageSize,
      pageCount: page.pageCount,
    },
    facets: page.facets,
    selectedEvent,
    exportEvents: page.allFilteredEvents,
    exportManifest: buildAuditExportManifest(page.allFilteredEvents),
    securityEventCount: page.securityEventCount,
  };
}

export function getAuditFixtureCount() {
  return AUDIT_FIXTURE_COUNT;
}
