import { REPORT_REFERENCE_DATE } from "@/lib/reports/constants";
import { buildReportModule } from "@/lib/reports/build-report";
import { resolveComparisonPeriod, resolveDatePreset } from "@/lib/reports/date-presets";
import { buildReportFacets } from "@/lib/reports/query-filters";
import { getOperationalFixtureGraph } from "@/lib/reports/aggregations";
import type { ReportModuleResult, ReportsModuleKey, ReportsQuery } from "@/types/report";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { createReadOnlyService, ReadOnlyServiceError, type ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { transformReportModule } from "@/lib/read-only/laravel/transformers/reports";
import type { LaravelReportPayload } from "@/lib/read-only/laravel/types";

export class ReportsServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "ReportsServiceError";
    this.referenceId = referenceId;
  }
}

function mapReadOnlyError(error: unknown): never {
  if (error instanceof ReadOnlyServiceError) {
    throw new ReportsServiceError(error.envelope.error.message, error.envelope.error.referenceIdSafe);
  }
  throw error;
}

function reportRouteForModule(module: ReportsModuleKey): string {
  switch (module) {
    case "bookings":
      return DASHBOARD_API_ROUTES.reportsBookings;
    case "payments":
      return DASHBOARD_API_ROUTES.reportsPayments;
    case "operations":
      return DASHBOARD_API_ROUTES.reportsSuppliers;
    case "sales":
      return DASHBOARD_API_ROUTES.reportsAgents;
    default:
      return DASHBOARD_API_ROUTES.reportsSummary;
  }
}

function toLaravelQuery(query: ReportsQuery): Record<string, string | number> {
  return {
    datePreset: query.datePreset,
    startDate: query.startDate,
    endDate: query.endDate,
    currency: query.currency,
    channel: query.channel,
    supplier: query.supplier,
    airline: query.airline,
    agent: query.agent,
    route: query.route,
    bookingStatus: query.bookingStatus,
    paymentStatus: query.paymentStatus,
    page: query.page,
    pageSize: query.pageSize,
    sort: query.sort,
    direction: query.direction,
  };
}

function buildFixtureResult(query: ReportsQuery, module: ReportsModuleKey): ReportModuleResult {
  const dateRange = resolveDatePreset(query.datePreset, query.startDate, query.endDate);
  const comparison = { mode: query.comparison, ...resolveComparisonPeriod(query.comparison, dateRange) };
  const facets = buildReportFacets(getOperationalFixtureGraph());

  if (query.previewLoading) {
    return {
      state: "loading",
      module,
      query,
      dateRange,
      comparison,
      validation: { valid: true, issues: [] },
      referenceDate: REPORT_REFERENCE_DATE,
      metrics: [],
      facets,
      series: {},
      breakdowns: {},
      charts: {},
      attentionQueue: [],
      funnel: [],
      table: { columns: [], rows: [], total: 0, page: 1, pageSize: query.pageSize, pageCount: 1 },
      exportRows: [],
      exportManifest: {
        id: `JP-RPT-EXP-${module}`,
        reportKey: module,
        title: "Report export",
        generatedAt: REPORT_REFERENCE_DATE,
        dateRange,
        currency: query.currency,
        columns: [],
        rowCount: 0,
        previewOnly: true,
      },
      limitationNotices: [],
    };
  }

  const result = buildReportModule(module, query);
  if (query.previewEmpty) {
    return { ...result, state: "empty", metrics: [], table: { ...result.table, rows: [] } };
  }
  return result;
}

const reportsService = createReadOnlyService<{ query: ReportsQuery; module: ReportsModuleKey }, ReportModuleResult>({
  module: "reports",
  fixtureAdapter: {
    mode: "fixture",
    async fetch({ query, module }, options) {
      if (query.previewError) {
        throw new ReadOnlyServiceError({
          error: {
            code: "internal_error",
            message: "Mock report service returned a recoverable error (preview simulation).",
            referenceIdSafe: "RPT-PREVIEW-SIM-ERR",
          },
          meta: { source: "fixture", schemaVersion: "dash-read-only-v1" },
        });
      }
      await new Promise((r) => setTimeout(r, module === "overview" ? 60 : 40));
      return createReadOnlyEnvelope({ data: buildFixtureResult(query, module), metadata: options?.metadata });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch({ query, module }, options) {
      const envelope = await fetchDashboardApi<LaravelReportPayload>(reportRouteForModule(module), {
        signal: options?.signal,
        query: toLaravelQuery(query),
      });
      return { ...envelope, data: transformReportModule(envelope.data, query, module) };
    },
  },
});

export async function getReportModule(
  query: ReportsQuery,
  module: ReportsModuleKey,
  options?: ReadOnlyFetchOptions,
): Promise<ReportModuleResult> {
  try {
    const envelope = await reportsService.fetchReadOnly({ query, module }, options);
    return envelope.data;
  } catch (error) {
    mapReadOnlyError(error);
  }
}
