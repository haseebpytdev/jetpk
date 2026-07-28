import { REPORT_REFERENCE_DATE } from "@/lib/reports/constants";
import { resolveComparisonPeriod, resolveDatePreset } from "@/lib/reports/date-presets";
import { buildReportFacets } from "@/lib/reports/query-filters";
import { getOperationalFixtureGraph } from "@/lib/reports/aggregations";
import type { LaravelReportPayload } from "@/lib/read-only/laravel/types";
import type { ReportModuleResult, ReportsModuleKey, ReportsQuery } from "@/types/report";

export function transformReportModule(
  payload: LaravelReportPayload,
  query: ReportsQuery,
  module: ReportsModuleKey,
): ReportModuleResult {
  const dateRange = resolveDatePreset(query.datePreset, query.startDate, query.endDate);
  const comparison = { mode: query.comparison, ...resolveComparisonPeriod(query.comparison, dateRange) };
  const facets = buildReportFacets(getOperationalFixtureGraph());
  const metrics = (payload.metrics ?? []).map((metric) => ({
    key: String(metric.key ?? "booking_count") as import("@/types/report").ReportMetricKey,
    label: String(metric.label ?? "Metric"),
    value: typeof metric.value === "number" ? metric.value : null,
    formattedValue: String(metric.formattedValue ?? "—"),
    currency: (metric.currency as import("@/lib/reports/constants").ReportSupportedCurrency | null) ?? null,
    trend: (metric.trend as import("@/types/report").ReportMetricTrend) ?? "neutral",
    comparisonDelta: null,
    comparisonLabel: null,
    unavailableReason: null,
  }));

  const rows = (payload.tableRows ?? []).map((row, index) => ({
    id: String(row.id ?? `row-${index}`),
    label: String(row.label ?? row.id ?? "Row"),
    bookings: Number(row.bookings ?? 0),
    sales: Number(row.sales ?? row.value ?? 0),
    currency: payload.currency,
  }));

  return {
    state: payload.hasLiveData || rows.length > 0 || metrics.length > 0 ? "ready" : "empty",
    module,
    query,
    dateRange,
    comparison,
    validation: { valid: true, issues: [] },
    referenceDate: payload.referenceTime ?? REPORT_REFERENCE_DATE,
    metrics,
    facets,
    series: {},
    breakdowns: {},
    charts: {},
    attentionQueue: [],
    funnel: [],
    table: {
      columns: [
        { key: "label", label: "Label", sortable: true },
        { key: "bookings", label: "Bookings", align: "end", sortable: true },
        { key: "sales", label: "Sales", align: "end", sortable: true },
      ],
      rows,
      total: rows.length,
      page: query.page,
      pageSize: query.pageSize,
      pageCount: Math.max(1, Math.ceil(rows.length / query.pageSize)),
    },
    exportRows: rows,
    exportManifest: {
      id: `JP-RPT-EXP-${module}`,
      reportKey: module,
      title: `${module} report export preview`,
      generatedAt: payload.referenceTime ?? REPORT_REFERENCE_DATE,
      dateRange,
      currency: query.currency === "all" ? "PKR" : query.currency,
      columns: [
        { key: "label", header: "Label", includeByDefault: true },
        { key: "bookings", header: "Bookings", includeByDefault: true },
        { key: "sales", header: "Sales", includeByDefault: true },
      ],
      rowCount: rows.length,
      previewOnly: true,
    },
    limitationNotices: (payload.warnings ?? []).map((w) => w.message),
  };
}
