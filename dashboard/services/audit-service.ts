import { buildAuditPage } from "@/lib/audit/query-filters";
import { buildAuditExportManifest } from "@/lib/audit/export-preview";
import { AUDIT_FIXTURE_COUNT, getAuditEventById, mockAuditEvents } from "@/mocks/audit-fixtures";
import type { AuditModuleResult, AuditQuery } from "@/types/audit";
import type { AuditEvent } from "@/types/access-control";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { createReadOnlyService, ReadOnlyServiceError, type ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { transformAuditDetail, transformAuditModule } from "@/lib/read-only/laravel/transformers/audit";
import type { LaravelAuditListPayload } from "@/lib/read-only/laravel/types";

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

function mapReadOnlyError(error: unknown): never {
  if (error instanceof ReadOnlyServiceError) {
    throw new AuditServiceError(error.envelope.error.message, error.envelope.error.referenceIdSafe);
  }
  throw error;
}

function toLaravelQuery(query: AuditQuery): Record<string, string | number> {
  return {
    page: query.page,
    pageSize: query.pageSize,
    q: query.search,
    category: query.category,
    eventType: query.eventType,
    severity: query.severity,
    outcome: query.outcome,
    actorType: query.actorType,
    actor: query.actor,
    targetType: query.targetType,
    sourceModule: query.sourceModule,
    risk: query.risk,
    authorization: query.authorization,
    channel: query.channel ?? "all",
    datePreset: query.datePreset,
    startDate: query.startDate,
    endDate: query.endDate,
    validationState: query.validationState,
    sort: query.sort,
    direction: query.direction,
  };
}

function buildFixtureResult(query: AuditQuery): AuditModuleResult {
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

const auditService = createReadOnlyService<AuditQuery, AuditModuleResult>({
  module: "audit",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(query, options) {
      if (query.previewError) {
        throw new ReadOnlyServiceError({
          error: {
            code: "internal_error",
            message: "Mock audit service returned a recoverable error (preview simulation).",
            referenceIdSafe: "AUD-PREVIEW-SIM-ERR",
          },
          meta: { source: "fixture", schemaVersion: "dash-read-only-v1" },
        });
      }
      await new Promise((r) => setTimeout(r, 60));
      return createReadOnlyEnvelope({ data: buildFixtureResult(query), metadata: options?.metadata });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(query, options) {
      const envelope = await fetchDashboardApi<LaravelAuditListPayload>(DASHBOARD_API_ROUTES.audit, {
        signal: options?.signal,
        query: toLaravelQuery(query),
      });
      const pagination = envelope.pagination ?? { page: 1, pageSize: 25, total: 0, pageCount: 1 };
      let selectedEvent: AuditEvent | null = null;
      if (query.selected) {
        try {
          const detail = await fetchDashboardApi<Record<string, unknown>>(DASHBOARD_API_ROUTES.auditDetail(query.selected), {
            signal: options?.signal,
          });
          selectedEvent = transformAuditDetail(detail.data);
        } catch (error) {
          if (!(error instanceof ReadOnlyServiceError && error.envelope.error.code === "not_found")) {
            throw error;
          }
        }
      }
      return {
        ...envelope,
        data: transformAuditModule(envelope.data, query, pagination, selectedEvent),
      };
    },
  },
});

export async function getAuditModule(query: AuditQuery, options?: ReadOnlyFetchOptions): Promise<AuditModuleResult> {
  try {
    const envelope = await auditService.fetchReadOnly(query, options);
    return envelope.data;
  } catch (error) {
    mapReadOnlyError(error);
  }
}

export function getAuditFixtureCount() {
  return AUDIT_FIXTURE_COUNT;
}
