/**
 * Build browser-matrix-summary.json from authoritative browser-matrix-report.jsonl.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const evidenceDir = path.resolve(__dirname, "../../docs/evidence/jp-ai-production-canary-01");
const reportPath = path.join(evidenceDir, "browser-matrix-report.jsonl");
const summaryPath = path.join(evidenceDir, "browser-matrix-summary.json");

export function summarizeBrowserMatrixFromJsonl(options = {}) {
  const jsonlPath = options.reportPath ?? reportPath;
  const lines = fs.existsSync(jsonlPath)
    ? fs.readFileSync(jsonlPath, "utf8").split(/\r?\n/).filter(Boolean)
    : [];

  /** @type {Map<string, Record<string, unknown>>} */
  const byCase = new Map();
  for (const line of lines) {
    const row = JSON.parse(line);
    const caseId = String(row.case_id ?? "");
    if (!caseId) continue;
    byCase.set(caseId, row);
  }

  const caseIds = [...byCase.keys()].sort();
  const passed = caseIds.filter((id) => byCase.get(id)?.pass_fail === "PASS");
  const failed = caseIds.filter((id) => byCase.get(id)?.pass_fail === "FAIL");
  const duplicateIds = lines
    .map((line) => String(JSON.parse(line).case_id ?? ""))
    .filter(Boolean)
    .reduce((acc, id) => {
      acc[id] = (acc[id] ?? 0) + 1;
      return acc;
    }, /** @type {Record<string, number>} */ ({}));

  const duplicates = Object.entries(duplicateIds)
    .filter(([, count]) => count > 1)
    .map(([id, count]) => ({ case_id: id, count }));

  const summary = {
    phase: "JP-AI-PRODUCTION-CANARY-HTTP-GATEWAY-01",
    source: path.basename(jsonlPath),
    generated_at: new Date().toISOString(),
    TOTAL: caseIds.length,
    PASS: passed.length,
    FAIL: failed.length,
    NOT_RUN: Math.max(0, 30 - caseIds.length),
    PASSED_CASE_IDS: passed,
    FAILED_CASE_IDS: failed,
    unique_case_ids: caseIds.length,
    duplicate_case_ids: duplicates,
    arithmetic_ok: passed.length + failed.length === caseIds.length,
    cases: caseIds.map((id) => ({
      case_id: id,
      pass_fail: byCase.get(id)?.pass_fail ?? "UNKNOWN",
      failure_class: byCase.get(id)?.failure_class ?? null,
    })),
  };

  if (!options.skipWrite) {
    fs.mkdirSync(evidenceDir, { recursive: true });
    fs.writeFileSync(options.summaryPath ?? summaryPath, JSON.stringify(summary, null, 2));
  }

  return summary;
}

if (import.meta.url === `file://${process.argv[1].replace(/\\/g, "/")}` || process.argv[1]?.endsWith("summarize-browser-matrix-from-jsonl.mjs")) {
  const summary = summarizeBrowserMatrixFromJsonl();
  console.log(JSON.stringify(summary, null, 2));
  if (summary.TOTAL !== 30 || !summary.arithmetic_ok || summary.duplicate_case_ids.length > 0) {
    process.exit(1);
  }
}
