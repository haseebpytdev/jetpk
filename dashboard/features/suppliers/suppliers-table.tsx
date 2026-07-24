"use client";

import { Button } from "@/components/ui/button";
import {
  CredentialStatusBadge,
  IntegrationStatusBadge,
  OperationalStatusBadge,
  SettlementStatusBadge,
} from "@/components/ui/status-badge";
import { Table, TableBody, TableHead, TableRow, Td, Th } from "@/components/ui/table";
import { formatCurrency, formatDate } from "@/lib/format";
import type { SupplierRecord, SupplierSortField, SuppliersQuery } from "@/types/supplier";

type Props = {
  suppliers: SupplierRecord[];
  query: SuppliersQuery;
  onSort: (field: SupplierSortField) => void;
  onView: (id: string) => void;
};

function sortIndicator(active: boolean, direction: SuppliersQuery["direction"]) {
  if (!active) return " ↕";
  return direction === "asc" ? " ↑" : " ↓";
}

export function SuppliersTable({ suppliers, query, onSort, onView }: Props) {
  return (
    <div className="hidden md:block" data-testid="suppliers-table">
      <Table>
        <TableHead>
          <TableRow>
            <Th scope="col">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("supplierName")}
              >
                Supplier{sortIndicator(query.sort === "supplierName", query.direction)}
              </button>
            </Th>
            <Th scope="col">Category</Th>
            <Th scope="col">Region</Th>
            <Th scope="col">Operational</Th>
            <Th scope="col">Integration</Th>
            <Th scope="col">Credentials</Th>
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
                onClick={() => onSort("totalBookingValue")}
              >
                Booking value{sortIndicator(query.sort === "totalBookingValue", query.direction)}
              </button>
            </Th>
            <Th scope="col" className="text-right">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("outstandingSettlement")}
              >
                Outstanding{sortIndicator(query.sort === "outstandingSettlement", query.direction)}
              </button>
            </Th>
            <Th scope="col">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("lastActivity")}
              >
                Last activity{sortIndicator(query.sort === "lastActivity", query.direction)}
              </button>
            </Th>
            <Th scope="col" className="w-24">
              <span className="sr-only">Actions</span>
            </Th>
          </TableRow>
        </TableHead>
        <TableBody>
          {suppliers.map((supplier) => (
            <TableRow key={supplier.id}>
              <Td>
                <div className="font-medium text-gray-900">{supplier.supplierName}</div>
                <div className="text-xs text-jp-muted">
                  {supplier.displayCode} · {supplier.id}
                </div>
              </Td>
              <Td>{supplier.supplierCategory}</Td>
              <Td>{supplier.operatingRegion}</Td>
              <Td>
                <OperationalStatusBadge status={supplier.operationalStatus} />
              </Td>
              <Td>
                <IntegrationStatusBadge status={supplier.integrationStatus} />
              </Td>
              <Td>
                <CredentialStatusBadge status={supplier.credentialStatus} />
              </Td>
              <Td>
                <SettlementStatusBadge status={supplier.settlementStatus} />
              </Td>
              <Td className="text-right tabular-nums">{supplier.bookingCount}</Td>
              <Td className="text-right tabular-nums font-medium">
                {formatCurrency(supplier.totalBookingValue, supplier.currency)}
              </Td>
              <Td className="text-right tabular-nums">
                {formatCurrency(supplier.outstandingSettlement, supplier.currency)}
              </Td>
              <Td>
                {supplier.lastBookingActivity ? formatDate(supplier.lastBookingActivity) : "—"}
              </Td>
              <Td>
                <Button variant="secondary" size="sm" onClick={() => onView(supplier.id)}>
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
