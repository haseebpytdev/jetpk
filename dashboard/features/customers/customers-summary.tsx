import { MetricCard, MetricCardRow } from "@/components/ui/metric-card";
import { formatCurrency } from "@/lib/format";
import type { CustomersSummaryMetrics } from "@/types/customer";

export function CustomersSummary({ summary }: { summary: CustomersSummaryMetrics }) {
  return (
    <MetricCardRow aria-label="Customer summary metrics">
      <MetricCard
        label="Total customers"
        value={summary.totalCustomers}
        hint="Count after current filters"
      />
      <MetricCard label="Active customers" value={summary.activeCustomers} hint="Account status Active" />
      <MetricCard label="Total travellers" value={summary.totalTravellers} hint="Traveller profiles" />
      <MetricCard
        label="Outstanding balances"
        value={summary.customersWithOutstanding}
        hint="Customers with balance due"
      />
      <MetricCard
        label="Lifetime value"
        value={formatCurrency(summary.totalLifetimeValue, summary.currency)}
        hint="Sum of total booked value"
      />
      <MetricCard
        label="Recent customers"
        value={summary.recentCustomers}
        hint="Created since 2026-01-01"
      />
    </MetricCardRow>
  );
}
