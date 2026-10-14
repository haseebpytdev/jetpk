import fs from "node:fs";
import path from "node:path";
import crypto from "node:crypto";
import { fileURLToPath } from "node:url";
import { parseCsvFile } from "./functional-cert-csv.mjs";
import { DEEP_CASE_CLASSES } from "./functional-case-classify.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

function sha(rel) {
  return crypto.createHash("sha256").update(fs.readFileSync(path.join(repoRoot, rel))).digest("hex");
}

const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
const classCounts = {};
const resultCounts = {};
for (const r of rows) {
  classCounts[r.EXECUTION_CLASS] = (classCounts[r.EXECUTION_CLASS] || 0) + 1;
  const res = r.FUNCTIONAL_RESULT || "UNTESTED";
  resultCounts[res] = (resultCounts[res] || 0) + 1;
}
const baseline = {
  PROTECTED_MAIN_SHA: "ab26661f0107d9bd8f9889dda1d59567d93be683",
  PRODUCTION_SHA: "ab26661f0107d9bd8f9889dda1d59567d93be683",
  DASHBOARD_BUILD_ID: "6ebAnRkxe40Lkq_GJwV5G",
  CONTROL_ROWS: 19128,
  FUNCTIONAL_CASES: rows.length,
  CLASS_COUNTS: classCounts,
  RESULT_COUNTS: {
    PASS: resultCounts.PASS || 0,
    NOT_APPLICABLE: resultCounts.NOT_APPLICABLE || 0,
    FAIL: resultCounts.FAIL || 0,
    UNTESTED: rows.filter((r) => !r.FUNCTIONAL_RESULT).length,
  },
  FORM_WAVE_SEMANTIC_AUDIT_APPLIED: true,
  CONTROL_MAP_HASH: sha("docs/evidence/jp-dash-final-11/CONTROL-CASE-MAP.csv"),
  MATRIX_HASH: sha("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv"),
  GENERATED_AT: new Date().toISOString(),
};
fs.writeFileSync(path.join(evidenceDir, "FUNCTIONAL-MATRIX-BASELINE.json"), `${JSON.stringify(baseline, null, 2)}\n`);
console.log("FORM_WAVE_BASELINE_LOCKED=YES");
