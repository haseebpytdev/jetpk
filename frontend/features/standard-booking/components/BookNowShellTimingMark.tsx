"use client";

import { useLayoutEffect } from "react";
import { markBookNowTiming, restoreBookNowTimingFromStorage } from "@/features/flight-results/utils/book-now-timing";
import { primeStandardPassengersContext } from "../services/standard-booking-api";

/** Marks Traveler shell as soon as the route loading UI mounts (before JSON context). */
export function BookNowShellTimingMark() {
  useLayoutEffect(() => {
    restoreBookNowTimingFromStorage();
    markBookNowTiming("T8_shell_visible", { phase: "passengers_route_loading" });
    // Start authoritative passengers GET during shell — do not wait for Suspense/page mount.
    try {
      const params: Record<string, string | undefined> = {};
      new URLSearchParams(window.location.search).forEach((value, key) => {
        if (!(key in params)) params[key] = value;
      });
      primeStandardPassengersContext(params);
    } catch {
      /* best-effort early fetch */
    }
  }, []);

  return null;
}
