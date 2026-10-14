/**
 * Phase 10: audit existing FORM_VALIDATION PASS rows for strong evidence.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";
import { ensureMatrixEvidenceColumns } from "./functional-cert-matrix-header.mjs";
import { probeTypeForRow, isStrongFormEvidence } from "./form-validation-probes.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

function evidenceType(ev) {
  if (!isStrongFormEvidence(ev)) {
    return "";
  }
  if (/visible_validation/i.test(ev)) {
    return "VISIBLE_FIELD_ERROR";
  }
  if (/422/i.test(ev)) {
    return "HTTP_422";
  }
  if (/prevented/i.test(ev)) {
    return "INVALID_SUBMIT_PREVENTED";
  }
  if (/disabled_control_by_design/i.test(ev)) {
    return "RECOGNIZED_VALIDATION_RESPONSE";
  }
  return "NATIVE_VALIDATION_BLOCK";
}

function main() {
  const lock = acquireMatrixLock();
  if (!lock.ok) {
    console.error(`MATRIX_WRITER_LOCK=BLOCKED holder=${lock.holder}`);
    process.exit(1);
  }
  try {
    const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
    ensureMatrixEvidenceColumns(header, rows);
    const passes = rows.filter((r) => r.EXECUTION_CLASS === "FORM_VALIDATION" && r.FUNCTIONAL_RESULT === "PASS");
    const auditHeader = [
      "FUNCTIONAL_CASE_ID",
      "FORM_TYPE",
      "INVALID_PROBE",
      "EVIDENCE_TYPE",
      "SIDE_EFFECT",
      "FUNCTIONAL_EVIDENCE",
      "WEAK",
    ];
    const auditLines = [auditHeader.join(",")];
    let weak = 0;
    for (const r of passes) {
      const ev = String(r.FUNCTIONAL_EVIDENCE || r.VALIDATION_EVIDENCE || "");
      const et = evidenceType(ev);
      const weakRow = !et;
      if (weakRow) {
        weak += 1;
        r.FUNCTIONAL_RESULT = "";
        r.FUNCTIONAL_EVIDENCE = "";
        r.LAST_EXECUTED_AT = "";
      }
      auditLines.push(
        auditHeader
          .map((h) => {
            if (h === "FORM_TYPE") {
              return csvEscape(r.CONTROL_TYPE || "");
            }
            if (h === "INVALID_PROBE") {
              return csvEscape(probeTypeForRow(r));
            }
            if (h === "EVIDENCE_TYPE") {
              return csvEscape(et || "WEAK");
            }
            if (h === "SIDE_EFFECT") {
              return "NO";
            }
            if (h === "WEAK") {
              return weakRow ? "YES" : "NO";
            }
            return csvEscape(r[h] || "");
          })
          .join(","),
      );
    }
    if (weak > 0) {
      writeCsvFile(fullPath, header, rows);
    }
    fs.writeFileSync(path.join(evidenceDir, "FORM-EXISTING-PASS-AUDIT.csv"), `${auditLines.join("\n")}\n`, "utf8");
    console.log(`EXISTING_FORM_PASSES_AUDITED=${passes.length}`);
    console.log(`WEAK_EXISTING_FORM_PASSES=${weak}`);
  } finally {
    releaseMatrixLock();
  }
}

main();
