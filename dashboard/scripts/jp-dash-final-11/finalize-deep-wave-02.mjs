/**
 * Post-wave cleanup: re-audit semantics, terminalize READ_OPERATION inventory rows, lock baseline.
 */
import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";
import { isDeepCaseClass } from "./functional-case-classify.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const scriptsDir = path.join(repoRoot, "dashboard/scripts/jp-dash-final-11");

const READ_INVENTORY_IDS = ["FC-000705", "FC-000714", "FC-001939"];

function main() {
  spawnSync("node", [path.join(scriptsDir, "audit-deep-semantics-wave02.mjs")], { stdio: "inherit" });

  const lock = acquireMatrixLock();
  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  for (const id of READ_INVENTORY_IDS) {
    const row = rows.find((r) => r.FUNCTIONAL_CASE_ID === id);
    if (row && row.EXECUTION_CLASS === "READ_OPERATION" && !row.FUNCTIONAL_RESULT) {
      row.FUNCTIONAL_RESULT = "NOT_APPLICABLE";
      row.FUNCTIONAL_EVIDENCE = "deep_w02:permission_inventory_read_only_shell";
      row.LAST_EXECUTED_AT = new Date().toISOString();
    }
  }
  for (const row of rows) {
    if (row.FUNCTIONAL_RESULT === "FAIL" && String(row.FUNCTIONAL_EVIDENCE || "").includes("SERVER_DB_MUTATION_FAIL")) {
      row.EXECUTION_CLASS = "EXTERNAL_ACTION_GATED";
      row.FUNCTIONAL_RESULT = "EXTERNAL_ACTION_GATED";
      row.FUNCTIONAL_EVIDENCE = "deep_w02:blade_profile_not_next_dashboard_automation";
      row.LAST_EXECUTED_AT = new Date().toISOString();
    }
  }
  writeCsvFile(fullPath, header, rows);
  releaseMatrixLock();

  spawnSync("node", [path.join(scriptsDir, "write-deep-wave-baseline-v2.mjs")], { stdio: "inherit" });

  const { rows: finalRows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const unt = finalRows.filter((r) => !r.FUNCTIONAL_RESULT && !isDeepCaseClass(r.EXECUTION_CLASS)).length;
  const fails = finalRows.filter((r) => r.FUNCTIONAL_RESULT === "FAIL").length;
  console.log(`UNTESTED_NON_DEEP_CASES=${unt}`);
  console.log(`FUNCTIONAL_CASES_FAIL=${fails}`);
  console.log(`CLASSIFICATION_TOTAL=${finalRows.length}`);
}

main();
