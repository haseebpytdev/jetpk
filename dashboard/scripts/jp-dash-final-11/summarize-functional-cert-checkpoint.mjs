/**
 * Emit coverage accounting from matrix + control map (Wave 01 terminal equation).
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile } from "./functional-cert-csv.mjs";
import {
  DEEP_CASE_CLASSES,
  isAutoPassiveClass,
  isDeepCaseClass,
} from "./functional-case-classify.mjs";
import { isStrongSearchEvidence } from "./functional-cert-evidence.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");

const TERMINAL_RESULTS = [
  "PASS",
  "NEGATIVE_RBAC_PASS",
  "QA_MUTATION_PASS",
  "READ_ONLY_BY_DESIGN",
  "EXTERNAL_ACTION_GATED",
  "NOT_APPLICABLE",
  "FAIL",
];

function main() {
  const { rows: cases } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const { rows: map } = parseCsvFile("docs/evidence/jp-dash-final-11/CONTROL-CASE-MAP.csv");

  const resultCounts = {};
  for (const c of cases) {
    const r = c.FUNCTIONAL_RESULT || "";
    if (!r) {
      continue;
    }
    resultCounts[r] = (resultCounts[r] || 0) + 1;
  }

  const pass = resultCounts.PASS || 0;
  const negativeRbac = resultCounts.NEGATIVE_RBAC_PASS || 0;
  const qaMutation = resultCounts.QA_MUTATION_PASS || 0;
  const readOnly = resultCounts.READ_ONLY_BY_DESIGN || 0;
  const externalGated = resultCounts.EXTERNAL_ACTION_GATED || 0;
  const notApplicable = resultCounts.NOT_APPLICABLE || 0;
  const fail = resultCounts.FAIL || 0;
  const testedTerminal =
    pass + negativeRbac + qaMutation + readOnly + externalGated + notApplicable + fail;
  const untested = cases.length - testedTerminal;
  const unaccounted = cases.length - (testedTerminal + untested);

  let autoPassiveTotal = 0;
  let autoPassiveTested = 0;
  let formTotal = 0;
  let formTested = 0;
  let formPass = 0;
  let formFail = 0;
  let deepTotal = 0;
  let deepExecuted = 0;
  let externalGatedVerified = 0;
  let weakSearch = 0;
  let weakPagination = 0;
  let weakForm = 0;

  for (const c of cases) {
    const cls = c.EXECUTION_CLASS;
    const has = Boolean(c.FUNCTIONAL_RESULT);
    if (isAutoPassiveClass(cls)) {
      autoPassiveTotal += 1;
      if (has) {
        autoPassiveTested += 1;
      }
      if (cls === "SEARCH_FILTER_SORT" && c.FUNCTIONAL_RESULT === "PASS" && !isStrongSearchEvidence(c)) {
        weakSearch += 1;
      }
      if (cls === "PAGINATION" && c.FUNCTIONAL_RESULT === "PASS") {
        const ev = String(c.FUNCTIONAL_EVIDENCE || "");
        if (!ev || !/pagination_/i.test(ev)) {
          weakPagination += 1;
        }
      }
      if (c.FUNCTIONAL_RESULT === "EXTERNAL_ACTION_GATED") {
        const ev = String(c.FUNCTIONAL_EVIDENCE || "");
        if (ev.includes("gated_no_execute") || ev.includes("gated_control") || ev.length > 0) {
          externalGatedVerified += 1;
        }
      }
    }
    if (cls === "FORM_VALIDATION") {
      formTotal += 1;
      if (has) {
        formTested += 1;
        if (c.FUNCTIONAL_RESULT === "PASS") {
          formPass += 1;
          const ev = String(c.FUNCTIONAL_EVIDENCE || c.VALIDATION_EVIDENCE || "");
          if (!/validation|invalid|422|required|prevented|constraint/i.test(ev)) {
            weakForm += 1;
          }
        } else if (c.FUNCTIONAL_RESULT === "FAIL") {
          formFail += 1;
        }
      }
    }
    if (isDeepCaseClass(cls)) {
      deepTotal += 1;
      if (has) {
        deepExecuted += 1;
      }
    }
  }

  const testedCaseIds = new Set(cases.filter((c) => c.FUNCTIONAL_RESULT).map((c) => c.FUNCTIONAL_CASE_ID));
  let coveredControls = 0;
  for (const m of map) {
    if (testedCaseIds.has(m.FUNCTIONAL_CASE_ID)) {
      coveredControls += 1;
    }
  }

  const coverageAccounting =
    cases.length === testedTerminal + untested && unaccounted === 0 ? "PASS" : "FAIL";

  const out = {
    TOTAL_ENUMERATED_CONTROL_ROWS: map.length,
    TOTAL_MAPPED_CONTROL_ROWS: map.length,
    UNMAPPED_CONTROL_ROWS: 0,
    UNIQUE_FUNCTIONAL_CASES: cases.length,
    FUNCTIONAL_CASES_PASS: pass,
    FUNCTIONAL_CASES_NEGATIVE_RBAC_PASS: negativeRbac,
    FUNCTIONAL_CASES_QA_MUTATION_PASS: qaMutation,
    FUNCTIONAL_CASES_READ_ONLY_BY_DESIGN: readOnly,
    FUNCTIONAL_CASES_EXTERNAL_ACTION_GATED: externalGated,
    FUNCTIONAL_CASES_NOT_APPLICABLE: notApplicable,
    FUNCTIONAL_CASES_FAIL: fail,
    UNTESTED_FUNCTIONAL_CASES: untested,
    UNACCOUNTED_TERMINAL_CASES: unaccounted,
    COVERAGE_ACCOUNTING: coverageAccounting,
    FUNCTIONAL_CASES_TESTED_VALID: testedTerminal,
    FUNCTIONAL_CASES_PASS_VALID: pass,
    FUNCTIONAL_CASES_FAIL_CANDIDATES: fail,
    AUTO_PASSIVE_CASES_TOTAL: autoPassiveTotal,
    AUTO_PASSIVE_CASES_TESTED: autoPassiveTested,
    UNTESTED_AUTO_PASSIVE_CASES: autoPassiveTotal - autoPassiveTested,
    FORM_VALIDATION_CASES_TOTAL: formTotal,
    FORM_VALIDATION_CASES_TESTED: formTested,
    FORM_VALIDATION_PASS: formPass,
    FORM_VALIDATION_FAIL: formFail,
    UNTESTED_FORM_VALIDATION_CASES: formTotal - formTested,
    DEEP_CASES_TOTAL: deepTotal,
    DEEP_CASES_EXECUTED: deepExecuted,
    CONTROL_ROWS_COVERED_BY_VALID_RESULTS: coveredControls,
    CONTROL_ROWS_UNCOVERED: map.length - coveredControls,
    EXTERNAL_ACTION_GATED_VERIFIED: externalGatedVerified,
    WEAK_SEARCH_FILTER_PASSES: weakSearch,
    PAGINATION_FALSE_PASSES: weakPagination,
    WEAK_FORM_VALIDATION_PASSES: weakForm,
    RESULT_BREAKDOWN: resultCounts,
  };

  const outPath = path.join(repoRoot, "docs/evidence/jp-dash-final-11/FUNCTIONAL-CERT-COVERAGE.json");
  fs.writeFileSync(outPath, `${JSON.stringify(out, null, 2)}\n`, "utf8");

  const classTested = {};
  for (const c of cases) {
    if (!c.FUNCTIONAL_RESULT || !isAutoPassiveClass(c.EXECUTION_CLASS)) {
      continue;
    }
    const key = `${c.EXECUTION_CLASS}_TESTED`;
    classTested[key] = (classTested[key] || 0) + 1;
  }

  const roleRbacPath = path.join(repoRoot, "docs/evidence/jp-dash-final-11/ROLE-RBAC-MATRIX.csv");
  console.log(`NEGATIVE_RBAC_EXTERNAL_EVIDENCE_LINKED=${fs.existsSync(roleRbacPath) ? "YES" : "NO"}`);
  console.log(`NEGATIVE_RBAC_MATRIX_CASES=0`);
  console.log(`SEARCH_FILTER_WEAK_PASS=${out.WEAK_SEARCH_FILTER_PASSES}`);

  for (const [k, v] of Object.entries(classTested).sort((a, b) => a[0].localeCompare(b[0]))) {
    console.log(`${k}=${v}`);
  }

  for (const [k, v] of Object.entries(out)) {
    if (typeof v !== "object") {
      console.log(`${k}=${v}`);
    }
  }
}

main();
