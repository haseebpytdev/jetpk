"use client";

import { buildCsvContent } from "@/lib/csv-safe";
import type { ReportModuleResult } from "@/types/report";

export function downloadReportCsv(result: ReportModuleResult, rows: Record<string, string | number | null>[]): void {
  const headers = result.exportManifest.columns.map((c) => c.header);
  const keys = result.exportManifest.columns.map((c) => c.key);
  const csvRows = rows.map((row) => keys.map((k) => row[k] ?? ""));
  const content = buildCsvContent(headers, csvRows);
  const blob = new Blob([content], { type: "text/csv;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  const range = `${result.dateRange.startDate}_${result.dateRange.endDate}`;
  anchor.href = url;
  anchor.download = `jetpakistan-${result.module}-report-${range}-preview.csv`;
  anchor.click();
  URL.revokeObjectURL(url);
}
