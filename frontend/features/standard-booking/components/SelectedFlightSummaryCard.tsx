import type { SelectedFlightSummary } from "../types";
import { OrderSummary } from "@/features/booking-layout";

type SelectedFlightSummaryCardProps = {
  itinerary: SelectedFlightSummary;
  travellerTotal: number;
  collapsed?: boolean;
};

/** @deprecated Use OrderSummary directly — thin compatibility wrapper. */
export function SelectedFlightSummaryCard({
  itinerary,
  travellerTotal,
  collapsed,
}: SelectedFlightSummaryCardProps) {
  return (
    <OrderSummary
      itinerary={itinerary}
      travellerTotal={travellerTotal}
      collapsed={collapsed}
      testId="selected-flight-summary"
    />
  );
}
