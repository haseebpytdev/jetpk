import { Suspense } from "react";
import { FareSelectionPage } from "@/features/fare-selection";
import { BookingLoadingState } from "@/features/booking-layout";

export default function Page() {
  return (
    <Suspense fallback={<BookingLoadingState message="Loading fare options…" />}>
      <FareSelectionPage />
    </Suspense>
  );
}
