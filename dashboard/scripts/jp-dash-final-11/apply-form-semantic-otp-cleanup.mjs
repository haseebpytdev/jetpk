/**
 * Re-apply OTP + deposit textarea semantic rules on current matrix.
 */
import { parseCsvFile, writeCsvFile } from "./functional-cert-csv.mjs";
import { classifyFormSemantic } from "./form-semantic-classify.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";

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
      const sem = classifyFormSemantic(row);
      if (sem.RECOMMENDED_CLASS === row.EXECUTION_CLASS) {
        continue;
      }
      row.EXECUTION_CLASS = sem.RECOMMENDED_CLASS;
      if (sem.RECOMMENDED_CLASS === "NOT_APPLICABLE") {
        row.FUNCTIONAL_RESULT = "NOT_APPLICABLE";
        row.FUNCTIONAL_EVIDENCE = `semantic_audit:${sem.REASON}`;
      } else {
        row.FUNCTIONAL_RESULT = "";
        row.FUNCTIONAL_EVIDENCE = `semantic_reclass:${sem.REASON}`;
      }
      row.LAST_EXECUTED_AT = new Date().toISOString();
      n += 1;
    }
    writeCsvFile(fullPath, header, rows);
    console.log(`FORM_SEMANTIC_FOLLOWUP_RECLASS=${n}`);
  } finally {
    releaseMatrixLock();
  }
}

main();
