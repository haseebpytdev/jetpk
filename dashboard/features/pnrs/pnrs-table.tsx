"use client";

import { Button } from "@/components/ui/button";
import {
  ChannelBadge,
  LifecycleStatusBadge,
  PnrTicketingStatusBadge,
  ReferenceTypeBadge,
} from "@/components/ui/status-badge";
import { Table, TableBody, TableHead, TableRow, Td, Th } from "@/components/ui/table";
import { formatCurrency, formatDate } from "@/lib/format";
import type { PnrRecord, PnrSortField, PnrsQuery } from "@/types/pnr";

type Props = {
  pnrs: PnrRecord[];
  query: PnrsQuery;
  onSort: (field: PnrSortField) => void;
  onView: (id: string) => void;
};

function sortIndicator(active: boolean, direction: PnrsQuery["direction"]) {
  if (!active) return " ↕";
  return direction === "asc" ? " ↑" : " ↓";
}

export function PnrsTable({ pnrs, query, onSort, onView }: Props) {
  return (
    <div className="hidden xl:block min-w-0 w-full max-w-full" data-testid="pnrs-table">
      <Table>
        <TableHead>
          <TableRow>
            <Th scope="col">Reference</Th>
            <Th scope="col">Type</Th>
            <Th scope="col">Channel</Th>
            <Th scope="col">Customer</Th>
            <Th scope="col">Itinerary</Th>
            <Th scope="col">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("departureDate")}
              >
                Departure{sortIndicator(query.sort === "departureDate", query.direction)}
              </button>
            </Th>
            <Th scope="col">Lifecycle</Th>
            <Th scope="col">Ticketing</Th>
            <Th scope="col" className="text-right">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("bookingValue")}
              >
                Value{sortIndicator(query.sort === "bookingValue", query.direction)}
              </button>
            </Th>
            <Th scope="col" className="w-24">
              <span className="sr-only">Actions</span>
            </Th>
          </TableRow>
        </TableHead>
        <TableBody>
          {pnrs.map((pnr) => (
            <TableRow key={pnr.id}>
              <Td>
                <div className="font-medium text-gray-900">{pnr.externalReference}</div>
                <div className="text-xs text-jp-muted">{pnr.id}</div>
              </Td>
              <Td>
                <ReferenceTypeBadge status={pnr.referenceType} />
              </Td>
              <Td>
                <ChannelBadge status={pnr.channel} />
              </Td>
              <Td>
                <div className="max-w-[10rem] truncate">{pnr.customerName}</div>
                <div className="text-xs text-jp-muted">{pnr.bookingId}</div>
              </Td>
              <Td>
                <div>{pnr.itinerarySummary}</div>
                <div className="text-xs text-jp-muted">
                  {pnr.airline} · {pnr.travellerCount} pax
                </div>
              </Td>
              <Td>{formatDate(pnr.departureDate)}</Td>
              <Td>
                <LifecycleStatusBadge status={pnr.lifecycleStatus} />
              </Td>
              <Td>
                <PnrTicketingStatusBadge status={pnr.ticketingStatus} />
              </Td>
              <Td className="text-right tabular-nums font-medium">
                {formatCurrency(pnr.bookingValue, pnr.currency)}
              </Td>
              <Td>
                <Button variant="secondary" size="sm" aria-label={pnr.id} onClick={() => onView(pnr.id)}>
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
