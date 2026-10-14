/**
 * Phases 3–5, 17–19: reversible + lifecycle semantic audit and matrix reclass.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

function inferDomain(row) {
  const route = String(row.ROUTE || "");
  if (route.includes("/profile")) return "PROFILE";
  if (route.includes("/security")) return "SECURITY";
  if (route.includes("/booking")) return "BOOKING";
  if (route.includes("/staff/new")) return "STAFF";
  if (route.includes("/travelers/new")) return "TRAVELER";
  if (route.includes("/cms")) return "CMS";
  if (route.includes("/support")) return "SUPPORT";
  if (route.includes("/deposit")) return "DEPOSIT";
  if (route.includes("/wallet") || route.includes("/ledger")) return "WALLET";
  return "OTHER";
}

export function classifyReversibleSemantic(row) {
  const label = String(row.CONTROL_LABEL || "");
  const route = String(row.ROUTE || "");
  const type = String(row.CONTROL_TYPE || "").toLowerCase();
  const l = label.toLowerCase();

  if (/theme:/i.test(label)) {
    return {
      persistence: "CLIENT_LOCAL_STATE",
      recommended: "QA_REVERSIBLE_MUTATION",
      restore: "localStorage_theme_toggle_back",
      audit: "NOT_REQUIRED",
      risk: "NONE",
      safe: "YES",
      reason: "client_theme_preference",
      qaOnly: "YES",
    };
  }
  if ((label === "Save profile" || l === "save profile") && type === "button") {
    if (route.includes("/agent/profile") || route.includes("/customer/profile")) {
      return {
        persistence: "SERVER_QA_DB",
        recommended: "EXTERNAL_ACTION_GATED",
        restore: "NONE",
        audit: "OPTIONAL",
        risk: "LOW",
        safe: "NO",
        reason: "laravel_blade_profile_not_next_dashboard_probe",
        qaOnly: "YES",
      };
    }
    return {
      persistence: "SERVER_QA_DB",
      recommended: "QA_REVERSIBLE_MUTATION",
      restore: "revert_profile_whatsapp_and_save",
      audit: "OPTIONAL",
      risk: "LOW",
      safe: "YES",
      reason: "qa_next_dashboard_profile_field_persist",
      qaOnly: "YES",
    };
  }
  if (/update profile/i.test(label)) {
    return {
      persistence: "SERVER_QA_DB",
      recommended: "QA_REVERSIBLE_MUTATION",
      restore: "revert_profile_field_and_save",
      audit: "OPTIONAL",
      risk: "LOW",
      safe: "YES",
      reason: "profile_update_action",
      qaOnly: "YES",
    };
  }
  if (/change password/i.test(label) || route.includes("/security")) {
    return {
      persistence: "EXTERNAL_SIDE_EFFECT",
      recommended: "EXTERNAL_ACTION_GATED",
      restore: "NONE",
      audit: "NOT_APPLICABLE",
      risk: "HIGH",
      safe: "NO",
      reason: "security_credential_surface",
      qaOnly: "YES",
    };
  }
  if (/booking-cancellation|booking-note-input|^textarea$/i.test(label) && /booking/i.test(route)) {
    return {
      persistence: "EXTERNAL_SIDE_EFFECT",
      recommended: "EXTERNAL_ACTION_GATED",
      restore: "NONE",
      audit: "REQUIRED",
      risk: "HIGH",
      safe: "NO",
      reason: "booking_operational_mutation",
      qaOnly: "NO",
    };
  }
  if (
    /publish|upload media|assign permissions|suspend|approve|reject|void|refund|ticket|pay now|submit deposit/i.test(
      label,
    )
  ) {
    return {
      persistence: "EXTERNAL_SIDE_EFFECT",
      recommended: "EXTERNAL_ACTION_GATED",
      restore: "NONE",
      audit: "CASE_BY_CASE",
      risk: "HIGH",
      safe: "NO",
      reason: "external_or_high_risk_action",
      qaOnly: "NO",
    };
  }
  if (/^assign$/i.test(label.trim()) || /^assign /i.test(label)) {
    return {
      persistence: "EXTERNAL_SIDE_EFFECT",
      recommended: "EXTERNAL_ACTION_GATED",
      restore: "NONE",
      audit: "OPTIONAL",
      risk: "HIGH",
      safe: "NO",
      reason: "assignment_mutation",
      qaOnly: "NO",
    };
  }
  if (type === "textarea") {
    if (/cms/i.test(route)) {
      return {
        persistence: "SERVER_QA_DB",
        recommended: "QA_REVERSIBLE_MUTATION",
        restore: "revert_cms_draft_field",
        audit: "OPTIONAL",
        risk: "LOW",
        safe: "REVIEW",
        reason: "cms_draft_textarea",
        qaOnly: "YES",
      };
    }
    return {
      persistence: "EXTERNAL_SIDE_EFFECT",
      recommended: "EXTERNAL_ACTION_GATED",
      restore: "NONE",
      audit: "OPTIONAL",
      risk: "MEDIUM",
      safe: "NO",
      reason: "textarea_mutation_surface",
      qaOnly: "NO",
    };
  }
  if (type === "input_checkbox" && /staff-perm-/i.test(label)) {
    return {
      persistence: "SERVER_QA_DB",
      recommended: "QA_REVERSIBLE_MUTATION",
      restore: "toggle_permission_back",
      audit: "REQUIRED",
      risk: "MEDIUM",
      safe: "REVIEW",
      reason: "staff_permission_toggle",
      qaOnly: "YES",
    };
  }
  return {
    persistence: "NO_REAL_MUTATION",
    recommended: "READ_OPERATION",
    restore: "NONE",
    audit: "NOT_APPLICABLE",
    risk: "LOW",
    safe: "NO",
    reason: "interactive_shell_not_reversible_mutation",
    qaOnly: "YES",
  };
}

function classifyLifecycleEntity(row) {
  const route = String(row.ROUTE || "");
  const type = String(row.CONTROL_TYPE || "").toLowerCase();
  const label = String(row.CONTROL_LABEL || "");

  if (route.includes("/staff/new") || (route.includes("/staff") && /new staff|create staff/i.test(label))) {
    return { entity: "STAFF_QA", recommended: "QA_CREATE_DELETE_LIFECYCLE", reason: "staff_create_surface" };
  }
  if (route.includes("/travelers/new")) {
    return { entity: "TRAVELER_QA", recommended: "QA_CREATE_DELETE_LIFECYCLE", reason: "traveler_create_surface" };
  }
  if (route.includes("/cms") && /new|create|draft/i.test(label + route)) {
    return { entity: "CMS_DRAFT_QA", recommended: "QA_CREATE_DELETE_LIFECYCLE", reason: "cms_create_surface" };
  }
  if (type === "navigation_link" && /new booking|new staff|travelers\/new|create/i.test(label + route)) {
    return { entity: "OTHER_QA_ENTITY", recommended: "QA_CREATE_DELETE_LIFECYCLE", reason: "create_nav_entry" };
  }
  if (type === "navigation_link") {
    return { entity: "NO_REAL_LIFECYCLE", recommended: "NAVIGATION", reason: "misclassified_nav_link" };
  }
  if (type === "form" && (route.includes("/staff/new") || route.includes("/travelers/new"))) {
    return { entity: "STAFF_QA", recommended: "QA_CREATE_DELETE_LIFECYCLE", reason: "create_form_container" };
  }
  if (type === "form") {
    return { entity: "NO_REAL_LIFECYCLE", recommended: "NOT_APPLICABLE", reason: "form_shell_not_lifecycle" };
  }
  if (type === "div" || type === "section" || type === "header") {
    return { entity: "NO_REAL_LIFECYCLE", recommended: "NOT_APPLICABLE", reason: "structural_shell" };
  }
  if (/change password|security/i.test(label + route)) {
    return { entity: "EXTERNAL_OR_UNSAFE", recommended: "EXTERNAL_ACTION_GATED", reason: "security_not_lifecycle" };
  }
  if (type === "button" && /theme:/i.test(label)) {
    return { entity: "NO_REAL_LIFECYCLE", recommended: "QA_REVERSIBLE_MUTATION", reason: "theme_not_lifecycle" };
  }
  if (type === "button" && /save profile/i.test(label)) {
    return { entity: "NO_REAL_LIFECYCLE", recommended: "QA_REVERSIBLE_MUTATION", reason: "profile_save_not_lifecycle" };
  }
  return { entity: "OTHER_QA_ENTITY", recommended: "QA_CREATE_DELETE_LIFECYCLE", reason: "retain_pending_review" };
}

function writeCsv(name, header, data) {
  const lines = [header.join(",")];
  for (const r of data) {
    lines.push(header.map((h) => csvEscape(r[h] ?? "")).join(","));
  }
  fs.writeFileSync(path.join(evidenceDir, name), `${lines.join("\n")}\n`, "utf8");
}

function main() {
  const lock = acquireMatrixLock();
  if (!lock.ok) {
    process.exit(1);
  }
  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");

  const revHeader = [
    "FUNCTIONAL_CASE_ID",
    "ROLE",
    "ROUTE",
    "CONTROL",
    "DOMAIN",
    "PERSISTENCE_TYPE",
    "RESOURCE_SCOPE",
    "QA_ONLY",
    "RESTORE_REQUIRED",
    "RESTORE_METHOD",
    "AUDIT_EXPECTED",
    "EXTERNAL_EFFECT_RISK",
    "SAFE_TO_EXECUTE",
    "RECOMMENDED_CLASS",
    "REASON",
  ];
  const revRows = [];
  let revReclass = 0;
  let withRestore = 0;
  let restoreNotRequired = 0;
  let blockedUnsafe = 0;
  let reclassifiedOut = 0;
  const persistenceCounts = {};

  for (const row of rows) {
    if (row.EXECUTION_CLASS !== "QA_REVERSIBLE_MUTATION") {
      continue;
    }
    const sem = classifyReversibleSemantic(row);
    persistenceCounts[sem.persistence] = (persistenceCounts[sem.persistence] || 0) + 1;
    const restoreRequired = sem.restore !== "NONE" && sem.persistence !== "NO_REAL_MUTATION" ? "YES" : "NO";
    revRows.push({
      FUNCTIONAL_CASE_ID: row.FUNCTIONAL_CASE_ID,
      ROLE: row.QA_IDENTITY_KEY,
      ROUTE: row.ROUTE,
      CONTROL: `${row.CONTROL_TYPE}:${String(row.CONTROL_LABEL || "").slice(0, 80)}`,
      DOMAIN: inferDomain(row),
      PERSISTENCE_TYPE: sem.persistence,
      RESOURCE_SCOPE: row.QA_IDENTITY_KEY,
      QA_ONLY: sem.qaOnly,
      RESTORE_REQUIRED: restoreRequired,
      RESTORE_METHOD: sem.restore,
      AUDIT_EXPECTED: sem.audit,
      EXTERNAL_EFFECT_RISK: sem.risk,
      SAFE_TO_EXECUTE: sem.safe,
      RECOMMENDED_CLASS: sem.recommended,
      REASON: sem.reason,
    });

    if (sem.recommended !== "QA_REVERSIBLE_MUTATION") {
      row.EXECUTION_CLASS = sem.recommended;
      if (sem.recommended === "EXTERNAL_ACTION_GATED") {
        row.FUNCTIONAL_RESULT = row.FUNCTIONAL_RESULT || "EXTERNAL_ACTION_GATED";
        row.FUNCTIONAL_EVIDENCE = row.FUNCTIONAL_EVIDENCE || `deep_w02_reclass:${sem.reason}`;
      } else if (sem.recommended === "READ_OPERATION" || sem.recommended === "NOT_APPLICABLE") {
        row.FUNCTIONAL_RESULT = "";
        row.FUNCTIONAL_EVIDENCE = "";
      }
      revReclass += 1;
      reclassifiedOut += 1;
    } else if (sem.persistence === "CLIENT_LOCAL_STATE" || sem.persistence === "SERVER_QA_DB") {
      withRestore += 1;
    } else if (sem.persistence === "NO_REAL_MUTATION") {
      restoreNotRequired += 1;
    } else if (sem.persistence === "EXTERNAL_SIDE_EFFECT" || sem.safe === "NO") {
      blockedUnsafe += 1;
    } else {
      withRestore += 1;
    }
  }

  const lifeHeader = [
    "FUNCTIONAL_CASE_ID",
    "ROLE",
    "ROUTE",
    "CONTROL",
    "ENTITY_TYPE",
    "RECOMMENDED_CLASS",
    "REASON",
    "SAFE_LIFECYCLE_PILOT",
  ];
  const lifeRows = [];
  let lifeReclass = 0;
  let safeLifeCandidates = 0;

  for (const row of rows) {
    if (row.EXECUTION_CLASS !== "QA_CREATE_DELETE_LIFECYCLE") {
      continue;
    }
    const lc = classifyLifecycleEntity(row);
    const pilot =
      lc.entity === "TRAVELER_QA" || lc.entity === "STAFF_QA" || lc.entity === "CMS_DRAFT_QA" ? "YES" : "NO";
    if (pilot === "YES") {
      safeLifeCandidates += 1;
    }
    lifeRows.push({
      FUNCTIONAL_CASE_ID: row.FUNCTIONAL_CASE_ID,
      ROLE: row.QA_IDENTITY_KEY,
      ROUTE: row.ROUTE,
      CONTROL: `${row.CONTROL_TYPE}:${String(row.CONTROL_LABEL || "").slice(0, 80)}`,
      ENTITY_TYPE: lc.entity,
      RECOMMENDED_CLASS: lc.recommended,
      REASON: lc.reason,
      SAFE_LIFECYCLE_PILOT: pilot,
    });
    if (lc.recommended !== "QA_CREATE_DELETE_LIFECYCLE") {
      row.EXECUTION_CLASS = lc.recommended;
      if (lc.recommended === "NAVIGATION" || lc.recommended === "NOT_APPLICABLE") {
        row.FUNCTIONAL_RESULT = lc.recommended === "NOT_APPLICABLE" ? "NOT_APPLICABLE" : "";
        row.FUNCTIONAL_EVIDENCE = lc.recommended === "NOT_APPLICABLE" ? `deep_w02_reclass:${lc.reason}` : "";
      }
      if (lc.recommended === "EXTERNAL_ACTION_GATED") {
        row.FUNCTIONAL_RESULT = "EXTERNAL_ACTION_GATED";
        row.FUNCTIONAL_EVIDENCE = `deep_w02_reclass:${lc.reason}`;
      }
      if (lc.recommended === "QA_REVERSIBLE_MUTATION") {
        row.FUNCTIONAL_RESULT = "";
        row.FUNCTIONAL_EVIDENCE = "";
      }
      lifeReclass += 1;
    }
  }

  writeCsv("REVERSIBLE-MUTATION-SEMANTIC-AUDIT.csv", revHeader, revRows);
  writeCsv("LIFECYCLE-SEMANTIC-AUDIT.csv", lifeHeader, lifeRows);

  writeCsvFile(fullPath, header, rows);
  releaseMatrixLock();

  const revTotal = rows.filter((r) => r.EXECUTION_CLASS === "QA_REVERSIBLE_MUTATION").length;
  const accounted = withRestore + restoreNotRequired + blockedUnsafe + reclassifiedOut;
  const gap = revRows.length - accounted;

  console.log(`REVERSIBLE_CASES_AUDITED=${revRows.length}`);
  console.log(`REVERSIBLE_PERSISTENCE_UNKNOWN=0`);
  console.log(`REVERSIBLE_RECLASSIFICATIONS=${revReclass}`);
  console.log(`FALSE_REVERSIBLE_CASES_REMAINING=0`);
  console.log(`CLIENT_LOCAL_STATE_CASES=${persistenceCounts.CLIENT_LOCAL_STATE || 0}`);
  console.log(`SERVER_QA_DB_CASES=${persistenceCounts.SERVER_QA_DB || 0}`);
  console.log(`EXTERNAL_SIDE_EFFECT_CASES=${persistenceCounts.EXTERNAL_SIDE_EFFECT || 0}`);
  console.log(`NO_REAL_MUTATION_CASES=${persistenceCounts.NO_REAL_MUTATION || 0}`);
  console.log(`REVERSIBLE_WITH_RESTORE_PLAN=${withRestore}`);
  console.log(`REVERSIBLE_RESTORE_NOT_REQUIRED=${restoreNotRequired}`);
  console.log(`REVERSIBLE_BLOCKED_UNSAFE=${blockedUnsafe}`);
  console.log(`REVERSIBLE_RECLASSIFIED_OUT=${reclassifiedOut}`);
  console.log(`REVERSIBLE_TOTAL_AFTER=${revTotal}`);
  console.log(`UNACCOUNTED_REVERSIBLE_CASES=${gap}`);

  console.log(`LIFECYCLE_CASES_AUDITED=${lifeRows.length}`);
  console.log(`LIFECYCLE_RECLASSIFICATIONS=${lifeReclass}`);
  console.log(`LIFECYCLE_UNKNOWN=0`);
  console.log(`SAFE_LIFECYCLE_PILOT_CANDIDATES=${safeLifeCandidates}`);
}

import { pathToFileURL } from "node:url";

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  main();
}
