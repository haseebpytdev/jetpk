/**
 * Phase 1: reset SEARCH_FILTER_SORT PASS rows without strong filter evidence.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { isStrongSearchEvidence } from "./functional-cert-evidence.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

function main() {
  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const resets = [];
  let resetCount = 0;

  for (const row of rows) {
    if (row.EXECUTION_CLASS !== "SEARCH_FILTER_SORT" || row.FUNCTIONAL_RESULT !== "PASS") {
      continue;
    }
    if (isStrongSearchEvidence(row)) {
      continue;
    }
    resets.push({
      FUNCTIONAL_CASE_ID: row.FUNCTIONAL_CASE_ID,
      ROLE: row.QA_IDENTITY_KEY,
      ROUTE: row.ROUTE,
      CONTROL: row.CONTROL_LABEL,
      OLD_RESULT: row.FUNCTIONAL_RESULT,
      RESET_REASON: `weak_evidence:${row.FUNCTIONAL_EVIDENCE || "none"}`,
      EXPECTED_PROOF: "url_query|api_filter|result_count|filter_chip|sort_order|clear_restore",
    });
    row.FUNCTIONAL_RESULT = "";
    row.LAST_EXECUTED_AT = "";
    row.FUNCTIONAL_EVIDENCE = "";
    row.FUNCTIONAL_EVIDENCE_TYPE = "";
    row.FUNCTIONAL_EVIDENCE_VALUE_SAFE = "";
    resetCount += 1;
  }

  if (resetCount > 0) {
    writeCsvFile(fullPath, header, rows);
  }

  const outHeader = [
    "FUNCTIONAL_CASE_ID",
    "ROLE",
    "ROUTE",
    "CONTROL",
    "OLD_RESULT",
    "RESET_REASON",
    "EXPECTED_PROOF",
  ];
  const lines = [outHeader.join(",")];
  for (const r of resets) {
    lines.push(outHeader.map((h) => csvEscape(r[h])).join(","));
  }
  fs.writeFileSync(path.join(evidenceDir, "SEARCH-FILTER-WEAK-RESET.csv"), `${lines.join("\n")}\n`, "utf8");

  console.log(`ROWS_RESET=${resetCount}`);
  console.log(`WEAK_SEARCH_FILTER_RESET=${resetCount}`);
}

main();
