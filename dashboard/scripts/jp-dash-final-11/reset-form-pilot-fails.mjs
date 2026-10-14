/**
 * Reset FORM_VALIDATION FAIL rows without application-defect evidence to UNTESTED.
 */
import { parseCsvFile, writeCsvFile } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";

const TOOLING_FAIL =
  /server_validation_deferred|form_not_found|control_not_found|auth_|probe_error|deferred_no_submit|no_submit|no_validation_signal/i;

function main() {
  const lock = acquireMatrixLock();
  if (!lock.ok) {
    process.exit(1);
  }
  try {
    const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
    let reset = 0;
    for (const row of rows) {
      if (row.EXECUTION_CLASS !== "FORM_VALIDATION" || row.FUNCTIONAL_RESULT !== "FAIL") {
        continue;
      }
      const ev = String(row.FUNCTIONAL_EVIDENCE || "");
      if (TOOLING_FAIL.test(ev) || !ev) {
        row.FUNCTIONAL_RESULT = "";
        row.FUNCTIONAL_EVIDENCE = "";
        row.LAST_EXECUTED_AT = "";
        reset += 1;
      }
    }
    writeCsvFile(fullPath, header, rows);
    console.log(`FORM_PILOT_FAIL_RESET=${reset}`);
  } finally {
    releaseMatrixLock();
  }
}

main();
