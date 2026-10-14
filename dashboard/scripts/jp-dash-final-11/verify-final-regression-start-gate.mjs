/**
 * Phase 0 — FINAL responsive/regression freeze and start gate.
 */
import fs from "node:fs";
import path from "node:path";
import crypto from "node:crypto";
import { fileURLToPath } from "node:url";
import { spawnSync } from "node:child_process";
import { requireDashboardBuildId, requireProductionSha } from "../jp-dashboard-prod-cert/production-cert-env.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

function shaFile(relPath) {
  const p = path.join(repoRoot, relPath);
  return crypto.createHash("sha256").update(fs.readFileSync(p)).digest("hex");
}

function main() {
  const productionSha = requireProductionSha();
  const buildId = requireDashboardBuildId();
  const baselinePath = path.join(evidenceDir, "DEEP-WAVE-BASELINE.json");
  const baseline = JSON.parse(fs.readFileSync(baselinePath, "utf8"));

  spawnSync("node", [path.join(__dirname, "check-functional-cert-writers.mjs")], { stdio: "inherit", cwd: repoRoot });

  const matrixHash = shaFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const mapHash = shaFile("docs/evidence/jp-dash-final-11/CONTROL-CASE-MAP.csv");

  const checks = {
    protectedMain: baseline.PROTECTED_MAIN_SHA === productionSha,
    productionSha: baseline.PRODUCTION_SHA === productionSha,
    buildId: baseline.DASHBOARD_BUILD_ID === buildId,
    baselineVersion: baseline.VERSION === 4,
    matrixHash: baseline.MATRIX_HASH === matrixHash,
    controlMapHash: baseline.CONTROL_MAP_HASH === mapHash,
    untestedDeep: baseline.DEEP_CASES_UNTESTED === 0,
    untestedNonDeep: baseline.UNTESTED_NON_DEEP_CASES === 0,
  };

  console.log(`PROTECTED_MAIN_SHA=${productionSha}`);
  console.log(`PRODUCTION_SHA=${productionSha}`);
  console.log(`DASHBOARD_BUILD_ID=${buildId}`);
  console.log(`DEEP_WAVE_BASELINE_VERSION=${baseline.VERSION}`);
  console.log(`MATRIX_HASH_MATCH=${checks.matrixHash ? "YES" : "NO"}`);
  console.log(`CONTROL_MAP_HASH_MATCH=${checks.controlMapHash ? "YES" : "NO"}`);

  const pass = Object.values(checks).every(Boolean);
  console.log(`FINAL_REGRESSION_START_GATE=${pass ? "PASS" : "FAIL"}`);
  if (!pass) {
    console.log(`START_GATE_CHECKS=${JSON.stringify(checks)}`);
    process.exit(1);
  }
}

main();
