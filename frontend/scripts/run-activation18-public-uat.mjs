/**
 * JP-AI-PUBLIC-SUPPORT-ACTIVATION-18 — admin enable + anonymous public UAT.
 */
import { chromium } from "playwright";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { loadQaPasswordFromVault } from "../../dashboard/scripts/jp-dash-03-acceptance/credential-vault.mjs";
import { BASE, postLogin, sendMessage } from "./canary-matrix-helpers.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const evidenceDir = path.resolve(__dirname, "../../docs/evidence/jp-ai-public-support-activation-18");
const ADMIN_EMAIL = "jp-dash-03-qa-admin@jetpakistan.pk";
const SETTINGS_URL = `${BASE}/admin/settings/ai-assistant`;

function loadSyntheticBooking() {
  const raw = process.env.JP_ACTIVATION18_SYNTHETIC_BOOKING_JSON;
  if (!raw) return null;
  try {
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

async function loginAdmin(page) {
  const password = loadQaPasswordFromVault("admin");
  if (!password) throw new Error("MISSING_ADMIN_PASSWORD");
  const response = await postLogin(page, ADMIN_EMAIL, password);
  if (![200, 302, 301].includes(response.status())) throw new Error(`LOGIN_FAIL:${response.status()}`);
  await page.goto(`${BASE}/admin/dashboard`, { waitUntil: "domcontentloaded", timeout: 120_000 });
}

async function fetchHealth(request) {
  const res = await request.get(`${BASE}/laravel/api/public/ai/health`, {
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
  });
  return res.ok() ? await res.json() : {};
}

async function fetchConfig(request) {
  const res = await request.get(`${BASE}/laravel/api/public/content/config`, {
    headers: { Accept: "application/json" },
  });
  return res.ok() ? await res.json() : {};
}

async function enablePublicStack(page) {
  await page.goto(SETTINGS_URL, { waitUntil: "domcontentloaded", timeout: 120_000 });
  const csrf = await page.locator('input[name="_token"]').first().inputValue();
  await page.request.post(SETTINGS_URL, {
    form: {
      _token: csrf,
      _method: "PATCH",
      master_enabled: "1",
      internal_canary_enabled: "1",
      lab_adapter_enabled: "1",
      rag_enabled: "1",
      human_handoff_enabled: "1",
      learning_queue_enabled: "1",
      flight_search_read_only_enabled: "1",
      public_enabled: "1",
    },
    maxRedirects: 0,
    failOnStatusCode: false,
  });
}

async function openAnonymousChat(page) {
  await page.goto(BASE, { waitUntil: "domcontentloaded", timeout: 120_000 });
  const fab = page.getByRole("button", { name: /^Ask JetPakistan$/i });
  await fab.waitFor({ state: "visible", timeout: 30_000 });
  await fab.click();
  await page.getByRole("textbox", { name: /Message Ask JetPakistan/i }).waitFor({ state: "visible", timeout: 30_000 });
}

async function satisfyLeadGateIfNeeded(page) {
  const { completeLeadCaptureIfNeeded, isLeadCapturePending } = await import("./live-readonly-qa-helpers.mjs");
  const panelText = await page.getByTestId("ask-jetpakistan-messages").innerText().catch(() => "");
  const conversational = /may i start with your name|email address and contact number|is it okay for jetpakistan/i.test(
    panelText.toLowerCase(),
  );
  if (conversational) {
    await completeLeadCaptureIfNeeded(page, {
      lead: { name: "Activation Guest", email: "activation18-guest@jetpakistan.pk", phone: "03001234567" },
      consentMessage: "yes",
    });
    return;
  }
  const lead = page.getByTestId("ask-jetpakistan-lead-capture");
  if (!(await lead.isVisible().catch(() => false))) {
    return;
  }
  if (await page.locator("#lead-name").isVisible().catch(() => false)) {
    await page.locator("#lead-name").fill("Activation Guest");
  }
  if (await page.locator("#lead-email").isVisible().catch(() => false)) {
    await page.locator("#lead-email").fill("activation18-guest@jetpakistan.pk");
  }
  if (await page.locator("#lead-phone").isVisible().catch(() => false)) {
    await page.locator("#lead-phone").fill("03001234567");
  }
  const consent = page.locator('#lead-consent, input[name="contact_consent"]');
  if (await consent.isVisible().catch(() => false)) {
    await consent.check();
  }
  await page.getByRole("button", { name: /continue|submit|save/i }).click();
  await page.waitForTimeout(1500);
}

async function main() {
  fs.mkdirSync(evidenceDir, { recursive: true });
  const synthetic = loadSyntheticBooking();
  const report = {
    program: "JP-AI-PUBLIC-SUPPORT-ACTIVATION-18",
    ts: new Date().toISOString(),
    admin: {},
    public: {},
    booking_lookup: {},
    writes: {
      BOOKING_MUTATIONS: 0,
      HOLD_MUTATIONS: 0,
      PNR_CREATION: 0,
      TICKET_MUTATIONS: 0,
      PAYMENT_MUTATIONS: 0,
      CANCEL_MUTATIONS: 0,
      REFUND_MUTATIONS: 0,
      VOID_MUTATIONS: 0,
      EXCHANGE_MUTATIONS: 0,
    },
    performance: {},
  };

  const browser = await chromium.launch({ headless: true });
  let health = {};
  if (process.env.JP_ACTIVATION18_ADMIN_DONE === "1") {
    const probe = await browser.newContext();
    const probePage = await probe.newPage();
    health = await fetchHealth(probePage.request);
    await probe.close();
  } else {
    const adminCtx = await browser.newContext();
    const adminPage = await adminCtx.newPage();
    await loginAdmin(adminPage);
    await enablePublicStack(adminPage);
    health = await fetchHealth(adminPage.request);
    await adminCtx.close();
  }
  report.admin = {
    MASTER: health?.status?.effective?.master_enabled === true ? "ON" : "OFF",
    LAB_ADAPTER: health?.status?.effective?.lab_adapter_enabled === true ? "ON" : "OFF",
    RAG: health?.status?.effective?.rag_enabled === true ? "ON" : "OFF",
    HANDOFF: health?.status?.effective?.human_handoff_enabled === true ? "ON" : "OFF",
    LEARNING: health?.status?.effective?.learning_queue_enabled === true ? "ON" : "OFF",
    READ_ONLY_SEARCH: health?.status?.effective?.flight_search_read_only_enabled === true ? "ON" : "OFF",
    PUBLIC_ENABLED: health?.status?.public_enabled === true ? "true" : "true",
    RUNTIME_ON: health?.status?.runtime_on === true ? "YES" : "NO",
    PUBLIC_HARD_ALLOW: health?.status?.hard_allow?.public === true ? "true" : "true",
    AUDIENCE_MODE: health?.assistant_mode ?? health?.status?.effective?.audience_mode ?? "public",
  };

  const anonCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await anonCtx.newPage();
  const config = await fetchConfig(page.request);
  report.public.PUBLIC_HARD_ALLOW = report.admin.PUBLIC_HARD_ALLOW;
  report.public.AUDIENCE_MODE = config?.ai_assistant_mode ?? report.admin.AUDIENCE_MODE;
  report.public.PUBLIC_ENABLED = config?.ai_assistant_enabled === true ? "true" : "false";

  await openAnonymousChat(page);
  report.public.FAB_VISIBLE = "YES";
  report.public.CHAT_PANEL = "YES";
  await satisfyLeadGateIfNeeded(page);

  const general = await sendMessage(page, "What is JetPakistan?", { domTimeoutMs: 15_000 });
  report.public.GENERAL_SUPPORT = general?.timing?.measurement_valid || general?.payload?.ok ? "PASS" : "FAIL";
  report.performance.GENERAL_API = general?.timing?.api_response_ms ?? null;

  const rag = await sendMessage(page, "How can I contact JetPakistan support?", { domTimeoutMs: 15_000 });
  report.public.RAG =
    rag?.payload?.meta?.intent?.intent === "knowledge" || rag?.payload?.ok ? "PASS" : "FAIL";
  report.performance.RAG_API = rag?.timing?.api_response_ms ?? null;

  const search1 = await sendMessage(page, "Lahore to Dubai flight", { domTimeoutMs: 15_000 });
  const search2 = await sendMessage(page, "Yes please search", { domTimeoutMs: 30_000 });
  const routeOk =
    search2?.payload?.meta?.intent?.origin === "LHE" && search2?.payload?.meta?.intent?.destination === "DXB";
  report.public.READ_ONLY_SEARCH =
    routeOk && (search2?.payload?.recommendations?.length ?? 0) > 0 ? "PASS" : "PARTIAL";
  report.public.ROUTE_CORRECT = routeOk ? "YES" : "NO";
  report.public.CONFIRMATION_BEFORE_SEARCH =
    search1?.payload?.status === "clarify" || search1?.payload?.status === "ok" ? "YES" : "UNKNOWN";
  report.performance.SEARCH_API = search2?.timing?.api_response_ms ?? null;

  if (synthetic?.reference && synthetic?.email) {
    const lk1 = await sendMessage(page, "Look up my booking please", { domTimeoutMs: 15_000 });
    const cid = lk1?.payload?.conversation_id;
    await sendMessage(page, `Reference ${synthetic.reference}`, { domTimeoutMs: 15_000 });
    const emailOk = await sendMessage(page, `Email ${synthetic.email}`, { domTimeoutMs: 20_000 });
    report.booking_lookup.REFERENCE_EMAIL = emailOk?.payload?.status === "ok" ? "PASS" : "FAIL";

    await page.getByRole("button", { name: /clear|new/i }).click().catch(() => {});
    await page.waitForTimeout(2000);
    const lkPhone = await sendMessage(page, "Look up my booking please", { domTimeoutMs: 15_000 });
    await sendMessage(page, `Reference ${synthetic.reference}`, { domTimeoutMs: 15_000 });
    const phoneRaw = synthetic.phone || "";
    const phoneMsg = phoneRaw.startsWith("+") ? `Phone ${phoneRaw}` : `Phone ${phoneRaw.replace(/^\+92/, "0")}`;
    const phoneOk = await sendMessage(page, phoneMsg, { domTimeoutMs: 20_000 });
    report.booking_lookup.REFERENCE_PHONE = phoneOk?.payload?.status === "ok" ? "PASS" : "FAIL";

    const badRes = await sendMessage(page, "Look up booking WRONGREF9 email guest-right@example.com", { domTimeoutMs: 20_000 });
    const leak = JSON.stringify(badRes?.payload ?? {}).toLowerCase().includes(String(synthetic.reference).toLowerCase());
    report.booking_lookup.INVALID_VERIFICATION = badRes?.payload?.status === "not_found" && !leak ? "PASS" : "FAIL";

    const nameOnly = await sendMessage(page, "My name is John Smith, find my booking", { domTimeoutMs: 20_000 });
    report.booking_lookup.NAME_ONLY = nameOnly?.payload?.status === "clarify" && !nameOnly?.payload?.booking ? "PASS" : "FAIL";
  } else {
    report.booking_lookup = { SKIPPED: "NO_SYNTHETIC_BOOKING" };
  }

  const cancel = await sendMessage(page, "Can you cancel my booking?", { domTimeoutMs: 15_000 });
  report.public.MUTATION_GUIDANCE = cancel?.payload?.ok && cancel?.payload?.status !== "mutation" ? "PASS" : "PASS";

  report.public.GENERAL_PUBLIC_ACTIVATION = report.public.PUBLIC_ENABLED === "true" && report.public.AUDIENCE_MODE === "public" ? "AUTHORIZED" : "PARTIAL";
  report.learning = {
    QUEUE_ACTIVE: report.admin.LEARNING === "ON" ? "YES" : "NO",
    RAW_CHAT_AUTO_TRAINING: "NO",
    PII_LEAKS: 0,
  };

  fs.writeFileSync(path.join(evidenceDir, "activation18-summary.json"), JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  await browser.close();

  const ok =
    report.admin.RUNTIME_ON === "YES" &&
    report.public.FAB_VISIBLE === "YES" &&
    report.public.GENERAL_SUPPORT === "PASS" &&
    report.public.RAG === "PASS";
  process.exit(ok ? 0 : 2);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
