/**
 * Reset auth-contaminated functional results; preserve valid-auth outcomes.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile, csvEscape } from "./functional-cert-csv.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

const VALID_AUTH_IDENTITIES = new Set([
  "platform_admin",
  "staff",
  "agent_owner_a",
  "agent_owner_b",
  "agent_staff_manager",
  "agent_staff_accountant",
  "agent_staff_sales",
  "agent_staff_support",
  "agent_staff_ticketing",
  "agent_staff_viewer",
  "legacy_agency_admin",
  "customer_a",
  "customer_b",
]);

/** Post FINAL-11 password sync + probe-auth-states 13/13, no identities are auth-blocked. */
const CONTAMINATED_IDENTITIES = new Set([]);

const PASSIVE_LIGHT_CLASSES = new Set([
  "NAVIGATION",
  "READ_OPERATION",
  "SEARCH_FILTER_SORT",
  "PAGINATION",
  "DRAWER_MODAL",
  "FORM_VALIDATION",
  "READ_ONLY_BY_DESIGN",
  "NEGATIVE_RBAC",
  "EXTERNAL_ACTION_GATED",
]);

function main() {
  const matrixRel = "docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv";
  const { header, rows, fullPath } = parseCsvFile(matrixRel);

  const reconciliationRows = [];
  let authContaminated = 0;
  let authFailsReset = 0;
  let validFailCandidates = 0;
  let formValidationReset = 0;

  for (const row of rows) {
    const id = row.FUNCTIONAL_CASE_ID;
    const identity = row.QA_IDENTITY_KEY;
    const oldResult = row.FUNCTIONAL_RESULT || "";
    const oldError = "";
    let reconciliation = "";
    let newResult = oldResult;
    let rationale = "";

    if (CONTAMINATED_IDENTITIES.has(identity) && oldResult) {
      reconciliation = "RESET_AUTH_CONTAMINATED";
      newResult = "";
      rationale = "Executed while identity auth was known invalid/stale";
      authContaminated += 1;
      if (oldResult === "FAIL") {
        authFailsReset += 1;
      }
      row.FUNCTIONAL_RESULT = "";
      row.LAST_EXECUTED_AT = "";
    } else if (VALID_AUTH_IDENTITIES.has(identity) && oldResult === "FAIL") {
      reconciliation = "KEEP_GENUINE_FAIL_CANDIDATE";
      newResult = oldResult;
      rationale = "Valid-auth identity; retain for triage rerun";
      validFailCandidates += 1;
    } else if (VALID_AUTH_IDENTITIES.has(identity) && oldResult) {
      reconciliation = oldResult === "PASS" ? "KEEP_VALID_PASS" : "KEEP_CLASSIFICATION";
      newResult = oldResult;
      rationale = "Valid-auth session preserved";
    }

    if (
      VALID_AUTH_IDENTITIES.has(identity) &&
      row.EXECUTION_CLASS === "FORM_VALIDATION" &&
      oldResult === "PASS"
    ) {
      reconciliation = "RESET_FORM_VALIDATION_WEAK";
      newResult = "";
      rationale = "Pilot form validation did not prove validation response; reset to UNTESTED";
      row.FUNCTIONAL_RESULT = "";
      row.LAST_EXECUTED_AT = "";
      formValidationReset += 1;
    }

    if (reconciliation) {
      reconciliationRows.push({
        FUNCTIONAL_CASE_ID: id,
        QA_IDENTITY_KEY: identity,
        OLD_RESULT: oldResult,
        OLD_ERROR: oldError,
        AUTH_STATE_AT_EXECUTION: CONTAMINATED_IDENTITIES.has(identity) ? "INVALID" : "VALID",
        RECONCILIATION: reconciliation,
        NEW_RESULT: newResult ? newResult : "UNTESTED",
        RATIONALE: rationale,
      });
    }
  }

  writeCsvFile(fullPath, header, rows);

  const reconHeader = [
    "FUNCTIONAL_CASE_ID",
    "QA_IDENTITY_KEY",
    "OLD_RESULT",
    "OLD_ERROR",
    "AUTH_STATE_AT_EXECUTION",
    "RECONCILIATION",
    "NEW_RESULT",
    "RATIONALE",
  ];
  const reconLines = [reconHeader.join(",")];
  for (const r of reconciliationRows) {
    reconLines.push(reconHeader.map((h) => csvEscape(r[h])).join(","));
  }
  fs.writeFileSync(path.join(evidenceDir, "FUNCTIONAL-RESULT-RECONCILIATION.csv"), `${reconLines.join("\n")}\n`, "utf8");

  const testedValid = rows.filter((r) => r.FUNCTIONAL_RESULT).length;
  const passValid = rows.filter((r) => r.FUNCTIONAL_RESULT === "PASS").length;
  const failCandidates = rows.filter((r) => r.FUNCTIONAL_RESULT === "FAIL").length;
  const untested = rows.length - testedValid;

  const { rows: map } = parseCsvFile("docs/evidence/jp-dash-final-11/CONTROL-CASE-MAP.csv");
  const testedIds = new Set(rows.filter((r) => r.FUNCTIONAL_RESULT).map((r) => r.FUNCTIONAL_CASE_ID));
  let covered = 0;
  for (const m of map) {
    if (testedIds.has(m.FUNCTIONAL_CASE_ID)) {
      covered += 1;
    }
  }

  const passiveLightTotal = rows.filter((r) => PASSIVE_LIGHT_CLASSES.has(r.EXECUTION_CLASS)).length;
  const passiveLightTested = rows.filter(
    (r) => PASSIVE_LIGHT_CLASSES.has(r.EXECUTION_CLASS) && r.FUNCTIONAL_RESULT,
  ).length;

  console.log(`AUTH_CONTAMINATED_CASES=${authContaminated}`);
  console.log(`AUTH_CONTAMINATED_FAILS_RESET=${authFailsReset}`);
  console.log(`VALID_AUTH_FAIL_CANDIDATES=${validFailCandidates}`);
  console.log(`FORM_VALIDATION_RESET=${formValidationReset}`);
  console.log(`FUNCTIONAL_CASES_TESTED_VALID=${testedValid}`);
  console.log(`FUNCTIONAL_CASES_PASS_VALID=${passValid}`);
  console.log(`FUNCTIONAL_CASES_FAIL_CANDIDATES=${failCandidates}`);
  console.log(`UNTESTED_FUNCTIONAL_CASES=${untested}`);
  console.log(`CONTROL_ROWS_COVERED_BY_VALID_RESULTS=${covered}`);
  console.log(`CONTROL_ROWS_UNCOVERED=${map.length - covered}`);
  console.log(`PASSIVE_LIGHT_CASES_TOTAL=${passiveLightTotal}`);
  console.log(`PASSIVE_LIGHT_CASES_TESTED=${passiveLightTested}`);
  console.log(`UNTESTED_PASSIVE_LIGHT_CASES=${passiveLightTotal - passiveLightTested}`);
}

main();
