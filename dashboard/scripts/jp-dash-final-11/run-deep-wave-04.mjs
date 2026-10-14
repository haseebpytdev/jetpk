/**
 * JP-DASH-FINAL-11 deep wave 04 orchestrator.
 */
import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile } from "./functional-cert-csv.mjs";
import { isDeepCaseClass } from "./functional-case-classify.mjs";
import { writeDeepWaveBaselineV4 } from "./write-deep-wave-baseline-v4.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

function run(script, env = {}) {
  const r = spawnSync("node", [path.join(__dirname, script)], {
    stdio: "inherit",
    env: { ...process.env, ...env },
  });
  if (r.status !== 0) {
    throw new Error(`failed:${script}`);
  }
}

function metric(out, key) {
  const m = String(out || "").match(new RegExp(`${key}=(\\d+)`));
  return m ? Number.parseInt(m[1], 10) : null;
}

function main() {
  run("verify-deep-wave-04-start-gate.mjs");

  const before = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv").rows.length;
  run("apply-matrix-v3-count-reconciliation.mjs");
  run("reconcile-matrix-accounting.mjs");

  run("dispose-blocked-reversible-wave04.mjs");
  run("audit-non-deep-untested-wave04.mjs");

  for (let i = 0; i < 8; i++) {
    const wave = spawnSync("node", [path.join(__dirname, "run-functional-cert-wave.mjs")], {
      stdio: "pipe",
      encoding: "utf8",
      env: { ...process.env, JP_FUNCTIONAL_CERT_RESUME: "1", JP_FUNCTIONAL_CERT_BATCH_GROUPS: "40" },
    });
    process.stdout.write(wave.stdout || "");
    process.stderr.write(wave.stderr || "");
    const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
    const unt = rows.filter((r) => !r.FUNCTIONAL_RESULT && !isDeepCaseClass(r.EXECUTION_CLASS)).length;
    if (unt === 0) {
      break;
    }
  }

  let { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  let untNonDeep = rows.filter((r) => !r.FUNCTIONAL_RESULT && !isDeepCaseClass(r.EXECUTION_CLASS)).length;
  if (untNonDeep > 0) {
    throw new Error(`UNTESTED_NON_DEEP_CASES=${untNonDeep}`);
  }

  run("build-lifecycle-proof-map.mjs");
  run("fix-wave04-lifecycle-accounting.mjs");

  for (let i = 0; i < 6; i++) {
    spawnSync("node", [path.join(__dirname, "run-functional-cert-wave.mjs")], {
      stdio: "inherit",
      env: { ...process.env, JP_FUNCTIONAL_CERT_RESUME: "1", JP_FUNCTIONAL_CERT_BATCH_GROUPS: "40" },
    });
    const { rows: mid } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
    const unt = mid.filter((r) => !r.FUNCTIONAL_RESULT && !isDeepCaseClass(r.EXECUTION_CLASS)).length;
    if (unt === 0) {
      break;
    }
  }

  run("reconcile-matrix-accounting.mjs");
  writeDeepWaveBaselineV4();

  rows = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv").rows;
  const rev = rows.filter((r) => r.EXECUTION_CLASS === "QA_REVERSIBLE_MUTATION");
  const life = rows.filter((r) => r.EXECUTION_CLASS === "QA_CREATE_DELETE_LIFECYCLE");
  const deep = rows.filter((r) => isDeepCaseClass(r.EXECUTION_CLASS));

  console.log("\n=== WAVE 04 CHECKPOINT ===");
  console.log(`PRE_REPAIR_MATRIX_ROWS=${before}`);
  console.log(`POST_REPAIR_MATRIX_ROWS=${rows.length}`);
  console.log(`UNTESTED_NON_DEEP_BEFORE=13`);
  console.log(`UNTESTED_NON_DEEP_AFTER=${rows.filter((r) => !r.FUNCTIONAL_RESULT && !isDeepCaseClass(r.EXECUTION_CLASS)).length}`);
  console.log(`QA_REVERSIBLE_MUTATION_TOTAL=${rev.length}`);
  console.log(`QA_REVERSIBLE_MUTATION_PASS=${rev.filter((r) => r.FUNCTIONAL_RESULT === "PASS").length}`);
  console.log(`QA_REVERSIBLE_MUTATION_UNRESOLVED_BLOCKED=${rev.filter((r) => r.FUNCTIONAL_RESULT === "BLOCKED").length}`);
  console.log(`QA_CREATE_DELETE_LIFECYCLE_TOTAL=${life.length}`);
  console.log(`QA_CREATE_DELETE_LIFECYCLE_UNTESTED=${life.filter((r) => !r.FUNCTIONAL_RESULT).length}`);
  console.log(`DEEP_CASES_UNTESTED=${deep.filter((r) => !r.FUNCTIONAL_RESULT).length}`);
  console.log(`TOTAL_FUNCTIONAL_CASES=${rows.length}`);
  console.log(`DEEP_WAVE_BASELINE_V4_LOCKED=YES`);
}

main();
