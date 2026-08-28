import { BookingProgress } from "@/features/booking-progress";
import {
  BookingLayout,
  BookingMainColumn,
  BookingPageHeader,
  BookingPageShell,
  BookingSidebar,
} from "@/features/booking-layout";

const FALLBACK_PROGRESS = [
  { key: "search", label: "Search", state: "completed" as const },
  { key: "results", label: "Results", state: "completed" as const },
  { key: "passenger_details", label: "Travelers", state: "current" as const },
  { key: "review", label: "Review", state: "upcoming" as const },
  { key: "payment", label: "Payment", state: "upcoming" as const },
];

/** Immediate booking shell — avoid blank full-page loader while the passengers chunk loads. */
export default function Loading() {
  return (
    <BookingPageShell testId="passengers-route-loading">
      <BookingProgress steps={FALLBACK_PROGRESS} className="mb-6" />
      <BookingPageHeader title="Traveler information" description="Preparing your traveler form…" />
      <BookingLayout
        main={
          <BookingMainColumn>
            <div className="space-y-4" aria-busy="true" data-testid="passenger-skeleton">
              <div className="h-40 animate-pulse rounded-jp-card border border-jp-border bg-jp-surface" />
              <div className="h-40 animate-pulse rounded-jp-card border border-jp-border bg-jp-surface" />
            </div>
          </BookingMainColumn>
        }
        sidebar={
          <BookingSidebar>
            <div
              className="h-48 animate-pulse rounded-jp-card border border-jp-border bg-jp-surface"
              data-testid="order-summary-skeleton"
            />
          </BookingSidebar>
        }
      />
    </BookingPageShell>
  );
}
