/**
 * Phase 1: verify FUNCTIONAL-MATRIX-BASELINE.json against current matrix/map.
 */
import fs from "node:fs";
import path from "node:path";
import crypto from "node:crypto";
import { fileURLToPath } from "node:url";
import { parseCsvFile } from "./functional-cert-csv.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const baselinePath = path.join(repoRoot, "docs/evidence/jp-dash-final-11/FUNCTIONAL-MATRIX-BASELINE.json");

function shaFile(rel) {
  const p = path.join(repoRoot, rel);
  return crypto.createHash("sha256").update(fs.readFileSync(p)).digest("hex");
}

function main() {
  const baseline = JSON.parse(fs.readFileSync(baselinePath, "utf8"));
  const mapHash = shaFile("docs/evidence/jp-dash-final-11/CONTROL-CASE-MAP.csv");
  const matrixHash = shaFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const ok =
    baseline.CONTROL_ROWS === 19128 &&
    rows.length === 15155 &&
    mapHash === baseline.CONTROL_MAP_HASH;
  console.log(`BASELINE_INTEGRITY=${ok ? "PASS" : "FAIL"}`);
  console.log(`BASELINE_MATRIX_HASH_MATCH=${matrixHash === baseline.MATRIX_HASH ? "YES" : "NO"}`);
  console.log(`BASELINE_MAP_HASH_MATCH=${mapHash === baseline.CONTROL_MAP_HASH ? "YES" : "NO"}`);
  console.log(`CURRENT_MATRIX_ROWS=${rows.length}`);
  if (!ok) {
    console.log(`BASELINE_NOTE=matrix_or_map_changed_since_baseline`);
  }
}

main();
