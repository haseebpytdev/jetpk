import type { ReportSupportedCurrency } from "@/lib/reports/constants";

export type ReportDatePreset =
  | "last_7_days"
  | "last_30_days"
  | "current_month"
  | "previous_month"
  | "current_quarter"
  | "previous_quarter"
  | "current_year"
  | "custom";

export type ReportGranularity = "day" | "week" | "month" | "quarter";

export type ReportComparisonMode = "none" | "previous_period" | "previous_year";

export type ReportCurrencyMode = "single" | "filter_by_currency";

export type ReportMetricTrend = "positive" | "negative" | "neutral" | "warning" | "unavailable";

export type ReportMetricKey =
  | "gross_booking_value"
  | "collected_payments"
  | "outstanding_balance"
  | "refunded_amount"
  | "booking_count"
  | "customer_count"
  | "agent_assisted_booking_count"
  | "direct_booking_count"
  | "supplier_exposure"
  | "issued_ticket_count"
  | "pending_fulfilment_count"
  | "pnr_order_count"
  | "gds_share"
  | "ndc_share"
  | "cancellation_eligible_count"
  | "review_required_count";

export type ReportDateRange = {
  preset: ReportDatePreset;
  startDate: string;
  endDate: string;
};

export type ReportComparisonPeriod = {
  mode: ReportComparisonMode;
  startDate: string | null;
  endDate: string | null;
  label: string;
};

export type ReportFilterState = {
  datePreset: ReportDatePreset;
  startDate: string;
  endDate: string;
  comparison: ReportComparisonMode;
  granularity: ReportGranularity;
  currency: ReportSupportedCurrency | "all";
  channel: string;
  supplier: string;
  airline: string;
  agent: string;
  route: string;
  bookingStatus: string;
  paymentStatus: string;
  ticketStatus: string;
  fulfilmentStatus: string;
  page: number;
  pageSize: number;
  sort: string;
  direction: "asc" | "desc";
  previewError: boolean;
  previewLoading: boolean;
  previewEmpty: boolean;
};

export type ReportMetric = {
  key: ReportMetricKey;
  label: string;
  value: number | null;
  formattedValue: string;
  currency: ReportSupportedCurrency | null;
  trend: ReportMetricTrend;
  comparisonDelta: number | null;
  comparisonLabel: string | null;
  unavailableReason: string | null;
};

export type ReportSeriesPoint = {
  periodStart: string;
  periodEnd: string;
  label: string;
  value: number;
};

export type ReportSeries = {
  key: string;
  label: string;
  currency: ReportSupportedCurrency | null;
  points: ReportSeriesPoint[];
};

export type ReportBreakdownRow = {
  id: string;
  label: string;
  value: number;
  formattedValue: string;
  sharePercent: number | null;
  currency: ReportSupportedCurrency | null;
};

export type ReportTableColumn = {
  key: string;
  label: string;
  align?: "start" | "center" | "end";
  sortable?: boolean;
};

export type ReportExportColumn = {
  key: string;
  header: string;
  includeByDefault: boolean;
};

export type ReportExportManifest = {
  id: string;
  reportKey: string;
  title: string;
  generatedAt: string;
  dateRange: ReportDateRange;
  currency: ReportSupportedCurrency | "all";
  columns: ReportExportColumn[];
  rowCount: number;
  previewOnly: true;
};

export type ReportValidationResult = {
  valid: boolean;
  issues: { code: string; message: string; field?: string }[];
};

export type ReportDataState = "loading" | "empty" | "error" | "ready";

export type ReportsQuery = ReportFilterState;

export type ReportsModuleKey = "overview" | "sales" | "bookings" | "payments" | "operations";

export type ReportsFoundationResult = {
  state: ReportDataState;
  dateRange: ReportDateRange;
  comparison: ReportComparisonPeriod;
  metrics: ReportMetric[];
  validation: ReportValidationResult;
  referenceDate: string;
};

export type ReportFacets = {
  suppliers: string[];
  airlines: string[];
  agents: string[];
  routes: string[];
  channels: { value: string; label: string }[];
};

export type ReportAttentionCategory =
  | "outstanding_balance"
  | "payment_reconciliation"
  | "pending_fulfilment"
  | "ticketing_blocked"
  | "supplier_response_pending"
  | "review_required"
  | "cancellation_eligible"
  | "deadline_expiring";

export type ReportAttentionItem = {
  id: string;
  category: ReportAttentionCategory;
  categoryLabel: string;
  title: string;
  description: string;
  href: string;
  linkLabel: string;
};

export type ReportChartSegment = {
  id: string;
  label: string;
  value: number;
  formattedValue: string;
  color: string;
  sharePercent: number | null;
};

export type ReportFunnelStage = {
  id: string;
  label: string;
  count: number;
  description: string;
  statusType: "booking" | "payment" | "pnr" | "ticket";
};

export type ReportTableRow = Record<string, string | number | null>;

export type ReportModuleTable = {
  columns: ReportTableColumn[];
  rows: ReportTableRow[];
  total: number;
  page: number;
  pageSize: number;
  pageCount: number;
};

export type ReportModuleResult = {
  state: ReportDataState;
  module: ReportsModuleKey;
  query: ReportsQuery;
  dateRange: ReportDateRange;
  comparison: ReportComparisonPeriod;
  validation: ReportValidationResult;
  referenceDate: string;
  metrics: ReportMetric[];
  facets: ReportFacets;
  series: Record<string, ReportSeries>;
  breakdowns: Record<string, ReportBreakdownRow[]>;
  charts: Record<string, ReportChartSegment[]>;
  attentionQueue: ReportAttentionItem[];
  funnel: ReportFunnelStage[];
  table: ReportModuleTable;
  exportRows: ReportTableRow[];
  exportManifest: ReportExportManifest;
  limitationNotices: string[];
};
