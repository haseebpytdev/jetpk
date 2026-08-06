/**
 * Shared force-password fixture clearance policy for SSR guards and regression tests.
 * Keep behavior aligned with frontend/features/auth/utils/force-password-clearance.ts.
 */

export const FORCE_PASSWORD_CLEARANCE_COOKIE = "ota_force_password_cleared";
export const SESSION_FIXTURE_COOKIE = "ota_session_fixture";

/** Fixture values that simulate an active force-password requirement. */
export const FORCE_PASSWORD_SESSION_FIXTURE_VALUES = new Set([
  "customer_force_password",
  "agent_force_password",
]);

/**
 * @param {string | null | undefined} fixtureValue
 */
export function shouldWriteForcePasswordClearanceCookie(fixtureValue) {
  return FORCE_PASSWORD_SESSION_FIXTURE_VALUES.has(fixtureValue ?? "");
}

/**
 * @param {boolean} fixtureModeEnabled
 * @param {string | null | undefined} fixtureValue
 * @param {boolean} clearancePresent
 */
export function shouldHonorForcePasswordClearanceCookie(
  fixtureModeEnabled,
  fixtureValue,
  clearancePresent,
) {
  if (!fixtureModeEnabled) return false;
  if (!FORCE_PASSWORD_SESSION_FIXTURE_VALUES.has(fixtureValue ?? "")) return false;
  return clearancePresent;
}

/**
 * @param {Array<{ name: string; value: string }>} cookies
 */
export function hasForcePasswordClearanceCookie(cookies) {
  return cookies.some(
    (cookie) => cookie.name === FORCE_PASSWORD_CLEARANCE_COOKIE && cookie.value === "1",
  );
}

/**
 * @param {{ requires_password_change?: boolean }} bootstrap
 * @param {boolean} fixtureModeEnabled
 * @param {string | null | undefined} fixtureValue
 * @param {Array<{ name: string; value: string }>} cookies
 */
export function applyForcePasswordFixtureClearancePolicy(
  bootstrap,
  fixtureModeEnabled,
  fixtureValue,
  cookies,
) {
  if (!bootstrap.requires_password_change) {
    return bootstrap;
  }

  const clearancePresent = hasForcePasswordClearanceCookie(cookies);
  if (
    !shouldHonorForcePasswordClearanceCookie(fixtureModeEnabled, fixtureValue, clearancePresent)
  ) {
    return bootstrap;
  }

  return {
    ...bootstrap,
    requires_password_change: false,
  };
}

/**
 * @param {string} cookieHeader
 */
export function readSessionFixtureValueFromCookieHeader(cookieHeader) {
  const match = cookieHeader.match(/(?:^|;\s*)ota_session_fixture=([^;]*)/);
  if (!match) return null;
  try {
    return decodeURIComponent(match[1]);
  } catch {
    return match[1];
  }
}
