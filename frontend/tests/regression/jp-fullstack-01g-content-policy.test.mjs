/**
 * JP-FULLSTACK-01G CMS fixture-authority environment matrix.
 */

import assert from "node:assert/strict";
import {
  evaluateAllowContentFixtures,
  evaluateResolveContentSource,
} from "../../features/public-content/utils/content-policy-core.mjs";

function test(name, fn) {
  try {
    fn();
    console.log(`ok ${name}`);
  } catch (error) {
    console.error(`not ok ${name}`);
    console.error(error);
    process.exitCode = 1;
  }
}

const production = { nodeEnv: "production" };
const development = { nodeEnv: "development" };
const testEnv = { nodeEnv: "test" };

test("production + no flags → false", () => {
  assert.equal(evaluateAllowContentFixtures(production), false);
});

test("production + OTA_ALLOW_CONTENT_FIXTURE smoke gate → true", () => {
  assert.equal(
    evaluateAllowContentFixtures({ ...production, otaAllowContentFixture: "true" }),
    true,
  );
});

test("production + NEXT_PUBLIC flag → false", () => {
  assert.equal(
    evaluateAllowContentFixtures({ ...production, allowContentFixturesFlag: "true" }),
    false,
  );
});

test("production + OTA smoke gate resolves to fixture when CMS absent", () => {
  assert.equal(
    evaluateResolveContentSource(false, { ...production, otaAllowContentFixture: "true" }),
    "fixture",
  );
});

test("production + explicit flag still resolves to empty when CMS absent", () => {
  assert.equal(
    evaluateResolveContentSource(false, { ...production, allowContentFixturesFlag: "true" }),
    "empty",
  );
});

test("development → true", () => {
  assert.equal(evaluateAllowContentFixtures(development), true);
});

test("non-production + explicit content-fixture flag → true", () => {
  assert.equal(
    evaluateAllowContentFixtures({ ...testEnv, allowContentFixturesFlag: "true" }),
    true,
  );
});

test("non-production without development or explicit flag → false", () => {
  assert.equal(evaluateAllowContentFixtures(testEnv), false);
});

test("CMS present → cms source regardless of environment", () => {
  assert.equal(evaluateResolveContentSource(true, production), "cms");
  assert.equal(evaluateResolveContentSource(true, development), "cms");
});

test("CMS absent + development → fixture", () => {
  assert.equal(evaluateResolveContentSource(false, development), "fixture");
});

test("CMS absent + production → empty", () => {
  assert.equal(evaluateResolveContentSource(false, production), "empty");
});

if (process.exitCode) {
  process.exit(process.exitCode);
}

console.log("jp-fullstack-01g-content-policy: all assertions passed");
