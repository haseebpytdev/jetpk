import { formatCurrency } from "@/lib/format";
import type { ReportBreakdownRow, ReportChartSegment } from "@/types/report";
import type { ReportSupportedCurrency } from "@/lib/reports/constants";
import { chartColor } from "@/lib/reports/chart-colors";

export function buildBreakdownRows(
  entries: { id: string; label: string; value: number }[],
  currency: ReportSupportedCurrency | null,
  limit = 8,
): ReportBreakdownRow[] {
  const sorted = [...entries].sort((a, b) => b.value - a.value || a.label.localeCompare(b.label));
  const total = sorted.reduce((sum, e) => sum + e.value, 0);
  const top = sorted.slice(0, limit);
  const rest = sorted.slice(limit);
  const rows: ReportBreakdownRow[] = top.map((e) => ({
    id: e.id,
    label: e.label,
    value: e.value,
    formattedValue: currency ? formatCurrency(e.value, currency) : String(e.value),
    sharePercent: total > 0 ? Math.round((e.value / total) * 1000) / 10 : null,
    currency,
  }));
  if (rest.length > 0) {
    const otherValue = rest.reduce((sum, e) => sum + e.value, 0);
    rows.push({
      id: "other",
      label: "Other",
      value: otherValue,
      formattedValue: currency ? formatCurrency(otherValue, currency) : String(otherValue),
      sharePercent: total > 0 ? Math.round((otherValue / total) * 1000) / 10 : null,
      currency,
    });
  }
  return rows;
}

export function breakdownToChartSegments(rows: ReportBreakdownRow[]): ReportChartSegment[] {
  return rows.map((row, index) => ({
    id: row.id,
    label: row.label,
    value: row.value,
    formattedValue: row.formattedValue,
    color: chartColor(index),
    sharePercent: row.sharePercent,
  }));
}

export function countBreakdown(
  items: string[],
  labelFn: (key: string) => string = (k) => k,
): { id: string; label: string; value: number }[] {
  const counts = new Map<string, number>();
  for (const item of items) {
    counts.set(item, (counts.get(item) ?? 0) + 1);
  }
  return [...counts.entries()]
    .map(([key, value]) => ({ id: key, label: labelFn(key), value }))
    .sort((a, b) => b.value - a.value || a.label.localeCompare(b.label));
}
