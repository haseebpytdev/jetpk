/**
 * Reset matrix FAIL rows classified as CERT_TOOLING_DEFECT in FUNCTIONAL-FAIL-TRIAGE.csv.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile } from "./functional-cert-csv.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");

function main() {
  const triagePath = path.join(repoRoot, "docs/evidence/jp-dash-final-11/FUNCTIONAL-FAIL-TRIAGE.csv");
  if (!fs.existsSync(triagePath)) {
    console.log("TOOLING_FAIL_RESET=0");
    return;
  }
  const { rows: triage } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-FAIL-TRIAGE.csv");
  const toolingIds = new Set(
    triage.filter((t) => t.CLASSIFICATION === "CERT_TOOLING_DEFECT").map((t) => t.FUNCTIONAL_CASE_ID),
  );
  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  let reset = 0;
  for (const row of rows) {
    if (row.FUNCTIONAL_RESULT === "FAIL" && toolingIds.has(row.FUNCTIONAL_CASE_ID)) {
      row.FUNCTIONAL_RESULT = "";
      row.LAST_EXECUTED_AT = "";
      reset += 1;
    }
  }
  if (reset > 0) {
    writeCsvFile(fullPath, header, rows);
  }
  console.log(`TOOLING_FAIL_RESET=${reset}`);
}

main();
