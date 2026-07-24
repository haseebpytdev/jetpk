import { MetricCard, MetricCardRow } from "@/components/ui/metric-card";
import { formatCurrency } from "@/lib/format";
import type { AgentsSummaryMetrics } from "@/types/agent";

export function AgentsSummary({ summary }: { summary: AgentsSummaryMetrics }) {
  return (
    <MetricCardRow aria-label="Agent summary metrics">
      <MetricCard
        label="Total agents"
        value={summary.totalAgents}
        hint="Count after current filters"
      />
      <MetricCard label="Active agents" value={summary.activeAgents} hint="Account status Active" />
      <MetricCard label="Verified agents" value={summary.verifiedAgents} hint="Verification Verified" />
      <MetricCard
        label="Overdue balances"
        value={summary.agentsWithOverdueBalances}
        hint="Agents with overdue settlement or balance"
      />
      <MetricCard
        label="Gross booking value"
        value={formatCurrency(summary.grossBookingValue, summary.currency)}
        hint="Sum of gross booking value"
      />
      <MetricCard
        label="Pending commission"
        value={formatCurrency(summary.pendingCommission, summary.currency)}
        hint="Commission awaiting payout"
      />
    </MetricCardRow>
  );
}
