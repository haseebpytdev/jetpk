/**
 * Apply DEEP-LEGACY-RESULT-AUDIT RESET_UNTESTED to matrix.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile } from "./functional-cert-csv.mjs";
import { isDeepCaseClass } from "./functional-case-classify.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");

function main() {
  const auditPath = path.join(repoRoot, "docs/evidence/jp-dash-final-11/DEEP-LEGACY-RESULT-AUDIT.csv");
  const resetIds = new Set();
  if (fs.existsSync(auditPath)) {
    const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/DEEP-LEGACY-RESULT-AUDIT.csv");
    for (const r of rows) {
      if (r.VALID_FOR_FINAL_CERT === "RESET_UNTESTED") {
        resetIds.add(r.FUNCTIONAL_CASE_ID);
      }
    }
  }

  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  let applied = 0;
  for (const row of rows) {
    if (!isDeepCaseClass(row.EXECUTION_CLASS) || !row.FUNCTIONAL_RESULT) {
      continue;
    }
    if (resetIds.size > 0 && !resetIds.has(row.FUNCTIONAL_CASE_ID)) {
      continue;
    }
    row.FUNCTIONAL_RESULT = "";
    row.LAST_EXECUTED_AT = "";
    row.FUNCTIONAL_EVIDENCE = "";
    row.FUNCTIONAL_EVIDENCE_TYPE = "";
    row.FUNCTIONAL_EVIDENCE_VALUE_SAFE = "";
    applied += 1;
  }
  writeCsvFile(fullPath, header, rows);
  console.log(`LEGACY_DEEP_RESULTS_RESET_APPLIED=${applied}`);
  const remaining = rows.filter((r) => isDeepCaseClass(r.EXECUTION_CLASS) && r.FUNCTIONAL_RESULT).length;
  console.log(`INVALID_LEGACY_DEEP_TERMINAL_RESULTS=${remaining}`);
}

main();
