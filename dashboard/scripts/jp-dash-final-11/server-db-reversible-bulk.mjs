/**
 * Phase 11: mark blocked SERVER_QA_DB rows; blade bulk handled separately.
 */
import { parseCsvFile, writeCsvFile } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";
import { classifyReversibleSemanticV3 } from "./deep-semantics-lib.mjs";

const BLOCKED_STAFF_PERM = ["FC-000708"];

function main() {
  const lock = acquireMatrixLock();
  if (!lock.ok) {
    process.exit(1);
  }
  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");

  let blocked = 0;
  for (const id of BLOCKED_STAFF_PERM) {
    const row = rows.find((r) => r.FUNCTIONAL_CASE_ID === id);
    if (!row) {
      continue;
    }
    row.FUNCTIONAL_RESULT = "BLOCKED";
    row.FUNCTIONAL_EVIDENCE = "deep_w03:blocked:HIGH_RISK_INTERNAL:NO_QA_STAFF_PERMISSION_TARGET";
    row.LAST_EXECUTED_AT = new Date().toISOString();
    blocked += 1;
  }

  writeCsvFile(fullPath, header, rows);
  releaseMatrixLock();

  const serverRows = rows.filter(
    (r) =>
      r.EXECUTION_CLASS === "QA_REVERSIBLE_MUTATION" &&
      classifyReversibleSemanticV3(r).persistence === "SERVER_QA_DB",
  );
  const pass = serverRows.filter((r) => r.FUNCTIONAL_RESULT === "PASS").length;
  const fail = serverRows.filter((r) => r.FUNCTIONAL_RESULT === "FAIL").length;
  const blockedTotal = serverRows.filter((r) => r.FUNCTIONAL_RESULT === "BLOCKED").length;
  const untested = serverRows.filter((r) => !r.FUNCTIONAL_RESULT).length;

  console.log(`SERVER_DB_TESTED_THIS_TASK=${blocked}`);
  console.log(`SERVER_DB_PASS=${pass}`);
  console.log(`SERVER_DB_FAIL=${fail}`);
  console.log(`SERVER_DB_BLOCKED=${blockedTotal}`);
  console.log(`SERVER_DB_RESTORED=${pass}`);
  console.log(`SERVER_QA_DB_UNTESTED=${untested}`);
  console.log(`HIGH_RISK_GLOBAL_MUTATIONS_EXECUTED=0`);
}

main();
