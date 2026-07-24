"use client";

import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import {
  LedgerPaymentStatusBadge,
  ReconciliationStatusBadge,
  TransactionTypeBadge,
} from "@/components/ui/status-badge";
import { formatCurrency, formatDate } from "@/lib/format";
import type { TransactionRecord } from "@/types/payment";

type Props = {
  transactions: TransactionRecord[];
  onView: (transactionId: string) => void;
};

export function PaymentsMobileCards({ transactions, onView }: Props) {
  return (
    <ul className="space-y-3 md:hidden" data-testid="payments-mobile-cards">
      {transactions.map((tx) => (
        <li key={tx.transactionId}>
          <Card className="space-y-3">
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0">
                <p className="truncate font-semibold text-gray-900">{tx.transactionId}</p>
                <p className="text-xs text-jp-muted">
                  {tx.bookingId} · PNR {tx.pnr}
                </p>
              </div>
              <p className="shrink-0 font-semibold tabular-nums">
                {formatCurrency(tx.grossAmount, tx.currency)}
              </p>
            </div>
            <p className="text-sm text-gray-800">{tx.customerName}</p>
            <p className="text-sm text-jp-muted">
              {formatDate(tx.transactionDate)} · {tx.paymentMethod.replace(/_/g, " ")}
            </p>
            <div className="flex flex-wrap gap-2">
              <TransactionTypeBadge type={tx.transactionType} />
              <LedgerPaymentStatusBadge status={tx.paymentStatus} />
              <ReconciliationStatusBadge status={tx.reconciliationStatus} />
            </div>
            <Button variant="secondary" size="sm" className="w-full" onClick={() => onView(tx.transactionId)}>
              View details
            </Button>
          </Card>
        </li>
      ))}
    </ul>
  );
}
