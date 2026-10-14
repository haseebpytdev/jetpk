/**
 * Phase 1: reclassify 7 misclassified DOWNLOAD_EXPORT rows.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

const RECLASS = {
  "FC-000705": {
    correct: "READ_OPERATION",
    reason: "staff_permission_checkbox_inventory_not_file_export",
    semantics: "permission_grant_checkbox",
  },
  "FC-000714": {
    correct: "READ_OPERATION",
    reason: "staff_permission_checkbox_inventory_not_file_export",
    semantics: "permission_grant_checkbox",
  },
  "FC-001939": {
    correct: "READ_OPERATION",
    reason: "permission_catalog_key_not_export_action",
    semantics: "permissions_matrix_label",
  },
  "FC-004849": {
    correct: "EXTERNAL_ACTION_GATED",
    reason: "deposit_submission_form_not_download",
    semantics: "deposit_request_form_shell",
  },
  "FC-006257": {
    correct: "EXTERNAL_ACTION_GATED",
    reason: "deposit_submission_form_not_download",
    semantics: "deposit_request_form_shell",
  },
  "FC-007630": {
    correct: "EXTERNAL_ACTION_GATED",
    reason: "deposit_submission_form_not_download",
    semantics: "deposit_request_form_shell",
  },
  "FC-008962": {
    correct: "EXTERNAL_ACTION_GATED",
    reason: "deposit_submission_form_not_download",
    semantics: "deposit_request_form_shell",
  },
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
    "ROLE",
    "ROUTE",
    "CONTROL",
    "OLD_CLASS",
    "OBSERVED_SEMANTICS",
    "CORRECT_CLASS",
    "REASON",
    "CONTROL_IDS",
  ];
  const outLines = [outHeader.join(",")];
  let applied = 0;

  for (const row of rows) {
    const rule = RECLASS[row.FUNCTIONAL_CASE_ID];
    if (!rule) {
      continue;
    }
    outLines.push(
      outHeader
        .map((h) => {
          if (h === "ROLE") return csvEscape(row.QA_IDENTITY_KEY);
          if (h === "CONTROL") return csvEscape(`${row.CONTROL_TYPE}:${row.CONTROL_LABEL}`);
          if (h === "OLD_CLASS") return csvEscape("DOWNLOAD_EXPORT");
          if (h === "OBSERVED_SEMANTICS") return csvEscape(rule.semantics);
          if (h === "CORRECT_CLASS") return csvEscape(rule.correct);
          if (h === "REASON") return csvEscape(rule.reason);
          if (h === "CONTROL_IDS") return csvEscape((controlsByCase.get(row.FUNCTIONAL_CASE_ID) || []).join("|"));
          return csvEscape(row[h] ?? "");
        })
        .join(","),
    );
    row.EXECUTION_CLASS = rule.correct;
    if (rule.correct === "EXTERNAL_ACTION_GATED") {
      row.FUNCTIONAL_RESULT = "EXTERNAL_ACTION_GATED";
      row.FUNCTIONAL_EVIDENCE = `deep_w02_reclass:${rule.reason}`;
    } else {
      row.FUNCTIONAL_RESULT = "";
      row.FUNCTIONAL_EVIDENCE = "";
      row.FUNCTIONAL_EVIDENCE_TYPE = "";
      row.FUNCTIONAL_EVIDENCE_VALUE_SAFE = "";
    }
    row.LAST_EXECUTED_AT = new Date().toISOString();
    applied += 1;
  }

  writeCsvFile(fullPath, header, rows);
  fs.writeFileSync(path.join(evidenceDir, "DOWNLOAD-SEMANTIC-RECONCILIATION.csv"), `${outLines.join("\n")}\n`, "utf8");
  releaseMatrixLock();
  console.log(`MISCLASSIFIED_DOWNLOAD_CASES=${Object.keys(RECLASS).length}`);
  console.log(`DOWNLOAD_RECLASSIFICATIONS_APPLIED=${applied}`);
}

main();
