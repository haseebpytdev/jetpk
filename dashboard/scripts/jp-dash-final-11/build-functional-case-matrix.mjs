/**
 * Normalize ROUTE-ACTION-INVENTORY.csv into unique functional cases + control map.
 * Does not modify the source inventory.
 */
import crypto from "node:crypto";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");
const inventoryPath = path.join(evidenceDir, "ROUTE-ACTION-INVENTORY.csv");
const matrixPath = path.join(evidenceDir, "FUNCTIONAL-CASE-MATRIX.csv");
const mapPath = path.join(evidenceDir, "CONTROL-CASE-MAP.csv");

function parseCsvLine(line) {
  const out = [];
  let cur = "";
  let inQuotes = false;
  for (let i = 0; i < line.length; i++) {
    const ch = line[i];
    if (inQuotes) {
      if (ch === '"' && line[i + 1] === '"') {
        cur += '"';
        i++;
      } else if (ch === '"') {
        inQuotes = false;
      } else {
        cur += ch;
      }
    } else if (ch === '"') {
      inQuotes = true;
    } else if (ch === ",") {
      out.push(cur);
      cur = "";
    } else {
      cur += ch;
    }
  }
  out.push(cur);
  return out;
}

function csvEscape(value) {
  const s = value == null ? "" : String(value);
  if (/[",\n\r]/.test(s)) {
    return `"${s.replace(/"/g, '""')}"`;
  }
  return s;
}

import { classifyExecution, normalizeSemantic } from "./functional-case-classify.mjs";

function functionalCaseKey(row) {
  const parts = [
    row.PORTAL,
    row.QA_IDENTITY_KEY,
    row.ACCOUNT_TYPE,
    row.AGENCY_ROLE,
    row.ROUTE,
    row.PAGE,
    row.SECTION,
    row.CONTROL_TYPE,
    normalizeSemantic(row.CONTROL_LABEL),
    row.EXPECTED_PERMISSION,
    row.EXPECTED_BEHAVIOR,
    row.DATA_SOURCE,
    row.MUTATION_CLASS,
    row.EXTERNAL_EFFECT,
  ];
  return parts.join("|");
}

function deriveTestMethod(row, executionClass) {
  if (row.TEST_METHOD && row.TEST_METHOD !== "VISUAL_ENUMERATION") {
    return row.TEST_METHOD;
  }
  switch (executionClass) {
    case "NEGATIVE_RBAC":
    case "READ_ONLY_BY_DESIGN":
      return "LIVE_NEGATIVE_RBAC";
    case "FORM_VALIDATION":
      return "LIVE_FORM_VALIDATION";
    case "QA_REVERSIBLE_MUTATION":
    case "QA_CREATE_DELETE_LIFECYCLE":
      return "LIVE_QA_MUTATION";
    case "DOWNLOAD_EXPORT":
      return "LIVE_DOWNLOAD";
    case "EXTERNAL_ACTION_GATED":
      return "LIVE_GATING_PROBE";
    default:
      return "LIVE_FUNCTIONAL_PROBE";
  }
}

function qaResourceRequired(executionClass) {
  if (executionClass === "QA_REVERSIBLE_MUTATION" || executionClass === "QA_CREATE_DELETE_LIFECYCLE") {
    return "YES";
  }
  return "NO";
}

function restoreRequired(executionClass) {
  if (executionClass === "QA_REVERSIBLE_MUTATION" || executionClass === "QA_CREATE_DELETE_LIFECYCLE") {
    return "YES";
  }
  return "NO";
}

function main() {
  const raw = fs.readFileSync(inventoryPath, "utf8");
  const lines = raw.split(/\r?\n/).filter((l) => l.length > 0);
  const header = parseCsvLine(lines[0]);
  const rows = lines.slice(1).map((line) => {
    const cols = parseCsvLine(line);
    /** @type {Record<string, string>} */
    const row = {};
    header.forEach((h, i) => {
      row[h] = cols[i] ?? "";
    });
    return row;
  });

  const totalEnumerated = rows.length;
  const keyToCaseId = new Map();
  const cases = new Map();
  const mapRows = [];
  let seq = 0;

  for (const row of rows) {
    const key = functionalCaseKey(row);
    let caseId = keyToCaseId.get(key);
    if (!caseId) {
      seq += 1;
      caseId = `FC-${String(seq).padStart(6, "0")}`;
      keyToCaseId.set(key, caseId);
      const executionClass = classifyExecution(row);
      cases.set(caseId, {
        FUNCTIONAL_CASE_ID: caseId,
        PORTAL: row.PORTAL,
        QA_IDENTITY_KEY: row.QA_IDENTITY_KEY,
        ACCOUNT_TYPE: row.ACCOUNT_TYPE,
        AGENCY_ROLE: row.AGENCY_ROLE,
        ROUTE: row.ROUTE,
        PAGE: row.PAGE,
        SECTION: row.SECTION,
        CONTROL_TYPE: row.CONTROL_TYPE,
        CONTROL_LABEL: row.CONTROL_LABEL,
        EXPECTED_PERMISSION: row.EXPECTED_PERMISSION,
        EXPECTED_BEHAVIOR: row.EXPECTED_BEHAVIOR,
        DATA_SOURCE: row.DATA_SOURCE,
        MUTATION_CLASS: row.MUTATION_CLASS,
        EXTERNAL_EFFECT: row.EXTERNAL_EFFECT,
        EXECUTION_CLASS: executionClass,
        TEST_METHOD: deriveTestMethod(row, executionClass),
        QA_RESOURCE_REQUIRED: qaResourceRequired(executionClass),
        RESTORE_REQUIRED: restoreRequired(executionClass),
        EXPECTED_HTTP: row.HTTP_RESULT || "",
        EXPECTED_PERSISTENCE: row.PERSISTENCE_RESULT || "NOT_APPLICABLE",
        EXPECTED_AUDIT: row.AUDIT_RESULT || "NOT_APPLICABLE",
        EXPECTED_EXTERNAL_EFFECT: row.EXTERNAL_EFFECT || "NONE",
        FUNCTIONAL_RESULT: "",
        LAST_EXECUTED_AT: "",
        CONTROL_INSTANCE_COUNT: 0,
      });
    }
    const caseRow = cases.get(caseId);
    caseRow.CONTROL_INSTANCE_COUNT += 1;
    mapRows.push({
      CONTROL_ID: row.CONTROL_ID,
      FUNCTIONAL_CASE_ID: caseId,
      VIEWPORT: row.VIEWPORT,
      INVENTORY_RESULT: row.RESULT,
    });
  }

  const caseList = [...cases.values()].sort((a, b) => a.FUNCTIONAL_CASE_ID.localeCompare(b.FUNCTIONAL_CASE_ID));
  const unclassified = caseList.filter((c) => !c.EXECUTION_CLASS).length;

  const matrixHeader = [
    "FUNCTIONAL_CASE_ID",
    "PORTAL",
    "QA_IDENTITY_KEY",
    "ACCOUNT_TYPE",
    "AGENCY_ROLE",
    "ROUTE",
    "PAGE",
    "SECTION",
    "CONTROL_TYPE",
    "CONTROL_LABEL",
    "EXPECTED_PERMISSION",
    "EXPECTED_BEHAVIOR",
    "DATA_SOURCE",
    "MUTATION_CLASS",
    "EXTERNAL_EFFECT",
    "EXECUTION_CLASS",
    "TEST_METHOD",
    "QA_RESOURCE_REQUIRED",
    "RESTORE_REQUIRED",
    "EXPECTED_HTTP",
    "EXPECTED_PERSISTENCE",
    "EXPECTED_AUDIT",
    "EXPECTED_EXTERNAL_EFFECT",
    "FUNCTIONAL_RESULT",
    "LAST_EXECUTED_AT",
    "CONTROL_INSTANCE_COUNT",
  ];

  const matrixLines = [matrixHeader.join(",")];
  for (const c of caseList) {
    matrixLines.push(matrixHeader.map((h) => csvEscape(c[h])).join(","));
  }
  fs.writeFileSync(matrixPath, `${matrixLines.join("\n")}\n`, "utf8");

  const mapHeader = ["CONTROL_ID", "FUNCTIONAL_CASE_ID", "VIEWPORT", "INVENTORY_RESULT"];
  const mapLines = [mapHeader.join(",")];
  for (const m of mapRows) {
    mapLines.push(mapHeader.map((h) => csvEscape(m[h])).join(","));
  }
  fs.writeFileSync(mapPath, `${mapLines.join("\n")}\n`, "utf8");

  const multiMapped = mapRows.length - new Set(mapRows.map((m) => m.CONTROL_ID)).size;

  console.log(`TOTAL_ENUMERATED_CONTROL_ROWS=${totalEnumerated}`);
  console.log(`TOTAL_MAPPED_CONTROL_ROWS=${mapRows.length}`);
  console.log(`UNMAPPED_CONTROL_ROWS=0`);
  console.log(`MULTI_MAPPED_CONTROL_ROWS=${multiMapped}`);
  console.log(`UNIQUE_FUNCTIONAL_CASES=${caseList.length}`);
  console.log(`UNCLASSIFIED_FUNCTIONAL_CASES=${unclassified}`);

  const byClass = {};
  for (const c of caseList) {
    byClass[c.EXECUTION_CLASS] = (byClass[c.EXECUTION_CLASS] || 0) + 1;
  }
  console.log(`EXECUTION_CLASS_BREAKDOWN=${JSON.stringify(byClass)}`);
}

main();
