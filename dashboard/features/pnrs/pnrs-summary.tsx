import { MetricCard, MetricCardRow } from "@/components/ui/metric-card";
import type { PnrsSummaryMetrics } from "@/types/pnr";

export function PnrsSummary({ summary }: { summary: PnrsSummaryMetrics }) {
  return (
    <MetricCardRow aria-label="PNR summary metrics">
      <MetricCard
        label="Total records"
        value={summary.totalRecords}
        hint="Count after current filters"
      />
      <MetricCard label="Active records" value={summary.activeRecords} hint="Active or confirmed" />
      <MetricCard label="GDS PNRs" value={summary.gdsPnrCount} hint="Traditional GDS references" />
      <MetricCard label="NDC orders" value={summary.ndcOrderCount} hint="NDC order references" />
      <MetricCard
        label="Awaiting fulfilment"
        value={summary.awaitingFulfilment}
        hint="Pending or partial fulfilment"
      />
      <MetricCard
        label="Review required"
        value={summary.reviewRequired}
        hint="Lifecycle or queue review"
      />
      <MetricCard
        label="Approaching deadline"
        value={summary.approachingDeadline}
        hint="Ticketing deadline on or before Sep 2026"
      />
    </MetricCardRow>
  );
}
