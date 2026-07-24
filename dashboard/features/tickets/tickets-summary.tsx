import { MetricCard, MetricCardRow } from "@/components/ui/metric-card";
import { formatCurrency } from "@/lib/format";
import type { TicketsSummaryMetrics } from "@/types/ticket";

export function TicketsSummary({ summary }: { summary: TicketsSummaryMetrics }) {
  return (
    <MetricCardRow aria-label="Ticket summary metrics">
      <MetricCard
        label="Total documents"
        value={summary.totalDocuments}
        hint="Count after current filters"
      />
      <MetricCard label="Issued" value={summary.issued} hint="Issued or partially issued" />
      <MetricCard label="Pending" value={summary.pending} hint="Awaiting issue" />
      <MetricCard
        label="Blocked or failed"
        value={summary.blockedOrFailed}
        hint="Issue blocked or failed"
      />
      <MetricCard label="Refunded" value={summary.refunded} hint="Refund documents or status" />
      <MetricCard
        label="Document value"
        value={formatCurrency(summary.totalDocumentValue, summary.currency)}
        hint="Sum of document totals"
      />
      <MetricCard
        label="Upcoming travel"
        value={summary.upcomingTravel}
        hint="Travel from 2026-03-01 onward"
      />
    </MetricCardRow>
  );
}
