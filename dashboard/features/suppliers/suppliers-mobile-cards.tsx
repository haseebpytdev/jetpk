"use client";

import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import {
  CredentialStatusBadge,
  OperationalStatusBadge,
  SettlementStatusBadge,
} from "@/components/ui/status-badge";
import { formatCurrency, formatDate } from "@/lib/format";
import type { SupplierRecord } from "@/types/supplier";

type Props = {
  suppliers: SupplierRecord[];
  onView: (id: string) => void;
};

export function SuppliersMobileCards({ suppliers, onView }: Props) {
  return (
    <ul className="space-y-3 md:hidden" data-testid="suppliers-mobile-cards">
      {suppliers.map((supplier) => (
        <li key={supplier.id}>
          <Card className="space-y-3">
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0">
                <p className="truncate font-semibold text-gray-900">{supplier.supplierName}</p>
                <p className="text-xs text-jp-muted">
                  {supplier.displayCode} · {supplier.supplierCategory}
                </p>
              </div>
              <OperationalStatusBadge status={supplier.operationalStatus} />
            </div>
            <p className="text-sm text-jp-muted">{supplier.operatingRegion}</p>
            <div className="flex flex-wrap gap-2">
              <CredentialStatusBadge status={supplier.credentialStatus} />
              <SettlementStatusBadge status={supplier.settlementStatus} />
            </div>
            <div className="flex items-center justify-between text-sm">
              <span className="text-jp-muted">{supplier.bookingCount} bookings</span>
              <span className="font-semibold tabular-nums">
                {formatCurrency(supplier.outstandingSettlement, supplier.currency)}
              </span>
            </div>
            {supplier.lastBookingActivity ? (
              <p className="text-xs text-jp-muted">
                Last activity {formatDate(supplier.lastBookingActivity)}
              </p>
            ) : null}
            <Button variant="secondary" size="sm" className="w-full" onClick={() => onView(supplier.id)}>
              View details
            </Button>
          </Card>
        </li>
      ))}
    </ul>
  );
}
