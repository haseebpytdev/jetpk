"use client";

import { useLayoutEffect } from "react";
import { markBookNowTiming, restoreBookNowTimingFromStorage } from "@/features/flight-results/utils/book-now-timing";

/** Marks Traveler shell as soon as the route loading UI mounts (before JSON context). */
export function BookNowShellTimingMark() {
  useLayoutEffect(() => {
    restoreBookNowTimingFromStorage();
    markBookNowTiming("T8_shell_visible", { phase: "passengers_route_loading" });
  }, []);

  return null;
}
