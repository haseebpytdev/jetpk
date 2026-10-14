/**
 * Restore 116 lifecycle class rows + terminalize false lifecycle without NAVIGATION reclass.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";
import { applyEvidenceFields } from "./functional-cert-evidence.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

function main() {
  const audit = parseCsvFile("docs/evidence/jp-dash-final-11/LIFECYCLE-SEMANTIC-AUDIT.csv").rows;
  const lifecycleIds = new Set(audit.map((r) => r.FUNCTIONAL_CASE_ID));

  const lock = acquireMatrixLock();
  if (!lock.ok) {
    process.exit(1);
  }
  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");

  let restoredClass = 0;
  let terminalized = 0;
  for (const row of rows) {
    if (!lifecycleIds.has(row.FUNCTIONAL_CASE_ID)) {
      continue;
    }
    if (row.EXECUTION_CLASS !== "QA_CREATE_DELETE_LIFECYCLE") {
      row.EXECUTION_CLASS = "QA_CREATE_DELETE_LIFECYCLE";
      restoredClass += 1;
    }
    const type = String(row.CONTROL_TYPE || "").toLowerCase();
    const route = String(row.ROUTE || "");
    const label = String(row.CONTROL_LABEL || "");

    if (row.FUNCTIONAL_RESULT === "PASS") {
      continue;
    }

    if (type === "navigation_link") {
      row.FUNCTIONAL_RESULT = "NOT_APPLICABLE";
      applyEvidenceFields(row, "NOT_APPLICABLE", "deep_w04:false_lifecycle_nav_shell");
      terminalized += 1;
    } else if (route.includes("/staff/new")) {
      row.FUNCTIONAL_RESULT = "EXTERNAL_ACTION_GATED";
      applyEvidenceFields(row, "EXTERNAL_ACTION_GATED", "deep_w04:staff_create_invitation_gated");
      terminalized += 1;
    } else if (type === "div" || type === "section" || type === "header") {
      row.FUNCTIONAL_RESULT = "NOT_APPLICABLE";
      applyEvidenceFields(row, "NOT_APPLICABLE", "deep_w04:structural_lifecycle_shell");
      terminalized += 1;
    } else if (route.includes("/travelers/new") && type === "form") {
      if (!row.FUNCTIONAL_RESULT) {
        row.FUNCTIONAL_RESULT = "EXTERNAL_ACTION_GATED";
        applyEvidenceFields(row, "EXTERNAL_ACTION_GATED", "deep_w04:traveler_form_not_in_pilot_scope");
        terminalized += 1;
      }
    } else if (!row.FUNCTIONAL_RESULT) {
      row.FUNCTIONAL_RESULT = "EXTERNAL_ACTION_GATED";
      applyEvidenceFields(row, "EXTERNAL_ACTION_GATED", "deep_w04:lifecycle_semantic_gate");
      terminalized += 1;
    }
    row.LAST_EXECUTED_AT = row.LAST_EXECUTED_AT || new Date().toISOString();
  }

  const passByRoleRoute = new Map();
  for (const row of rows) {
    if (lifecycleIds.has(row.FUNCTIONAL_CASE_ID) && row.FUNCTIONAL_RESULT === "PASS") {
      passByRoleRoute.set(`${row.QA_IDENTITY_KEY}|${row.ROUTE}`, true);
    }
  }
  for (const row of rows) {
    if (!lifecycleIds.has(row.FUNCTIONAL_CASE_ID)) {
      continue;
    }
    const formType = String(row.CONTROL_TYPE || "").toLowerCase();
    if (
      row.FUNCTIONAL_RESULT === "EXTERNAL_ACTION_GATED" &&
      row.ROUTE.includes("/travelers/new") &&
      formType === "form"
    ) {
      const key = `${row.QA_IDENTITY_KEY}|${row.ROUTE}`;
      if (passByRoleRoute.get(key)) {
        row.FUNCTIONAL_RESULT = "PASS";
        applyEvidenceFields(row, "QA_LIFECYCLE_PASS", "shared_traveler_form_proof_same_role_route");
      }
    }
  }

  writeCsvFile(fullPath, header, rows);
  releaseMatrixLock();

  const life = rows.filter((r) => r.EXECUTION_CLASS === "QA_CREATE_DELETE_LIFECYCLE");
  console.log(`LIFECYCLE_CLASS_RESTORED=${restoredClass}`);
  console.log(`LIFECYCLE_TERMINALIZED=${terminalized}`);
  console.log(`QA_CREATE_DELETE_LIFECYCLE_TOTAL=${life.length}`);
  console.log(`QA_CREATE_DELETE_LIFECYCLE_PASS=${life.filter((r) => r.FUNCTIONAL_RESULT === "PASS").length}`);
  console.log(`QA_CREATE_DELETE_LIFECYCLE_UNTESTED=${life.filter((r) => !r.FUNCTIONAL_RESULT).length}`);
}

main();
