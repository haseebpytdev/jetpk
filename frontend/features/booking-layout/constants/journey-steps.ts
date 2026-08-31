import type { BookingProgressStep } from "@/features/booking-progress";

/**
 * Canonical display labels for the standard booking journey.
 * Laravel `progress[]` arrays remain authoritative for state and href.
 */
export const BOOKING_JOURNEY_STEP_LABELS: Record<string, string> = {
  search: "Search",
  results: "Results",
  account: "Account",
  flight_selected: "Results",
  fare_selection: "Fare Selection",
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
