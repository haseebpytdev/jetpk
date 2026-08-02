"use client";

import type { ApiResult } from "@/lib/api/types";

let sessionRecoveryInFlight = false;

/**
 * Navigate to login once when Laravel reports an expired session.
 * Prevents redirect storms from concurrent 401 responses.
 */
export function recoverFromUnauthorized(result: ApiResult<unknown>): boolean {
  if (result.ok || result.code !== "unauthorized") {
    return false;
  }

  if (typeof window === "undefined") {
    return true;
  }

  if (sessionRecoveryInFlight) {
    return true;
  }

  const path = window.location.pathname;
  if (path.startsWith("/login") || path.startsWith("/register")) {
    return true;
  }

  sessionRecoveryInFlight = true;
  const params = new URLSearchParams();
  params.set("reason", "session-expired");
  window.location.assign(`/login?${params.toString()}`);
  return true;
}

export function resetSessionRecoveryGuard(): void {
  sessionRecoveryInFlight = false;
}
