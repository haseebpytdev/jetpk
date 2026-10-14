/**
 * Reset matrix FAIL rows triaged as CERT_TOOLING_DEFECT to UNTESTED (retry).
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { ensureMatrixEvidenceColumns } from "./functional-cert-matrix-header.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

function ensureRetryColumns(header, rows) {
  for (const col of ["LAST_ATTEMPT_RESULT", "TOOLING_BLOCK_REASON", "RETRY_REQUIRED"]) {
    if (!header.includes(col)) {
      header.push(col);
    }
  }
  for (const row of rows) {
    if (row.LAST_ATTEMPT_RESULT == null) row.LAST_ATTEMPT_RESULT = "";
    if (row.TOOLING_BLOCK_REASON == null) row.TOOLING_BLOCK_REASON = "";
    if (row.RETRY_REQUIRED == null) row.RETRY_REQUIRED = "";
  }
}

function main() {
  const triagePath = path.join(evidenceDir, "FUNCTIONAL-FAIL-TRIAGE.csv");
  const toolingIds = new Map();
  if (fs.existsSync(triagePath)) {
    const { rows: triage } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-FAIL-TRIAGE.csv");
    for (const t of triage) {
      if (t.CLASSIFICATION === "CERT_TOOLING_DEFECT" && t.FUNCTIONAL_CASE_ID) {
        toolingIds.set(t.FUNCTIONAL_CASE_ID, t.ROOT_CAUSE_GROUP || t.OBSERVED || "cert_tooling");
      }
    }
  }

  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  ensureMatrixEvidenceColumns(header, rows);
  ensureRetryColumns(header, rows);

  let reset = 0;
  let excluded = 0;
  const log = [];
  for (const row of rows) {
    if (row.FUNCTIONAL_RESULT !== "FAIL") {
      continue;
    }
    const id = row.FUNCTIONAL_CASE_ID;
    if (!toolingIds.has(id)) {
      excluded += 1;
      continue;
    }
    log.push({
      FUNCTIONAL_CASE_ID: id,
      OLD_RESULT: "FAIL",
      TOOLING_BLOCK_REASON: toolingIds.get(id),
      LAST_ATTEMPT_EVIDENCE: row.FUNCTIONAL_EVIDENCE || "",
    });
    row.LAST_ATTEMPT_RESULT = "TOOLING_BLOCKED";
    row.TOOLING_BLOCK_REASON = String(toolingIds.get(id)).slice(0, 200);
    row.RETRY_REQUIRED = "YES";
    row.FUNCTIONAL_RESULT = "";
    row.LAST_EXECUTED_AT = "";
    reset += 1;
  }

  writeCsvFile(fullPath, header, rows);
  const outHeader = ["FUNCTIONAL_CASE_ID", "OLD_RESULT", "TOOLING_BLOCK_REASON", "LAST_ATTEMPT_EVIDENCE"];
  const lines = [outHeader.join(",")];
  for (const r of log) {
    lines.push(outHeader.map((h) => csvEscape(r[h])).join(","));
  }
  fs.writeFileSync(path.join(evidenceDir, "CERT-TOOLING-FAIL-RESET.csv"), `${lines.join("\n")}\n`, "utf8");

  const failRemaining = rows.filter((r) => r.FUNCTIONAL_RESULT === "FAIL").length;
  console.log(`CERT_TOOLING_FAILED_CASES_RESET=${reset}`);
  console.log(`CERT_TOOLING_FAIL_RESET_EXCLUDED=${excluded}`);
  console.log(`FUNCTIONAL_CASES_FAIL=${failRemaining}`);
}

main();
