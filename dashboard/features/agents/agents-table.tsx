"use client";

import { Button } from "@/components/ui/button";
import {
  AccountStatusBadge,
  CommercialStatusBadge,
  SettlementStatusBadge,
  VerificationStatusBadge,
} from "@/components/ui/status-badge";
import { Table, TableBody, TableHead, TableRow, Td, Th } from "@/components/ui/table";
import { formatCurrency, formatDate } from "@/lib/format";
import type { AgentRecord, AgentSortField, AgentsQuery } from "@/types/agent";

type Props = {
  agents: AgentRecord[];
  query: AgentsQuery;
  onSort: (field: AgentSortField) => void;
  onView: (id: string) => void;
};

function sortIndicator(active: boolean, direction: AgentsQuery["direction"]) {
  if (!active) return " ↕";
  return direction === "asc" ? " ↑" : " ↓";
}

export function AgentsTable({ agents, query, onSort, onView }: Props) {
  return (
    <div className="hidden md:block" data-testid="agents-table">
      <Table>
        <TableHead>
          <TableRow>
            <Th scope="col">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("agentName")}
              >
                Agent{sortIndicator(query.sort === "agentName", query.direction)}
              </button>
            </Th>
            <Th scope="col">Location</Th>
            <Th scope="col">Type</Th>
            <Th scope="col">Account</Th>
            <Th scope="col">Commercial</Th>
            <Th scope="col">Settlement</Th>
            <Th scope="col" className="text-right">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("bookingCount")}
              >
                Bookings{sortIndicator(query.sort === "bookingCount", query.direction)}
              </button>
            </Th>
            <Th scope="col" className="text-right">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("grossBookingValue")}
              >
                Gross value{sortIndicator(query.sort === "grossBookingValue", query.direction)}
              </button>
            </Th>
            <Th scope="col" className="text-right">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("outstandingBalance")}
              >
                Outstanding{sortIndicator(query.sort === "outstandingBalance", query.direction)}
              </button>
            </Th>
            <Th scope="col" className="text-right">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("commissionPending")}
              >
                Commission{sortIndicator(query.sort === "commissionPending", query.direction)}
              </button>
            </Th>
            <Th scope="col">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("lastBookingDate")}
              >
                Last booking{sortIndicator(query.sort === "lastBookingDate", query.direction)}
              </button>
            </Th>
            <Th scope="col" className="w-24">
              <span className="sr-only">Actions</span>
            </Th>
          </TableRow>
        </TableHead>
        <TableBody>
          {agents.map((agent) => (
            <TableRow key={agent.id}>
              <Td>
                <div className="font-medium text-gray-900">{agent.agencyName}</div>
                <div className="text-xs text-jp-muted">
                  {agent.id} · {agent.tradingName}
                </div>
              </Td>
              <Td>
                <div>{agent.city}</div>
                <div className="text-xs text-jp-muted">{agent.operatingRegion}</div>
              </Td>
              <Td>{agent.agentType}</Td>
              <Td>
                <AccountStatusBadge status={agent.accountStatus} />
              </Td>
              <Td>
                <div className="flex flex-col gap-1">
                  <CommercialStatusBadge status={agent.commercialStatus} />
                  <VerificationStatusBadge status={agent.verificationStatus} />
                </div>
              </Td>
              <Td>
                <SettlementStatusBadge status={agent.settlementStatus} />
              </Td>
              <Td className="text-right tabular-nums">{agent.bookingCount}</Td>
              <Td className="text-right tabular-nums font-medium">
                {formatCurrency(agent.grossBookingValue, agent.currency)}
              </Td>
              <Td className="text-right tabular-nums">
                {formatCurrency(agent.outstandingCustomerBalance, agent.currency)}
              </Td>
              <Td className="text-right tabular-nums">
                {formatCurrency(agent.commissionPending, agent.currency)}
              </Td>
              <Td>{agent.lastBookingDate ? formatDate(agent.lastBookingDate) : "—"}</Td>
              <Td>
                <Button variant="secondary" size="sm" onClick={() => onView(agent.id)}>
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
