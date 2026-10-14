/**
 * Remove non-canonical matrix rows (invalid FUNCTIONAL_CASE_ID).
 * Backs up matrix before mutation.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock, writeFileAtomic } from "./functional-cert-lock.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");
const matrixRel = "docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv";
const FC_ID = /^FC-\d+$/;

function main() {
  const lock = acquireMatrixLock();
  if (!lock.ok) {
    console.error(`MATRIX_WRITER_LOCK=BLOCKED holder=${lock.holder}`);
    process.exit(1);
  }
  try {
    const { header, rows, fullPath } = parseCsvFile(matrixRel);
    const backup = path.join(evidenceDir, `FUNCTIONAL-CASE-MATRIX.pre-repair-${Date.now()}.csv`);
    fs.copyFileSync(fullPath, backup);
    const removed = [];
    const kept = [];
    for (const row of rows) {
      if (FC_ID.test(String(row.FUNCTIONAL_CASE_ID || ""))) {
        kept.push(row);
      } else {
        removed.push(row);
      }
    }
    const seen = new Set();
    const deduped = [];
    for (const row of kept) {
      const id = row.FUNCTIONAL_CASE_ID;
      if (seen.has(id)) {
        removed.push(row);
        continue;
      }
      seen.add(id);
      deduped.push(row);
    }
    writeCsvFile(fullPath, header, deduped);
    const logPath = path.join(evidenceDir, "MATRIX-CORRUPTION-REPAIR.csv");
    const logHeader = ["REMOVED_KEY", "FUNCTIONAL_RESULT", "NOTE"];
    const lines = [logHeader.join(",")];
    for (const r of removed) {
      lines.push(
        [
          JSON.stringify(String(r.FUNCTIONAL_CASE_ID || "").slice(0, 120)),
          r.FUNCTIONAL_RESULT || "",
          "invalid_or_duplicate_row",
        ].join(","),
      );
    }
    writeFileAtomic(logPath, `${lines.join("\n")}\n`);
    console.log(`MATRIX_ROWS_BEFORE=${rows.length}`);
    console.log(`MATRIX_ROWS_AFTER=${deduped.length}`);
    console.log(`MATRIX_ROWS_REMOVED=${removed.length}`);
    console.log(`MATRIX_BACKUP=${backup}`);
    console.log(`MATRIX_REPAIR=PASS`);
  } finally {
    releaseMatrixLock();
  }
}

main();
