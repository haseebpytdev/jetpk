import type { ReportMetric, ReportMetricKey, ReportMetricTrend } from "@/types/report";

const LOWER_IS_BETTER: ReportMetricKey[] = ["outstanding_balance", "refunded_amount", "pending_fulfilment_count", "review_required_count"];

export function safePercentChange(current: number, previous: number): number | null {
  if (previous === 0) {
    if (current === 0) return 0;
    return null;
  }
  return ((current - previous) / Math.abs(previous)) * 100;
}

export function formatPercentChange(value: number | null): string | null {
  if (value === null || !Number.isFinite(value)) return null;
  const rounded = Math.round(value * 10) / 10;
  if (rounded === 0) return "0%";
  return `${rounded > 0 ? "+" : ""}${rounded}%`;
}

export function comparisonTrend(
  key: ReportMetricKey,
  current: number | null,
  previous: number | null,
): { delta: number | null; deltaPercent: number | null; trend: ReportMetricTrend } {
  if (current === null || previous === null) {
    return { delta: null, deltaPercent: null, trend: "unavailable" };
  }
  const delta = current - previous;
  const deltaPercent = safePercentChange(current, previous);
  if (delta === 0) return { delta: 0, deltaPercent: deltaPercent ?? 0, trend: "neutral" };

  const increased = delta > 0;
  const lowerBetter = LOWER_IS_BETTER.includes(key);
  let trend: ReportMetricTrend;
  if (lowerBetter) {
    trend = increased ? "negative" : "positive";
  } else {
    trend = increased ? "positive" : "negative";
  }
  return { delta, deltaPercent, trend };
}

export function enrichMetricsWithComparison(
  current: ReportMetric[],
  previous: ReportMetric[],
  comparisonLabel: string | null,
): ReportMetric[] {
  const prevByKey = new Map(previous.map((m) => [m.key, m]));
  return current.map((metric) => {
    const prev = prevByKey.get(metric.key);
    if (!prev || metric.value === null || prev.value === null || !comparisonLabel) {
      return metric;
    }
    const { deltaPercent, trend } = comparisonTrend(metric.key, metric.value, prev.value);
    return {
      ...metric,
      trend,
      comparisonDelta: deltaPercent,
      comparisonLabel: comparisonLabel
        ? `${comparisonLabel}: ${prev.formattedValue}${deltaPercent !== null ? ` (${formatPercentChange(deltaPercent)})` : ""}`
        : null,
    };
  });
}
