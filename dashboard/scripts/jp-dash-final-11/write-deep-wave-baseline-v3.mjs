/**
 * Phase 24: DEEP-WAVE-BASELINE.json v3 lock.
 */
import fs from "node:fs";
import path from "node:path";
import crypto from "node:crypto";
import { fileURLToPath } from "node:url";
import { parseCsvFile } from "./functional-cert-csv.mjs";
import { isDeepCaseClass } from "./functional-case-classify.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

function sha(rel) {
  return crypto.createHash("sha256").update(fs.readFileSync(path.join(repoRoot, rel))).digest("hex");
}

export function writeDeepWaveBaselineV3({
  qaFunctionalDbMutationsCumulative = 6,
  qaFunctionalDbRestoreMutationsCumulative = 3,
  qaLifecycleCreate = 0,
  qaLifecycleEdit = 0,
  qaLifecycleDelete = 0,
} = {}) {
  const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const deep = rows.filter((r) => isDeepCaseClass(r.EXECUTION_CLASS));
  const classes = ["DOWNLOAD_EXPORT", "QA_REVERSIBLE_MUTATION", "QA_CREATE_DELETE_LIFECYCLE"];
  const counts = {};
  for (const c of classes) {
    const rs = rows.filter((r) => r.EXECUTION_CLASS === c);
    counts[c] = {
      total: rs.length,
      terminal: rs.filter((r) => r.FUNCTIONAL_RESULT && r.FUNCTIONAL_RESULT !== "BLOCKED").length,
      blocked: rs.filter((r) => r.FUNCTIONAL_RESULT === "BLOCKED").length,
      untested: rs.filter((r) => !r.FUNCTIONAL_RESULT).length,
    };
  }
  const untestedNonDeep = rows.filter((r) => !r.FUNCTIONAL_RESULT && !isDeepCaseClass(r.EXECUTION_CLASS)).length;
  const validTerminal = deep.filter(
    (r) => r.FUNCTIONAL_RESULT && !["BLOCKED", "EXTERNAL_ACTION_GATED"].includes(r.FUNCTIONAL_RESULT),
  ).length;
  const blockedDeep = deep.filter((r) => r.FUNCTIONAL_RESULT === "BLOCKED").length;

  const baseline = {
    VERSION: 3,
    PROTECTED_MAIN_SHA: "ab26661f0107d9bd8f9889dda1d59567d93be683",
    PRODUCTION_SHA: "ab26661f0107d9bd8f9889dda1d59567d93be683",
    DASHBOARD_BUILD_ID: "6ebAnRkxe40Lkq_GJwV5G",
    TOTAL_FUNCTIONAL_CASES: rows.length,
    DOWNLOAD_EXPORT_TOTAL: counts.DOWNLOAD_EXPORT.total,
    QA_REVERSIBLE_MUTATION_TOTAL: counts.QA_REVERSIBLE_MUTATION.total,
    QA_CREATE_DELETE_LIFECYCLE_TOTAL: counts.QA_CREATE_DELETE_LIFECYCLE.total,
    DEEP_CASES_TOTAL: deep.length,
    DEEP_CASES_VALID_TERMINAL: validTerminal,
    DEEP_CASES_BLOCKED: blockedDeep,
    DEEP_CASES_UNTESTED: deep.filter((r) => !r.FUNCTIONAL_RESULT).length,
    UNTESTED_NON_DEEP_CASES: untestedNonDeep,
    QA_FUNCTIONAL_DB_MUTATIONS_CUMULATIVE: qaFunctionalDbMutationsCumulative,
    QA_FUNCTIONAL_DB_RESTORE_MUTATIONS_CUMULATIVE: qaFunctionalDbRestoreMutationsCumulative,
    QA_LIFECYCLE_CREATE_MUTATIONS_CUMULATIVE: qaLifecycleCreate,
    QA_LIFECYCLE_EDIT_MUTATIONS_CUMULATIVE: qaLifecycleEdit,
    QA_LIFECYCLE_DELETE_MUTATIONS_CUMULATIVE: qaLifecycleDelete,
    MATRIX_HASH: sha("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv"),
    CONTROL_MAP_HASH: sha("docs/evidence/jp-dash-final-11/CONTROL-CASE-MAP.csv"),
    GENERATED_AT: new Date().toISOString(),
  };
  fs.writeFileSync(path.join(evidenceDir, "DEEP-WAVE-BASELINE.json"), `${JSON.stringify(baseline, null, 2)}\n`, "utf8");
  return baseline;
}

function main() {
  const baseline = writeDeepWaveBaselineV3();
  console.log(`DEEP_WAVE_BASELINE_V3_LOCKED=YES`);
  console.log(`DOWNLOAD_EXPORT_TOTAL=${baseline.DOWNLOAD_EXPORT_TOTAL}`);
  console.log(`QA_REVERSIBLE_MUTATION_TOTAL=${baseline.QA_REVERSIBLE_MUTATION_TOTAL}`);
  console.log(`QA_CREATE_DELETE_LIFECYCLE_TOTAL=${baseline.QA_CREATE_DELETE_LIFECYCLE_TOTAL}`);
  console.log(`DEEP_CASES_TOTAL=${baseline.DEEP_CASES_TOTAL}`);
  console.log(`DEEP_CASES_VALID_TERMINAL=${baseline.DEEP_CASES_VALID_TERMINAL}`);
  console.log(`DEEP_CASES_BLOCKED=${baseline.DEEP_CASES_BLOCKED}`);
  console.log(`DEEP_CASES_UNTESTED=${baseline.DEEP_CASES_UNTESTED}`);
  console.log(`UNTESTED_NON_DEEP_CASES=${baseline.UNTESTED_NON_DEEP_CASES}`);
}

import { pathToFileURL } from "node:url";

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  main();
}
