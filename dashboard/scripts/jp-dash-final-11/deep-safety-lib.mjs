/**
 * Safety classification helpers for FINAL-11 deep cases.
 */
import { isDeepCaseClass } from "./functional-case-classify.mjs";

const HIGH_RISK =
  /\b(cancel|refund|ticket|issue|payment|deposit|wallet|supplier|approve|reject|void|charge|payout|sync)\b/i;

export function inferDomain(row) {
  const route = String(row.ROUTE || "");
  const label = String(row.CONTROL_LABEL || "").toLowerCase();
  if (route.includes("/staff")) return "STAFF";
  if (route.includes("/users") || route.includes("/permissions")) return "ROLE_PERMISSION";
  if (route.includes("/profile") || route.includes("/security")) return label.includes("security") ? "SECURITY" : "PROFILE";
  if (route.includes("/cms")) return "CMS";
  if (route.includes("/seo")) return "SEO";
  if (route.includes("/booking")) return "BOOKING";
  if (route.includes("/pnr")) return "PNR";
  if (route.includes("/traveler")) return "TRAVELER";
  if (route.includes("/support")) return "SUPPORT";
  if (route.includes("/deposit")) return "DEPOSIT";
  if (route.includes("/payment")) return "PAYMENT";
  if (route.includes("/commission")) return "COMMISSION";
  if (route.includes("/wallet") || route.includes("/ledger") || route.includes("/finance")) return "WALLET";
  if (route.includes("/report") || route.includes("/audit")) return "REPORT";
  if (route.includes("/operations")) return "OPERATIONS";
  if (route.includes("/settings")) return "SETTINGS";
  if (label.includes("theme")) return "SETTINGS";
  if (route.includes("/download") || label.includes("export") || label.includes("download")) return "DOWNLOAD";
  return "OTHER";
}

export function isMisclassifiedDownload(row) {
  if (row.EXECUTION_CLASS !== "DOWNLOAD_EXPORT") {
    return false;
  }
  const type = String(row.CONTROL_TYPE || "").toLowerCase();
  const label = String(row.CONTROL_LABEL || "");
  if (type === "input_checkbox" && /^staff-perm-/i.test(label)) {
    return true;
  }
  if (type === "form" && /\/deposits\/new/i.test(row.ROUTE || "")) {
    return true;
  }
  if (label === "audit.export" && /\/users\/permissions/i.test(row.ROUTE || "")) {
    return true;
  }
  return false;
}

export function isRealDownloadCase(row) {
  return row.EXECUTION_CLASS === "DOWNLOAD_EXPORT" && !isMisclassifiedDownload(row);
}

export function inferSafetyLevel(row) {
  const cls = row.EXECUTION_CLASS;
  const route = String(row.ROUTE || "");
  const label = String(row.CONTROL_LABEL || "");

  if (!isDeepCaseClass(cls)) {
    return "NOT_APPLICABLE";
  }
  if (cls === "DOWNLOAD_EXPORT") {
    if (isMisclassifiedDownload(row)) {
      return "NOT_APPLICABLE";
    }
    return "SAFE_READ_DOWNLOAD";
  }
  if (cls === "QA_CREATE_DELETE_LIFECYCLE") {
    if (HIGH_RISK.test(label) || HIGH_RISK.test(route)) {
      return "EXTERNAL_ACTION_GATED";
    }
    if (/\/staff\/new|\/travelers\/new/i.test(route)) {
      return "SAFE_QA_CREATE_DELETE";
    }
    return "SAFE_QA_CREATE_DELETE";
  }
  if (cls === "QA_REVERSIBLE_MUTATION") {
    if (HIGH_RISK.test(label) && !/theme/i.test(label)) {
      return "EXTERNAL_ACTION_GATED";
    }
    if (/theme:/i.test(label)) {
      return "SAFE_QA_REVERSIBLE";
    }
    if (/save profile/i.test(label)) {
      return "SAFE_QA_REVERSIBLE";
    }
    if (HIGH_RISK.test(route)) {
      return "EXTERNAL_ACTION_GATED";
    }
    return "SAFE_QA_REVERSIBLE";
  }
  return "UNSAFE_NO_QA_RESOURCE";
}

export function mutationScope(row) {
  const level = inferSafetyLevel(row);
  if (level === "SAFE_QA_REVERSIBLE" && /theme/i.test(row.CONTROL_LABEL || "")) {
    return "CLIENT_THEME_PREFERENCE";
  }
  if (level === "SAFE_QA_REVERSIBLE" && /save profile/i.test(row.CONTROL_LABEL || "")) {
    return "QA_PROFILE_FIELD";
  }
  if (level === "SAFE_READ_DOWNLOAD") {
    return "READ_EXPORT_BLOB";
  }
  if (level === "SAFE_QA_CREATE_DELETE") {
    return "QA_CREATE_DELETE_RECORD";
  }
  return "NONE";
}

export function restoreMethod(row) {
  const scope = mutationScope(row);
  if (scope === "CLIENT_THEME_PREFERENCE") {
    return "localStorage_theme_preference_toggle_back";
  }
  if (scope === "QA_PROFILE_FIELD") {
    return "revert_field_and_save_profile";
  }
  if (scope === "QA_CREATE_DELETE_RECORD") {
    return "delete_or_archive_created_qa_record";
  }
  return "NONE";
}

export function auditExpected(row) {
  if (/theme/i.test(row.CONTROL_LABEL || "")) {
    return "NOT_REQUIRED_CLIENT_ONLY";
  }
  if (/save profile/i.test(row.CONTROL_LABEL || "")) {
    return "OPTIONAL_SERVER_AUDIT";
  }
  if (row.EXECUTION_CLASS === "DOWNLOAD_EXPORT") {
    return "NOT_REQUIRED_PREVIEW_EXPORT";
  }
  if (row.EXECUTION_CLASS === "QA_CREATE_DELETE_LIFECYCLE") {
    return "REQUIRED_ON_CREATE_DELETE";
  }
  return "CASE_BY_CASE";
}

export function externalEffectRisk(row) {
  const level = inferSafetyLevel(row);
  if (level === "EXTERNAL_ACTION_GATED" || level === "UNSAFE_NO_QA_RESOURCE") {
    return "HIGH";
  }
  if (level === "SAFE_READ_DOWNLOAD") {
    return "LOW";
  }
  if (/theme/i.test(row.CONTROL_LABEL || "")) {
    return "NONE";
  }
  return "LOW";
}

export function safeToExecute(row) {
  const level = inferSafetyLevel(row);
  if (level === "SAFE_READ_DOWNLOAD" || level === "SAFE_QA_REVERSIBLE") {
    return "YES";
  }
  if (level === "NOT_APPLICABLE" && row.EXECUTION_CLASS === "DOWNLOAD_EXPORT") {
    return "YES";
  }
  return "NO";
}

export const REVERSIBLE_PILOT_CASE_IDS = [
  "FC-004260",
  "FC-005668",
  "FC-007075",
  "FC-008421",
  "FC-009701",
  "FC-010848",
  "FC-011929",
  "FC-013016",
  "FC-014680",
  "FC-005420",
];
