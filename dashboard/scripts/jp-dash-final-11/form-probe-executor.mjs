/**
 * Portal-aware form validation probes from semantic metadata.
 */
import { classifyFormSemantic } from "./form-semantic-classify.mjs";
import { runFormProbe, probeTypeForRow, isStrongFormEvidence } from "./form-validation-probes.mjs";

export async function discoverLocator(page, caseRow) {
  const route = String(caseRow.ROUTE || "");
  const controlLabel = String(caseRow.CONTROL_LABEL || "").trim();
  if (controlLabel === "profile-email" || /\/profile$/i.test(route)) {
    await page.getByTestId("profile-email").waitFor({ state: "visible", timeout: 25000 }).catch(() => {});
    const profileEmail = page.getByTestId("profile-email").first();
    if ((await profileEmail.count()) > 0) {
      return { loc: profileEmail, sem: classifyFormSemantic(caseRow) };
    }
  }
  if (route.includes("/admin/dashboard/staff")) {
    const controlTestId = String(caseRow.CONTROL_LABEL || "").trim();
    if (controlTestId.startsWith("staff-")) {
      if (controlTestId.includes("create")) {
        await page.getByTestId("staff-create-open").click({ timeout: 8000 }).catch(() => {});
        await page.waitForSelector(`[data-testid='${controlTestId}']`, { timeout: 12000 }).catch(() => {});
      } else {
        const manage = page.locator("[data-testid^='staff-select-']").first();
        if ((await manage.count()) > 0) {
          await manage.click({ timeout: 10000 });
          await page.waitForSelector("[data-testid='staff-editor']", { timeout: 20000 }).catch(() => {});
          await page
            .waitForSelector(`[data-testid='${controlTestId}']:not([disabled])`, { timeout: 20000 })
            .catch(() => page.waitForSelector(`[data-testid='${controlTestId}']`, { timeout: 5000 }).catch(() => {}));
        }
      }
      const byTestId = page.getByTestId(controlTestId).first();
      if ((await byTestId.count()) > 0) {
        const sem = classifyFormSemantic(caseRow);
        return { loc: byTestId, sem };
      }
    }
    const addBtn = page.getByRole("button", { name: /create staff|add staff|new staff|invite/i }).first();
    if ((await addBtn.count()) > 0) {
      await addBtn.click({ timeout: 5000 }).catch(() => {});
      await page.waitForTimeout(600);
    }
  }
  const sem = classifyFormSemantic(caseRow);
  const sel = sem.PROBE_TARGET_SELECTOR_SAFE;
  if (sel) {
    const loc = page.locator(sel).first();
    if ((await loc.count()) > 0 && (await loc.isVisible().catch(() => false))) {
      return { loc, sem };
    }
  }
  const label = String(caseRow.CONTROL_LABEL || "").slice(0, 40);
  if (label && !/login otp|security/i.test(label)) {
    const byLabel = page.getByLabel(new RegExp(label.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"), "i")).first();
    if ((await byLabel.count()) > 0) {
      return { loc: byLabel, sem };
    }
  }
  const emailGlobal = page.locator("input[type='email']:visible").first();
  if ((await emailGlobal.count()) > 0) {
    return { loc: emailGlobal, sem: { ...sem, PROBE_TARGET_KIND: "input_email" } };
  }
  const textareaGlobal = page.locator("textarea:visible").first();
  if ((await textareaGlobal.count()) > 0 && sem.PROBE_TARGET_KIND === "textarea") {
    return { loc: textareaGlobal, sem };
  }
  const form = page.locator("form:visible").first();
  if ((await form.count()) > 0) {
    const email = form.locator("input[type='email']:visible").first();
    if ((await email.count()) > 0) {
      return { loc: email, sem: { ...sem, PROBE_TARGET_KIND: "input_email" } };
    }
    const any = form.locator("input:visible, textarea:visible, select:visible").first();
    if ((await any.count()) > 0) {
      return { loc: any, sem };
    }
  }
  const otp = page
    .locator("input[autocomplete='one-time-code'], input[name*='otp' i], input[inputmode='numeric']")
    .first();
  if ((await otp.count()) > 0) {
    return { loc: otp, sem };
  }
  return { loc: null, sem };
}

export async function executeFormValidationProbe(page, caseRow) {
  const { loc, sem } = await discoverLocator(page, caseRow);
  if (!loc) {
    return {
      ok: false,
      triage: "CERT_TOOLING_DEFECT",
      evidence: "probe_target_not_found",
      signal: "",
      sideEffect: "NO",
    };
  }
  const inputType = await loc.getAttribute("type").catch(() => "");
  const probeType =
    sem.PROBE_TARGET_KIND === "input_email" || inputType === "email"
      ? "email_format"
      : sem.PROBE_TARGET_KIND === "input_password" || inputType === "password"
        ? "password_confirmation"
        : sem.PROBE_TARGET_KIND === "textarea"
          ? "textarea_required"
          : sem.SEMANTIC_TYPE === "LOGIN_AUTH_SHELL"
            ? "text_required"
            : probeTypeForRow(caseRow);
  const outcome = await runFormProbe(page, probeType, loc);
  if (outcome.pass && isStrongFormEvidence(outcome.evidence)) {
    return {
      ok: true,
      triage: "PASS",
      evidence: outcome.evidence,
      signal: outcome.evidence.split(":")[0],
      sideEffect: "NO",
      invalidApplied: "YES",
    };
  }
  if (/disabled_control_by_design/i.test(outcome.evidence || "")) {
    return {
      ok: true,
      triage: "EXPECTED_VALIDATION_BEHAVIOR",
      evidence: outcome.evidence,
      signal: "RECOGNIZED_VALIDATION_RESPONSE",
      sideEffect: "NO",
      invalidApplied: "YES",
    };
  }
  return {
    ok: false,
    triage: /not_found/i.test(outcome.evidence || "") ? "CERT_TOOLING_DEFECT" : "WRONG_CLASSIFICATION",
    evidence: outcome.evidence || "no_validation_signal",
    signal: "",
    sideEffect: "NO",
    invalidApplied: "YES",
  };
}
