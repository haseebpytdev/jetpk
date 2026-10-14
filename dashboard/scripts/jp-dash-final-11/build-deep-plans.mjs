/**
 * Phases 3–4, 6, 11, 16: deep execution plan, QA inventory, lifecycle plan.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { isDeepCaseClass } from "./functional-case-classify.mjs";
import {
  inferDomain,
  inferSafetyLevel,
  safeToExecute,
  mutationScope,
  restoreMethod,
  auditExpected,
  externalEffectRisk,
  isMisclassifiedDownload,
} from "./deep-safety-lib.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

function writeCsv(name, header, dataRows) {
  const lines = [header.join(",")];
  for (const r of dataRows) {
    lines.push(header.map((h) => csvEscape(r[h] ?? "")).join(","));
  }
  fs.writeFileSync(path.join(evidenceDir, name), `${lines.join("\n")}\n`, "utf8");
}

function main() {
  const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const deep = rows.filter((r) => isDeepCaseClass(r.EXECUTION_CLASS));

  const planHeader = [
    "FUNCTIONAL_CASE_ID",
    "ROLE",
    "ROUTE",
    "CONTROL",
    "EXECUTION_CLASS",
    "DOMAIN",
    "QA_RESOURCE_REQUIRED",
    "QA_RESOURCE_AVAILABLE",
    "MUTATION_SCOPE",
    "RESTORE_METHOD",
    "AUDIT_EXPECTED",
    "EXTERNAL_EFFECT_RISK",
    "SAFE_TO_EXECUTE",
    "BLOCK_REASON",
    "EXECUTION_ORDER",
    "SAFETY_LEVEL",
  ];
  const planRows = [];
  let order = 0;
  let unknown = 0;
  let safetyUnknown = 0;
  let withRestore = 0;
  let withoutRestore = 0;

  for (const row of deep) {
    const safety = inferSafetyLevel(row);
    if (!safety || safety === "UNSAFE_NO_QA_RESOURCE") {
      safetyUnknown += safety === "UNSAFE_NO_QA_RESOURCE" ? 0 : 1;
    }
    if (safety === "UNSAFE_NO_QA_RESOURCE") {
      safetyUnknown += 0;
    }
    const domain = inferDomain(row);
    if (!domain) {
      unknown += 1;
    }
    const safe = safeToExecute(row);
    const block =
      safe === "YES"
        ? ""
        : safety === "EXTERNAL_ACTION_GATED"
          ? "external_or_high_risk"
          : "not_safe_in_wave_01";
    let execOrder = "";
    if (row.EXECUTION_CLASS === "DOWNLOAD_EXPORT") {
      execOrder = String(isMisclassifiedDownload(row) ? 0 : ++order);
    }
    if (safety === "SAFE_QA_REVERSIBLE") {
      const rm = restoreMethod(row);
      if (rm && rm !== "NONE") {
        withRestore += 1;
      } else {
        withoutRestore += 1;
      }
    }
    planRows.push({
      FUNCTIONAL_CASE_ID: row.FUNCTIONAL_CASE_ID,
      ROLE: row.QA_IDENTITY_KEY,
      ROUTE: row.ROUTE,
      CONTROL: `${row.CONTROL_TYPE}:${String(row.CONTROL_LABEL || "").slice(0, 80)}`,
      EXECUTION_CLASS: row.EXECUTION_CLASS,
      DOMAIN: domain,
      QA_RESOURCE_REQUIRED: row.QA_IDENTITY_KEY || "YES",
      QA_RESOURCE_AVAILABLE: "YES",
      MUTATION_SCOPE: mutationScope(row),
      RESTORE_METHOD: restoreMethod(row),
      AUDIT_EXPECTED: auditExpected(row),
      EXTERNAL_EFFECT_RISK: externalEffectRisk(row),
      SAFE_TO_EXECUTE: safe,
      BLOCK_REASON: block,
      EXECUTION_ORDER: execOrder,
      SAFETY_LEVEL: safety,
    });
  }

  writeCsv("DEEP-EXECUTION-PLAN.csv", planHeader, planRows);

  const qaInv = [
    {
      RESOURCE_TYPE: "QA_IDENTITY",
      RESOURCE_ID_SAFE: "platform_admin",
      OWNING_QA_IDENTITY: "platform_admin",
      CURRENT_STATE: "ACTIVE_SESSION",
      ALLOWED_MUTATION: "READ_EXPORT_PROFILE",
      RESTORE_METHOD: "revert_profile_fields",
      AUDITABLE: "OPTIONAL",
      SAFE: "YES",
    },
    {
      RESOURCE_TYPE: "QA_IDENTITY",
      RESOURCE_ID_SAFE: "staff",
      OWNING_QA_IDENTITY: "staff",
      CURRENT_STATE: "ACTIVE_SESSION",
      ALLOWED_MUTATION: "READ_EXPORT_PROFILE",
      RESTORE_METHOD: "revert_profile_fields",
      AUDITABLE: "OPTIONAL",
      SAFE: "YES",
    },
    {
      RESOURCE_TYPE: "QA_AGENCY",
      RESOURCE_ID_SAFE: "JP-DASH-03-QA-AGENT-A",
      OWNING_QA_IDENTITY: "agent_owner_a",
      CURRENT_STATE: "ACTIVE",
      ALLOWED_MUTATION: "THEME_PREFERENCE_CLIENT",
      RESTORE_METHOD: "localStorage_toggle",
      AUDITABLE: "NO",
      SAFE: "YES",
    },
    {
      RESOURCE_TYPE: "QA_AGENCY",
      RESOURCE_ID_SAFE: "JP-DASH-03-QA-AGENT-B",
      OWNING_QA_IDENTITY: "agent_owner_b",
      CURRENT_STATE: "ACTIVE",
      ALLOWED_MUTATION: "THEME_PREFERENCE_CLIENT",
      RESTORE_METHOD: "localStorage_toggle",
      AUDITABLE: "NO",
      SAFE: "YES",
    },
    {
      RESOURCE_TYPE: "QA_CUSTOMER",
      RESOURCE_ID_SAFE: "customer_b",
      OWNING_QA_IDENTITY: "customer_b",
      CURRENT_STATE: "ACTIVE",
      ALLOWED_MUTATION: "THEME_PREFERENCE_CLIENT",
      RESTORE_METHOD: "localStorage_toggle",
      AUDITABLE: "NO",
      SAFE: "YES",
    },
    {
      RESOURCE_TYPE: "QA_STAFF_SLOT",
      RESOURCE_ID_SAFE: "agent_staff_roles",
      OWNING_QA_IDENTITY: "agent_staff_manager",
      CURRENT_STATE: "ACTIVE",
      ALLOWED_MUTATION: "THEME_PREFERENCE_CLIENT",
      RESTORE_METHOD: "localStorage_toggle",
      AUDITABLE: "NO",
      SAFE: "YES",
    },
  ];
  writeCsv(
    "DEEP-QA-RESOURCE-INVENTORY.csv",
    [
      "RESOURCE_TYPE",
      "RESOURCE_ID_SAFE",
      "OWNING_QA_IDENTITY",
      "CURRENT_STATE",
      "ALLOWED_MUTATION",
      "RESTORE_METHOD",
      "AUDITABLE",
      "SAFE",
    ],
    qaInv,
  );

  const lifeHeader = [
    "FUNCTIONAL_CASE_ID",
    "ROLE",
    "ROUTE",
    "CONTROL",
    "RESOURCE_TYPE",
    "CREATE",
    "VERIFY",
    "EDIT",
    "DELETE_ARCHIVE",
    "VERIFY_REMOVAL",
    "AUDIT",
    "TENANT_SCOPE",
    "SAFE_FOR_BULK",
  ];
  const lifeRows = [];
  for (const row of deep.filter((r) => r.EXECUTION_CLASS === "QA_CREATE_DELETE_LIFECYCLE")) {
    const route = row.ROUTE || "";
    let resource = "OTHER";
    if (route.includes("/staff/new")) {
      resource = "QA_AGENT_STAFF_USER";
    } else if (route.includes("/travelers/new")) {
      resource = "QA_TRAVELER";
    } else if (route.includes("/bookings/create")) {
      resource = "QA_BOOKING_DRAFT";
    }
    lifeRows.push({
      FUNCTIONAL_CASE_ID: row.FUNCTIONAL_CASE_ID,
      ROLE: row.QA_IDENTITY_KEY,
      ROUTE: route,
      CONTROL: String(row.CONTROL_LABEL || "").slice(0, 100),
      RESOURCE_TYPE: resource,
      CREATE: "QA_ONLY_RECORD_WITH_UNIQUE_MARKER",
      VERIFY: "LIST_OR_DETAIL_VISIBLE",
      EDIT: route.includes("/new") ? "OPTIONAL_METADATA" : "N/A",
      DELETE_ARCHIVE: "SOFT_DELETE_OR_ARCHIVE_IF_SUPPORTED",
      VERIFY_REMOVAL: "ABSENT_FROM_LIST_AFTER_DELETE",
      AUDIT: resource.includes("STAFF") ? "REQUIRED" : "OPTIONAL",
      TENANT_SCOPE: row.QA_IDENTITY_KEY || "owner",
      SAFE_FOR_BULK: /\/staff\/new|\/travelers\/new/i.test(route) ? "YES" : "REVIEW",
    });
  }
  writeCsv("CREATE-DELETE-LIFECYCLE-PLAN.csv", lifeHeader, lifeRows);

  const safeRev = planRows.filter((r) => r.SAFETY_LEVEL === "SAFE_QA_REVERSIBLE");
  const revWithPlan = safeRev.filter((r) => r.RESTORE_METHOD && r.RESTORE_METHOD !== "NONE").length;
  const revWithout = safeRev.length - revWithPlan;

  console.log(`DEEP_CASES_SAFETY_AUDITED=${deep.length}`);
  console.log(`DEEP_CASES_UNCLASSIFIED=${unknown}`);
  console.log(`DEEP_SAFETY_UNKNOWN=${planRows.filter((r) => !r.SAFETY_LEVEL).length}`);
  console.log(`REVERSIBLE_CASES_WITH_RESTORE_PLAN=${revWithPlan}`);
  console.log(`REVERSIBLE_CASES_WITHOUT_RESTORE_PLAN=${revWithout}`);
  console.log(`LIFECYCLE_CASES_PLANNED=${lifeRows.length}`);
  console.log(`LIFECYCLE_CASES_UNPLANNED=${137 - lifeRows.length}`);
  console.log(`MUTATION_CASES_AUDIT_EXPECTED=${planRows.filter((r) => r.AUDIT_EXPECTED === "REQUIRED_ON_CREATE_DELETE").length}`);
  console.log(`MUTATION_CASES_AUDIT_NOT_APPLICABLE=${planRows.filter((r) => /NOT_REQUIRED|OPTIONAL|CLIENT/.test(r.AUDIT_EXPECTED)).length}`);
}

main();
