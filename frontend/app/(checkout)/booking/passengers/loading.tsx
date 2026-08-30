import { BookingProgress } from "@/features/booking-progress";
import {
  BookingLayout,
  BookingMainColumn,
  BookingPageHeader,
  BookingPageShell,
  BookingSidebar,
} from "@/features/booking-layout";
import { BookNowShellTimingMark } from "@/features/standard-booking/components/BookNowShellTimingMark";

const FALLBACK_PROGRESS = [
  { key: "search", label: "Search", state: "completed" as const },
  { key: "results", label: "Results", state: "completed" as const },
  { key: "passenger_details", label: "Travelers", state: "current" as const },
  { key: "review", label: "Review", state: "upcoming" as const },
  { key: "payment", label: "Payment", state: "upcoming" as const },
];

/** Immediate booking shell — match stable traveler form dimensions (no duplicate loading copy). */
export default function Loading() {
  return (
    <BookingPageShell testId="passengers-route-loading">
      <BookNowShellTimingMark />
      <BookingProgress steps={FALLBACK_PROGRESS} className="mb-6" />
      <BookingPageHeader title="Traveler information" description="Confirm each traveler and contact details." />
      <BookingLayout
        main={
          <BookingMainColumn>
            <div className="space-y-4" aria-busy="true" data-testid="passenger-skeleton">
              <div className="min-h-[10rem] rounded-jp-card border border-jp-border bg-jp-surface p-4">
                <div className="h-4 w-28 animate-pulse rounded bg-jp-surface-muted" />
                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                  <div className="h-10 animate-pulse rounded-jp-md bg-jp-surface-muted" />
                  <div className="h-10 animate-pulse rounded-jp-md bg-jp-surface-muted" />
                  <div className="h-10 animate-pulse rounded-jp-md bg-jp-surface-muted" />
                  <div className="h-10 animate-pulse rounded-jp-md bg-jp-surface-muted" />
                </div>
              </div>
            </div>
          </BookingMainColumn>
        }
        sidebar={
          <BookingSidebar>
            <div
              className="min-h-[12rem] rounded-jp-card border border-jp-border bg-jp-surface p-4"
              data-testid="order-summary-skeleton"
            >
              <div className="h-4 w-24 animate-pulse rounded bg-jp-surface-muted" />
              <div className="mt-3 h-16 animate-pulse rounded-jp-md bg-jp-surface-muted" />
            </div>
          </BookingSidebar>
        }
      />
    </BookingPageShell>
  );
}
