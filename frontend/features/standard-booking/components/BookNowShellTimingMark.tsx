"use client";

import { useLayoutEffect } from "react";
import { markBookNowTiming, restoreBookNowTimingFromStorage } from "@/features/flight-results/utils/book-now-timing";
import { primeStandardPassengersContext } from "../services/standard-booking-api";

function paramsFromLocation(): Record<string, string | undefined> {
  const params: Record<string, string | undefined> = {};
  if (typeof window === "undefined") return params;
  new URLSearchParams(window.location.search).forEach((value, key) => {
    if (!(key in params)) params[key] = value;
  });
  return params;
}

/** Marks Traveler shell as soon as the route loading UI mounts (before JSON context). */
export function BookNowShellTimingMark() {
  // Sync prime during render — do not wait for useLayoutEffect/paint.
  if (typeof window !== "undefined") {
    try {
      primeStandardPassengersContext(paramsFromLocation());
    } catch {
      /* best-effort */
    }
  }

  useLayoutEffect(() => {
    restoreBookNowTimingFromStorage();
    markBookNowTiming("T8_shell_visible", { phase: "passengers_route_loading" });
    try {
      primeStandardPassengersContext(paramsFromLocation());
    } catch {
      /* best-effort early fetch */
    }
  }, []);

  return null;
}
