"use client";

import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import {
  ChannelBadge,
  LifecycleStatusBadge,
  ReferenceTypeBadge,
} from "@/components/ui/status-badge";
import { formatCurrency, formatDate } from "@/lib/format";
import type { PnrRecord } from "@/types/pnr";

type Props = {
  pnrs: PnrRecord[];
  onView: (id: string) => void;
};

export function PnrsMobileCards({ pnrs, onView }: Props) {
  return (
    <ul className="space-y-3 md:hidden" data-testid="pnrs-mobile-cards">
      {pnrs.map((pnr) => (
        <li key={pnr.id}>
          <Card className="space-y-3">
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0">
                <p className="truncate font-semibold text-gray-900">{pnr.externalReference}</p>
                <p className="text-xs text-jp-muted">{pnr.id}</p>
              </div>
              <ReferenceTypeBadge status={pnr.referenceType} />
            </div>
            <div className="flex flex-wrap gap-2">
              <ChannelBadge status={pnr.channel} />
              <LifecycleStatusBadge status={pnr.lifecycleStatus} />
            </div>
            <p className="text-sm text-gray-800">{pnr.itinerarySummary}</p>
            <p className="text-sm text-jp-muted">
              {pnr.customerName} · {formatDate(pnr.departureDate)}
            </p>
            <div className="flex items-center justify-between text-sm">
              <span className="text-jp-muted">Booking value</span>
              <span className="font-semibold tabular-nums">
                {formatCurrency(pnr.bookingValue, pnr.currency)}
              </span>
            </div>
            <Button variant="secondary" size="sm" className="w-full" onClick={() => onView(pnr.id)}>
              View details
            </Button>
          </Card>
        </li>
      ))}
    </ul>
  );
}
