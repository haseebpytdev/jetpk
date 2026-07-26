/** Deterministic chart palette using dashboard semantic tones. */
export const REPORT_CHART_COLORS = [
  "#10B981",
  "#3B82F6",
  "#8B5CF6",
  "#F59E0B",
  "#EF4444",
  "#06B6D4",
  "#EC4899",
  "#6B7280",
] as const;

export function chartColor(index: number): string {
  return REPORT_CHART_COLORS[index % REPORT_CHART_COLORS.length]!;
}
