/**
 * Pure CMS fixture-authority policy (shared by TypeScript wrapper and Node regression tests).
 * Production builds must never treat fixture copy as authoritative CMS content.
 */

/**
 * @param {{ nodeEnv?: string; allowContentFixturesFlag?: string; otaAllowContentFixture?: string }} env
 * @returns {boolean}
 */
export function evaluateAllowContentFixtures(env) {
  if (env.otaAllowContentFixture === "true") {
    return true;
  }

  if (env.nodeEnv === "production") {
    return false;
  }

  if (env.allowContentFixturesFlag === "true") {
    return true;
  }

  return env.nodeEnv === "development";
}

/**
 * @param {boolean} hasCmsContent
 * @param {{ nodeEnv?: string; allowContentFixturesFlag?: string; otaAllowContentFixture?: string }} env
 * @param {"fixture" | "empty"} [fallbackSource]
 * @returns {"cms" | "fixture" | "empty"}
 */
export function evaluateResolveContentSource(hasCmsContent, env, fallbackSource = "empty") {
  if (hasCmsContent) {
    return "cms";
  }

  return evaluateAllowContentFixtures(env) ? "fixture" : fallbackSource;
}
