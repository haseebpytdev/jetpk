/**
 * Shared EXECUTION_CLASS rules for FINAL-11 functional cases.
 */
export function normalizeSemantic(value) {
  return String(value ?? "")
    .trim()
    .replace(/\s+/g, " ")
    .toLowerCase();
}

const DANGEROUS_ACTION_LABEL =
  /\b(approve|reject|void|refund|mark paid|charge|payout|transfer|submit deposit|submit payment|send email|execute|process cancellation|ticket issue|pay now|capture payment|issue ticket|cancel booking|issue pnr|sync supplier|book now)\b/i;

const DOWNLOAD_LABEL = /\b(download|export|csv|pdf|xlsx|excel|spreadsheet)\b/i;
const CREATE_LABEL = /\b(create|add new|new traveler|new staff|new user|new ticket|new booking|register)\b/i;
const SAVE_LABEL = /\b(save changes|save profile|save settings|update profile|submit form|publish|upload|assign|activate|deactivate|suspend|restore)\b/i;
const DELETE_LABEL = /\b(delete|remove|archive|trash)\b/i;
const SECURITY_LABEL = /\b(password|security|mfa|otp|two-factor|2fa)\b/i;
const REPLY_LABEL = /\b(reply|send message|send reply)\b/i;
const NEGATIVE_ROUTE = /\b(forbidden|denied|not allowed|escalation)\b/i;

export function classifyExecution(row) {
  const behavior = normalizeSemantic(row.EXPECTED_BEHAVIOR);
  const mutation = String(row.MUTATION_CLASS ?? "").toUpperCase();
  const type = normalizeSemantic(row.CONTROL_TYPE);
  const external = String(row.EXTERNAL_EFFECT ?? "").toUpperCase();
  const label = normalizeSemantic(row.CONTROL_LABEL);
  const rawLabel = row.CONTROL_LABEL || "";
  const section = normalizeSemantic(row.SECTION);

  if (DANGEROUS_ACTION_LABEL.test(label) || DANGEROUS_ACTION_LABEL.test(rawLabel)) {
    return "EXTERNAL_ACTION_GATED";
  }
  if (behavior.includes("direct_url_denied") || behavior.includes("denied_by_design")) {
    return "READ_ONLY_BY_DESIGN";
  }
  if (
    behavior.includes("forbidden") ||
    behavior.includes("negative_rbac") ||
    behavior.includes("not_allowed") ||
    NEGATIVE_ROUTE.test(behavior)
  ) {
    return "NEGATIVE_RBAC";
  }
  if (external && external !== "NONE") {
    return "EXTERNAL_ACTION_GATED";
  }
  if (DOWNLOAD_LABEL.test(label) || DOWNLOAD_LABEL.test(rawLabel)) {
    return "DOWNLOAD_EXPORT";
  }
  if (CREATE_LABEL.test(label) || DELETE_LABEL.test(label)) {
    return "QA_CREATE_DELETE_LIFECYCLE";
  }
  if (SAVE_LABEL.test(label) || REPLY_LABEL.test(label)) {
    return "QA_REVERSIBLE_MUTATION";
  }
  if (SECURITY_LABEL.test(label) || SECURITY_LABEL.test(section)) {
    return "FORM_VALIDATION";
  }
  if (mutation.includes("QA_REVERSIBLE") || mutation === "QA_CREATE_DELETE") {
    return "QA_CREATE_DELETE_LIFECYCLE";
  }
  if (mutation.includes("QA_MUTATION") || mutation === "QA_REVERSIBLE_MUTATION") {
    return "QA_REVERSIBLE_MUTATION";
  }
  if (type === "route" && section === "page_load") {
    return "READ_OPERATION";
  }
  if (type === "navigation_link" || label.includes("breadcrumb")) {
    return "NAVIGATION";
  }
  if (
    type.includes("pagination") ||
    label.includes("page size") ||
    label.includes("next page") ||
    label.includes("previous page")
  ) {
    return "PAGINATION";
  }
  if (
    type.includes("filter") ||
    type === "input_search" ||
    label.includes("sort") ||
    label.includes("filter") ||
    section.includes("filter")
  ) {
    return "SEARCH_FILTER_SORT";
  }
  if (type.includes("drawer") || type.includes("modal") || label.includes("drawer") || label.includes("modal")) {
    return "DRAWER_MODAL";
  }
  if (type.includes("download") || type.includes("export") || behavior.includes("download") || behavior.includes("export")) {
    return "DOWNLOAD_EXPORT";
  }
  if (type === "select" && !SECURITY_LABEL.test(label)) {
    return "SEARCH_FILTER_SORT";
  }
  if (
    type === "form" ||
    type === "input" ||
    type === "textarea" ||
    type === "input_email" ||
    type === "input_password" ||
    mutation.includes("FORM")
  ) {
    return "FORM_VALIDATION";
  }
  if (row.RESULT === "NOT_APPLICABLE" || behavior === "not_applicable") {
    return "NOT_APPLICABLE";
  }
  if (mutation === "SAFE_UI_PROBE" || mutation === "READ_ONLY") {
    return "READ_OPERATION";
  }
  return "READ_OPERATION";
}

export const PASSIVE_LIGHT_CLASSES = new Set([
  "READ_OPERATION",
  "NAVIGATION",
  "SEARCH_FILTER_SORT",
  "PAGINATION",
  "DRAWER_MODAL",
  "FORM_VALIDATION",
  "NEGATIVE_RBAC",
  "READ_ONLY_BY_DESIGN",
  "EXTERNAL_ACTION_GATED",
]);

/** Later dedicated phases — never run in auto-passive wave. */
export const DEEP_CASE_CLASSES = new Set([
  "DOWNLOAD_EXPORT",
  "QA_REVERSIBLE_MUTATION",
  "QA_CREATE_DELETE_LIFECYCLE",
]);

/** Passive/light minus deferred form-validation cases. */
export const AUTO_PASSIVE_CLASSES = new Set(
  [...PASSIVE_LIGHT_CLASSES].filter((c) => c !== "FORM_VALIDATION"),
);

export function isAutoPassiveClass(executionClass) {
  return AUTO_PASSIVE_CLASSES.has(executionClass);
}

export function isDeepCaseClass(executionClass) {
  return DEEP_CASE_CLASSES.has(executionClass);
}
