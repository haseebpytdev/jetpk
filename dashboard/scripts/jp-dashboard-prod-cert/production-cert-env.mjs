/**
 * Runtime inputs for JP dashboard production certification runners.
 * Never embed certified production SHAs in source — pass them at invocation time.
 */

const FULL_SHA_RE = /^[0-9a-f]{40}$/;

/**
 * @param {NodeJS.ProcessEnv} [env]
 * @returns {string} normalized lowercase full Git SHA
 */
export function requireProductionSha(env = process.env) {
  const raw = env.JP_PRODUCTION_SHA?.trim();
  if (!raw) {
    throw new Error(
      "JP_PRODUCTION_SHA is required (full 40-character Git SHA of the certified production deploy).",
    );
  }
  const normalized = raw.toLowerCase();
  if (!FULL_SHA_RE.test(normalized)) {
    throw new Error(`JP_PRODUCTION_SHA invalid: expected 40 hex characters, received "${raw}".`);
  }
  return normalized;
}

/**
 * @param {NodeJS.ProcessEnv} [env]
 * @returns {string}
 */
export function requireDashboardBuildId(env = process.env) {
  const raw = env.JP_DASHBOARD_BUILD_ID?.trim();
  if (!raw) {
    throw new Error(
      "JP_DASHBOARD_BUILD_ID is required (Next.js dashboard BUILD_ID from certified production).",
    );
  }
  if (raw.length < 8 || raw.length > 128 || /\s/.test(raw)) {
    throw new Error(`JP_DASHBOARD_BUILD_ID invalid: received "${raw}".`);
  }
  return raw;
}
