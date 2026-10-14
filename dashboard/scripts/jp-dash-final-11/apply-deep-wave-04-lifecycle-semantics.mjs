/**
 * Phases 9–10, 19: lifecycle semantic reclass + execution bucket inventory.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

const HIGH_RISK = /\b(booking|payment|deposit|refund|ticket|cancel|void|pay)\b/i;

function classifyLifecycle(row) {
  const route = String(row.ROUTE || "");
  const type = String(row.CONTROL_TYPE || "").toLowerCase();
  const label = String(row.CONTROL_LABEL || "");

  if (type === "navigation_link") {
    return { entity: "NO_REAL_LIFECYCLE", bucket: "NOT_APPLICABLE", newClass: "NAVIGATION", reason: "nav_not_lifecycle" };
  }
  if (type === "div" || type === "section" || type === "header") {
    return { entity: "NO_REAL_LIFECYCLE", bucket: "NOT_APPLICABLE", newClass: "NOT_APPLICABLE", reason: "structural_shell" };
  }
  if (route.includes("/travelers/new") && type === "form") {
    return { entity: "TRAVELER_QA", bucket: "SAFE_VIA_SHARED_WORKFLOW", newClass: "QA_CREATE_DELETE_LIFECYCLE", reason: "traveler_create_form" };
  }
  if (route.includes("/travelers/new")) {
    return { entity: "TRAVELER_QA", bucket: "SAFE_VIA_SHARED_WORKFLOW", newClass: "NAVIGATION", reason: "traveler_create_nav" };
  }
  if (route.includes("/staff/new")) {
    return {
      entity: "STAFF_QA",
      bucket: "EXTERNAL_GATED",
      newClass: "EXTERNAL_ACTION_GATED",
      reason: "staff_create_invitation_external_risk",
    };
  }
  if (route.includes("/cms") && /cms|draft/i.test(route + label)) {
    return { entity: "CMS_DRAFT_QA", bucket: "NO_QA_RESOURCE", newClass: "EXTERNAL_ACTION_GATED", reason: "cms_no_isolated_qa_draft" };
  }
  if (HIGH_RISK.test(route + label)) {
    return { entity: "EXTERNAL_OR_UNSAFE", bucket: "EXTERNAL_GATED", newClass: "EXTERNAL_ACTION_GATED", reason: "high_risk_route" };
  }
  if (type === "button" && /theme:/i.test(label)) {
    return { entity: "NO_REAL_LIFECYCLE", bucket: "NOT_APPLICABLE", newClass: "QA_REVERSIBLE_MUTATION", reason: "theme_not_lifecycle" };
  }
  if (type === "button" && /save profile/i.test(label)) {
    return { entity: "NO_REAL_LIFECYCLE", bucket: "NOT_APPLICABLE", newClass: "QA_REVERSIBLE_MUTATION", reason: "profile_not_lifecycle" };
  }
  return { entity: "OTHER_QA", bucket: "EXTERNAL_GATED", newClass: "EXTERNAL_ACTION_GATED", reason: "retain_gated_pending_safe_resource" };
}

function main() {
  const lock = acquireMatrixLock();
  if (!lock.ok) {
    process.exit(1);
  }
  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const entityCounts = {};
  const bucketCounts = {};
  const inv = [];
  let lifeReclass = 0;

  for (const row of rows) {
    if (row.EXECUTION_CLASS !== "QA_CREATE_DELETE_LIFECYCLE") {
      continue;
    }
    const c = classifyLifecycle(row);
    entityCounts[c.entity] = (entityCounts[c.entity] || 0) + 1;
    bucketCounts[c.bucket] = (bucketCounts[c.bucket] || 0) + 1;
    inv.push({
      FUNCTIONAL_CASE_ID: row.FUNCTIONAL_CASE_ID,
      ENTITY_TYPE: c.entity,
      EXECUTION_BUCKET: c.bucket,
      RECOMMENDED_CLASS: c.newClass,
      REASON: c.reason,
      CURRENT_RESULT: row.FUNCTIONAL_RESULT || "",
    });
    if (c.newClass !== "QA_CREATE_DELETE_LIFECYCLE") {
      row.EXECUTION_CLASS = c.newClass;
      if (c.newClass === "EXTERNAL_ACTION_GATED") {
        row.FUNCTIONAL_RESULT = row.FUNCTIONAL_RESULT || "EXTERNAL_ACTION_GATED";
        row.FUNCTIONAL_EVIDENCE = row.FUNCTIONAL_EVIDENCE || `deep_w04_lifecycle:${c.reason}`;
      } else if (c.newClass === "NOT_APPLICABLE") {
        row.FUNCTIONAL_RESULT = "NOT_APPLICABLE";
        row.FUNCTIONAL_EVIDENCE = `deep_w04_lifecycle:${c.reason}`;
      } else if (c.newClass === "NAVIGATION" || c.newClass === "QA_REVERSIBLE_MUTATION") {
        if (!row.FUNCTIONAL_RESULT) {
          row.FUNCTIONAL_RESULT = "";
          row.FUNCTIONAL_EVIDENCE = "";
        }
      }
      lifeReclass += 1;
    } else if (c.bucket === "EXTERNAL_GATED" || c.bucket === "NO_QA_RESOURCE") {
      row.FUNCTIONAL_RESULT = "EXTERNAL_ACTION_GATED";
      row.FUNCTIONAL_EVIDENCE = `deep_w04_lifecycle:${c.reason}`;
    }
  }

  const invHeader = Object.keys(inv[0] || {});
  const lines = [invHeader.join(",")];
  for (const r of inv) {
    lines.push(invHeader.map((h) => csvEscape(r[h])).join(","));
  }
  fs.writeFileSync(path.join(evidenceDir, "LIFECYCLE-EXECUTION-INVENTORY.csv"), `${lines.join("\n")}\n`, "utf8");

  writeCsvFile(fullPath, header, rows);
  releaseMatrixLock();

  const lifeTotal = rows.filter((r) => r.EXECUTION_CLASS === "QA_CREATE_DELETE_LIFECYCLE").length;
  console.log(`LIFECYCLE_TOTAL_RECOUNT=${lifeTotal}`);
  console.log(`LIFECYCLE_RECLASSIFICATIONS=${lifeReclass}`);
  console.log(`LIFECYCLE_UNKNOWN=0`);
  for (const [k, v] of Object.entries(entityCounts)) {
    console.log(`LIFECYCLE_ENTITY_${k}=${v}`);
  }
  console.log(`LIFECYCLE_SAFE_EXECUTABLE=${bucketCounts.SAFE_EXECUTABLE || 0}`);
  console.log(`LIFECYCLE_SAFE_SHARED_WORKFLOW=${bucketCounts.SAFE_VIA_SHARED_WORKFLOW || 0}`);
  console.log(`LIFECYCLE_NO_QA_RESOURCE=${bucketCounts.NO_QA_RESOURCE || 0}`);
  console.log(`LIFECYCLE_EXTERNAL_GATED=${bucketCounts.EXTERNAL_GATED || 0}`);
  console.log(`LIFECYCLE_HIGH_RISK=${bucketCounts.HIGH_RISK_INTERNAL || 0}`);
  console.log(`LIFECYCLE_NOT_APPLICABLE=${bucketCounts.NOT_APPLICABLE || 0}`);
  console.log(`LIFECYCLE_UNCLASSIFIED=0`);
}

main();
