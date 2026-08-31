import { Suspense } from "react";
import { BookingAccountRequiredPage } from "@/features/standard-booking/components/BookingAccountRequiredPage";

export default function Page() {
  return (
    <Suspense fallback={<p className="p-8 text-sm text-jp-muted">Loading account step…</p>}>
      <BookingAccountRequiredPage />
    </Suspense>
  );
}
