/**
 * Phase 0: verify DEEP-WAVE-BASELINE v2 lock before wave 03.
 */
import fs from "node:fs";
import crypto from "node:crypto";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { spawnSync } from "node:child_process";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

function sha(rel) {
  return crypto.createHash("sha256").update(fs.readFileSync(path.join(repoRoot, rel))).digest("hex");
}

function main() {
  const baselinePath = path.join(evidenceDir, "DEEP-WAVE-BASELINE.json");
  const baseline = JSON.parse(fs.readFileSync(baselinePath, "utf8"));
  if (baseline.VERSION !== 2) {
    console.log(`DEEP_WAVE_03_START_GATE=FAIL`);
    console.log(`BASELINE_VERSION_EXPECTED=2`);
    process.exit(1);
  }

  const matrixHash = sha("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const controlHash = sha("docs/evidence/jp-dash-final-11/CONTROL-CASE-MAP.csv");
  const ok =
    baseline.MATRIX_HASH === matrixHash &&
    baseline.CONTROL_MAP_HASH === controlHash &&
    baseline.PROTECTED_MAIN_SHA === "ab26661f0107d9bd8f9889dda1d59567d93be683" &&
    baseline.PRODUCTION_SHA === "ab26661f0107d9bd8f9889dda1d59567d93be683";

  spawnSync("node", [path.join(__dirname, "check-functional-cert-writers.mjs")], { stdio: "inherit" });

  console.log(`MATRIX_HASH_MATCH=${baseline.MATRIX_HASH === matrixHash ? "YES" : "NO"}`);
  console.log(`CONTROL_MAP_HASH_MATCH=${baseline.CONTROL_MAP_HASH === controlHash ? "YES" : "NO"}`);
  console.log(`PROTECTED_MAIN_SHA=${baseline.PROTECTED_MAIN_SHA}`);
  console.log(`PRODUCTION_SHA=${baseline.PRODUCTION_SHA}`);
  console.log(`DASHBOARD_BUILD_ID=${baseline.DASHBOARD_BUILD_ID}`);
  console.log(`DEEP_WAVE_03_START_GATE=${ok ? "PASS" : "FAIL"}`);
  if (!ok) {
    process.exit(1);
  }
}

main();
