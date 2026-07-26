import type {
  ReportComparisonMode,
  ReportDatePreset,
  ReportGranularity,
  ReportsQuery,
} from "@/types/report";
import { REPORT_SUPPORTED_CURRENCIES } from "@/lib/reports/constants";
import { resolveDatePreset } from "@/lib/reports/date-presets";

const DATE_PRESETS: ReportDatePreset[] = [
  "last_7_days",
  "last_30_days",
  "current_month",
  "previous_month",
  "current_quarter",
  "previous_quarter",
  "current_year",
  "custom",
];

const COMPARISON_MODES: ReportComparisonMode[] = ["none", "previous_period", "previous_year"];
const GRANULARITIES: ReportGranularity[] = ["day", "week", "month", "quarter"];

function first(value: string | string[] | undefined): string {
  if (Array.isArray(value)) return value[0] ?? "";
  return value ?? "";
}

function parseEnum<T extends string>(raw: string, allowed: readonly T[], fallback: T): T {
  return (allowed as readonly string[]).includes(raw) ? (raw as T) : fallback;
}

function parsePositiveInt(raw: string, fallback: number): number {
  const n = Number.parseInt(raw, 10);
  return Number.isFinite(n) && n >= 1 ? n : fallback;
}

export function parseReportsQuery(
  searchParams: Record<string, string | string[] | undefined>,
): ReportsQuery {
  const preset = parseEnum(first(searchParams.datePreset), DATE_PRESETS, "current_year");
  const customStart = first(searchParams.startDate);
  const customEnd = first(searchParams.endDate);
  const resolved = resolveDatePreset(preset, customStart, customEnd);
  const currencyRaw = first(searchParams.currency);

  return {
    datePreset: preset,
    startDate: resolved.startDate,
    endDate: resolved.endDate,
    comparison: parseEnum(first(searchParams.comparison), COMPARISON_MODES, "none"),
    granularity: parseEnum(first(searchParams.granularity), GRANULARITIES, "month"),
    currency: (REPORT_SUPPORTED_CURRENCIES as readonly string[]).includes(currencyRaw)
      ? (currencyRaw as (typeof REPORT_SUPPORTED_CURRENCIES)[number])
      : "PKR",
    channel: first(searchParams.channel),
    supplier: first(searchParams.supplier),
    airline: first(searchParams.airline),
    agent: first(searchParams.agent),
    route: first(searchParams.route),
    bookingStatus: first(searchParams.bookingStatus) || "all",
    paymentStatus: first(searchParams.paymentStatus) || "all",
    ticketStatus: first(searchParams.ticketStatus) || "all",
    fulfilmentStatus: first(searchParams.fulfilmentStatus) || "all",
    page: parsePositiveInt(first(searchParams.page), 1),
    pageSize: [10, 20, 50].includes(parsePositiveInt(first(searchParams.pageSize), 20))
      ? parsePositiveInt(first(searchParams.pageSize), 20)
      : 20,
    sort: first(searchParams.sort) || "bookingDate",
    direction: first(searchParams.direction) === "asc" ? "asc" : "desc",
    previewError: first(searchParams.previewError) === "1",
    previewLoading: first(searchParams.previewLoading) === "1",
    previewEmpty: first(searchParams.previewEmpty) === "1",
  };
}

export function reportsQueryToSearchParams(query: ReportsQuery, overrides?: Partial<ReportsQuery>): string {
  const merged = { ...query, ...overrides };
  const params = new URLSearchParams();

  if (merged.datePreset !== "current_year") params.set("datePreset", merged.datePreset);
  if (merged.datePreset === "custom") {
    params.set("startDate", merged.startDate);
    params.set("endDate", merged.endDate);
  }
  if (merged.comparison !== "none") params.set("comparison", merged.comparison);
  if (merged.granularity !== "month") params.set("granularity", merged.granularity);
  if (merged.currency !== "PKR") params.set("currency", merged.currency);
  if (merged.channel) params.set("channel", merged.channel);
  if (merged.supplier) params.set("supplier", merged.supplier);
  if (merged.airline) params.set("airline", merged.airline);
  if (merged.agent) params.set("agent", merged.agent);
  if (merged.route) params.set("route", merged.route);
  if (merged.bookingStatus !== "all") params.set("bookingStatus", merged.bookingStatus);
  if (merged.paymentStatus !== "all") params.set("paymentStatus", merged.paymentStatus);
  if (merged.ticketStatus !== "all") params.set("ticketStatus", merged.ticketStatus);
  if (merged.fulfilmentStatus !== "all") params.set("fulfilmentStatus", merged.fulfilmentStatus);
  if (merged.page > 1) params.set("page", String(merged.page));
  if (merged.pageSize !== 20) params.set("pageSize", String(merged.pageSize));
  if (merged.sort !== "bookingDate") params.set("sort", merged.sort);
  if (merged.direction !== "desc") params.set("direction", merged.direction);
  if (merged.previewError) params.set("previewError", "1");
  if (merged.previewLoading) params.set("previewLoading", "1");
  if (merged.previewEmpty) params.set("previewEmpty", "1");

  const s = params.toString();
  return s ? `?${s}` : "";
}

export function defaultReportsQuery(): ReportsQuery {
  return parseReportsQuery({});
}
