import {
  evaluateAllowContentFixtures,
  evaluateResolveContentSource,
} from "./content-policy-core.mjs";

export type ContentFixtureEnv = {
  nodeEnv?: string;
  allowContentFixturesFlag?: string;
  otaAllowContentFixture?: string;
};

/**
 * Production public pages must not silently substitute fixture copy when Laravel CMS is empty.
 * Development and explicit preview builds may still use fixtures for local UI work.
 *
 * OTA_ALLOW_CONTENT_FIXTURE is a gated smoke-test override (set only by start-smoke.mjs).
 * OTA_ALLOW_SESSION_FIXTURE is intentionally excluded — session/auth test fixtures must not
 * grant CMS or public-content fixture authority.
 */
export function allowContentFixtures(env?: ContentFixtureEnv): boolean {
  return evaluateAllowContentFixtures({
    nodeEnv: env?.nodeEnv ?? process.env.NODE_ENV,
    allowContentFixturesFlag:
      env?.allowContentFixturesFlag ?? process.env.NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES,
    otaAllowContentFixture:
      env?.otaAllowContentFixture ?? process.env.OTA_ALLOW_CONTENT_FIXTURE,
  });
}

export function resolveContentSource(
  hasCmsContent: boolean,
  fallbackSource: "fixture" | "empty" = "empty",
  env?: ContentFixtureEnv,
): "cms" | "fixture" | "empty" {
  return evaluateResolveContentSource(
    hasCmsContent,
    {
      nodeEnv: env?.nodeEnv ?? process.env.NODE_ENV,
      allowContentFixturesFlag:
        env?.allowContentFixturesFlag ?? process.env.NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES,
      otaAllowContentFixture:
        env?.otaAllowContentFixture ?? process.env.OTA_ALLOW_CONTENT_FIXTURE,
    },
    fallbackSource,
  );
}

export { evaluateAllowContentFixtures, evaluateResolveContentSource };
