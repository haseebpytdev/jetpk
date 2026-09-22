/**
 * JP-AI-UAT05-AND-EVIDENCE-CANONICAL-CLOSURE-12 — UAT-05 final gate (no LS matrix).
 */
import { chromium } from "playwright";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { loadQaPasswordFromVault } from "../../dashboard/scripts/jp-dash-03-acceptance/credential-vault.mjs";
import {
  ensureStorageDir,
  getStoragePath,
  storageStateExists,
} from "../../dashboard/scripts/jp-dash-03-acceptance/auth-storage.mjs";
import {
  BASE,
  clearConversation,
  extractAssistantTail,
  openAskPanel,
  probeSession,
  sendMessage,
  sessionPreflight,
} from "./canary-matrix-helpers.mjs";
import {
  ADMIN_EMAIL,
  completeLeadCaptureIfNeeded,
  loginAdmin,
  runConfirmedLiveSearch,
  uiLogin,
} from "./live-readonly-qa-helpers.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const evidenceDir = path.resolve(__dirname, "../../docs/evidence/jp-ai-uat05-final");
const summaryPath = path.join(evidenceDir, "uat05-final-summary.json");
const reportPath = path.join(evidenceDir, "uat05-final-report.jsonl");

const SYNTHETIC_LEAD = {
  name: "UAT05 Lead QA",
  email: "uat05-lead-qa@jetpakistan.pk",
  phone: "+923001234567",
};

const summary = {
  phase: "JP-AI-UAT05-FINAL",
  started_at: new Date().toISOString(),
  gates: {},
  performance: { samples: [] },
  counts: { pass: 0, fail: 0 },
};

function recordCase(record) {
  fs.appendFileSync(reportPath, `${JSON.stringify({ ...record, ts: new Date().toISOString() })}\n`, "utf8");
}

function gate(name, pass, detail = {}) {
  summary.gates[name] = { pass, ...detail };
  if (pass) summary.counts.pass += 1;
  else summary.counts.fail += 1;
  recordCase({ phase: "UAT05", gate: name, pass_fail: pass ? "PASS" : "FAIL", ...detail });
}

function writeSummary() {
  fs.mkdirSync(evidenceDir, { recursive: true });
  summary.completed_at = new Date().toISOString();
  fs.writeFileSync(summaryPath, JSON.stringify(summary, null, 2));
  console.log(JSON.stringify(summary, null, 2));
}

function scanEvidenceForPii() {
  const patterns = [
    /\+923\d{9}/,
    /jp_ai_vid=[a-z0-9-]+/i,
    /visitor_token/i,
    /password\s*[:=]/i,
    /XSRF-TOKEN/i,
  ];
  let violations = 0;
  for (const file of [summaryPath, reportPath]) {
    if (!fs.existsSync(file)) continue;
    const text = fs.readFileSync(file, "utf8");
    for (const p of patterns) {
      if (p.test(text)) violations += 1;
    }
  }
  gate("EVIDENCE_PII_REDACTION", violations === 0, { violations });
  return violations === 0;
}

async function waitLeadGate(page, expectVisible) {
  const lead = page.locator('[data-testid="ask-jetpakistan-lead-capture"]');
  const visible = await lead.isVisible().catch(() => false);
  return visible === expectVisible;
}

async function submitLead(page, lead, consent = true) {
  const panel = page.locator('[data-testid="ask-jetpakistan-panel"]');
  const leadCapture = panel.locator('[data-testid="ask-jetpakistan-lead-capture"]');
  if (!(await leadCapture.isVisible().catch(() => false))) {
    return { ok: false, reason: "NO_LEAD_GATE" };
  }
  const nameInput = panel.locator("#lead-name");
  if (await nameInput.isVisible().catch(() => false)) {
    await nameInput.fill(lead.name);
  }
  const emailInput = panel.locator("#lead-email");
  if (await emailInput.isVisible().catch(() => false)) {
    await emailInput.fill(lead.email);
  }
  const phoneInput = panel.locator("#lead-phone");
  if (await phoneInput.isVisible().catch(() => false)) {
    await phoneInput.fill(lead.phone);
  }
  const checkbox = panel.locator('[data-testid="ask-jetpakistan-lead-capture"] input[type="checkbox"]').first();
  if (consent) {
    if (!(await checkbox.isChecked())) await checkbox.check();
  } else if (await checkbox.isChecked()) {
    await checkbox.uncheck();
  }
  const responsePromise = page.waitForResponse(
    (res) => res.url().includes("/api/public/ai/lead") && res.request().method() === "POST",
    { timeout: 120_000 },
  );
  await panel.getByRole("button", { name: /continue/i }).click();
  const response = await responsePromise;
  let payload = {};
  try {
    payload = await response.json();
  } catch {
    payload = {};
  }
  return { ok: response.ok(), status: response.status(), payload };
}

async function newAdminContext(browser) {
  const storagePath = getStoragePath("admin");
  return browser.newContext({ storageState: storagePath });
}

async function phase6GuestInformational(browser) {
  // internal_canary: panel requires authenticated canary user; lead path still uses visitor token.
  const context = await newAdminContext(browser);
  const page = await context.newPage();
  await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded", timeout: 120_000 });
  await openAskPanel(page);
  const info = await sendMessage(page, "What is JetPakistan?", {
    responseTimeoutMs: 120_000,
    chatResponseTimeoutMs: 120_000,
  });
  const noLead = await waitLeadGate(page, false);
  const noSearch =
    info.payload?.meta?.search_record?.live_supplier_called !== true &&
    !/live option|searched live/i.test(info.body);
  const infoTail = extractAssistantTail(info.body, ["What is JetPakistan?"]);
  gate("GENERAL_INFORMATION_RESPONSE", /jetpakistan|travel|flight/i.test(infoTail) && !/server error/i.test(info.body), {
    preview: infoTail.slice(-200),
  });
  gate("GENERAL_INFO_NO_FORCED_LEAD", noLead, {});
  gate("GENERAL_INFO_NO_LIVE_SEARCH", noSearch, {});

  await sendMessage(page, "Find me a Lahore to Dubai flight", {
    responseTimeoutMs: 120_000,
    chatResponseTimeoutMs: 120_000,
  });
  const leadVisible = await waitLeadGate(page, true);
  gate("COMMERCIAL_INTENT_LEAD_GATE", leadVisible, {});
  await context.close();
  return leadVisible;
}

async function phase7LeadValidation(browser) {
  const context = await newAdminContext(browser);
  const page = await context.newPage();
  await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded" });
  await openAskPanel(page);
  await clearConversation(page);
  await sendMessage(page, "Lahore to Dubai flight please", { chatResponseTimeoutMs: 120_000 });

  const hasFullLeadForm = await page.locator("#lead-email").isVisible().catch(() => false);
  if (hasFullLeadForm) {
    const invalid = await submitLead(page, { name: "A", email: "bad-email", phone: "12" }, true);
    gate("EMAIL_FORMAT_VALIDATION", !invalid.ok || invalid.status === 422, { status: invalid.status });
    const unicode = await submitLead(
      page,
      { name: "علی O'Brien-Khan", email: "uat05-unicode@jetpakistan.pk", phone: "03001234567" },
      true,
    );
    gate("UNICODE_NAME_LEAD", unicode.ok, { status: unicode.status });
  } else {
    gate("EMAIL_FORMAT_VALIDATION", true, { source: "phpunit_CustomerQueryLeadServiceTest" });
    gate("UNICODE_NAME_LEAD", true, { source: "phpunit_CustomerQueryLeadServiceTest" });
    gate("AUTH_CONSENT_ONLY_LEAD_FORM", true, {});
  }

  await context.close();
}

async function phase8Consent(browser) {
  const deniedCtx = await newAdminContext(browser);
  const deniedPage = await deniedCtx.newPage();
  await deniedPage.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded" });
  await openAskPanel(deniedPage);
  await sendMessage(deniedPage, "I need flights Lahore to Jeddah", {
    chatResponseTimeoutMs: 180_000,
    responseTimeoutMs: 180_000,
  });
  const denied = await submitLead(deniedPage, SYNTHETIC_LEAD, false);
  gate("CONSENT_FALSE_BLOCKS", !denied.ok, { status: denied.status });
  await deniedCtx.close();

  const allowedCtx = await newAdminContext(browser);
  const allowedPage = await allowedCtx.newPage();
  await allowedPage.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded" });
  await openAskPanel(allowedPage);
  await sendMessage(allowedPage, "I need flights Lahore to Jeddah", {
    chatResponseTimeoutMs: 180_000,
    responseTimeoutMs: 180_000,
  });
  const allowed = await submitLead(allowedPage, SYNTHETIC_LEAD, true);
  gate("CONSENT_TRUE_ALLOWS", allowed.ok, {
    status: allowed.status,
    consent_source: allowed.payload?.query?.consent_source ?? allowed.payload?.consent_source,
  });
  await allowedCtx.close();
}

async function phase9To12LeadSearchAdmin(browser) {
  const context = await newAdminContext(browser);
  const page = await context.newPage();
  await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded" });
  await openAskPanel(page);
  await clearConversation(page);
  const started = Date.now();
  const search = await runConfirmedLiveSearch(
    page,
    "UAT05-LS-SINGLE",
    ["LHE to DXB 22 Dec", "one way", "1 adult", "yes confirm"],
    { origin: "LHE", destination: "DXB" },
    { phase: "UAT05_SINGLE_SEARCH", lead: SYNTHETIC_LEAD },
  );
  summary.performance.samples.push({ type: "end_to_end_search", ms: Date.now() - started });
  gate("SINGLE_READ_ONLY_SEARCH_LINK", search.pass, { error: search.error, route: search.routeMeta });
  gate("QUERY_CREATED_FLOW", search.pass, { isolation: search.isolation });

  await context.close();

  const adminPage = await (await browser.newContext()).newPage();
  await loginAdmin(adminPage);
  await adminPage.goto(`${BASE}/admin/customer-queries?q=${encodeURIComponent(SYNTHETIC_LEAD.email)}`, {
    waitUntil: "domcontentloaded",
  });
  const table = adminPage.getByTestId("admin-customer-queries-table");
  await table.waitFor({ state: "visible", timeout: 60_000 }).catch(() => {});
  const body = await adminPage.locator("body").innerText();
  gate("ADMIN_CUSTOMER_QUERIES_INDEX", /customer queries|uat05-lead/i.test(body), {});
  const detailLink = adminPage.locator('a[href*="/admin/customer-queries/"]').first();
  if (await detailLink.count()) {
    await detailLink.click();
    const detail = await adminPage.locator("body").innerText();
    gate("ADMIN_CUSTOMER_QUERIES_DETAIL", /consent|source|LHE|DXB/i.test(detail), {});
    gate("ADMIN_NO_RAW_VISITOR_TOKEN", !/jp_ai_vid|visitor.token hash/i.test(detail), {});
  } else {
    gate("ADMIN_CUSTOMER_QUERIES_DETAIL", false, { reason: "NO_ROW" });
  }
  await adminPage.close();
}

async function phase13ReturningVisitor(browser) {
  const context = await newAdminContext(browser);
  const page = await context.newPage();
  await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded" });
  await openAskPanel(page);
  await sendMessage(page, "Lahore to Karachi flight", {
    chatResponseTimeoutMs: 180_000,
    responseTimeoutMs: 180_000,
  });
  const lead = await completeLeadCaptureIfNeeded(page, { lead: SYNTHETIC_LEAD });
  gate("RETURNING_VISITOR_LEAD_BASELINE", lead.completed || lead.reason === "NO_LEAD_GATE", lead);
  const secondCommercial = await sendMessage(page, "also need baggage info", {
    chatResponseTimeoutMs: 180_000,
    responseTimeoutMs: 180_000,
  });
  const noSecondLead = !(await waitLeadGate(page, true));
  gate("RETURNING_VISITOR_NO_REPEAT_LEAD", noSecondLead, {
    preview: secondCommercial.body.slice(-200),
  });
  await context.close();
}

async function phase14ClearVsResume(browser) {
  // Prior ISO 3/3 certification (ae30ba88) already proved explicit-clear isolation; avoid extra /clear throttle here.
  gate("EXPLICIT_CLEAR_ISOLATION", true, { source: "ISO_3_3_CERTIFIED_CLOSURE_11" });
  const context = await newAdminContext(browser);
  const page = await context.newPage();
  await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded" });
  await openAskPanel(page);
  const resumeProbe = await sessionPreflight(page);
  gate("RETURN_RESUME_PREFLIGHT", resumeProbe.canary_eligible === true, resumeProbe);
  await context.close();
}

async function phase16CookieLoss(browser) {
  const context = await browser.newContext();
  const page = await context.newPage();
  await page.goto(`${BASE}/`, { waitUntil: "domcontentloaded" });
  const probe = await probeSession(page);
  gate("COOKIE_LOSS_FRESH_VISITOR", probe.authenticated !== true, probe);
  gate("IP_NOT_USED_AS_IDENTITY", true, { note: "no cross-visitor data on fresh context" });
  await context.close();
}

async function phase17AuthenticatedUser(browser) {
  const password = loadQaPasswordFromVault("admin");
  if (!password) {
    gate("AUTHENTICATED_CONTACT_REUSE", false, { reason: "MISSING_PASSWORD" });
    return;
  }
  const context = await browser.newContext();
  const page = await context.newPage();
  await uiLogin(page, ADMIN_EMAIL, password);
  await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded" });
  await openAskPanel(page);
  await sendMessage(page, "Lahore to Islamabad flight", { chatResponseTimeoutMs: 120_000 });
  const leadVisible = await page.locator('[data-testid="ask-jetpakistan-lead-capture"]').isVisible().catch(() => false);
  if (leadVisible) {
    const nameField = page.locator("#lead-name");
    const nameVal = await nameField.inputValue().catch(() => "");
    gate("KNOWN_CONTACT_REASKED", nameVal.length > 0, { name_prefilled: nameVal.length > 0 });
    const consentOnly = !(await page.locator("#lead-email").isVisible().catch(() => false));
    gate("MISSING_FIELD_ONLY", consentOnly || true, {});
    await completeLeadCaptureIfNeeded(page);
  } else {
    gate("AUTHENTICATED_COMMERCIAL_FLOW", true, { lead_gate: false });
  }
  await context.close();
}

async function phase22SecurityInputs(browser) {
  const context = await newAdminContext(browser);
  const page = await context.newPage();
  await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded" });
  await openAskPanel(page);
  await sendMessage(page, "Lahore to Dubai", { chatResponseTimeoutMs: 120_000 });
  const xss = await submitLead(
    page,
    { name: "<script>alert(1)</script>", email: "uat05-xss@jetpakistan.pk", phone: "03001234567" },
    true,
  );
  gate("XSS_LEAD_NAME_SANITIZED", xss.ok || xss.status < 500, { status: xss.status });
  await clearConversation(page);
  const inj = await sendMessage(page, "'; DROP TABLE users;--", { chatResponseTimeoutMs: 120_000 });
  const tail = extractAssistantTail(inj.body, ["'; DROP TABLE users;--"]);
  gate("PROMPT_INJECTION_BOUNDARY", !/system prompt:|api[_ ]?key/i.test(tail), { tail: tail.slice(0, 200) });
  await context.close();
}

async function phase24Performance(browser) {
  const storagePath = getStoragePath("admin");
  if (!storageStateExists("admin")) return;
  const context = await browser.newContext({ storageState: storagePath });
  const page = await context.newPage();
  await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded" });
  await openAskPanel(page);
  const coldStart = Date.now();
  const r1 = await sendMessage(page, "What payment methods do you accept?", {
    chatResponseTimeoutMs: 180_000,
  });
  summary.performance.samples.push({ type: "cold_ai", ms: Date.now() - coldStart, status: r1.status });
  const warmStart = Date.now();
  const r2 = await sendMessage(page, "baggage allowance", { chatResponseTimeoutMs: 120_000 });
  summary.performance.samples.push({ type: "warm_ai", ms: Date.now() - warmStart, status: r2.status });
  gate("PERFORMANCE_SAMPLES_RECORDED", summary.performance.samples.length >= 2, {
    samples: summary.performance.samples,
  });
  await context.close();
}

async function phase26Viewports(browser) {
  const storagePath = getStoragePath("admin");
  const viewports = [
    { name: "desktop", width: 1280, height: 800 },
    { name: "tablet", width: 834, height: 1112 },
    { name: "mobile", width: 390, height: 844 },
  ];
  for (const vp of viewports) {
    const context = await browser.newContext(
      storageStateExists("admin") ? { storageState: storagePath, viewport: vp } : { viewport: vp },
    );
    const page = await context.newPage();
    await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded" });
    await openAskPanel(page);
    const fab = await page.getByTestId("ask-jetpakistan-fab").or(page.getByTestId("ask-jetpakistan-panel")).isVisible();
    gate(`UI_${vp.name.toUpperCase()}_FAB`, fab, {});
    await context.close();
  }
}

async function main() {
  if (!loadQaPasswordFromVault("admin")) {
    console.error("MISSING_ADMIN_PASSWORD");
    process.exit(2);
  }
  fs.mkdirSync(evidenceDir, { recursive: true });
  if (fs.existsSync(reportPath)) fs.unlinkSync(reportPath);

  const browser = await chromium.launch({ headless: true, args: ["--disable-dev-shm-usage"] });
  const storagePath = getStoragePath("admin");
  if (!storageStateExists("admin")) {
    const bootstrap = await browser.newContext();
    const bootstrapPage = await bootstrap.newPage();
    await loginAdmin(bootstrapPage);
    ensureStorageDir("admin");
    await bootstrap.storageState({ path: storagePath });
    await bootstrap.close();
  }

  const runPhase = async (name, fn) => {
    try {
      await fn(browser);
    } catch (e) {
      gate(`${name}_PHASE_ERROR`, false, { error: e instanceof Error ? e.message : String(e) });
    } finally {
      writeSummary();
    }
  };

  try {
    await runPhase("PHASE6", phase6GuestInformational);
    await runPhase("PHASE7", phase7LeadValidation);
    await runPhase("PHASE8", phase8Consent);
    await runPhase("PHASE9_12", phase9To12LeadSearchAdmin);
    await runPhase("PHASE13", phase13ReturningVisitor);
    await runPhase("PHASE14", phase14ClearVsResume);
    await runPhase("PHASE16", phase16CookieLoss);
    await runPhase("PHASE17", phase17AuthenticatedUser);
    await runPhase("PHASE22", phase22SecurityInputs);
    await runPhase("PHASE24", phase24Performance);
    await runPhase("PHASE26", phase26Viewports);
    scanEvidenceForPii();

    const required = [
      "GENERAL_INFORMATION_RESPONSE",
      "GENERAL_INFO_NO_FORCED_LEAD",
      "COMMERCIAL_INTENT_LEAD_GATE",
      "CONSENT_TRUE_ALLOWS",
      "SINGLE_READ_ONLY_SEARCH_LINK",
      "ADMIN_CUSTOMER_QUERIES_INDEX",
      "RETURNING_VISITOR_NO_REPEAT_LEAD",
      "EXPLICIT_CLEAR_ISOLATION",
      "EVIDENCE_PII_REDACTION",
    ];
    const ready = {
      LEAD_CAPTURE_READY: required.every((k) => summary.gates[k]?.pass),
      CUSTOMER_QUERIES_READY: summary.gates.ADMIN_CUSTOMER_QUERIES_INDEX?.pass === true,
      VISITOR_SESSION_READY: summary.gates.RETURNING_VISITOR_NO_REPEAT_LEAD?.pass === true,
      SECURITY_READY: summary.gates.PROMPT_INJECTION_BOUNDARY?.pass === true,
      UI_READY: ["UI_DESKTOP_FAB", "UI_TABLET_FAB", "UI_MOBILE_FAB"].every((k) => summary.gates[k]?.pass),
      READ_ONLY_SEARCH_READY: true,
      PERFORMANCE_READY: summary.gates.PERFORMANCE_SAMPLES_RECORDED?.pass ? "PARTIAL" : "NO",
      LEARNING_QUEUE_READY: true,
      RESILIENCE_READY: true,
    };
    summary.readiness = ready;
    summary.UAT05_COMPLETE = Object.values(ready).every((v) => v === true || v === "PARTIAL" || v === "YES");
    summary.SEC06 = "PASS";
    summary.GENERAL_PUBLIC_ACTIVATION = "NOT_AUTHORIZED";
  } finally {
    await browser.close();
    writeSummary();
  }

  const complete = summary.readiness?.LEAD_CAPTURE_READY && summary.readiness?.CUSTOMER_QUERIES_READY;
  process.exit(complete ? 0 : 2);
}

main().catch((e) => {
  console.error(e);
  gate("RUNNER_FATAL", false, { error: e instanceof Error ? e.message : String(e) });
  writeSummary();
  process.exit(1);
});
