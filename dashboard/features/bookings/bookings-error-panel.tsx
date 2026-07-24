"use client";

import { useRouter } from "next/navigation";
import { ErrorState } from "@/components/ui/error-state";
import { defaultBookingsQuery, bookingsQueryToSearchParams } from "@/lib/bookings-query";

export function BookingsErrorPanel({
  message,
  referenceId,
}: {
  message: string;
  referenceId: string;
}) {
  const router = useRouter();

  return (
    <ErrorState
      title="Could not load bookings"
      message={message}
      referenceId={referenceId}
      onRetry={() => {
        const q = defaultBookingsQuery();
        router.push(`/bookings${bookingsQueryToSearchParams(q)}`);
      }}
    />
  );
}
