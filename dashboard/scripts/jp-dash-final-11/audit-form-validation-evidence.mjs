/**
 * Phase 9: FORM_VALIDATION PASS must include recorded validation evidence.
 */
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile } from "./functional-cert-csv.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");

function hasRealEvidence(row) {
  const note = String(row.FUNCTIONAL_EVIDENCE ?? row.VALIDATION_EVIDENCE ?? "").trim();
  if (!note) {
    return false;
  }
  return /validation|invalid|422|required|error|prevented|constraint/i.test(note);
}

function main() {
  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  let weak = 0;
  let withEvidence = 0;
  for (const row of rows) {
    if (row.EXECUTION_CLASS !== "FORM_VALIDATION" || row.FUNCTIONAL_RESULT !== "PASS") {
      continue;
    }
    if (hasRealEvidence(row)) {
      withEvidence += 1;
      continue;
    }
    weak += 1;
    row.FUNCTIONAL_RESULT = "";
    row.LAST_EXECUTED_AT = "";
  }
  if (weak > 0) {
    writeCsvFile(fullPath, header, rows);
  }
  console.log(`FORM_VALIDATION_PASS_WITH_REAL_EVIDENCE=${withEvidence}`);
  console.log(`WEAK_FORM_VALIDATION_PASSES=${weak}`);
}

main();
