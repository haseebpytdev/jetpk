/**
 * Phase 2: audit existing deep terminal rows (strict evidence).
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { isDeepCaseClass } from "./functional-case-classify.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";
import { writeCsvFile } from "./functional-cert-csv.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

const DEEP_EVIDENCE_OK =
  /^(DOWNLOAD_|EXPORT_|MUTATION_|QA_|deep_|NATIVE_VALIDATION)/i;

function validDeepTerminal(row) {
  const result = row.FUNCTIONAL_RESULT || "";
  const ev = String(row.FUNCTIONAL_EVIDENCE || "");
  const type = String(row.FUNCTIONAL_EVIDENCE_TYPE || "");
  if (!result || result === "FAIL") {
    return { decision: "RESET_UNTESTED", reason: "no_or_fail_result" };
  }
  if (result === "NOT_APPLICABLE" && ev.includes("misclassified")) {
    return { decision: "KEEP_VALID", reason: "misclassified_deep_terminal" };
  }
  if (type && DEEP_EVIDENCE_OK.test(type)) {
    return { decision: "KEEP_VALID", reason: "structured_deep_evidence" };
  }
  if (DEEP_EVIDENCE_OK.test(ev)) {
    return { decision: "KEEP_VALID", reason: "documented_deep_evidence" };
  }
  if (result === "PASS" && /native_invalid_email|form_closure|semantic/i.test(ev)) {
    return { decision: "RESET_UNTESTED", reason: "wrong_wave_evidence" };
  }
  if (result === "PASS" && !ev) {
    return { decision: "RESET_UNTESTED", reason: "pass_without_deep_evidence" };
  }
  return { decision: "RESET_UNTESTED", reason: "legacy_unverified_terminal" };
}

function main() {
  const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const terminal = rows.filter((r) => isDeepCaseClass(r.EXECUTION_CLASS) && r.FUNCTIONAL_RESULT);
  const header = [
    "FUNCTIONAL_CASE_ID",
    "ROLE",
    "ROUTE",
    "EXECUTION_CLASS",
    "RESULT",
    "EVIDENCE_TYPE",
    "QA_RESOURCE",
    "PERSISTENCE_PROVEN",
    "AUDIT_PROVEN",
    "RESTORE_REQUIRED",
    "RESTORE_PROVEN",
    "EXTERNAL_EFFECT",
    "VALID_FOR_FINAL_CERT",
    "NOTES",
  ];
  const out = [header.join(",")];
  let keep = 0;
  let reset = 0;
  const resetIds = new Set();

  for (const row of terminal) {
    const { decision, reason } = validDeepTerminal(row);
    if (decision === "KEEP_VALID") {
      keep += 1;
    } else {
      reset += 1;
      resetIds.add(row.FUNCTIONAL_CASE_ID);
    }
    out.push(
      header
        .map((h) => {
          if (h === "ROLE") return csvEscape(row.QA_IDENTITY_KEY || "");
          if (h === "EVIDENCE_TYPE") return csvEscape(row.FUNCTIONAL_EVIDENCE_TYPE || "");
          if (h === "QA_RESOURCE") return csvEscape(row.QA_RESOURCE_REQUIRED || "NO");
          if (h === "PERSISTENCE_PROVEN") return csvEscape("NO");
          if (h === "AUDIT_PROVEN") return csvEscape("NO");
          if (h === "RESTORE_REQUIRED") return csvEscape(row.RESTORE_REQUIRED || "NO");
          if (h === "RESTORE_PROVEN") return csvEscape("NO");
          if (h === "EXTERNAL_EFFECT") return csvEscape(row.EXTERNAL_EFFECT || "NONE");
          if (h === "VALID_FOR_FINAL_CERT") return csvEscape(decision);
          if (h === "NOTES") return csvEscape(reason);
          return csvEscape(row[h] || "");
        })
        .join(","),
    );
  }

  fs.writeFileSync(path.join(evidenceDir, "DEEP-EXISTING-TERMINAL-AUDIT.csv"), `${out.join("\n")}\n`, "utf8");

  const lock = acquireMatrixLock();
  if (lock.ok) {
    const { header: mh, rows: mrows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
    let applied = 0;
    for (const row of mrows) {
      if (!resetIds.has(row.FUNCTIONAL_CASE_ID)) {
        continue;
      }
      row.FUNCTIONAL_RESULT = "";
      row.FUNCTIONAL_EVIDENCE = "";
      row.FUNCTIONAL_EVIDENCE_TYPE = "";
      row.FUNCTIONAL_EVIDENCE_VALUE_SAFE = "";
      row.LAST_EXECUTED_AT = "";
      applied += 1;
    }
    writeCsvFile(fullPath, mh, mrows);
    releaseMatrixLock();
    console.log(`EXISTING_DEEP_TERMINAL_RESET_APPLIED=${applied}`);
  }

  const invalidRemain = rows.filter(
    (r) => isDeepCaseClass(r.EXECUTION_CLASS) && r.FUNCTIONAL_RESULT && resetIds.has(r.FUNCTIONAL_CASE_ID),
  ).length;

  console.log(`EXISTING_DEEP_TERMINAL_AUDITED=${terminal.length}`);
  console.log(`EXISTING_DEEP_TERMINAL_KEEP=${keep}`);
  console.log(`EXISTING_DEEP_TERMINAL_RESET=${reset}`);
  console.log(`INVALID_DEEP_TERMINAL_REMAINING=${invalidRemain}`);
}

main();
