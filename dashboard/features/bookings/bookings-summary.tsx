import { MetricCard, MetricCardRow } from "@/components/ui/metric-card";
import { formatCurrency } from "@/lib/format";
import type { BookingsSummaryMetrics } from "@/types/booking";

export function BookingsSummary({ summary }: { summary: BookingsSummaryMetrics }) {
  return (
    <MetricCardRow aria-label="Booking summary metrics">
      <MetricCard
        label="Displayed"
        value={summary.totalDisplayed}
        hint="Count after current filters"
      />
      <MetricCard label="Confirmed" value={summary.confirmed} />
      <MetricCard label="Pending" value={summary.pending} />
      <MetricCard label="Cancelled / failed" value={summary.cancelledOrFailed} />
      <MetricCard label="Paid" value={summary.paid} hint="Payment status paid" />
      <MetricCard
        label="Outstanding"
        value={formatCurrency(summary.outstandingAmount, summary.currency)}
        hint="Sum of (total − paid) on filtered set"
      />
    </MetricCardRow>
  );
}
