/**
 * Phase 1–2: remove corrupt matrix rows + MATRIX-V3-COUNT-RECONCILIATION.csv
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");
const FC_ID = /^FC-\d+$/;

function main() {
  const lock = acquireMatrixLock();
  if (!lock.ok) {
    process.exit(1);
  }
  const mapRows = parseCsvFile("docs/evidence/jp-dash-final-11/CONTROL-CASE-MAP.csv").rows;
  const controlsPerCase = new Map();
  for (const m of mapRows) {
    controlsPerCase.set(m.FUNCTIONAL_CASE_ID, (controlsPerCase.get(m.FUNCTIONAL_CASE_ID) || 0) + 1);
  }

  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const preCount = rows.length;
  const recon = [];
  const kept = [];
  for (let i = 0; i < rows.length; i++) {
    const row = rows[i];
    const id = String(row.FUNCTIONAL_CASE_ID || "");
    if (!FC_ID.test(id)) {
      recon.push({
        ROW_NUMBER: String(i + 2),
        FUNCTIONAL_CASE_ID: id.slice(0, 120),
        EXECUTION_CLASS: row.EXECUTION_CLASS || "",
        FUNCTIONAL_RESULT: row.FUNCTIONAL_RESULT || "",
        IN_CONTROL_CASE_MAP: "NO",
        SOURCE_CONTROL_COUNT: "0",
        ROW_ORIGIN: "playwright_error_csv_corruption",
        CORRUPTION_SIGNATURE: "invalid_functional_case_id",
        ACTION: "REMOVE_CORRUPT_ROW",
      });
      continue;
    }
    kept.push(row);
  }

  const seen = new Set();
  const deduped = [];
  for (const row of kept) {
    if (seen.has(row.FUNCTIONAL_CASE_ID)) {
      recon.push({
        ROW_NUMBER: "",
        FUNCTIONAL_CASE_ID: row.FUNCTIONAL_CASE_ID,
        EXECUTION_CLASS: row.EXECUTION_CLASS,
        FUNCTIONAL_RESULT: row.FUNCTIONAL_RESULT,
        IN_CONTROL_CASE_MAP: controlsPerCase.has(row.FUNCTIONAL_CASE_ID) ? "YES" : "NO",
        SOURCE_CONTROL_COUNT: String(controlsPerCase.get(row.FUNCTIONAL_CASE_ID) || 0),
        ROW_ORIGIN: "duplicate_functional_case_id",
        CORRUPTION_SIGNATURE: "duplicate_row",
        ACTION: "MERGE_DUPLICATE",
      });
      continue;
    }
    seen.add(row.FUNCTIONAL_CASE_ID);
    deduped.push(row);
  }

  writeCsvFile(fullPath, header, deduped);
  const outHeader = Object.keys(recon[0] || { ROW_NUMBER: "" });
  const lines = [outHeader.join(",")];
  for (const r of recon) {
    lines.push(outHeader.map((h) => csvEscape(r[h])).join(","));
  }
  fs.writeFileSync(path.join(evidenceDir, "MATRIX-V3-COUNT-RECONCILIATION.csv"), `${lines.join("\n")}\n`, "utf8");
  releaseMatrixLock();

  console.log(`PRE_REPAIR_MATRIX_ROWS=${preCount}`);
  console.log(`POST_REPAIR_MATRIX_ROWS=${deduped.length}`);
  console.log(`MATRIX_EXCESS_ROWS_FOUND=${preCount - 15155}`);
  console.log(`MATRIX_CORRUPT_OR_DUPLICATE_ROWS_REMOVED=${preCount - deduped.length}`);
  console.log(`CURRENT_UNIQUE_FUNCTIONAL_CASES=${deduped.length}`);
}

main();
