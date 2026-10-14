/**
 * Apply / confirm the five Pilot V2 WRONG_CLASSIFICATION reclassifications.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

const WRONG_CLASS_IDS = ["FC-003466", "FC-005531", "FC-014474", "FC-001118", "FC-006938"];

const DEST_RULES = {
  "FC-003466": { newClass: "NOT_APPLICABLE", reason: "textarea_no_native_validation_constraint" },
  "FC-001118": { newClass: "NOT_APPLICABLE", reason: "textarea_no_native_validation_constraint" },
  "FC-005531": { newClass: "NOT_APPLICABLE", reason: "password_field_no_validation_constraint" },
  "FC-006938": { newClass: "NOT_APPLICABLE", reason: "password_field_no_validation_constraint" },
  "FC-014474": { newClass: "NOT_APPLICABLE", reason: "password_field_no_validation_constraint" },
};

function main() {
  const lock = acquireMatrixLock();
  if (!lock.ok) {
    process.exit(1);
  }
  const mapRows = parseCsvFile("docs/evidence/jp-dash-final-11/CONTROL-CASE-MAP.csv").rows;
  const controlsByCase = new Map();
  for (const m of mapRows) {
    if (!controlsByCase.has(m.FUNCTIONAL_CASE_ID)) {
      controlsByCase.set(m.FUNCTIONAL_CASE_ID, []);
    }
    controlsByCase.get(m.FUNCTIONAL_CASE_ID).push(m.CONTROL_ID);
  }

  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const outHeader = [
    "FUNCTIONAL_CASE_ID",
    "OLD_CLASS",
    "NEW_CLASS",
    "ROOT_REASON",
    "CONTROL_IDS",
    "DESTINATION_CLASS",
  ];
  const outLines = [outHeader.join(",")];
  let applied = 0;

  for (const id of WRONG_CLASS_IDS) {
    const row = rows.find((r) => r.FUNCTIONAL_CASE_ID === id);
    if (!row) {
      continue;
    }
    const rule = DEST_RULES[id];
    const oldClass = row.EXECUTION_CLASS === "FORM_VALIDATION" ? "FORM_VALIDATION" : "FORM_VALIDATION";
    const prevClass = row.EXECUTION_CLASS;
    row.EXECUTION_CLASS = rule.newClass;
    row.FUNCTIONAL_RESULT = "NOT_APPLICABLE";
    row.FUNCTIONAL_EVIDENCE = `pilot_v2_reclass:${rule.reason}`;
    row.LAST_EXECUTED_AT = new Date().toISOString();
    row.LAST_ATTEMPT_RESULT = "";
    row.TOOLING_BLOCK_REASON = "";
    row.RETRY_REQUIRED = "";
    applied += 1;
    outLines.push(
      outHeader
        .map((h) => {
          if (h === "OLD_CLASS") {
            return csvEscape(prevClass);
          }
          if (h === "NEW_CLASS" || h === "DESTINATION_CLASS") {
            return csvEscape(rule.newClass);
          }
          if (h === "ROOT_REASON") {
            return csvEscape(rule.reason);
          }
          if (h === "CONTROL_IDS") {
            return csvEscape((controlsByCase.get(id) || []).join("|"));
          }
          return csvEscape(id);
        })
        .join(","),
    );
  }

  writeCsvFile(fullPath, header, rows);
  fs.writeFileSync(path.join(evidenceDir, "PILOT-V2-RECLASSIFICATIONS-APPLIED.csv"), `${outLines.join("\n")}\n`, "utf8");
  releaseMatrixLock();
  console.log(`PILOT_V2_RECLASSIFICATIONS_APPLIED=${applied}`);
}

main();
