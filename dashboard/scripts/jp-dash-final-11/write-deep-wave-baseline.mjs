/**
 * Phase 15: DEEP-WAVE-BASELINE.json + full checkpoint metrics.
 */
import fs from "node:fs";
import path from "node:path";
import crypto from "node:crypto";
import { fileURLToPath } from "node:url";
import { parseCsvFile } from "./functional-cert-csv.mjs";
import { isStrongFormEvidence } from "./form-validation-probes.mjs";
import { isStrongSearchEvidence } from "./functional-cert-evidence.mjs";
import { isAutoPassiveClass } from "./functional-case-classify.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

const DEEP_CLASSES = ["DOWNLOAD_EXPORT", "QA_REVERSIBLE_MUTATION", "QA_CREATE_DELETE_LIFECYCLE"];

function sha(rel) {
  return crypto.createHash("sha256").update(fs.readFileSync(path.join(repoRoot, rel))).digest("hex");
}

function main() {
  const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const classCounts = {};
  const resultCounts = {};
  for (const r of rows) {
    classCounts[r.EXECUTION_CLASS] = (classCounts[r.EXECUTION_CLASS] || 0) + 1;
    const res = r.FUNCTIONAL_RESULT || "";
    if (!res) {
      resultCounts.UNTESTED = (resultCounts.UNTESTED || 0) + 1;
    } else {
      resultCounts[res] = (resultCounts[res] || 0) + 1;
    }
  }

  const formRows = rows.filter((r) => r.EXECUTION_CLASS === "FORM_VALIDATION");
  const formPass = formRows.filter((r) => r.FUNCTIONAL_RESULT === "PASS").length;
  const formUntested = formRows.filter((r) => !r.FUNCTIONAL_RESULT).length;
  const formFail = formRows.filter((r) => r.FUNCTIONAL_RESULT === "FAIL").length;
  const formStrong = formRows.filter((r) => r.FUNCTIONAL_RESULT === "PASS" && isStrongFormEvidence(r)).length;
  const formWeak = formRows.filter((r) => r.FUNCTIONAL_RESULT === "PASS" && !isStrongFormEvidence(r)).length;

  const deepRows = rows.filter((r) => DEEP_CLASSES.includes(r.EXECUTION_CLASS));
  const deepUntested = deepRows.filter((r) => !r.FUNCTIONAL_RESULT).length;
  const deepTerminal = deepRows.filter((r) => r.FUNCTIONAL_RESULT && r.FUNCTIONAL_RESULT !== "FAIL").length;

  const untestedNonDeep = rows.filter((r) => !r.FUNCTIONAL_RESULT && !DEEP_CLASSES.includes(r.EXECUTION_CLASS)).length;

  let autoPassiveUntested = 0;
  for (const r of rows) {
    if (isAutoPassiveClass(r.EXECUTION_CLASS) && !r.FUNCTIONAL_RESULT) {
      autoPassiveUntested += 1;
    }
  }

  const searchRows = rows.filter((r) => r.EXECUTION_CLASS === "SEARCH_FILTER_SORT");

  const baseline = {
    PROTECTED_MAIN_SHA: "ab26661f0107d9bd8f9889dda1d59567d93be683",
    PRODUCTION_SHA: "ab26661f0107d9bd8f9889dda1d59567d93be683",
    DASHBOARD_BUILD_ID: "6ebAnRkxe40Lkq_GJwV5G",
    TOTAL_FUNCTIONAL_CASES: rows.length,
    FORM_VALIDATION_TOTAL: formRows.length,
    FORM_VALIDATION_PASS: formPass,
    DOWNLOAD_EXPORT_TOTAL: classCounts.DOWNLOAD_EXPORT || 0,
    QA_REVERSIBLE_MUTATION_TOTAL: classCounts.QA_REVERSIBLE_MUTATION || 0,
    QA_CREATE_DELETE_LIFECYCLE_TOTAL: classCounts.QA_CREATE_DELETE_LIFECYCLE || 0,
    DEEP_CASES_TOTAL: deepRows.length,
    DEEP_CASES_UNTESTED: deepUntested,
    MATRIX_HASH: sha("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv"),
    CONTROL_MAP_HASH: sha("docs/evidence/jp-dash-final-11/CONTROL-CASE-MAP.csv"),
    GENERATED_AT: new Date().toISOString(),
  };
  fs.writeFileSync(path.join(evidenceDir, "DEEP-WAVE-BASELINE.json"), `${JSON.stringify(baseline, null, 2)}\n`, "utf8");

  const pass = resultCounts.PASS || 0;
  const untested = resultCounts.UNTESTED || 0;
  const sum =
    (resultCounts.PASS || 0) +
    (resultCounts.NEGATIVE_RBAC_PASS || 0) +
    (resultCounts.QA_MUTATION_PASS || 0) +
    (resultCounts.READ_ONLY_BY_DESIGN || 0) +
    (resultCounts.EXTERNAL_ACTION_GATED || 0) +
    (resultCounts.NOT_APPLICABLE || 0) +
    (resultCounts.FAIL || 0) +
    untested;

  console.log(`DEEP_WAVE_BASELINE_LOCKED=YES`);
  console.log(`TOTAL_FUNCTIONAL_CASES=${rows.length}`);
  console.log(`FORM_VALIDATION_CASES_TOTAL=${formRows.length}`);
  console.log(`FORM_VALIDATION_CASES_PASS=${formPass}`);
  console.log(`FORM_VALIDATION_CASES_FAIL=${formFail}`);
  console.log(`FORM_VALIDATION_CASES_UNTESTED=${formUntested}`);
  console.log(`FORM_MATRIX_UNTESTED=${formUntested}`);
  console.log(`FORM_PASSES_WITH_STRONG_EVIDENCE=${formStrong}`);
  console.log(`FORM_PASSES_WITHOUT_STRONG_EVIDENCE=${formWeak}`);
  console.log(`WEAK_FORM_VALIDATION_PASSES=${formWeak}`);
  console.log(`DOWNLOAD_EXPORT_TOTAL=${classCounts.DOWNLOAD_EXPORT || 0}`);
  console.log(`QA_REVERSIBLE_MUTATION_TOTAL=${classCounts.QA_REVERSIBLE_MUTATION || 0}`);
  console.log(`QA_CREATE_DELETE_LIFECYCLE_TOTAL=${classCounts.QA_CREATE_DELETE_LIFECYCLE || 0}`);
  console.log(`DEEP_CASES_TOTAL=${deepRows.length}`);
  console.log(`DEEP_CASES_VALID_TERMINAL=${deepTerminal}`);
  console.log(`DEEP_CASES_UNTESTED=${deepUntested}`);
  console.log(`DOWNLOAD_EXPORT_UNTESTED=${rows.filter((r) => r.EXECUTION_CLASS === "DOWNLOAD_EXPORT" && !r.FUNCTIONAL_RESULT).length}`);
  console.log(`QA_REVERSIBLE_MUTATION_UNTESTED=${rows.filter((r) => r.EXECUTION_CLASS === "QA_REVERSIBLE_MUTATION" && !r.FUNCTIONAL_RESULT).length}`);
  console.log(`QA_CREATE_DELETE_LIFECYCLE_UNTESTED=${rows.filter((r) => r.EXECUTION_CLASS === "QA_CREATE_DELETE_LIFECYCLE" && !r.FUNCTIONAL_RESULT).length}`);
  console.log(`UNTESTED_NON_DEEP_CASES=${untestedNonDeep}`);
  console.log(`UNTESTED_FUNCTIONAL_CASES=${untested}`);
  console.log(`UNACCOUNTED_TERMINAL_CASES=${rows.length === sum ? 0 : rows.length - sum}`);
  console.log(`CLASSIFICATION_TOTAL=${rows.length}`);
  console.log(`AUTO_PASSIVE_MATRIX_UNTESTED=${autoPassiveUntested}`);
  console.log(`SEARCH_FILTER_SORT_PASS=${searchRows.filter((r) => r.FUNCTIONAL_RESULT === "PASS").length}`);
  console.log(`SEARCH_FILTER_SORT_FAIL=${searchRows.filter((r) => r.FUNCTIONAL_RESULT === "FAIL").length}`);
  console.log(`SEARCH_FILTER_SORT_UNTESTED=${searchRows.filter((r) => !r.FUNCTIONAL_RESULT).length}`);
  for (const [k, v] of Object.entries(classCounts).sort((a, b) => a[0].localeCompare(b[0]))) {
    console.log(`${k}=${v}`);
  }
}

main();
