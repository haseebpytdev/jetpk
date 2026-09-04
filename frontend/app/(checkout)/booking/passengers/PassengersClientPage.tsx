"use client";

import { useMemo } from "react";
import { useSearchParams } from "next/navigation";
import { PassengerDetailsPage } from "@/features/standard-booking/components/PassengerDetailsPage";
import { BookNowShellTimingMark } from "@/features/standard-booking/components/BookNowShellTimingMark";

/**
 * Client page so soft-nav mounts Traveler UI without awaiting an async server
 * searchParams Promise. Parent page.tsx wraps this in Suspense.
 */
export default function PassengersClientPage() {
  const searchParams = useSearchParams();
  const normalized = useMemo(() => {
    const next: Record<string, string | undefined> = {};
    searchParams.forEach((value, key) => {
      if (!(key in next)) next[key] = value;
    });
    return next;
  }, [searchParams]);

  return (
    <>
      <BookNowShellTimingMark />
      <PassengerDetailsPage searchParams={normalized} />
    </>
  );
}
