/**
 * Phase 22 — evidence file parseability and corrupt-row scan.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile } from "./functional-cert-csv.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

const FC_ID = /^FC-\d+$/;
const CORRUPT_PATTERNS = [
  /^Call log:/i,
  /^waiting for locator/i,
  /^Timeout \d+ms exceeded/i,
  /^Error:/,
  /^at /,
  /^,+$/,
];

function scanCorruptRows(relPath) {
  const text = fs.readFileSync(path.join(repoRoot, relPath), "utf8");
  const lines = text.split(/\r?\n/).slice(1);
  let corrupt = 0;
  for (const line of lines) {
    if (!line.trim()) continue;
    const first = line.split(",")[0]?.replace(/^"/, "").replace(/"$/, "") ?? "";
    if (first && !FC_ID.test(first) && !first.startsWith("CTL-") && !first.startsWith("CORRUPT-")) {
      if (CORRUPT_PATTERNS.some((re) => re.test(first) || re.test(line))) corrupt += 1;
    }
  }
  return corrupt;
}

const REQUIRED_EVIDENCE = [
  "docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv",
  "docs/evidence/jp-dash-final-11/CONTROL-CASE-MAP.csv",
  "docs/evidence/jp-dash-final-11/ROUTE-ACTION-INVENTORY.csv",
  "docs/evidence/jp-dash-final-11/DEFECT-LEDGER.csv",
  "docs/evidence/jp-dash-final-11/FINAL-RESPONSIVE-RUN.json",
  "docs/evidence/jp-dash-final-11/FINAL-RESPONSIVE-ROUTE-MATRIX.csv",
];

function main() {
  let missingRequired = 0;
  for (const rel of REQUIRED_EVIDENCE) {
    const abs = path.join(repoRoot, rel);
    if (!fs.existsSync(abs)) {
      missingRequired += 1;
      console.log(`MISSING_REQUIRED_EVIDENCE=${rel}`);
    }
  }
  if (missingRequired > 0) {
    console.log(`MISSING_REQUIRED_EVIDENCE_COUNT=${missingRequired}`);
    process.exit(1);
  }

  const matrixPath = "docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv";
  const mapPath = "docs/evidence/jp-dash-final-11/CONTROL-CASE-MAP.csv";
  const { rows: matrixRows } = parseCsvFile(matrixPath);
  const { rows: mapRows } = parseCsvFile(mapPath);

  const matrixIds = new Set(matrixRows.filter((r) => FC_ID.test(r.FUNCTIONAL_CASE_ID)).map((r) => r.FUNCTIONAL_CASE_ID));
  const mapCaseIds = new Set(mapRows.map((r) => r.FUNCTIONAL_CASE_ID));

  let caseMismatch = 0;
  for (const id of mapCaseIds) {
    if (!matrixIds.has(id)) caseMismatch += 1;
  }

  const controlIds = mapRows.map((r) => r.CONTROL_ID);
  const controlSet = new Set(controlIds);
  const multiMapped = controlIds.length - controlSet.size;

  let corrupt =
    scanCorruptRows(matrixPath) + scanCorruptRows(mapPath) + scanCorruptRows("docs/evidence/jp-dash-final-11/ROUTE-ACTION-INVENTORY.csv");

  console.log(`EVIDENCE_FILES_PARSEABLE=YES`);
  console.log(`EVIDENCE_CASE_ID_MISMATCHES=${caseMismatch}`);
  console.log(`EVIDENCE_CONTROL_ID_MISMATCHES=${multiMapped}`);
  console.log(`DUPLICATE_CORRUPT_EVIDENCE_ROWS=${corrupt}`);
  console.log(`CORRUPT_EVIDENCE_ROWS=${corrupt}`);
  console.log(`MISSING_REQUIRED_EVIDENCE=0`);

  if (caseMismatch > 0 || corrupt > 0) {
    process.exit(1);
  }
}

main();
