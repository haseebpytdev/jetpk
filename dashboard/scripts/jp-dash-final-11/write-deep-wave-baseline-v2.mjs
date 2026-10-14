/**
 * Phase 22: DEEP-WAVE-BASELINE.json v2 lock.
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

function main() {
  const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const deep = rows.filter((r) => isDeepCaseClass(r.EXECUTION_CLASS));
  const classes = ["DOWNLOAD_EXPORT", "QA_REVERSIBLE_MUTATION", "QA_CREATE_DELETE_LIFECYCLE"];
  const counts = {};
  for (const c of classes) {
    const rs = rows.filter((r) => r.EXECUTION_CLASS === c);
    counts[c] = {
      total: rs.length,
      terminal: rs.filter((r) => r.FUNCTIONAL_RESULT).length,
      untested: rs.filter((r) => !r.FUNCTIONAL_RESULT).length,
    };
  }
  const untestedNonDeep = rows.filter((r) => !r.FUNCTIONAL_RESULT && !isDeepCaseClass(r.EXECUTION_CLASS)).length;

  const baseline = {
    VERSION: 2,
    PROTECTED_MAIN_SHA: "ab26661f0107d9bd8f9889dda1d59567d93be683",
    PRODUCTION_SHA: "ab26661f0107d9bd8f9889dda1d59567d93be683",
    DASHBOARD_BUILD_ID: "6ebAnRkxe40Lkq_GJwV5G",
    TOTAL_FUNCTIONAL_CASES: rows.length,
    DOWNLOAD_EXPORT_TOTAL: counts.DOWNLOAD_EXPORT.total,
    QA_REVERSIBLE_MUTATION_TOTAL: counts.QA_REVERSIBLE_MUTATION.total,
    QA_CREATE_DELETE_LIFECYCLE_TOTAL: counts.QA_CREATE_DELETE_LIFECYCLE.total,
    DEEP_CASES_TOTAL: deep.length,
    DEEP_CASES_VALID_TERMINAL: deep.filter((r) => r.FUNCTIONAL_RESULT).length,
    DEEP_CASES_UNTESTED: deep.filter((r) => !r.FUNCTIONAL_RESULT).length,
    UNTESTED_NON_DEEP_CASES: untestedNonDeep,
    MATRIX_HASH: sha("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv"),
    CONTROL_MAP_HASH: sha("docs/evidence/jp-dash-final-11/CONTROL-CASE-MAP.csv"),
    GENERATED_AT: new Date().toISOString(),
  };
  fs.writeFileSync(path.join(evidenceDir, "DEEP-WAVE-BASELINE.json"), `${JSON.stringify(baseline, null, 2)}\n`, "utf8");
  console.log(`DEEP_WAVE_BASELINE_V2_LOCKED=YES`);
  console.log(`DOWNLOAD_EXPORT_TOTAL=${counts.DOWNLOAD_EXPORT.total}`);
  console.log(`QA_REVERSIBLE_MUTATION_TOTAL=${counts.QA_REVERSIBLE_MUTATION.total}`);
  console.log(`QA_CREATE_DELETE_LIFECYCLE_TOTAL=${counts.QA_CREATE_DELETE_LIFECYCLE.total}`);
  console.log(`DEEP_CASES_TOTAL=${deep.length}`);
  console.log(`DEEP_CASES_VALID_TERMINAL=${baseline.DEEP_CASES_VALID_TERMINAL}`);
  console.log(`DEEP_CASES_UNTESTED=${baseline.DEEP_CASES_UNTESTED}`);
  console.log(`UNTESTED_NON_DEEP_CASES=${untestedNonDeep}`);
  console.log(`CLASSIFICATION_TOTAL=${rows.length}`);
}

main();
