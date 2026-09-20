"use client";

import { useCallback } from "react";
import type { TripType } from "../types";

export const TRIP_TYPES: TripType[] = ["one_way", "return", "multi_city"];

export const TRIP_TYPE_LABELS: Record<TripType, string> = {
  one_way: "One Way",
  return: "Return",
  multi_city: "Multi-City",
};

/** Narrow mobile visible labels — full semantics stay on aria-label. */
export const TRIP_TYPE_COMPACT_LABELS: Record<TripType, string> = {
  one_way: "One",
  return: "Return",
  multi_city: "Multi",
};

/** @deprecated Prefer TRIP_TYPE_LABELS — kept for any residual callers. */
export const MODE_LABELS = {
  ...TRIP_TYPE_LABELS,
  group: "Group Ticketing",
} as const;

export function useSearchTabKeyboard(
  mode: TripType,
  onModeChange: (mode: TripType) => void,
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
        const nextMode = TRIP_TYPES[nextIndex]!;
        onModeChange(nextMode);
        const tabId = `search-tab-${nextMode}`;
        requestAnimationFrame(() => document.getElementById(tabId)?.focus());
      }
    },
    [onModeChange],
  );

  return {
    modes: TRIP_TYPES,
    modeLabels: TRIP_TYPE_LABELS,
    compactModeLabels: TRIP_TYPE_COMPACT_LABELS,
    handleKeyDown,
  };
}
