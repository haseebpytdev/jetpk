"use client";

import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { AccountStatusBadge, VerificationStatusBadge } from "@/components/ui/status-badge";
import { formatCurrency, formatDate } from "@/lib/format";
import type { CustomerRecord } from "@/types/customer";

type Props = {
  customers: CustomerRecord[];
  onView: (id: string) => void;
};

export function CustomersMobileCards({ customers, onView }: Props) {
  return (
    <ul className="space-y-3 md:hidden" data-testid="customers-mobile-cards">
      {customers.map((customer) => (
        <li key={customer.id}>
          <Card className="space-y-3">
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0">
                <p className="truncate font-semibold text-gray-900">{customer.fullName}</p>
                <p className="text-xs text-jp-muted">{customer.id}</p>
              </div>
              <AccountStatusBadge status={customer.accountStatus} />
            </div>
            <p className="truncate text-sm text-gray-800">{customer.email}</p>
            <p className="text-sm text-jp-muted">
              {customer.city}, {customer.country} · {customer.bookingCount} booking
              {customer.bookingCount === 1 ? "" : "s"}
            </p>
            <div className="flex flex-wrap gap-2">
              <VerificationStatusBadge status={customer.verificationStatus} />
              <span className="text-xs text-jp-muted">{customer.customerType}</span>
            </div>
            <div className="flex items-center justify-between text-sm">
              <span className="text-jp-muted">Outstanding</span>
              <span className="font-semibold tabular-nums">
                {formatCurrency(customer.outstandingBalance, customer.currency)}
              </span>
            </div>
            {customer.lastBookingDate ? (
              <p className="text-xs text-jp-muted">Last booking {formatDate(customer.lastBookingDate)}</p>
            ) : null}
            <Button variant="secondary" size="sm" className="w-full" onClick={() => onView(customer.id)}>
              View details
            </Button>
          </Card>
        </li>
      ))}
    </ul>
  );
}
