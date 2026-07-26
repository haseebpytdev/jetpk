import { Card } from "@/components/ui/card";
import { ReportTrendBadge } from "@/components/ui/status-badge";
import type { ReportMetric } from "@/types/report";

export function ReportMetricCard({ metric }: { metric: ReportMetric }) {
  return (
    <Card className="p-4" data-testid={`report-metric-${metric.key}`}>
      <p className="text-xs text-jp-muted">{metric.label}</p>
      <p className="mt-1 text-lg font-semibold tabular-nums">{metric.formattedValue}</p>
      <div className="mt-2">
        <ReportTrendBadge trend={metric.trend} />
      </div>
      {metric.comparisonLabel ? (
        <p className="mt-2 text-xs text-jp-muted">{metric.comparisonLabel}</p>
      ) : null}
      {metric.unavailableReason ? (
        <p className="mt-2 text-xs text-jp-muted">{metric.unavailableReason}</p>
      ) : null}
    </Card>
  );
}

export function ReportMetricGrid({ metrics }: { metrics: ReportMetric[] }) {
  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-testid="reports-metric-grid">
      {metrics.map((metric, index) => (
        <ReportMetricCard key={`${metric.key}-${metric.label}-${index}`} metric={metric} />
      ))}
    </div>
  );
}
