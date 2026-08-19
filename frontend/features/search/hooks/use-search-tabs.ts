"use client";

import { useCallback } from "react";
import type { TripType } from "../types";

export const TRIP_TYPES: TripType[] = ["one_way", "return", "multi_city"];

export const TRIP_TYPE_LABELS: Record<TripType, string> = {
  one_way: "One Way",
  return: "Return",
  multi_city: "Multi-City",
};

/** @deprecated Legacy tab keyboard helper — trip type now uses TripTypeDropdown. */
export function useSearchTabKeyboard(
  tripType: TripType,
  onTripTypeChange: (tripType: TripType) => void,
) {
  const handleKeyDown = useCallback(
    (event: React.KeyboardEvent<HTMLButtonElement>, current: TripType) => {
      const index = TRIP_TYPES.indexOf(current);
      if (index === -1) return;

      let nextIndex = index;
      if (event.key === "ArrowRight") nextIndex = (index + 1) % TRIP_TYPES.length;
      if (event.key === "ArrowLeft") nextIndex = (index - 1 + TRIP_TYPES.length) % TRIP_TYPES.length;
      if (event.key === "Home") nextIndex = 0;
      if (event.key === "End") nextIndex = TRIP_TYPES.length - 1;

      if (nextIndex !== index) {
        event.preventDefault();
        onTripTypeChange(TRIP_TYPES[nextIndex]!);
      }
    },
    [onTripTypeChange],
  );

  return { tripTypes: TRIP_TYPES, tripTypeLabels: TRIP_TYPE_LABELS, handleKeyDown };
}
