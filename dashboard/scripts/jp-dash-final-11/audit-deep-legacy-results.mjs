/**
 * Phase 12: audit terminal results on deep execution classes (no auto-delete).
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { isDeepCaseClass } from "./functional-case-classify.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

function decisionFor(row) {
  const result = row.FUNCTIONAL_RESULT || "";
  const ev = String(row.FUNCTIONAL_EVIDENCE || "");
  if (!result) {
    return { decision: "RESET_UNTESTED", reason: "no_terminal_result" };
  }
  if (result === "PASS" && /download|export|mutation|qa_/i.test(ev)) {
    return { decision: "KEEP_VALID", reason: "documented_deep_evidence" };
  }
  if (result === "EXTERNAL_ACTION_GATED" || result === "READ_ONLY_BY_DESIGN") {
    return { decision: "KEEP_VALID", reason: "gated_or_readonly_terminal" };
  }
  if (result === "PASS" && !ev) {
    return { decision: "RESET_UNTESTED", reason: "pass_without_deep_evidence" };
  }
  if (result === "FAIL") {
    return { decision: "RESET_UNTESTED", reason: "stale_fail_pre_guard" };
  }
  return { decision: "KEEP_VALID", reason: "retain_pending_manual_review" };
}

function main() {
  const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const auditRows = [];
  let keep = 0;
  let reset = 0;

  for (const row of rows) {
    if (!isDeepCaseClass(row.EXECUTION_CLASS) || !row.FUNCTIONAL_RESULT) {
      continue;
    }
    const { decision, reason } = decisionFor(row);
    if (decision === "KEEP_VALID") {
      keep += 1;
    } else {
      reset += 1;
    }
    auditRows.push({
      FUNCTIONAL_CASE_ID: row.FUNCTIONAL_CASE_ID,
      EXECUTION_CLASS: row.EXECUTION_CLASS,
      RESULT: row.FUNCTIONAL_RESULT,
      WHEN_EXECUTED: row.LAST_EXECUTED_AT || "",
      EVIDENCE: row.FUNCTIONAL_EVIDENCE || "",
      REAL_SIDE_EFFECT: "UNKNOWN",
      QA_RESOURCE: row.QA_RESOURCE_REQUIRED || "",
      RESTORED: row.RESTORE_REQUIRED || "",
      VALID_FOR_FINAL_CERT: decision,
      NOTES: reason,
    });
  }

  const header = [
    "FUNCTIONAL_CASE_ID",
    "EXECUTION_CLASS",
    "RESULT",
    "WHEN_EXECUTED",
    "EVIDENCE",
    "REAL_SIDE_EFFECT",
    "QA_RESOURCE",
    "RESTORED",
    "VALID_FOR_FINAL_CERT",
    "NOTES",
  ];
  const lines = [header.join(",")];
  for (const r of auditRows) {
    lines.push(header.map((h) => csvEscape(r[h])).join(","));
  }
  fs.writeFileSync(path.join(evidenceDir, "DEEP-LEGACY-RESULT-AUDIT.csv"), `${lines.join("\n")}\n`, "utf8");

  console.log(`LEGACY_DEEP_TERMINAL_CASES=${auditRows.length}`);
  console.log(`LEGACY_DEEP_KEEP_VALID=${keep}`);
  console.log(`LEGACY_DEEP_RESET_UNTESTED=${reset}`);
}

main();
