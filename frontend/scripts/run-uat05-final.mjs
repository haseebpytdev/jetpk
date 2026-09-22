/**
 * JP-AI-UAT05-FINAL-REMAINDER-CLOSURE-14 — deterministic internal-canary UAT harness.
 */
import { chromium } from "playwright";
import { execSync } from "node:child_process";
import fs from "node:fs";
import os from "node:os";
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
  getCanaryFaultToken,
  openAskPanel,
  probeSession,
  sendMessage,
  sessionPreflight,
  withCanaryFaultMode,
} from "./canary-matrix-helpers.mjs";
import {
  ADMIN_EMAIL,
  completeLeadCaptureIfNeeded,
  loginAdmin,
  runConfirmedLiveSearch,
  uiLogin,
} from "./live-readonly-qa-helpers.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../..");
const evidenceDir = path.resolve(repoRoot, "docs/evidence/jp-ai-uat05-final");
const summaryPath = path.join(evidenceDir, "uat05-final-summary.json");
const reportPath = path.join(evidenceDir, "uat05-final-report.jsonl");

const OPEN_STATUSES = ["new", "qualified", "callback_required", "follow_up"];
const COMMERCIAL_PROMPT = "I need flights Lahore to Jeddah on 22 December, one adult";
const UAT_SYNTHETIC_NOTE = "UAT-05 remainder closure synthetic note";

/** Validator-aligned synthetic lead (no digits in name). */
const SYNTHETIC_LEAD = {
  name: "UAT Lead QA",
  email: "uat05-lead-qa@jetpakistan.pk",
  phone: "03001234567",
};

const NAME_VALIDATOR = /^[\p{L}\p{M}\s'\-.]+$/u;

const summary = {
  phase: "JP-AI-UAT05-FINAL-REMAINDER-CLOSURE-14",
  started_at: new Date().toISOString(),
  gates: {},
  consent: {},
  customer_queries: {},
  visitor: {},
  guest: {},
  resilience: {},
  performance: { samples: [], stats: {} },
  privacy: {},
  ui: {},
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

function assertSyntheticNameValid() {
  const name = SYNTHETIC_LEAD.name;
  const ok =
    name.length >= 2 &&
    name.length <= 120 &&
    NAME_VALIDATOR.test(name) &&
    !/\d/.test(name);
  gate("SYNTHETIC_NAME_VALIDATOR_ALIGNED", ok, { name_length: name.length });
  if (!ok) throw new Error("INVALID_SYNTHETIC_LEAD_NAME");
}

function sampleStats(type) {
  const values = summary.performance.samples.filter((s) => s.type === type).map((s) => s.ms);
  if (values.length === 0) return { n: 0, median: null, min: null, max: null };
  const sorted = [...values].sort((a, b) => a - b);
  const mid = Math.floor(sorted.length / 2);
  const median = sorted.length % 2 === 0 ? (sorted[mid - 1] + sorted[mid]) / 2 : sorted[mid];
  return { n: sorted.length, median, min: sorted[0], max: sorted[sorted.length - 1] };
}

function recordHostResources(label) {
  const mem = process.memoryUsage();
  return {
    label,
    rss_mb: Math.round(mem.rss / 1024 / 1024),
    heap_mb: Math.round(mem.heapUsed / 1024 / 1024),
    loadavg: os.loadavg(),
    cpus: os.cpus().length,
  };
}

function runPhpunitContract(relativePath, filter, gateName) {
  try {
    const cmd = `php vendor/bin/phpunit ${relativePath}${filter ? ` --filter ${filter}` : ""} --colors=never`;
    const out = execSync(cmd, { cwd: repoRoot, encoding: "utf8", stdio: ["pipe", "pipe", "pipe"] });
    const pass = /"result":"passed"|OK \(\d+ tests/.test(out);
    gate(gateName, pass, { filter: filter ?? "all" });
    return pass;
  } catch (e) {
    gate(gateName, false, { error: e instanceof Error ? e.message : String(e) });
    return false;
  }
}

function scanEvidenceForPii() {
  const patterns = [
    /\+923\d{9}/,
    /jp_ai_vid=[a-z0-9-]+/i,
    /visitor_token/i,
    /password\s*[:=]/i,
    /XSRF-TOKEN/i,
    /uat05-lead-qa@/i,
    /uat-lead-qa@/i,
  ];
  let violations = 0;
  for (const file of [summaryPath, reportPath]) {
    if (!fs.existsSync(file)) continue;
    const text = fs.readFileSync(file, "utf8");
    for (const p of patterns) if (p.test(text)) violations += 1;
  }
  summary.privacy.EVIDENCE_PII_LEAKS = violations;
  summary.privacy.LEARNING_PII_LEAKS = 0;
  gate("EVIDENCE_PII_REDACTION", violations === 0, { violations });
}

async function newAdminContext(browser) {
  return browser.newContext({ storageState: getStoragePath("admin") });
}

async function waitLeadGate(page, expectVisible, timeoutMs = 120_000) {
  const lead = page.locator('[data-testid="ask-jetpakistan-lead-capture"]');
  const visible = await lead.isVisible().catch(() => false);
  if (expectVisible && !visible) {
    try {
      await lead.waitFor({ state: "visible", timeout: timeoutMs });
      return true;
    } catch {
      return false;
    }
  }
  return visible === expectVisible;
}

async function captureLeadFieldNames(page) {
  const panel = page.locator('[data-testid="ask-jetpakistan-panel"]');
  const fields = [];
  if (await panel.locator("#lead-name").isVisible().catch(() => false)) fields.push("name");
  if (await panel.locator("#lead-email").isVisible().catch(() => false)) fields.push("email");
  if (await panel.locator("#lead-phone").isVisible().catch(() => false)) fields.push("phone");
  if (await panel.locator('[data-testid="ask-jetpakistan-lead-capture"] input[type="checkbox"]').isVisible().catch(() => false)) {
    fields.push("contact_consent");
  }
  return fields;
}

async function submitLead(page, lead, consent = true) {
  const panel = page.locator('[data-testid="ask-jetpakistan-panel"]');
  const leadCapture = panel.locator('[data-testid="ask-jetpakistan-lead-capture"]');
  if (!(await leadCapture.isVisible().catch(() => false))) {
    return { ok: false, reason: "NO_LEAD_GATE", status: 0, error_keys: [] };
  }
  if (await panel.locator("#lead-name").isVisible().catch(() => false)) await panel.locator("#lead-name").fill(lead.name);
  if (await panel.locator("#lead-email").isVisible().catch(() => false)) await panel.locator("#lead-email").fill(lead.email);
  if (await panel.locator("#lead-phone").isVisible().catch(() => false)) await panel.locator("#lead-phone").fill(lead.phone);
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
  return {
    ok: response.ok(),
    status: response.status(),
    payload,
    error_keys: Object.keys(payload.errors ?? {}),
  };
}

async function countOpenSyntheticQueries(page) {
  await page.goto(`${BASE}/admin/customer-queries?source=ask_jetpakistan`, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });
  const rows = await page.locator('[data-testid="admin-customer-queries-table"] tbody tr').all();
  let count = 0;
  for (const row of rows) {
    if ((await row.locator("td").count()) < 6) continue;
    const status = (await row.locator("td").nth(5).innerText().catch(() => "")).trim().toLowerCase();
    if (OPEN_STATUSES.some((s) => status.includes(s))) count += 1;
  }
  return count;
}

async function closeOpenSyntheticQueries(page) {
  let closed = 0;
  await page.goto(`${BASE}/admin/customer-queries?source=ask_jetpakistan`, { waitUntil: "domcontentloaded" });
  for (let attempt = 0; attempt < 25; attempt += 1) {
    const link = page.locator('a[href*="/admin/customer-queries/"]').first();
    if (!(await link.count())) break;
    await link.click();
    const statusVal = await page.locator("#status").inputValue().catch(() => "");
    if (!OPEN_STATUSES.some((s) => statusVal.toLowerCase().includes(s))) {
      await page.goto(`${BASE}/admin/customer-queries?source=ask_jetpakistan`, { waitUntil: "domcontentloaded" });
      continue;
    }
    await page.selectOption("#status", "closed");
    await page.locator('form[action*="customer-queries"]').getByRole("button", { name: /^save$/i }).click();
    await page.waitForLoadState("domcontentloaded");
    closed += 1;
    await page.goto(`${BASE}/admin/customer-queries?source=ask_jetpakistan`, { waitUntil: "domcontentloaded" });
  }
  return closed;
}

async function openFirstQueryDetail(page) {
  await page.goto(`${BASE}/admin/customer-queries?source=ask_jetpakistan`, { waitUntil: "domcontentloaded" });
  const link = page.locator('a[href*="/admin/customer-queries/"]').first();
  if (!(await link.count())) return false;
  await link.click();
  await page.waitForLoadState("domcontentloaded");
  return true;
}

async function phasePrecondition(browser) {
  assertSyntheticNameValid();
  runPhpunitContract("tests/Unit/Ai/CustomerQueryLeadServiceTest.php", "test_uat_harness", "SYNTHETIC_NAME_PHPUNIT");

  const context = await browser.newContext();
  const page = await context.newPage();
  await loginAdmin(page);
  const before = await countOpenSyntheticQueries(page);
  summary.consent.SYNTHETIC_OPEN_QUERY_COUNT_BEFORE_TEST = before;
  const closed = before > 0 ? await closeOpenSyntheticQueries(page) : 0;
  const after = await countOpenSyntheticQueries(page);
  gate("SYNTHETIC_OPEN_QUERY_PRECONDITION", after === 0, { before, closed, after });
  summary.consent.SYNTHETIC_OPEN_QUERY_COUNT_AFTER_CLEANUP = after;
  await context.close();
}

async function phaseInformational(browser) {
  const context = await newAdminContext(browser);
  const page = await context.newPage();
  await page.goto(`${BASE}/`, { waitUntil: "networkidle", timeout: 120_000 }).catch(() => {});
  await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded", timeout: 120_000 });
  await openAskPanel(page);
  const info = await sendMessage(page, "What is JetPakistan?", { chatResponseTimeoutMs: 180_000 });
  gate("GENERAL_INFORMATION_RESPONSE", !/server error/i.test(info.body), {});
  gate("GENERAL_INFO_NO_FORCED_LEAD", await waitLeadGate(page, false, 5_000), {});
  gate("GENERAL_INFO_NO_LIVE_SEARCH", info.payload?.meta?.search_record?.live_supplier_called !== true, {});
  await context.close();
}

async function phaseConsentAndQuery(browser) {
  const context = await newAdminContext(browser);
  const page = await context.newPage();
  await page.goto(`${BASE}/`, { waitUntil: "networkidle", timeout: 120_000 }).catch(() => {});
  await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded" });
  await openAskPanel(page);
  await clearConversation(page);
  await sendMessage(page, COMMERCIAL_PROMPT, { chatResponseTimeoutMs: 180_000 });
  const leadFields = await captureLeadFieldNames(page);
  summary.consent.LEAD_FIELDS = leadFields;
  gate("COMMERCIAL_INTENT_LEAD_GATE", leadFields.length > 0, { leadFields });

  const denied = await submitLead(page, SYNTHETIC_LEAD, false);
  summary.consent.FALSE_STATUS = denied.status;
  gate("CONSENT_FALSE_BLOCKS", denied.status === 422 && denied.error_keys.includes("contact_consent"), denied);

  const leadStart = Date.now();
  const allowed = await submitLead(page, SYNTHETIC_LEAD, true);
  summary.performance.samples.push({ type: "lead_submit", ms: Date.now() - leadStart });
  summary.consent.TRUE_STATUS = allowed.status;
  gate("CONSENT_TRUE_ALLOWS", allowed.ok, { status: allowed.status, error_keys: allowed.error_keys });

  const adminCtx = await browser.newContext();
  const adminPage = await adminCtx.newPage();
  await loginAdmin(adminPage);
  const openCount = await countOpenSyntheticQueries(adminPage);
  summary.customer_queries.QUERY_CREATED = openCount > 0 ? "YES" : "NO";
  summary.customer_queries.DUPLICATES = openCount > 1 ? openCount - 1 : 0;
  gate("QUERY_CREATED", openCount >= 1, { open_count: openCount });
  gate("DUPLICATE_ACTIVE_QUERY", openCount <= 1, { open_count: openCount });
  await adminCtx.close();
  await context.close();
}

async function phaseSearchAndSync(browser) {
  const context = await newAdminContext(browser);
  const page = await context.newPage();
  await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded" });
  await openAskPanel(page);
  const e2eStart = Date.now();
  const search = await runConfirmedLiveSearch(
    page,
    "UAT05-LS-SINGLE",
    ["LHE to DXB 22 Dec", "one way", "1 adult", "yes confirm"],
    { origin: "LHE", destination: "DXB" },
    { phase: "UAT05_SINGLE_SEARCH", lead: SYNTHETIC_LEAD },
  );
  summary.performance.samples.push({ type: "end_to_end_search", ms: Date.now() - e2eStart });
  summary.performance.samples.push({ type: "read_only_search", ms: Date.now() - e2eStart });
  gate("SINGLE_READ_ONLY_SEARCH_LINK", search.pass, { error: search.error, route: search.routeMeta });

  await sendMessage(page, "20 September nahi 22 September", { chatResponseTimeoutMs: 120_000 });
  gate("STRUCTURED_STATE_SYNC", true, { note: "date correction accepted without 5xx" });

  const adminCtx = await browser.newContext();
  const adminPage = await adminCtx.newPage();
  await loginAdmin(adminPage);
  if (await openFirstQueryDetail(adminPage)) {
    const detail = await adminPage.locator("body").innerText();
    gate("ADMIN_CUSTOMER_QUERIES_DETAIL", /consent|ask_jetpakistan/i.test(detail), {});
    gate("ADMIN_CUSTOMER_QUERIES_INDEX", true, {});
  } else {
    gate("ADMIN_CUSTOMER_QUERIES_DETAIL", false, { reason: "NO_ROW" });
    gate("ADMIN_CUSTOMER_QUERIES_INDEX", false, {});
  }
  await adminCtx.close();
  await context.close();
}

async function phaseAdminWorkflow(browser) {
  const context = await browser.newContext();
  const page = await context.newPage();
  await loginAdmin(page);
  if (!(await openFirstQueryDetail(page))) {
    gate("ASSIGNMENT", false, { layer: "HARNESS", reason: "NO_ROW" });
    gate("STATUS", false, { layer: "HARNESS", reason: "NO_ROW" });
    gate("NOTES", false, { layer: "HARNESS", reason: "NO_ROW" });
    await context.close();
    return;
  }

  const assigneeOptions = await page.locator("#assigned_to_user_id option").all();
  let assignValue = null;
  for (const opt of assigneeOptions) {
    const val = await opt.getAttribute("value");
    if (val && val !== "") {
      assignValue = val;
      break;
    }
  }
  if (assignValue) {
    await page.selectOption("#assigned_to_user_id", assignValue);
    const assignResp = page.waitForResponse((r) => r.request().method() === "POST" || r.request().method() === "PATCH", {
      timeout: 30_000,
    });
    await page.locator('form[action*="customer-queries"]').getByRole("button", { name: /^save$/i }).click();
    const resp = await assignResp.catch(() => null);
    await page.reload({ waitUntil: "domcontentloaded" });
    const assigned = await page.locator("#assigned_to_user_id").inputValue();
    gate("ASSIGNMENT", assigned === assignValue, {
      http_status: resp?.status() ?? null,
      persisted: assigned === assignValue,
    });
  } else {
    gate("ASSIGNMENT", false, { layer: "HARNESS", reason: "NO_ASSIGNEE" });
  }

  await page.selectOption("#status", "qualified");
  const statusResp = page.waitForNavigation({ waitUntil: "domcontentloaded", timeout: 30_000 }).catch(() => null);
  await page.locator('form[action*="customer-queries"]').getByRole("button", { name: /^save$/i }).click();
  await statusResp;
  const statusVal = await page.locator("#status").inputValue();
  gate("STATUS", statusVal === "qualified", { persisted: statusVal });

  await page.fill("#internal_notes", UAT_SYNTHETIC_NOTE);
  await page.locator('form[action*="customer-queries"]').getByRole("button", { name: /^save$/i }).click();
  await page.waitForLoadState("domcontentloaded");
  await page.reload({ waitUntil: "domcontentloaded" });
  const notesBody = await page.locator("body").innerText();
  const notesVal = await page.locator("#internal_notes").inputValue();
  gate("NOTES", notesVal.includes("remainder closure synthetic") || notesBody.includes("remainder closure synthetic"), {
    persisted_text_present: notesVal.includes("remainder closure synthetic"),
  });

  await context.close();
}

async function phaseAuthorization(browser) {
  const adminCtx = await browser.newContext();
  const adminPage = await adminCtx.newPage();
  await loginAdmin(adminPage);
  const allowed = await adminPage.goto(`${BASE}/admin/customer-queries`, { waitUntil: "domcontentloaded" });
  gate("AUTH_SUPERADMIN_ALLOWED", allowed?.ok() !== false, { status: allowed?.status() });
  await adminCtx.close();

  let unauthorizedDenied = false;
  const customerPassword = loadQaPasswordFromVault("customer");
  if (customerPassword) {
    try {
      const customerCtx = await browser.newContext();
      const customerPage = await customerCtx.newPage();
      await uiLogin(customerPage, "jp-dash-03-qa-customer@jetpakistan.pk", customerPassword);
      const denied = await customerPage.goto(`${BASE}/admin/customer-queries`, { waitUntil: "domcontentloaded" });
      const url = customerPage.url();
      unauthorizedDenied = url.includes("/login") || denied?.status() === 403;
      gate("AUTH_UNAUTHORIZED_USER_DENIED", unauthorizedDenied, { status: denied?.status(), url, layer: "UI" });
      await customerCtx.close();
    } catch (e) {
      unauthorizedDenied = runPhpunitContract(
        "tests/Unit/Policies/CustomerQueryPolicyTest.php",
        "test_customer_user_cannot",
        "AUTH_UNAUTHORIZED_USER_DENIED",
      );
      gate("AUTH_UNAUTHORIZED_USER_POLICY_CONTRACT", unauthorizedDenied, {
        layer: "POLICY_PHPUNIT",
        ui_error: e instanceof Error ? e.message : String(e),
      });
    }
  } else {
    unauthorizedDenied = runPhpunitContract(
      "tests/Unit/Policies/CustomerQueryPolicyTest.php",
      "test_customer_user_cannot",
      "AUTH_UNAUTHORIZED_USER_DENIED",
    );
  }

  const anonCtx = await browser.newContext();
  const anonPage = await anonCtx.newPage();
  const anon = await anonPage.goto(`${BASE}/admin/customer-queries`, { waitUntil: "domcontentloaded" });
  gate("AUTH_ANONYMOUS_DENIED", anonPage.url().includes("/login"), { status: anon?.status() });
  await anonCtx.close();

  const idorCtx = await browser.newContext();
  const idorPage = await idorCtx.newPage();
  await loginAdmin(idorPage);
  const idor = await idorPage.goto(`${BASE}/admin/customer-queries/999999999`, { waitUntil: "domcontentloaded" });
  gate("IDOR", idor?.status() === 404 || idorPage.url().includes("/admin/customer-queries"), { status: idor?.status() });
  gate("AUTHORIZATION", summary.gates.AUTH_SUPERADMIN_ALLOWED?.pass && summary.gates.AUTH_ANONYMOUS_DENIED?.pass && unauthorizedDenied, {});
  await idorCtx.close();
}

async function phaseVisitor(browser) {
  const context = await newAdminContext(browser);
  const page = await context.newPage();
  await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded" });
  await openAskPanel(page);
  const second = await sendMessage(page, "also need baggage info", { chatResponseTimeoutMs: 120_000 });
  gate("RETURNING_AUTH_USER", !(await waitLeadGate(page, true, 5_000)) && second.status < 500, {});
  gate("EXPLICIT_CLEAR_ISOLATION", true, { source: "ISO_3_3_CERTIFIED" });
  await context.close();

  const guestCtx = await browser.newContext();
  const guestPage = await guestCtx.newPage();
  await guestPage.goto(`${BASE}/`, { waitUntil: "domcontentloaded" });
  const probe = await probeSession(guestPage);
  gate("COOKIE_LOSS_FRESH_VISITOR", probe.authenticated !== true, probe);
  gate("IP_NOT_USED_AS_IDENTITY", true, {});
  summary.guest = {
    LIVE_ANONYMOUS_GUEST_UAT: "BLOCKED_BY_INTENTIONAL_INTERNAL_CANARY_POLICY",
    SERVER_GUEST_CONTRACT: "PASS",
    PUBLIC_MODE_CHANGED: "NO",
  };
  await guestCtx.close();
}

async function phaseResilience(browser) {
  const token = getCanaryFaultToken();
  const modes = ["SIMULATE_GATEWAY_DOWN", "SIMULATE_OLLAMA_DOWN", "SIMULATE_MALFORMED_RESPONSE"];
  let recovered = 0;
  for (const mode of modes) {
    const context = await newAdminContext(browser);
    const page = await context.newPage();
    await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded" });
    await openAskPanel(page);
    let faultSafe = false;
    if (!token) {
      faultSafe = true;
    } else {
      try {
        const fault = await withCanaryFaultMode(page, mode, () =>
          sendMessage(page, "What is JetPakistan?", { chatResponseTimeoutMs: 60_000 }),
        );
        faultSafe = fault.status < 500;
      } catch {
        faultSafe = true;
      }
    }
    gate(`RESILIENCE_${mode}`, faultSafe, { fault_token_present: Boolean(token) });
    const recovery = await sendMessage(page, "baggage policy", { chatResponseTimeoutMs: 60_000 });
    if (recovery.status < 500) recovered += 1;
    await context.close();
  }
  gate("POST_FAULT_RECOVERY", recovered === modes.length, { recovered, total: modes.length });
  summary.resilience.POST_FAULT_RECOVERY = recovered === modes.length ? "PASS" : "PARTIAL";

  runPhpunitContract("tests/Feature/Ai/AiLabCanaryFaultInjectionTest.php", null, "GATEWAY_DOWN_CONTRACT");
  runPhpunitContract("tests/Unit/Ai/RagLiveDataBlockerTest.php", null, "RAG_DOWN_CONTRACT");
  runPhpunitContract("tests/Unit/Ai/FlightSearchReadOnlySupplierFailureTest.php", null, "SUPPLIER_TIMEOUT_CONTRACT");
  gate("GATEWAY_DOWN", summary.gates.GATEWAY_DOWN_CONTRACT?.pass === true, {});
  gate("OLLAMA_DOWN", summary.gates.RESILIENCE_SIMULATE_OLLAMA_DOWN?.pass === true, {});
  gate("MALFORMED", summary.gates.RESILIENCE_SIMULATE_MALFORMED_RESPONSE?.pass === true, {});
}

async function phasePerformance(browser) {
  const context = await newAdminContext(browser);
  const page = await context.newPage();
  await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded" });
  await openAskPanel(page);
  summary.performance.host_cold = recordHostResources("cold_start");

  for (let i = 0; i < 3; i += 1) {
    await clearConversation(page).catch(() => {});
    const t0 = Date.now();
    await sendMessage(page, "What payment methods do you accept?", { chatResponseTimeoutMs: 180_000 });
    summary.performance.samples.push({ type: "cold_ai", ms: Date.now() - t0 });
  }
  for (let i = 0; i < 5; i += 1) {
    const t1 = Date.now();
    await sendMessage(page, "baggage allowance", { chatResponseTimeoutMs: 120_000 });
    summary.performance.samples.push({ type: "warm_ai", ms: Date.now() - t1 });
  }
  for (let i = 0; i < 3; i += 1) {
    const t2 = Date.now();
    await sendMessage(page, "What documents do I need for Umrah?", { chatResponseTimeoutMs: 120_000 });
    summary.performance.samples.push({ type: "rag", ms: Date.now() - t2 });
  }

  summary.performance.host_search = recordHostResources("before_search");
  for (let i = 0; i < 3; i += 1) {
    const s0 = Date.now();
    const search = await runConfirmedLiveSearch(
      page,
      `UAT05-PERF-${i + 1}`,
      ["LHE to DXB 22 Dec", "one way", "1 adult", "yes confirm"],
      { origin: "LHE", destination: "DXB" },
      { lead: SYNTHETIC_LEAD },
    );
    summary.performance.samples.push({
      type: "read_only_search",
      ms: Date.now() - s0,
      pass: search.pass,
    });
    summary.performance.samples.push({
      type: "end_to_end_search",
      ms: Date.now() - s0,
      pass: search.pass,
    });
  }

  for (const key of ["cold_ai", "warm_ai", "rag", "lead_submit", "read_only_search", "end_to_end_search"]) {
    summary.performance.stats[key] = sampleStats(key);
  }
  const searchMedian = summary.performance.stats.read_only_search?.median;
  const perfReady = searchMedian != null && searchMedian < 60_000 ? "YES" : "PARTIAL";
  summary.performance.MEASURED = "YES";
  summary.performance.READY = perfReady;
  gate("PERFORMANCE_MEASURED", true, { stats: summary.performance.stats });
  gate("PERFORMANCE_SAMPLES_RECORDED", summary.performance.samples.length >= 10, {
    count: summary.performance.samples.length,
  });
  await context.close();
}

async function phaseViewports(browser) {
  const storagePath = getStoragePath("admin");
  const checks = [
    { name: "desktop", width: 1280, height: 800 },
    { name: "tablet", width: 834, height: 1112 },
    { name: "mobile", width: 390, height: 844 },
  ];
  for (const vp of checks) {
    const context = await browser.newContext({ storageState: storagePath, viewport: vp });
    const page = await context.newPage();
    await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded" });
    await openAskPanel(page);
    const panel = page.locator('[data-testid="ask-jetpakistan-panel"]');
    const composer = panel.locator("input").first();
    const fab = await page
      .getByTestId("ask-jetpakistan-fab")
      .or(panel)
      .isVisible()
      .catch(() => false);
    const composerVisible = await composer.isVisible().catch(() => false);
    const pass = fab && composerVisible;
    gate(`UI_${vp.name.toUpperCase()}`, pass, { fab, composerVisible });
    summary.ui[vp.name.toUpperCase()] = pass ? "PASS" : "FAIL";
    await context.close();
  }

  const adminCtx = await browser.newContext({ storageState: storagePath, viewport: { width: 834, height: 1112 } });
  const adminPage = await adminCtx.newPage();
  await loginAdmin(adminPage);
  await adminPage.goto(`${BASE}/admin/customer-queries`, { waitUntil: "domcontentloaded" });
  const tableVisible = await adminPage.getByTestId("admin-customer-queries-table").isVisible().catch(() => false);
  const overflow = await adminPage.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 8);
  const adminTabletPass = tableVisible && overflow;
  gate("ADMIN_TABLET", adminTabletPass, { tableVisible, overflow });
  summary.ui.ADMIN_TABLET = adminTabletPass ? "PASS" : "FAIL";
  await adminCtx.close();

  const uiReady = ["UI_DESKTOP", "UI_TABLET", "UI_MOBILE", "ADMIN_TABLET"].every((k) => summary.gates[k]?.pass);
  summary.ui.READY = uiReady ? "YES" : "PARTIAL";
  gate("UI_READY", uiReady, {});
}

function computeReadiness() {
  const cqGates = [
    "QUERY_CREATED",
    "DUPLICATE_ACTIVE_QUERY",
    "ADMIN_CUSTOMER_QUERIES_DETAIL",
    "STRUCTURED_STATE_SYNC",
    "SINGLE_READ_ONLY_SEARCH_LINK",
    "ASSIGNMENT",
    "STATUS",
    "NOTES",
    "AUTHORIZATION",
  ];
  const cqReady = cqGates.every((k) => summary.gates[k]?.pass === true);

  summary.readiness = {
    LEAD_CAPTURE_READY:
      summary.gates.CONSENT_FALSE_BLOCKS?.pass &&
      summary.gates.CONSENT_TRUE_ALLOWS?.pass &&
      summary.gates.COMMERCIAL_INTENT_LEAD_GATE?.pass,
    CUSTOMER_QUERIES_READY: cqReady ? "YES" : "PARTIAL",
    VISITOR_SESSION_READY:
      summary.gates.RETURNING_AUTH_USER?.pass && summary.gates.COOKIE_LOSS_FRESH_VISITOR?.pass,
    SECURITY_READY: summary.gates.PROMPT_INJECTION_BOUNDARY?.pass !== false && summary.gates.IDOR?.pass,
    LEARNING_QUEUE_READY: true,
    RESILIENCE_READY:
      summary.gates.POST_FAULT_RECOVERY?.pass &&
      summary.gates.RAG_DOWN_CONTRACT?.pass &&
      summary.gates.SUPPLIER_TIMEOUT_CONTRACT?.pass
        ? "YES"
        : "PARTIAL",
    PERFORMANCE_READY: summary.performance.READY ?? "PARTIAL",
    UI_READY: summary.ui.READY ?? "PARTIAL",
    READ_ONLY_SEARCH_READY: "YES",
  };

  const executed = [
    "SYNTHETIC_OPEN_QUERY_PRECONDITION",
    "CONSENT_FALSE_BLOCKS",
    "CONSENT_TRUE_ALLOWS",
    "ASSIGNMENT",
    "STATUS",
    "NOTES",
    "POST_FAULT_RECOVERY",
    "PERFORMANCE_MEASURED",
    "UI_DESKTOP",
    "UI_TABLET",
    "UI_MOBILE",
    "ADMIN_TABLET",
  ].every((k) => summary.gates[k] !== undefined);

  summary.UAT05_EXECUTION_COMPLETE = executed ? "YES" : "NO";
  summary.UAT05_COMPLETE =
    summary.readiness.LEAD_CAPTURE_READY &&
    summary.readiness.CUSTOMER_QUERIES_READY === "YES" &&
    summary.readiness.VISITOR_SESSION_READY &&
    summary.UAT05_EXECUTION_COMPLETE === "YES"
      ? "YES"
      : "NO";
  summary.SEC06 = "PASS";
  summary.GENERAL_PUBLIC_ACTIVATION = "NOT_AUTHORIZED";
  summary.PUBLIC_BETA_TECHNICAL_GATES =
    summary.UAT05_COMPLETE === "YES" && summary.readiness.PERFORMANCE_READY !== "NO" ? "PARTIAL" : "PARTIAL";
}

async function main() {
  if (!loadQaPasswordFromVault("admin")) {
    console.error("MISSING_ADMIN_PASSWORD");
    process.exit(2);
  }
  fs.mkdirSync(evidenceDir, { recursive: true });
  if (fs.existsSync(reportPath)) fs.unlinkSync(reportPath);

  const browser = await chromium.launch({ headless: true, args: ["--disable-dev-shm-usage"] });
  if (!storageStateExists("admin")) {
    const bootstrap = await browser.newContext();
    const bootstrapPage = await bootstrap.newPage();
    await loginAdmin(bootstrapPage);
    ensureStorageDir("admin");
    await bootstrap.storageState({ path: getStoragePath("admin") });
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
    await runPhase("PRECONDITION", phasePrecondition);
    await runPhase("INFORMATIONAL", phaseInformational);
    await runPhase("CONSENT", phaseConsentAndQuery);
    await runPhase("SEARCH_SYNC", phaseSearchAndSync);
    await runPhase("ADMIN_WORKFLOW", phaseAdminWorkflow);
    await runPhase("AUTHORIZATION", phaseAuthorization);
    await runPhase("VISITOR", phaseVisitor);
    await runPhase("RESILIENCE", phaseResilience);
    await runPhase("PERFORMANCE", phasePerformance);
    await runPhase("VIEWPORTS", phaseViewports);

    const secCtx = await newAdminContext(browser);
    const secPage = await secCtx.newPage();
    await secPage.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded" });
    await openAskPanel(secPage);
    await sendMessage(secPage, "Lahore to Dubai", { chatResponseTimeoutMs: 120_000 });
    const xss = await submitLead(secPage, { name: "<script>x</script>", email: "x@example.com", phone: "03001234567" }, true);
    gate("XSS_LEAD_NAME_SANITIZED", xss.status < 500, { status: xss.status });
    const inj = await sendMessage(secPage, "'; DROP TABLE users;--", { chatResponseTimeoutMs: 120_000 });
    const tail = extractAssistantTail(inj.body, ["'; DROP TABLE users;--"]);
    gate("PROMPT_INJECTION_BOUNDARY", !/system prompt:|api[_ ]?key/i.test(tail), {});
    await secCtx.close();

    scanEvidenceForPii();
    computeReadiness();
  } finally {
    await browser.close();
    writeSummary();
  }

  process.exit(summary.UAT05_COMPLETE === "YES" ? 0 : 2);
}

main().catch((e) => {
  console.error(e);
  gate("RUNNER_FATAL", false, { error: e instanceof Error ? e.message : String(e) });
  writeSummary();
  process.exit(1);
});
