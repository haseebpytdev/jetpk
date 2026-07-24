"use client";

import { Button } from "@/components/ui/button";
import {
  LedgerPaymentStatusBadge,
  ReconciliationStatusBadge,
  TransactionTypeBadge,
} from "@/components/ui/status-badge";
import { Table, TableBody, TableHead, TableRow, Td, Th } from "@/components/ui/table";
import { formatCurrency, formatDate } from "@/lib/format";
import type { PaymentSortField, PaymentsQuery, TransactionRecord } from "@/types/payment";

type Props = {
  transactions: TransactionRecord[];
  query: PaymentsQuery;
  onSort: (field: PaymentSortField) => void;
  onView: (transactionId: string) => void;
};

function sortIndicator(active: boolean, direction: PaymentsQuery["direction"]) {
  if (!active) return " ↕";
  return direction === "asc" ? " ↑" : " ↓";
}

function methodLabel(method: TransactionRecord["paymentMethod"]): string {
  return method.replace(/_/g, " ");
}

export function PaymentsTable({ transactions, query, onSort, onView }: Props) {
  return (
    <div className="hidden md:block" data-testid="payments-table">
      <Table>
        <TableHead>
          <TableRow>
            <Th scope="col">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("transactionDate")}
              >
                Transaction / Payment{sortIndicator(query.sort === "transactionDate", query.direction)}
              </button>
            </Th>
            <Th scope="col">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("booking")}
              >
                Booking / PNR{sortIndicator(query.sort === "booking", query.direction)}
              </button>
            </Th>
            <Th scope="col">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("customer")}
              >
                Customer{sortIndicator(query.sort === "customer", query.direction)}
              </button>
            </Th>
            <Th scope="col">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("transactionDate")}
              >
                Date{sortIndicator(query.sort === "transactionDate", query.direction)}
              </button>
            </Th>
            <Th scope="col">Method / Channel</Th>
            <Th scope="col">Type</Th>
            <Th scope="col" className="text-right">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("grossAmount")}
              >
                Gross{sortIndicator(query.sort === "grossAmount", query.direction)}
              </button>
            </Th>
            <Th scope="col" className="text-right">Fee</Th>
            <Th scope="col" className="text-right">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("netAmount")}
              >
                Net{sortIndicator(query.sort === "netAmount", query.direction)}
              </button>
            </Th>
            <Th scope="col">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("paymentStatus")}
              >
                Payment{sortIndicator(query.sort === "paymentStatus", query.direction)}
              </button>
            </Th>
            <Th scope="col">
              <button
                type="button"
                className="font-semibold hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                onClick={() => onSort("reconciliationStatus")}
              >
                Reconciliation{sortIndicator(query.sort === "reconciliationStatus", query.direction)}
              </button>
            </Th>
            <Th scope="col" className="w-24">
              <span className="sr-only">Actions</span>
            </Th>
          </TableRow>
        </TableHead>
        <TableBody>
          {transactions.map((tx) => (
            <TableRow key={tx.transactionId}>
              <Td>
                <div className="font-medium text-gray-900">{tx.transactionId}</div>
                <div className="text-xs text-jp-muted">{tx.paymentId}</div>
              </Td>
              <Td>
                <div className="font-medium text-gray-900">{tx.bookingId}</div>
                <div className="text-xs text-jp-muted">PNR {tx.pnr}</div>
              </Td>
              <Td>
                <div>{tx.customerName}</div>
                <div className="text-xs text-jp-muted">{tx.customerEmail}</div>
              </Td>
              <Td>{formatDate(tx.transactionDate)}</Td>
              <Td>
                <div className="capitalize">{methodLabel(tx.paymentMethod)}</div>
                <div className="text-xs capitalize text-jp-muted">{tx.paymentChannel}</div>
              </Td>
              <Td>
                <TransactionTypeBadge type={tx.transactionType} />
              </Td>
              <Td className="text-right tabular-nums font-medium">
                {formatCurrency(tx.grossAmount, tx.currency)}
              </Td>
              <Td className="text-right tabular-nums text-jp-muted">
                {formatCurrency(tx.feeAmount, tx.currency)}
              </Td>
              <Td className="text-right tabular-nums font-medium">
                {formatCurrency(tx.netAmount, tx.currency)}
              </Td>
              <Td>
                <LedgerPaymentStatusBadge status={tx.paymentStatus} />
              </Td>
              <Td>
                <ReconciliationStatusBadge status={tx.reconciliationStatus} />
              </Td>
              <Td>
                <Button variant="secondary" size="sm" onClick={() => onView(tx.transactionId)}>
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
