import { REPORT_REFERENCE_DATE } from "@/lib/reports/constants";
import { buildReportModule } from "@/lib/reports/build-report";
import { resolveComparisonPeriod, resolveDatePreset } from "@/lib/reports/date-presets";
import { buildReportFacets } from "@/lib/reports/query-filters";
import { getOperationalFixtureGraph } from "@/lib/reports/aggregations";
import type { ReportModuleResult, ReportsModuleKey, ReportsQuery } from "@/types/report";
import { useMockData } from "@/lib/preview";

export class ReportsServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "ReportsServiceError";
    this.referenceId = referenceId;
  }
}

export async function getReportModule(query: ReportsQuery, module: ReportsModuleKey): Promise<ReportModuleResult> {
  if (!useMockData()) {
    throw new ReportsServiceError("Live report data is disabled in preview.", "RPT-PREVIEW-NO-LIVE");
  }

  if (query.previewError) {
    throw new ReportsServiceError(
      "Mock report service returned a recoverable error (preview simulation).",
      "RPT-PREVIEW-SIM-ERR",
    );
  }

  await new Promise((r) => setTimeout(r, module === "overview" ? 60 : 40));

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
