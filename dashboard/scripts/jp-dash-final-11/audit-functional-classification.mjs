/**
 * Phase 3–6: classification distribution, semantic audit, optional reclassify.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { classifyExecution, PASSIVE_LIGHT_CLASSES, normalizeSemantic } from "./functional-case-classify.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

const SEMANTIC_TERMS =
  /\b(create|add|new|save|update|edit|delete|remove|archive|activate|deactivate|approve|reject|assign|publish|upload|download|export|send|reply|verify|reset|retry|sync|issue|cancel|refund|permission|role|password|security)\b/i;

const APPLY_RECLASSIFY = process.env.JP_APPLY_FUNCTIONAL_RECLASSIFY === "1";

function main() {
  const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const distribution = {};
  for (const row of rows) {
    const cls = row.EXECUTION_CLASS || "UNKNOWN";
    distribution[cls] = (distribution[cls] || 0) + 1;
  }

  let semanticAudited = 0;
  let misclassified = 0;
  const reconciliation = [];
  let classificationResets = 0;

  for (const row of rows) {
    const label = `${row.CONTROL_LABEL || ""} ${row.ROUTE || ""}`;
    if (!SEMANTIC_TERMS.test(label)) {
      continue;
    }
    semanticAudited += 1;
    const expectedClass = classifyExecution(row);
    if (expectedClass !== row.EXECUTION_CLASS) {
      misclassified += 1;
      reconciliation.push({
        OLD_FUNCTIONAL_CASE_ID: row.FUNCTIONAL_CASE_ID,
        NEW_FUNCTIONAL_CASE_ID: row.FUNCTIONAL_CASE_ID,
        CONTROL_ID: "",
        OLD_CLASS: row.EXECUTION_CLASS,
        NEW_CLASS: expectedClass,
        REASON: "semantic_classifier_v2",
      });
      if (APPLY_RECLASSIFY) {
        const hadResult = Boolean(row.FUNCTIONAL_RESULT);
        row.EXECUTION_CLASS = expectedClass;
        if (hadResult) {
          row.FUNCTIONAL_RESULT = "";
          row.LAST_EXECUTED_AT = "";
          classificationResets += 1;
        }
      }
    }
  }

  const unknownClasses = Object.keys(distribution).filter((k) => k === "UNKNOWN").reduce((a, k) => a + distribution[k], 0);
  const classTotal = rows.length;

  let passiveLight = 0;
  for (const row of rows) {
    if (PASSIVE_LIGHT_CLASSES.has(row.EXECUTION_CLASS)) {
      passiveLight += 1;
    }
  }

  for (const [k, v] of Object.entries(distribution).sort((a, b) => a[0].localeCompare(b[0]))) {
    console.log(`${k}=${v}`);
  }
  console.log(`CLASSIFICATION_TOTAL=${classTotal}`);
  console.log(`UNKNOWN_EXECUTION_CLASSES=${unknownClasses}`);
  console.log(`SEMANTIC_CASES_AUDITED=${semanticAudited}`);
  console.log(`MISCLASSIFIED_CASES_FOUND=${misclassified}`);
  console.log(`PASSIVE_LIGHT_CASES_TOTAL=${passiveLight}`);
  console.log(`CLASSIFICATION_RESETS=${classificationResets}`);

  const reconHeader = [
    "OLD_FUNCTIONAL_CASE_ID",
    "NEW_FUNCTIONAL_CASE_ID",
    "CONTROL_ID",
    "OLD_CLASS",
    "NEW_CLASS",
    "REASON",
  ];
  const reconLines = [reconHeader.join(",")];
  for (const r of reconciliation) {
    reconLines.push(reconHeader.map((h) => csvEscape(r[h])).join(","));
  }
  fs.writeFileSync(path.join(evidenceDir, "FUNCTIONAL-CLASSIFICATION-RECONCILIATION.csv"), `${reconLines.join("\n")}\n`, "utf8");

  if (APPLY_RECLASSIFY) {
    const { header, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
    writeCsvFile(fullPath, header, rows);
    console.log("FUNCTIONAL_MATRIX_RECLASSIFY_APPLIED=YES");
  }
}

main();
