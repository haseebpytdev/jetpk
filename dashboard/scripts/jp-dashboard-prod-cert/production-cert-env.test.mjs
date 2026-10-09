import assert from "node:assert/strict";
import test from "node:test";
import { requireDashboardBuildId, requireProductionSha } from "./production-cert-env.mjs";

const VALID_SHA = "50ae55c47161d211748ca2cc1204855a52442422";

test("requireProductionSha rejects missing env", () => {
  assert.throws(() => requireProductionSha({}), /JP_PRODUCTION_SHA is required/);
});

test("requireProductionSha rejects invalid length", () => {
  assert.throws(
    () => requireProductionSha({ JP_PRODUCTION_SHA: "abc" }),
    /JP_PRODUCTION_SHA invalid/,
  );
});

test("requireProductionSha accepts full SHA and normalizes case", () => {
  const upper = VALID_SHA.toUpperCase();
  assert.equal(requireProductionSha({ JP_PRODUCTION_SHA: upper }), VALID_SHA);
});

test("requireDashboardBuildId rejects missing env", () => {
  assert.throws(() => requireDashboardBuildId({}), /JP_DASHBOARD_BUILD_ID is required/);
});

test("requireDashboardBuildId accepts production build id", () => {
  assert.equal(
    requireDashboardBuildId({ JP_DASHBOARD_BUILD_ID: "G_-giY83K-tngzEQvf86p" }),
    "G_-giY83K-tngzEQvf86p",
  );
});
