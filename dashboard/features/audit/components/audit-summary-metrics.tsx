"use client";

import { Card, CardDescription, CardTitle } from "@/components/ui/card";
import type { AuditSummaryMetrics } from "@/types/audit";

const METRICS: { key: keyof AuditSummaryMetrics; label: string }[] = [
  { key: "totalEvents", label: "Total events" },
  { key: "securityEvents", label: "Security events" },
  { key: "warningCriticalEvents", label: "Warning / critical" },
  { key: "successfulOutcomes", label: "Successful outcomes" },
  { key: "deniedOutcomes", label: "Denied outcomes" },
  { key: "previewOnlyEvents", label: "Preview-only events" },
  { key: "highRiskEvents", label: "High-risk events" },
  { key: "eventsRequiringReview", label: "Requiring review" },
];

export function AuditSummaryMetrics({
  summary,
  invalidDate,
}: {
  summary: AuditSummaryMetrics;
  invalidDate?: boolean;
}) {
  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8" data-testid="audit-metric-grid">
      {METRICS.map((metric) => (
        <Card key={metric.key} className="p-3" data-testid={`audit-metric-${metric.key}`}>
          <CardDescription className="text-xs">{metric.label}</CardDescription>
          <CardTitle className="mt-1 text-xl tabular-nums" aria-hidden={invalidDate}>
            {invalidDate ? "—" : summary[metric.key]}
          </CardTitle>
          {invalidDate ? (
            <span className="sr-only">Unavailable due to invalid date range</span>
          ) : null}
        </Card>
      ))}
    </div>
  );
}
