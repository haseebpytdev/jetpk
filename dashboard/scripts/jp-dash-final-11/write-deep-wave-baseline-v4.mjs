/**
 * Phase 25: DEEP-WAVE-BASELINE.json v4
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

export function writeDeepWaveBaselineV4(extra = {}) {
  const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const deep = rows.filter((r) => isDeepCaseClass(r.EXECUTION_CLASS));
  const rev = rows.filter((r) => r.EXECUTION_CLASS === "QA_REVERSIBLE_MUTATION");
  const life = rows.filter((r) => r.EXECUTION_CLASS === "QA_CREATE_DELETE_LIFECYCLE");
  const dl = rows.filter((r) => r.EXECUTION_CLASS === "DOWNLOAD_EXPORT");

  const validTerminal = (r) =>
    r.FUNCTIONAL_RESULT && !["FAIL", "BLOCKED"].includes(r.FUNCTIONAL_RESULT);
  const gatedOrNa = (r) =>
    ["EXTERNAL_ACTION_GATED", "NOT_APPLICABLE", "READ_ONLY_BY_DESIGN"].includes(r.FUNCTIONAL_RESULT);

  const baseline = {
    VERSION: 4,
    PROTECTED_MAIN_SHA: "ab26661f0107d9bd8f9889dda1d59567d93be683",
    PRODUCTION_SHA: "ab26661f0107d9bd8f9889dda1d59567d93be683",
    DASHBOARD_BUILD_ID: "6ebAnRkxe40Lkq_GJwV5G",
    TOTAL_FUNCTIONAL_CASES: rows.length,
    DOWNLOAD_EXPORT_TOTAL: dl.length,
    QA_REVERSIBLE_MUTATION_TOTAL: rev.length,
    QA_CREATE_DELETE_LIFECYCLE_TOTAL: life.length,
    DEEP_CASES_TOTAL: deep.length,
    DEEP_CASES_PASS_OR_VALID_TERMINAL: deep.filter(validTerminal).length,
    DEEP_CASES_GATED_OR_NA: deep.filter(gatedOrNa).length,
    DEEP_CASES_FAIL: deep.filter((r) => r.FUNCTIONAL_RESULT === "FAIL").length,
    DEEP_CASES_BLOCKED: deep.filter((r) => r.FUNCTIONAL_RESULT === "BLOCKED").length,
    DEEP_CASES_UNTESTED: deep.filter((r) => !r.FUNCTIONAL_RESULT).length,
    UNTESTED_NON_DEEP_CASES: rows.filter((r) => !r.FUNCTIONAL_RESULT && !isDeepCaseClass(r.EXECUTION_CLASS)).length,
    QA_REVERSIBLE_MUTATION_PASS: rev.filter((r) => r.FUNCTIONAL_RESULT === "PASS").length,
    QA_REVERSIBLE_MUTATION_GATED_OR_NA: rev.filter(gatedOrNa).length,
    QA_CREATE_DELETE_LIFECYCLE_PASS: life.filter((r) => r.FUNCTIONAL_RESULT === "PASS").length,
    QA_FUNCTIONAL_DB_MUTATIONS_CUMULATIVE: extra.qaFunctionalDbMutationsCumulative ?? 16,
    QA_FUNCTIONAL_DB_RESTORE_MUTATIONS_CUMULATIVE: extra.qaFunctionalDbRestoreMutationsCumulative ?? 14,
    QA_LIFECYCLE_CREATE_MUTATIONS_CUMULATIVE: extra.qaLifecycleCreate ?? 2,
    QA_LIFECYCLE_DELETE_MUTATIONS_CUMULATIVE: extra.qaLifecycleDelete ?? 2,
    MATRIX_HASH: sha("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv"),
    CONTROL_MAP_HASH: sha("docs/evidence/jp-dash-final-11/CONTROL-CASE-MAP.csv"),
    GENERATED_AT: new Date().toISOString(),
  };
  fs.writeFileSync(path.join(evidenceDir, "DEEP-WAVE-BASELINE.json"), `${JSON.stringify(baseline, null, 2)}\n`, "utf8");
  return baseline;
}

function main() {
  const b = writeDeepWaveBaselineV4();
  console.log(`DEEP_WAVE_BASELINE_V4_LOCKED=YES`);
  console.log(`TOTAL_FUNCTIONAL_CASES=${b.TOTAL_FUNCTIONAL_CASES}`);
  console.log(`DEEP_CASES_UNTESTED=${b.DEEP_CASES_UNTESTED}`);
  console.log(`UNTESTED_NON_DEEP_CASES=${b.UNTESTED_NON_DEEP_CASES}`);
}

import { pathToFileURL } from "node:url";
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  main();
}
