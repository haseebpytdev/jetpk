/**
 * Client-only form validation probes (no successful business mutation).
 */
export const SUPPORTED_FORM_PROBE_TYPES = [
  "text_required",
  "email_format",
  "phone_format",
  "number_min_max",
  "date_invalid",
  "date_range",
  "select_required",
  "radio_required",
  "checkbox_required",
  "password_confirmation",
  "textarea_required",
  "file_type",
  "file_size",
];

export const UNSUPPORTED_FORM_PROBE_TYPES = ["payment_submit", "deposit_submit", "ticket_issue", "supplier_sync"];

const STRONG_FORM_RE =
  /native_|visible_validation|invalid|required|constraint|422|prevented|disabled_control_by_design/i;

export function isStrongFormEvidence(rowOrEvidence) {
  if (rowOrEvidence && typeof rowOrEvidence === "object") {
    const type = String(rowOrEvidence.FUNCTIONAL_EVIDENCE_TYPE || "").trim();
    if (
      type === "NATIVE_VALIDATION_BLOCK" ||
      type === "VISIBLE_FIELD_ERROR" ||
      type === "VISIBLE_FORM_ERROR" ||
      type === "HTTP_422" ||
      type === "RECOGNIZED_VALIDATION_RESPONSE" ||
      type === "INVALID_SUBMIT_PREVENTED"
    ) {
      return true;
    }
  }
  const ev =
    typeof rowOrEvidence === "string"
      ? rowOrEvidence
      : String(rowOrEvidence?.FUNCTIONAL_EVIDENCE || rowOrEvidence?.evidence || "");
  if (/^auth_invalid$/i.test(ev.trim()) || /^probe_target_not_found/i.test(ev)) {
    return false;
  }
  return STRONG_FORM_RE.test(ev);
}

export function probeTypeForRow(row) {
  const type = String(row.CONTROL_TYPE || "").toLowerCase();
  const label = String(row.CONTROL_LABEL || "").toLowerCase();
  if (type.includes("email")) {
    return "email_format";
  }
  if (type.includes("password")) {
    return "password_confirmation";
  }
  if (type.includes("phone") || label.includes("phone")) {
    return "phone_format";
  }
  if (type === "input_number" || type.includes("number")) {
    return "number_min_max";
  }
  if (type.includes("date")) {
    return label.includes("to") || label.includes("range") ? "date_range" : "date_invalid";
  }
  if (type === "select") {
    return "select_required";
  }
  if (type === "textarea") {
    return "textarea_required";
  }
  if (type.includes("checkbox")) {
    return "checkbox_required";
  }
  if (type.includes("radio")) {
    return "radio_required";
  }
  if (type.includes("file")) {
    return label.includes("size") ? "file_size" : "file_type";
  }
  return "text_required";
}

export async function runFormProbe(page, probeType, locator) {
  if ((await locator.count()) === 0) {
    return { pass: false, evidence: "control_not_found" };
  }
  const tag = await locator.evaluate((el) => el.tagName.toLowerCase()).catch(() => "");
  if (!(await locator.isEnabled().catch(() => false))) {
    return { pass: true, evidence: "native_disabled_control_by_design" };
  }

  if (probeType === "email_format") {
    await locator.fill("not-an-email");
    const invalid = await locator.evaluate((el) => !el.checkValidity()).catch(() => false);
    await locator.fill("");
    if (invalid) {
      return { pass: true, evidence: "native_invalid_email_format" };
    }
  }
  if (probeType === "number_min_max") {
    await locator.fill("-999999");
    const invalid = await locator.evaluate((el) => !el.checkValidity()).catch(() => false);
    await locator.fill("");
    if (invalid) {
      return { pass: true, evidence: "native_number_constraint" };
    }
  }
  if (probeType === "select_required") {
    const required = await locator.evaluate((el) => el.required).catch(() => false);
    if (required) {
      await locator.selectOption({ index: 0 }).catch(() => {});
      const invalid = await locator.evaluate((el) => !el.checkValidity()).catch(() => false);
      if (invalid) {
        return { pass: true, evidence: "native_select_required_invalid" };
      }
    }
  }
  if (probeType === "textarea_required" || probeType === "text_required") {
    const required = await locator.evaluate((el) => el.required).catch(() => false);
    if (required) {
      await locator.fill("");
      const invalid = await locator.evaluate((el) => !el.checkValidity()).catch(() => false);
      if (invalid) {
        return { pass: true, evidence: "native_required_empty_invalid" };
      }
    }
  }
  if (probeType === "password_confirmation") {
    await locator.fill("a");
    let invalid = await locator.evaluate((el) => !el.checkValidity()).catch(() => false);
    if (!invalid) {
      const minLen = await locator.getAttribute("minlength").catch(() => null);
      if (minLen && Number.parseInt(minLen, 10) > 1) {
        invalid = true;
      }
    }
    await locator.fill("");
    if (invalid) {
      return { pass: true, evidence: "native_password_min_length_invalid" };
    }
  }
  if (probeType === "checkbox_required") {
    const required = await locator.evaluate((el) => el.required).catch(() => false);
    if (required) {
      await locator.setChecked(false).catch(() => {});
      const invalid = await locator.evaluate((el) => !el.checkValidity()).catch(() => false);
      if (invalid) {
        return { pass: true, evidence: "native_checkbox_required_invalid" };
      }
    }
  }

  const msg = page.locator('[role="alert"], .text-destructive, [data-testid*="error" i]').first();
  if ((await msg.count()) > 0) {
    const text = (await msg.innerText().catch(() => "")).slice(0, 80);
    if (text) {
      return { pass: true, evidence: `visible_validation:${text}` };
    }
  }

  return { pass: false, evidence: `no_validation_signal:${probeType}:${tag}` };
}
