"use client";

import { Button } from "@/components/ui/button";
import {
  DocumentTypeBadge,
  ExchangeEligibilityBadge,
  IssueStatusBadge,
  RefundEligibilityBadge,
  VoidStatusBadge,
} from "@/components/ui/status-badge";
import { Table, TableBody, TableHead, TableRow, Td, Th } from "@/components/ui/table";
import { formatCurrency, formatDate } from "@/lib/format";
import type { TicketRecord, TicketSortField, TicketsQuery } from "@/types/ticket";

type Props = {
  tickets: TicketRecord[];
  query: TicketsQuery;
  onSort: (field: TicketSortField) => void;
  onView: (id: string) => void;
};

function sortIndicator(active: boolean, direction: TicketsQuery["direction"]) {
  if (!active) return " ↕";
  return direction === "asc" ? " ↑" : " ↓";
}

export function TicketsTable({ tickets, query, onSort, onView }: Props) {
  return (
    <div className="hidden md:block" data-testid="tickets-table">
      <Table>
        <TableHead>
          <TableRow>
            <Th scope="col">Document</Th>
            <Th scope="col">Traveller</Th>
            <Th scope="col">Itinerary</Th>
            <Th scope="col">Channel</Th>
            <Th scope="col">Issue</Th>
            <Th scope="col">Fulfilment</Th>
            <Th scope="col" className="text-right">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("totalValue")}
              >
                Total{sortIndicator(query.sort === "totalValue", query.direction)}
              </button>
            </Th>
            <Th scope="col">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("travelDate")}
              >
                Travel{sortIndicator(query.sort === "travelDate", query.direction)}
              </button>
            </Th>
            <Th scope="col" className="w-24">
              <span className="sr-only">Actions</span>
            </Th>
          </TableRow>
        </TableHead>
        <TableBody>
          {tickets.map((ticket) => (
            <TableRow key={ticket.id}>
              <Td>
                <div className="font-medium text-gray-900">{ticket.maskedExternalId}</div>
                <div className="text-xs text-jp-muted">{ticket.id}</div>
                <div className="mt-1">
                  <DocumentTypeBadge type={ticket.documentType} />
                </div>
              </Td>
              <Td>
                <div className="max-w-[10rem] truncate font-medium">{ticket.travellerName}</div>
                <div className="text-xs text-jp-muted">{ticket.customerId}</div>
              </Td>
              <Td>
                <div className="max-w-[12rem] truncate">{ticket.itinerarySummary}</div>
                <div className="text-xs text-jp-muted">
                  {ticket.airline} · {ticket.supplier}
                </div>
              </Td>
              <Td>{ticket.channel}</Td>
              <Td>
                <IssueStatusBadge status={ticket.issueStatus} />
              </Td>
              <Td>
                <div className="flex flex-col gap-1">
                  <RefundEligibilityBadge status={ticket.refundEligibility} />
                  <VoidStatusBadge status={ticket.voidStatus} />
                </div>
              </Td>
              <Td className="text-right tabular-nums font-medium">
                {formatCurrency(ticket.total, ticket.currency)}
              </Td>
              <Td>{formatDate(ticket.travelDate)}</Td>
              <Td>
                <Button variant="secondary" size="sm" onClick={() => onView(ticket.id)}>
                  View
                </Button>
              </Td>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}
