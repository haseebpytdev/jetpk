"use client";

import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import {
  AccountStatusBadge,
  CommercialStatusBadge,
  SettlementStatusBadge,
} from "@/components/ui/status-badge";
import { formatCurrency, formatDate } from "@/lib/format";
import type { AgentRecord } from "@/types/agent";

type Props = {
  agents: AgentRecord[];
  onView: (id: string) => void;
};

export function AgentsMobileCards({ agents, onView }: Props) {
  return (
    <ul className="space-y-3 xl:hidden" data-testid="agents-mobile-cards">
      {agents.map((agent) => (
        <li key={agent.id}>
          <Card className="space-y-3">
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0">
                <p className="truncate font-semibold text-gray-900">{agent.agencyName}</p>
                <p className="text-xs text-jp-muted">{agent.id}</p>
              </div>
              <AccountStatusBadge status={agent.accountStatus} />
            </div>
            <p className="truncate text-sm text-gray-800">{agent.primaryContact}</p>
            <p className="text-sm text-jp-muted">
              {agent.city}, {agent.operatingRegion} · {agent.bookingCount} booking
              {agent.bookingCount === 1 ? "" : "s"}
            </p>
            <div className="flex flex-wrap gap-2">
              <CommercialStatusBadge status={agent.commercialStatus} />
              <SettlementStatusBadge status={agent.settlementStatus} />
              <span className="text-xs text-jp-muted">{agent.agentType}</span>
            </div>
            <div className="flex items-center justify-between text-sm">
              <span className="text-jp-muted">Commission pending</span>
              <span className="font-semibold tabular-nums">
                {formatCurrency(agent.commissionPending, agent.currency)}
              </span>
            </div>
            {agent.lastBookingDate ? (
              <p className="text-xs text-jp-muted">Last booking {formatDate(agent.lastBookingDate)}</p>
            ) : null}
            <Button variant="secondary" size="sm" className="w-full" onClick={() => onView(agent.id)}>
              View details
            </Button>
          </Card>
        </li>
      ))}
    </ul>
  );
}
