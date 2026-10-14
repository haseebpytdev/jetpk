/**
 * Phase 9–10: SERVER_QA_DB reversible inventory + QA resource check.
 */
import { parseCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { classifyReversibleSemanticV3 } from "./deep-semantics-lib.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

const NEXT_PROFILE_DONE = new Set(["FC-002428", "FC-004161"]);
const BLADE_IDS = new Set([
  "FC-005424",
  "FC-006831",
  "FC-008186",
  "FC-009477",
  "FC-010648",
  "FC-011738",
  "FC-012824",
  "FC-013911",
  "FC-014389",
  "FC-014952",
]);

function classifyExecution(row) {
  const id = row.FUNCTIONAL_CASE_ID;
  const label = String(row.CONTROL_LABEL || "");
  if (NEXT_PROFILE_DONE.has(id)) {
    return { bucket: "SAFE_AUTOMATED", qaResource: "YES", note: "next_dashboard_profile_done" };
  }
  if (BLADE_IDS.has(id)) {
    return { bucket: "SAFE_AFTER_HARNESS", qaResource: "YES", note: "blade_profile_whatsapp" };
  }
  if (/staff-perm-/i.test(label)) {
    return { bucket: "HIGH_RISK_INTERNAL", qaResource: "NO", note: "staff_permission_toggle_requires_dedicated_qa_staff_edit" };
  }
  if (/cms/i.test(row.ROUTE || "")) {
    return { bucket: "NO_QA_RESOURCE", qaResource: "NO", note: "cms_qa_draft_required" };
  }
  return { bucket: "WRONG_CLASS", qaResource: "REVIEW", note: "unexpected_server_qa_row" };
}

function main() {
  const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const serverRows = rows.filter(
    (r) =>
      r.EXECUTION_CLASS === "QA_REVERSIBLE_MUTATION" &&
      classifyReversibleSemanticV3(r).persistence === "SERVER_QA_DB",
  );
  const terminalBefore = serverRows.filter((r) => r.FUNCTIONAL_RESULT === "PASS").length;
  const header = [
    "FUNCTIONAL_CASE_ID",
    "ROLE",
    "ROUTE",
    "CONTROL",
    "TERMINAL_BEFORE",
    "EXECUTION_BUCKET",
    "QA_RESOURCE",
    "NOTE",
  ];
  const inv = [];
  const bucketCounts = {};
  let withQa = 0;
  let withoutQa = 0;
  for (const row of serverRows) {
    const c = classifyExecution(row);
    bucketCounts[c.bucket] = (bucketCounts[c.bucket] || 0) + 1;
    if (c.qaResource === "YES") {
      withQa += 1;
    } else if (c.qaResource === "NO") {
      withoutQa += 1;
    }
    inv.push({
      FUNCTIONAL_CASE_ID: row.FUNCTIONAL_CASE_ID,
      ROLE: row.QA_IDENTITY_KEY,
      ROUTE: row.ROUTE,
      CONTROL: `${row.CONTROL_TYPE}:${row.CONTROL_LABEL}`,
      TERMINAL_BEFORE: row.FUNCTIONAL_RESULT || "",
      EXECUTION_BUCKET: c.bucket,
      QA_RESOURCE: c.qaResource,
      NOTE: c.note,
    });
  }

  const lines = [header.join(",")];
  for (const r of inv) {
    lines.push(header.map((h) => csvEscape(r[h])).join(","));
  }
  fs.writeFileSync(path.join(evidenceDir, "SERVER-QA-DB-INVENTORY.csv"), `${lines.join("\n")}\n`, "utf8");

  const unclassified = inv.filter((r) => !r.EXECUTION_BUCKET).length;
  console.log(`SERVER_QA_DB_TOTAL=${serverRows.length}`);
  console.log(`SERVER_QA_DB_TERMINAL_BEFORE=${terminalBefore}`);
  console.log(`SERVER_QA_DB_UNTESTED=${serverRows.filter((r) => !r.FUNCTIONAL_RESULT).length}`);
  console.log(`SERVER_QA_DB_UNCLASSIFIED=${unclassified}`);
  console.log(`SERVER_DB_CASES_WITH_QA_RESOURCE=${withQa}`);
  console.log(`SERVER_DB_CASES_WITHOUT_QA_RESOURCE=${withoutQa}`);
  for (const [k, v] of Object.entries(bucketCounts)) {
    console.log(`SERVER_QA_DB_BUCKET_${k}=${v}`);
  }
}

main();
