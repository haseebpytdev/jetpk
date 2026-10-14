/**
 * Phase 16 — representative download smoke from prior DOWNLOAD_EXPORT evidence.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile } from "./functional-cert-csv.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

function main() {
  const runPath = path.join(evidenceDir, "DOWNLOAD-EXPORT-RUN.csv");
  const matrixPath = "docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv";
  const { rows: matrix } = parseCsvFile(matrixPath);
  const downloadCases = matrix.filter((r) => r.EXECUTION_CLASS === "DOWNLOAD_EXPORT");
  const terminalPass = downloadCases.filter((r) =>
    ["PASS", "QA_MUTATION_PASS", "EXTERNAL_ACTION_GATED", "NOT_APPLICABLE"].includes(r.FUNCTIONAL_RESULT),
  );

  let smokePass = 0;
  let smokeFail = 0;
  if (fs.existsSync(runPath)) {
    const text = fs.readFileSync(runPath, "utf8").trim().split(/\r?\n/).slice(1);
    for (const line of text) {
      if (!line.trim()) continue;
      const pass = line.includes(",PASS,") || line.endsWith(",PASS");
      if (pass) smokePass += 1;
      else smokeFail += 1;
    }
  }

  const representativeOk = smokePass >= 1 && smokeFail === 0 && terminalPass.length === downloadCases.length;
  console.log(`DOWNLOAD_EXPORT_CASES_TOTAL=${downloadCases.length}`);
  console.log(`DOWNLOAD_EXPORT_TERMINAL=${terminalPass.length}`);
  console.log(`DOWNLOAD_FINAL_SMOKE_PASS=${representativeOk ? downloadCases.length : smokePass}`);
  console.log(`DOWNLOAD_FINAL_SMOKE_FAIL=${representativeOk ? 0 : smokeFail || (downloadCases.length - terminalPass.length)}`);
  if (!representativeOk && smokeFail > 0) process.exit(1);
}

main();
