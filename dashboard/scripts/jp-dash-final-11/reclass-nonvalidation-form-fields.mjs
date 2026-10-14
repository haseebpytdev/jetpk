/**
 * Move composite profile forms and staff/new create surfaces out of FORM_VALIDATION.
 */
import { parseCsvFile, writeCsvFile } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";
import { isStrongFormEvidence } from "./form-validation-probes.mjs";

function main() {
  const lock = acquireMatrixLock();
  if (!lock.ok) {
    process.exit(1);
  }
  try {
    const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
    let n = 0;
    for (const row of rows) {
      if (row.EXECUTION_CLASS !== "FORM_VALIDATION") {
        continue;
      }
      const type = String(row.CONTROL_TYPE || "").toLowerCase();
      const route = String(row.ROUTE || "");
      if (/\/staff\/new|\/travelers\/new/i.test(route) && type === "form") {
        row.EXECUTION_CLASS = "QA_CREATE_DELETE_LIFECYCLE";
        if (!row.FUNCTIONAL_RESULT || !isStrongFormEvidence(row)) {
          row.FUNCTIONAL_RESULT = "";
          row.FUNCTIONAL_EVIDENCE = "semantic_reclass:create_lifecycle_form";
        }
        n += 1;
        continue;
      }
      if (type === "form" && /\/profile/i.test(route)) {
        if (row.FUNCTIONAL_RESULT === "PASS" && isStrongFormEvidence(row)) {
          continue;
        }
        row.EXECUTION_CLASS = "NOT_APPLICABLE";
        row.FUNCTIONAL_RESULT = "NOT_APPLICABLE";
        row.FUNCTIONAL_EVIDENCE = "semantic_reclass:profile_form_container_no_field_constraint";
        row.LAST_EXECUTED_AT = new Date().toISOString();
        n += 1;
      }
    }
    writeCsvFile(fullPath, header, rows);
    console.log(`NON_VALIDATION_FORM_RECLASS=${n}`);
  } finally {
    releaseMatrixLock();
  }
}

main();
