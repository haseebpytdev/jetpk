"use client";

import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import {
  DocumentTypeBadge,
  IssueStatusBadge,
  RefundEligibilityBadge,
} from "@/components/ui/status-badge";
import { formatCurrency, formatDate } from "@/lib/format";
import type { TicketRecord } from "@/types/ticket";

type Props = {
  tickets: TicketRecord[];
  onView: (id: string) => void;
};

export function TicketsMobileCards({ tickets, onView }: Props) {
  return (
    <ul className="space-y-3 xl:hidden" data-testid="tickets-mobile-cards">
      {tickets.map((ticket) => (
        <li key={ticket.id}>
          <Card className="space-y-3">
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0">
                <p className="truncate font-mono text-sm font-semibold text-gray-900">
                  {ticket.maskedExternalId}
                </p>
                <p className="text-xs text-jp-muted">{ticket.id}</p>
              </div>
              <DocumentTypeBadge type={ticket.documentType} />
            </div>
            <p className="truncate text-sm font-medium text-gray-800">{ticket.travellerName}</p>
            <p className="text-sm text-jp-muted">{ticket.itinerarySummary}</p>
            <div className="flex flex-wrap gap-2">
              <IssueStatusBadge status={ticket.issueStatus} />
              <RefundEligibilityBadge status={ticket.refundEligibility} />
            </div>
            <div className="flex items-center justify-between text-sm">
              <span className="text-jp-muted">Total</span>
              <span className="font-semibold tabular-nums">
                {formatCurrency(ticket.total, ticket.currency)}
              </span>
            </div>
            <p className="text-xs text-jp-muted">Travel {formatDate(ticket.travelDate)}</p>
            <Button variant="secondary" size="sm" className="w-full" onClick={() => onView(ticket.id)}>
              View details
            </Button>
          </Card>
        </li>
      ))}
    </ul>
  );
}
