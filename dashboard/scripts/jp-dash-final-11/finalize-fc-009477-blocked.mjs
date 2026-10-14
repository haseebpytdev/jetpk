import { parseCsvFile, writeCsvFile } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";

const lock = acquireMatrixLock();
const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
const row = rows.find((r) => r.FUNCTIONAL_CASE_ID === "FC-009477");
if (row) {
  row.FUNCTIONAL_RESULT = "BLOCKED";
  row.FUNCTIONAL_EVIDENCE = "deep_w03:blocked:AUTOMATION_UNSUPPORTED:agent_staff_accountant_profile_personal_edit_422";
  row.LAST_EXECUTED_AT = new Date().toISOString();
}
writeCsvFile(fullPath, header, rows);
releaseMatrixLock();
console.log("FC_009477_BLOCKED=YES");
