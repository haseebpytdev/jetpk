import { MetricCard, MetricCardRow } from "@/components/ui/metric-card";
import { formatCurrency } from "@/lib/format";
import type { SuppliersSummaryMetrics } from "@/types/supplier";

export function SuppliersSummary({ summary }: { summary: SuppliersSummaryMetrics }) {
  return (
    <MetricCardRow aria-label="Supplier summary metrics">
      <MetricCard
        label="Total suppliers"
        value={summary.totalSuppliers}
        hint="Count after current filters"
      />
      <MetricCard label="Active suppliers" value={summary.activeSuppliers} hint="Operational status Active" />
      <MetricCard
        label="Connected"
        value={summary.connectedSuppliers}
        hint="Integration status Connected"
      />
      <MetricCard
        label="Requires review"
        value={summary.suppliersRequiringReview}
        hint="Review, overdue, or reconciliation"
      />
      <MetricCard
        label="Outstanding settlements"
        value={formatCurrency(summary.totalOutstandingSettlements, summary.currency)}
        hint="Sum of unsettled amounts"
      />
      <MetricCard
        label="Recent activity"
        value={summary.recentSupplierActivity}
        hint="Activity since 2026-01-01"
      />
    </MetricCardRow>
  );
}
