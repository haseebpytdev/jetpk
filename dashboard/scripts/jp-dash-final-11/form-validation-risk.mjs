/**
 * Form validation safety classification for FINAL-11 cert.
 */
const MUTATION_RISK_PATTERN =
  /\b(password|deposit|refund|payment|ticket|approve|reject|cancel|void|transfer|payout|charge|book now|issue|sync supplier|submit deposit|submit payment)\b/i;

export function classifyFormValidationRisk(row) {
  const label = `${row.CONTROL_LABEL || ""} ${row.ROUTE || ""} ${row.SECTION || ""}`;
  const type = String(row.CONTROL_TYPE || "").toLowerCase();
  const mutation = String(row.MUTATION_CLASS || "").toUpperCase();

  if (MUTATION_RISK_PATTERN.test(label) || mutation.includes("QA_MUTATION")) {
    return "MUTATION_RISK";
  }
  if (/\b(export csv|export|download)\b/i.test(label)) {
    return "MUTATION_RISK";
  }
  if (
    type === "input_email" ||
    type === "input" ||
    type === "textarea" ||
    type === "select" ||
    type.includes("input_")
  ) {
    return "CLIENT_ONLY_SAFE";
  }
  if (type === "form" || type === "button") {
    return "SERVER_VALIDATION_SAFE";
  }
  return "CLIENT_ONLY_SAFE";
}

export function suggestedReclassForMutationRisk(row) {
  const label = row.CONTROL_LABEL || "";
  if (/\b(export|download|csv|pdf)\b/i.test(label)) {
    return "DOWNLOAD_EXPORT";
  }
  if (/\b(create|delete|new )\b/i.test(label)) {
    return "QA_CREATE_DELETE_LIFECYCLE";
  }
  return "QA_REVERSIBLE_MUTATION";
}
