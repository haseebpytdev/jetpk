import { Card } from "@/components/ui/card";
import type { CmsMetric } from "@/types/cms";

export function CmsSummaryMetrics({ metrics }: { metrics: CmsMetric[] }) {
  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" data-testid="cms-metric-grid">
      {metrics.map((metric) => (
        <Card key={metric.key} className="p-4">
          <p className="text-xs text-jp-muted">{metric.label}</p>
          <p className="mt-1 text-2xl font-semibold text-gray-900">{metric.value}</p>
          {metric.description ? <p className="mt-1 text-xs text-jp-muted">{metric.description}</p> : null}
        </Card>
      ))}
    </div>
  );
}
