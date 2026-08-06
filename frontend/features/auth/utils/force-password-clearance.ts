import type { SessionBootstrap } from "../types";
import {
  FORCE_PASSWORD_CLEARANCE_COOKIE,
  SESSION_FIXTURE_COOKIE,
  applyForcePasswordFixtureClearancePolicy,
  hasForcePasswordClearanceCookie,
  shouldWriteForcePasswordClearanceCookie,
} from "./force-password-clearance-policy.mjs";

export { FORCE_PASSWORD_CLEARANCE_COOKIE };

function readCookieValue(name: string): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  if (!match) return null;
  try {
    return decodeURIComponent(match[1]);
  } catch {
    return match[1];
  }
}

/**
 * Marks the force-password requirement cleared for fixture-backed SSR guards only.
 * No-op in normal production: requires an active force-password session fixture cookie.
 */
export function markForcePasswordRequirementCleared(): void {
  if (typeof document === "undefined") return;

  const fixtureValue = readCookieValue(SESSION_FIXTURE_COOKIE);
  if (!shouldWriteForcePasswordClearanceCookie(fixtureValue)) return;

  document.cookie = `${FORCE_PASSWORD_CLEARANCE_COOKIE}=1; Path=/; SameSite=Lax`;
}

export function applyForcePasswordFixtureClearance(
  cookies: Array<{ name: string; value: string }>,
  bootstrap: SessionBootstrap,
  fixtureValue: string,
  fixtureModeEnabled: boolean,
): SessionBootstrap {
  return applyForcePasswordFixtureClearancePolicy(
    bootstrap,
    fixtureModeEnabled,
    fixtureValue,
    cookies,
  ) as SessionBootstrap;
}

export { hasForcePasswordClearanceCookie };
