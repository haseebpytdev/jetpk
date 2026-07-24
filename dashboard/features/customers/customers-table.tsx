"use client";

import { Button } from "@/components/ui/button";
import { AccountStatusBadge, VerificationStatusBadge } from "@/components/ui/status-badge";
import { Table, TableBody, TableHead, TableRow, Td, Th } from "@/components/ui/table";
import { formatCurrency, formatDate } from "@/lib/format";
import type { CustomerRecord, CustomerSortField, CustomersQuery } from "@/types/customer";

type Props = {
  customers: CustomerRecord[];
  query: CustomersQuery;
  onSort: (field: CustomerSortField) => void;
  onView: (id: string) => void;
};

function sortIndicator(active: boolean, direction: CustomersQuery["direction"]) {
  if (!active) return " ↕";
  return direction === "asc" ? " ↑" : " ↓";
}

export function CustomersTable({ customers, query, onSort, onView }: Props) {
  return (
    <div className="hidden md:block" data-testid="customers-table">
      <Table>
        <TableHead>
          <TableRow>
            <Th scope="col">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("name")}
              >
                Customer{sortIndicator(query.sort === "name", query.direction)}
              </button>
            </Th>
            <Th scope="col">Contact</Th>
            <Th scope="col">Location</Th>
            <Th scope="col">Type</Th>
            <Th scope="col">Account</Th>
            <Th scope="col">Verification</Th>
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
                onClick={() => onSort("totalBookedValue")}
              >
                Booked value{sortIndicator(query.sort === "totalBookedValue", query.direction)}
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
          {customers.map((customer) => (
            <TableRow key={customer.id}>
              <Td>
                <div className="font-medium text-gray-900">{customer.fullName}</div>
                <div className="text-xs text-jp-muted">{customer.id}</div>
              </Td>
              <Td>
                <div className="max-w-[12rem] truncate">{customer.email}</div>
                <div className="text-xs text-jp-muted">{customer.phone}</div>
              </Td>
              <Td>
                <div>{customer.city}</div>
                <div className="text-xs text-jp-muted">{customer.country}</div>
              </Td>
              <Td>{customer.customerType}</Td>
              <Td>
                <AccountStatusBadge status={customer.accountStatus} />
              </Td>
              <Td>
                <VerificationStatusBadge status={customer.verificationStatus} />
              </Td>
              <Td className="text-right tabular-nums">{customer.bookingCount}</Td>
              <Td className="text-right tabular-nums font-medium">
                {formatCurrency(customer.totalBookedValue, customer.currency)}
              </Td>
              <Td className="text-right tabular-nums">
                {formatCurrency(customer.outstandingBalance, customer.currency)}
              </Td>
              <Td>{customer.lastBookingDate ? formatDate(customer.lastBookingDate) : "—"}</Td>
              <Td>
                <Button variant="secondary" size="sm" onClick={() => onView(customer.id)}>
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
