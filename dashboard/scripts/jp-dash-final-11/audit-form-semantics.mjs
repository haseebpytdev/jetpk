/**
 * Phase 2: audit all FORM_VALIDATION semantics + optional apply.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { classifyFormSemantic } from "./form-semantic-classify.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";
import { isStrongFormEvidence } from "./form-validation-probes.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");
const APPLY = process.env.JP_FORM_SEMANTIC_APPLY === "1";

const auditHeader = [
  "FUNCTIONAL_CASE_ID",
  "ROLE",
  "ROUTE",
  "CONTROL_TYPE",
  "CONTROL_LABEL",
  "CURRENT_CLASS",
  "SEMANTIC_TYPE",
  "PROBEABLE",
  "PROBE_TARGET",
  "RECOMMENDED_CLASS",
  "REASON",
  "PROBE_TARGET_KIND",
  "PROBE_TARGET_SELECTOR_SAFE",
  "INVALID_VALUE_KIND",
  "EXPECTED_SIGNAL",
];

function main() {
  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const formRows = rows.filter((r) => r.EXECUTION_CLASS === "FORM_VALIDATION");
  const lines = [auditHeader.join(",")];
  let unknown = 0;
  let structural = 0;
  let reclassified = 0;
  const structuralTypes = new Set([
    "COMPOSITE_FORM_CONTAINER",
    "SEARCH_OR_FILTER_SHELL",
    "STRUCTURAL_FORM_ELEMENT",
  ]);

  for (const row of formRows) {
    const sem = classifyFormSemantic(row);
    if (sem.SEMANTIC_TYPE === "UNKNOWN") {
      unknown += 1;
    }
    if (structuralTypes.has(sem.SEMANTIC_TYPE)) {
      structural += 1;
    }
    lines.push(
      auditHeader
        .map((h) => {
          if (h === "ROLE") {
            return csvEscape(row.QA_IDENTITY_KEY || "");
          }
          if (h === "CURRENT_CLASS") {
            return csvEscape(row.EXECUTION_CLASS || "");
          }
          if (h === "CONTROL_LABEL") {
            return csvEscape(String(row.CONTROL_LABEL || "").slice(0, 200));
          }
          return csvEscape(sem[h] ?? row[h] ?? "");
        })
        .join(","),
    );
  }

  fs.writeFileSync(path.join(evidenceDir, "FORM-SEMANTIC-AUDIT.csv"), `${lines.join("\n")}\n`, "utf8");

  const probeTargets = formRows.filter((r) => {
    const s = classifyFormSemantic(r);
    return s.PROBEABLE === "YES" && s.RECOMMENDED_CLASS === "FORM_VALIDATION";
  });

  console.log(`FORM_CASES_AUDITED=${formRows.length}`);
  console.log(`FORM_SEMANTIC_UNKNOWN=${unknown}`);
  console.log(`STRUCTURAL_FORM_CASES=${structural}`);
  console.log(`FORM_CASES_WITH_PROBE_TARGET=${probeTargets.length}`);

  if (!APPLY) {
    console.log(`FORM_SEMANTIC_APPLY=SKIPPED set JP_FORM_SEMANTIC_APPLY=1`);
    return;
  }

  const lock = acquireMatrixLock();
  if (!lock.ok) {
    process.exit(1);
  }
  try {
    for (const row of formRows) {
      const sem = classifyFormSemantic(row);
      if (sem.RECOMMENDED_CLASS === row.EXECUTION_CLASS) {
        continue;
      }
      const prev = row.EXECUTION_CLASS;
      row.EXECUTION_CLASS = sem.RECOMMENDED_CLASS;
      if (sem.RECOMMENDED_CLASS === "NOT_APPLICABLE") {
        row.FUNCTIONAL_RESULT = "NOT_APPLICABLE";
        row.FUNCTIONAL_EVIDENCE = `semantic_audit:${sem.REASON}`;
        row.LAST_EXECUTED_AT = new Date().toISOString();
      } else {
        row.FUNCTIONAL_RESULT = "";
        row.FUNCTIONAL_EVIDENCE = `semantic_reclass:${prev}->${sem.RECOMMENDED_CLASS}`;
        row.LAST_EXECUTED_AT = "";
      }
      reclassified += 1;
    }
    for (const row of rows.filter((r) => r.EXECUTION_CLASS === "FORM_VALIDATION" && r.FUNCTIONAL_RESULT === "PASS")) {
      if (!isStrongFormEvidence(row)) {
        row.FUNCTIONAL_RESULT = "";
        row.FUNCTIONAL_EVIDENCE = "";
        row.LAST_EXECUTED_AT = "";
      }
    }
    writeCsvFile(fullPath, header, rows);
    console.log(`STRUCTURAL_FORM_RECLASSIFIED=${reclassified}`);
  } finally {
    releaseMatrixLock();
  }
}

main();
