import { MetricCard, MetricCardRow } from "@/components/ui/metric-card";
import { formatCurrency } from "@/lib/format";
import type { PaymentsSummaryMetrics } from "@/types/payment";

export function PaymentsSummary({ summary }: { summary: PaymentsSummaryMetrics }) {
  return (
    <MetricCardRow aria-label="Payment summary metrics">
      <MetricCard
        label="Transactions"
        value={summary.totalTransactions}
        hint="Count after current filters"
      />
      <MetricCard
        label="Gross collected"
        value={formatCurrency(summary.grossCollected, summary.currency)}
        hint="Sum of successful payment gross amounts"
      />
      <MetricCard
        label="Net collected"
        value={formatCurrency(summary.netCollected, summary.currency)}
        hint="Gross minus fees and refunds"
      />
      <MetricCard
        label="Outstanding"
        value={formatCurrency(summary.outstandingAmount, summary.currency)}
        hint="Sum of booking balances (payment rows)"
      />
      <MetricCard
        label="Refunded"
        value={formatCurrency(summary.refundedAmount, summary.currency)}
        hint="Sum of successful refund amounts"
      />
      <MetricCard
        label="Failed / pending"
        value={summary.failedOrPendingCount}
        hint="Transactions awaiting or declined"
      />
      <MetricCard
        label="Unreconciled"
        value={summary.unreconciledCount}
        hint="Unreconciled or pending review"
      />
    </MetricCardRow>
  );
}
