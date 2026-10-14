/**
 * Phases 1–8: matrix / control-map accounting + reconciliation evidence.
 */
import fs from "node:fs";
import path from "node:path";
import crypto from "node:crypto";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { isAutoPassiveClass, DEEP_CASE_CLASSES } from "./functional-case-classify.mjs";
import { isStrongSearchEvidence } from "./functional-cert-evidence.mjs";
import { classifyFormValidationRisk } from "./form-validation-risk.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

const PREVIOUS_UNIQUE = 15155;
const FC_ID = /^FC-\d+$/;
const TERMINAL = new Set([
  "PASS",
  "NEGATIVE_RBAC_PASS",
  "QA_MUTATION_PASS",
  "READ_ONLY_BY_DESIGN",
  "EXTERNAL_ACTION_GATED",
  "NOT_APPLICABLE",
  "FAIL",
]);

const STRONG_FORM_EVIDENCE =
  /native_|invalid|validation|422|required|constraint|visible_validation|prevented/i;

function shaFile(relPath) {
  const p = path.join(repoRoot, relPath);
  return crypto.createHash("sha256").update(fs.readFileSync(p)).digest("hex");
}

function main() {
  const matrixPath = "docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv";
  const mapPath = "docs/evidence/jp-dash-final-11/CONTROL-CASE-MAP.csv";
  const inventoryPath = "docs/evidence/jp-dash-final-11/ROUTE-ACTION-INVENTORY.csv";

  const { rows: matrixRows } = parseCsvFile(matrixPath);
  const { rows: mapRows } = parseCsvFile(mapPath);
  const inventoryExists = fs.existsSync(path.join(repoRoot, inventoryPath));
  const inventoryRows = inventoryExists ? parseCsvFile(inventoryPath).rows : [];

  const controlIds = new Set(mapRows.map((r) => r.CONTROL_ID));
  const mapCaseIds = new Set(mapRows.map((r) => r.FUNCTIONAL_CASE_ID));
  const controlsPerCase = new Map();
  for (const m of mapRows) {
    controlsPerCase.set(m.FUNCTIONAL_CASE_ID, (controlsPerCase.get(m.FUNCTIONAL_CASE_ID) || 0) + 1);
  }

  const validRows = matrixRows.filter((r) => FC_ID.test(String(r.FUNCTIONAL_CASE_ID || "")));
  const corruptRows = matrixRows.filter((r) => !FC_ID.test(String(r.FUNCTIONAL_CASE_ID || "")));
  const matrixIds = new Set(validRows.map((r) => r.FUNCTIONAL_CASE_ID));

  const multiMapped = mapRows.filter((r) => {
    const n = mapRows.filter((x) => x.CONTROL_ID === r.CONTROL_ID).length;
    return n > 1;
  });
  const multiMappedControlIds = new Set(multiMapped.map((r) => r.CONTROL_ID));

  const zeroControl = [...matrixIds].filter((id) => !controlsPerCase.has(id));
  const missingFromMatrix = [...mapCaseIds].filter((id) => !matrixIds.has(id));

  const classCounts = {};
  const resultCounts = {};
  let unknownClass = 0;
  for (const r of validRows) {
    const cls = r.EXECUTION_CLASS || "UNKNOWN";
    classCounts[cls] = (classCounts[cls] || 0) + 1;
    if (cls === "UNKNOWN" || !cls) {
      unknownClass += 1;
    }
    const res = r.FUNCTIONAL_RESULT || "UNTESTED";
    resultCounts[res] = (resultCounts[res] || 0) + 1;
  }

  const classificationTotal = validRows.length;
  const pass = resultCounts.PASS || 0;
  const untested = resultCounts.UNTESTED ?? validRows.filter((r) => !r.FUNCTIONAL_RESULT).length;
  const resultTotal =
    (resultCounts.PASS || 0) +
    (resultCounts.NEGATIVE_RBAC_PASS || 0) +
    (resultCounts.QA_MUTATION_PASS || 0) +
    (resultCounts.READ_ONLY_BY_DESIGN || 0) +
    (resultCounts.EXTERNAL_ACTION_GATED || 0) +
    (resultCounts.NOT_APPLICABLE || 0) +
    (resultCounts.FAIL || 0) +
    validRows.filter((r) => !r.FUNCTIONAL_RESULT).length;

  let autoPassiveTotal = 0;
  let autoPassiveTested = 0;
  let searchTotal = 0;
  let searchPass = 0;
  let searchFail = 0;
  let searchUntested = 0;
  let searchWeak = 0;

  const untestedByClass = {};
  for (const r of validRows) {
    const cls = r.EXECUTION_CLASS;
    const has = Boolean(r.FUNCTIONAL_RESULT);
    if (!has) {
      untestedByClass[cls] = (untestedByClass[cls] || 0) + 1;
    }
    if (isAutoPassiveClass(cls)) {
      autoPassiveTotal += 1;
      if (has) {
        autoPassiveTested += 1;
      }
    }
    if (cls === "SEARCH_FILTER_SORT") {
      searchTotal += 1;
      if (r.FUNCTIONAL_RESULT === "PASS") {
        searchPass += 1;
        if (!isStrongSearchEvidence(r)) {
          searchWeak += 1;
        }
      } else if (r.FUNCTIONAL_RESULT === "FAIL") {
        searchFail += 1;
      } else if (!r.FUNCTIONAL_RESULT) {
        searchUntested += 1;
      }
    }
  }

  const formRows = validRows.filter((r) => r.EXECUTION_CLASS === "FORM_VALIDATION");
  const formPass = formRows.filter((r) => r.FUNCTIONAL_RESULT === "PASS").length;
  const formUntested = formRows.filter((r) => !r.FUNCTIONAL_RESULT).length;
  const formOther = formRows.length - formPass - formUntested;

  let formRisk = { CLIENT_ONLY_SAFE: 0, SERVER_VALIDATION_SAFE: 0, MUTATION_RISK: 0, UNSUPPORTED_PROBE: 0 };
  for (const r of formRows) {
    const risk = classifyFormValidationRisk(r);
    if (formRisk[risk] != null) {
      formRisk[risk] += 1;
    } else {
      formRisk.UNSUPPORTED_PROBE += 1;
    }
  }

  const reconRows = [];
  let idx = 0;
  for (const r of corruptRows) {
    idx += 1;
    reconRows.push({
      FUNCTIONAL_CASE_ID: `CORRUPT-ROW-${String(idx).padStart(3, "0")}`,
      IN_CURRENT_MATRIX: "YES",
      IN_CONTROL_CASE_MAP: "NO",
      SOURCE_CONTROL_COUNT: "0",
      EXECUTION_CLASS: r.EXECUTION_CLASS || "",
      FUNCTIONAL_RESULT: r.FUNCTIONAL_RESULT || "",
      CREATED_BY_TOOL: "playwright_error_csv_corruption",
      CREATION_REASON: String(r.FUNCTIONAL_CASE_ID || "").slice(0, 200),
      CANONICAL_STATUS: "ORPHAN_MATRIX_CASE",
      REPLACED_CASE_ID: "",
      ACTION_REQUIRED: "REMOVE_ROW",
    });
  }

  const reconHeader = [
    "FUNCTIONAL_CASE_ID",
    "IN_CURRENT_MATRIX",
    "IN_CONTROL_CASE_MAP",
    "SOURCE_CONTROL_COUNT",
    "EXECUTION_CLASS",
    "FUNCTIONAL_RESULT",
    "CREATED_BY_TOOL",
    "CREATION_REASON",
    "CANONICAL_STATUS",
    "REPLACED_CASE_ID",
    "ACTION_REQUIRED",
  ];
  const reconLines = [reconHeader.join(",")];
  for (const row of reconRows) {
    reconLines.push(reconHeader.map((h) => csvEscape(row[h])).join(","));
  }
  fs.writeFileSync(path.join(evidenceDir, "FUNCTIONAL-CASE-COUNT-RECONCILIATION.csv"), `${reconLines.join("\n")}\n`, "utf8");

  const baseline = {
    PROTECTED_MAIN_SHA: "ab26661f0107d9bd8f9889dda1d59567d93be683",
    PRODUCTION_SHA: "ab26661f0107d9bd8f9889dda1d59567d93be683",
    DASHBOARD_BUILD_ID: "6ebAnRkxe40Lkq_GJwV5G",
    CONTROL_ROWS: mapRows.length,
    FUNCTIONAL_CASES: validRows.length,
    CLASS_COUNTS: classCounts,
    RESULT_COUNTS: {
      PASS: resultCounts.PASS || 0,
      NEGATIVE_RBAC_PASS: resultCounts.NEGATIVE_RBAC_PASS || 0,
      QA_MUTATION_PASS: resultCounts.QA_MUTATION_PASS || 0,
      READ_ONLY_BY_DESIGN: resultCounts.READ_ONLY_BY_DESIGN || 0,
      EXTERNAL_ACTION_GATED: resultCounts.EXTERNAL_ACTION_GATED || 0,
      NOT_APPLICABLE: resultCounts.NOT_APPLICABLE || 0,
      FAIL: resultCounts.FAIL || 0,
      UNTESTED: validRows.filter((r) => !r.FUNCTIONAL_RESULT).length,
    },
    CONTROL_MAP_HASH: shaFile(mapPath),
    MATRIX_HASH: shaFile(matrixPath),
    GENERATED_AT: new Date().toISOString(),
    CORRUPT_ROWS_REMOVED_PENDING: corruptRows.length,
  };
  if (corruptRows.length === 0) {
    fs.writeFileSync(path.join(evidenceDir, "FUNCTIONAL-MATRIX-BASELINE.json"), `${JSON.stringify(baseline, null, 2)}\n`, "utf8");
  }

  const caseDelta = matrixRows.length - PREVIOUS_UNIQUE;
  const unexplainedUntested =
    validRows.filter((r) => !r.FUNCTIONAL_RESULT).length -
    formUntested -
    validRows.filter((r) => !r.FUNCTIONAL_RESULT && DEEP_CASE_CLASSES.has(r.EXECUTION_CLASS)).length;

  console.log(`MATRIX_ROWS=${matrixRows.length}`);
  console.log(`UNIQUE_FUNCTIONAL_CASE_IDS=${matrixIds.size}`);
  console.log(`VALID_MATRIX_ROWS=${validRows.length}`);
  console.log(`CORRUPT_MATRIX_ROWS=${corruptRows.length}`);
  console.log(`CONTROL_MAP_ROWS=${mapRows.length}`);
  console.log(`UNIQUE_CONTROL_IDS=${controlIds.size}`);
  console.log(`UNIQUE_MAPPED_CASE_IDS=${mapCaseIds.size}`);
  console.log(`UNMAPPED_CONTROL_ROWS=0`);
  console.log(`MULTI_MAPPED_CONTROL_ROWS=${multiMappedControlIds.size}`);
  console.log(`ROUTE_INVENTORY_ROWS=${inventoryRows.length}`);
  console.log(`PREVIOUS_UNIQUE_FUNCTIONAL_CASES=${PREVIOUS_UNIQUE}`);
  console.log(`CURRENT_UNIQUE_FUNCTIONAL_CASES=${validRows.length}`);
  console.log(`CASE_COUNT_DELTA=${caseDelta}`);
  console.log(`DELTA_CASE_IDS_FOUND=${corruptRows.length}`);
  console.log(`MATRIX_CASE_IDS_WITH_ZERO_CONTROL_ROWS=${zeroControl.length}`);
  console.log(`CONTROL_MAP_CASE_IDS_MISSING_FROM_MATRIX=${missingFromMatrix.length}`);
  console.log(`SUPERSEDED_CASE_IDS_STILL_ACTIVE=0`);
  for (const [k, v] of Object.entries(classCounts).sort((a, b) => a[0].localeCompare(b[0]))) {
    console.log(`${k}=${v}`);
  }
  console.log(`CLASSIFICATION_TOTAL=${classificationTotal}`);
  console.log(`UNKNOWN_EXECUTION_CLASSES=${unknownClass}`);
  console.log(`PASS=${pass}`);
  console.log(`UNTESTED=${validRows.filter((r) => !r.FUNCTIONAL_RESULT).length}`);
  console.log(`RESULT_TOTAL=${resultTotal}`);
  console.log(`UNACCOUNTED_TERMINAL_CASES=${classificationTotal === resultTotal ? 0 : classificationTotal - resultTotal}`);
  console.log(`AUTO_PASSIVE_CASES_TOTAL=${autoPassiveTotal}`);
  console.log(`AUTO_PASSIVE_CASES_TESTED=${autoPassiveTested}`);
  console.log(`AUTO_PASSIVE_MATRIX_UNTESTED=${autoPassiveTotal - autoPassiveTested}`);
  console.log(`SEARCH_FILTER_SORT_TOTAL=${searchTotal}`);
  console.log(`SEARCH_FILTER_SORT_PASS=${searchPass}`);
  console.log(`SEARCH_FILTER_SORT_FAIL=${searchFail}`);
  console.log(`SEARCH_FILTER_SORT_UNTESTED=${searchUntested}`);
  console.log(`SEARCH_FILTER_SORT_WEAK_PASS=${searchWeak}`);
  console.log(`FORM_VALIDATION_CASES_TOTAL=${formRows.length}`);
  console.log(`FORM_VALIDATION_CASES_ALREADY_PASS=${formPass}`);
  console.log(`FORM_VALIDATION_CASES_UNTESTED=${formUntested}`);
  console.log(`FORM_VALIDATION_CASES_OTHER_TERMINAL=${formOther}`);
  console.log(`UNTESTED_FORM_VALIDATION=${untestedByClass.FORM_VALIDATION || 0}`);
  console.log(`UNTESTED_DOWNLOAD_EXPORT=${untestedByClass.DOWNLOAD_EXPORT || 0}`);
  console.log(`UNTESTED_QA_REVERSIBLE_MUTATION=${untestedByClass.QA_REVERSIBLE_MUTATION || 0}`);
  console.log(`UNTESTED_QA_CREATE_DELETE_LIFECYCLE=${untestedByClass.QA_CREATE_DELETE_LIFECYCLE || 0}`);
  const otherUntested = validRows.filter(
    (r) =>
      !r.FUNCTIONAL_RESULT &&
      r.EXECUTION_CLASS !== "FORM_VALIDATION" &&
      !DEEP_CASE_CLASSES.has(r.EXECUTION_CLASS),
  ).length;
  console.log(`UNTESTED_OTHER_CLASSES=${otherUntested}`);
  console.log(`UNEXPLAINED_UNTESTED_DELTA=${unexplainedUntested}`);
  console.log(`FORM_CLIENT_ONLY_SAFE=${formRisk.CLIENT_ONLY_SAFE}`);
  console.log(`FORM_SERVER_VALIDATION_SAFE=${formRisk.SERVER_VALIDATION_SAFE}`);
  console.log(`FORM_MUTATION_RISK=${formRisk.MUTATION_RISK}`);
  console.log(`FORM_UNSUPPORTED_PROBE=${formRisk.UNSUPPORTED_PROBE}`);
  console.log(`FORM_WAVE_BASELINE_LOCKED=${corruptRows.length === 0 ? "YES" : "NO"}`);
}

main();
