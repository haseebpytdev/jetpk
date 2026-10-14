import crypto from "node:crypto";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { FINAL11_AUTH_ROLES } from "./final-11-auth-roles.mjs";
import { VIEWPORTS } from "./responsive-cert-paths.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const SWEEP_SCRIPTS = [
  "final-responsive-sweep.mjs",
  "responsive-cert-sanitize.mjs",
  "responsive-cert-paths.mjs",
  "responsive-cert-lock.mjs",
  "responsive-cert-fingerprint.mjs",
  "responsive-cert-plan.mjs",
  "responsive-cert-checkpoint.mjs",
  "responsive-cert-aggregate.mjs",
  "build-final-responsive-execution-plan.mjs",
  "responsive-visibility-audit.mjs",
  "auth-session-probe.mjs",
  "final-route-inventory.mjs",
  "refresh-final11-sessions.mjs",
];

export const SCHEMA_VERSION = 1;

function sha256Text(text) {
  return crypto.createHash("sha256").update(text, "utf8").digest("hex");
}

export function hashViewports() {
  return sha256Text(JSON.stringify(VIEWPORTS));
}

export function hashAuthRoles() {
  const keys = Object.keys(FINAL11_AUTH_ROLES).sort();
  const canon = keys.map((k) => ({
    key: k,
    portal: FINAL11_AUTH_ROLES[k].portal,
    postLoginPath: FINAL11_AUTH_ROLES[k].postLoginPath,
    storageFile: FINAL11_AUTH_ROLES[k].storageFile,
  }));
  return sha256Text(JSON.stringify(canon));
}

export function hashSweepImplementation() {
  const parts = [];
  for (const name of SWEEP_SCRIPTS.sort()) {
    const p = path.join(__dirname, name);
    if (fs.existsSync(p)) {
      parts.push(`${name}\n${fs.readFileSync(p, "utf8")}`);
    }
  }
  return sha256Text(parts.join("\n---\n"));
}

export function hashExecutionPlan(plan) {
  const canon = plan.rows.map((r) => ({
    INDEX: r.INDEX,
    ROLE_KEY: r.ROLE_KEY,
    ROUTE: r.ROUTE,
    VIEWPORT: r.VIEWPORT,
    CASE_KEY: r.CASE_KEY,
  }));
  return sha256Text(JSON.stringify(canon));
}

export function buildRunFingerprint({ productionSha, buildId, baseUrl, executionPlanHash }) {
  return {
    SCHEMA_VERSION,
    PRODUCTION_SHA: productionSha,
    DASHBOARD_BUILD_ID: buildId,
    BASE_URL: baseUrl,
    EXECUTION_PLAN_HASH: executionPlanHash,
    VIEWPORT_DEFINITION_HASH: hashViewports(),
    AUTH_ROLE_DEFINITION_HASH: hashAuthRoles(),
    SWEEP_IMPLEMENTATION_HASH: hashSweepImplementation(),
  };
}

export function fingerprintMatches(a, b) {
  if (!a || !b) return false;
  const keys = [
    "SCHEMA_VERSION",
    "PRODUCTION_SHA",
    "DASHBOARD_BUILD_ID",
    "BASE_URL",
    "EXECUTION_PLAN_HASH",
    "VIEWPORT_DEFINITION_HASH",
    "AUTH_ROLE_DEFINITION_HASH",
    "SWEEP_IMPLEMENTATION_HASH",
  ];
  return keys.every((k) => String(a[k]) === String(b[k]));
}
