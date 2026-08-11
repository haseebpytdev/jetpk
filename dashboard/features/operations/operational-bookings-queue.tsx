"use client";

import Link from "next/link";
import { EmptyState } from "@/components/ui/empty-state";
import { useDashboardPortal } from "@/lib/portal-context";
import { dashboardHref } from "@/lib/portal-path";
import type { BookingRecord, BookingsPageResult, BookingsQueue } from "@/types/booking";

type Props = {
  queue: Exclude<BookingsQueue, "all">;
  title: string;
  description: string;
  result: BookingsPageResult;
  testId: string;
};

export function OperationalBookingsQueue({ queue, title, description, result, testId }: Props) {
  const portal = useDashboardPortal();
  const empty = result.total === 0;

  return (
    <div className="space-y-4" data-testid={testId} data-queue={queue}>
      <div>
        <h2 className="text-base font-semibold text-gray-900">{title}</h2>
        <p className="mt-1 text-sm text-jp-muted">{description}</p>
        <p className="mt-2 text-xs text-jp-muted" data-testid={`${testId}-count`}>
          {result.total} booking{result.total === 1 ? "" : "s"} in queue
        </p>
      </div>

      {empty ? (
        <EmptyState
          title="Queue is clear"
          description="No bookings currently match this operational queue."
        />
      ) : (
        <ul className="divide-y divide-jp-border rounded-xl border border-jp-border bg-white">
          {result.bookings.map((booking) => (
            <QueueRow key={booking.id} booking={booking} portal={portal} testId={testId} />
          ))}
        </ul>
      )}
    </div>
  );
}

function QueueRow({
  booking,
  portal,
  testId,
}: {
  booking: BookingRecord;
  portal: "admin" | "staff";
  testId: string;
}) {
  const href = dashboardHref(portal, `/bookings/${encodeURIComponent(booking.id)}`);

  return (
    <li className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between" data-testid={`${testId}-row`}>
      <div className="min-w-0 text-sm">
        <p className="font-medium text-gray-900">
          {booking.id}
          {booking.pnr ? ` · PNR ${booking.pnr}` : ""}
        </p>
        <p className="truncate text-jp-muted">
          {booking.customerName} · {booking.origin}→{booking.destination} · {booking.bookingStatus} /{" "}
          {booking.paymentStatus}
        </p>
      </div>
      <Link
        href={href}
        className="inline-flex min-h-11 items-center justify-center rounded-xl border border-jp-border px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-50"
        data-testid={`${testId}-open-${booking.id}`}
      >
        Open management
      </Link>
    </li>
  );
}
