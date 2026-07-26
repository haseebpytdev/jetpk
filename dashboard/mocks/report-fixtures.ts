import { REPORT_REFERENCE_DATE } from "@/lib/reports/constants";
import type { ReportExportManifest, ReportsModuleKey } from "@/types/report";

export const reportSavedViews = [
  {
    id: "JP-RPT-VW-001",
    label: "Operations — last 30 days",
    module: "operations" as ReportsModuleKey,
    datePreset: "last_30_days" as const,
    comparison: "previous_period" as const,
    currency: "PKR" as const,
  },
  {
    id: "JP-RPT-VW-002",
    label: "Sales — current quarter",
    module: "sales" as ReportsModuleKey,
    datePreset: "current_quarter" as const,
    comparison: "none" as const,
    currency: "PKR" as const,
  },
  {
    id: "JP-RPT-VW-003",
    label: "Payments — previous month",
    module: "payments" as ReportsModuleKey,
    datePreset: "previous_month" as const,
    comparison: "previous_year" as const,
    currency: "PKR" as const,
  },
];

export const reportExportManifests: ReportExportManifest[] = [
  {
    id: "JP-RPT-EXP-001",
    reportKey: "bookings_summary",
    title: "Bookings summary export",
    generatedAt: REPORT_REFERENCE_DATE,
    dateRange: { preset: "last_30_days", startDate: "2026-06-02", endDate: "2026-07-01" },
    currency: "PKR",
    columns: [
      { key: "booking_id", header: "Booking ID", includeByDefault: true },
      { key: "route", header: "Route", includeByDefault: true },
      { key: "amount", header: "Amount", includeByDefault: true },
      { key: "status", header: "Status", includeByDefault: true },
    ],
    rowCount: 0,
    previewOnly: true,
  },
];

export const reportChartMetadata = [
  {
    id: "JP-RPT-CHT-001",
    key: "bookings_by_status",
    label: "Bookings by status",
    chartType: "bar" as const,
    module: "bookings" as ReportsModuleKey,
  },
  {
    id: "JP-RPT-CHT-002",
    key: "payments_collected",
    label: "Collected payments trend",
    chartType: "line" as const,
    module: "payments" as ReportsModuleKey,
  },
];
