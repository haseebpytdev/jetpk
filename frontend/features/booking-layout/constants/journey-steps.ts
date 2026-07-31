import type { BookingProgressStep } from "@/features/booking-progress";

/**
 * Canonical display labels for the standard booking journey.
 * Laravel `progress[]` arrays remain authoritative for state and href.
 */
export const BOOKING_JOURNEY_STEP_LABELS: Record<string, string> = {
  search: "Search",
  results: "Results",
  flight_selected: "Results",
  fare_selection: "Select Fare",
  passenger_details: "Travelers",
  seat_extras: "Seats",
  review: "Review",
  payment: "Payment",
  confirmation: "Success",
};

/** Steps omitted from the visual stepper when Laravel marks them skipped. */
export function visibleProgressSteps(steps: BookingProgressStep[]): BookingProgressStep[] {
  return steps.filter((step) => step.state !== "skipped");
}

/** Renumber display index for visible steps only (conditional Seats omission). */
export function progressDisplayIndex(steps: BookingProgressStep[], key: string): number {
  const visible = visibleProgressSteps(steps);
  const index = visible.findIndex((step) => step.key === key);
  return index >= 0 ? index + 1 : 0;
}

/**
 * Pre-session progress for fare selection (before Laravel booking session exists).
 * Operational order per exception A: Search → Results → Fare Selection → Travelers → Review → Payment → Success.
 */
export function preSessionFareSelectionProgress(): BookingProgressStep[] {
  return [
    { key: "search", label: BOOKING_JOURNEY_STEP_LABELS.search, state: "completed", href: "/" },
    { key: "results", label: BOOKING_JOURNEY_STEP_LABELS.results, state: "completed", href: null },
    { key: "fare_selection", label: BOOKING_JOURNEY_STEP_LABELS.fare_selection, state: "current", href: null },
    { key: "passenger_details", label: BOOKING_JOURNEY_STEP_LABELS.passenger_details, state: "upcoming", href: null },
    { key: "review", label: BOOKING_JOURNEY_STEP_LABELS.review, state: "upcoming", href: null },
    { key: "payment", label: BOOKING_JOURNEY_STEP_LABELS.payment, state: "upcoming", href: null },
    { key: "confirmation", label: BOOKING_JOURNEY_STEP_LABELS.confirmation, state: "upcoming", href: null },
  ];
}
