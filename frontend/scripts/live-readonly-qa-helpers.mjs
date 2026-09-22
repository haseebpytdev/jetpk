/**
 * JP-AI-LIVE-READONLY-FINAL-INTERNAL-QA-01 shared helpers.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { loadQaPasswordFromVault } from "../../dashboard/scripts/jp-dash-03-acceptance/credential-vault.mjs";
import {
  BASE,
  appendCaseRecord,
  clearConversation,
  openAskPanel,
  postLogin,
  probeSession,
  sendMessage,
  sessionPreflight,
  waitForAskReady,
} from "./canary-matrix-helpers.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
export const evidenceDir = path.resolve(
  __dirname,
  "../../docs/evidence/jp-ai-conversation-isolation-canonical-10",
);
export const reportPath = path.join(evidenceDir, "final-qa-report.jsonl");
export const summaryPath = path.join(evidenceDir, "final-qa-summary.json");

export const ADMIN_EMAIL = "jp-dash-03-qa-admin@jetpakistan.pk";
export const ADMIN_SETTINGS_URL = `${BASE}/admin/settings/ai-assistant`;

fs.mkdirSync(evidenceDir, { recursive: true });

export function resetReport() {
  if (fs.existsSync(reportPath)) fs.unlinkSync(reportPath);
}

export function recordCase(record) {
  fs.appendFileSync(reportPath, `${JSON.stringify({ ...record, ts: new Date().toISOString() })}\n`, "utf8");
}

export async function loginAs(page, email, role) {
  const password = loadQaPasswordFromVault(role);
  if (!password) throw new Error(`MISSING_PASSWORD_${role}`);
  await page.goto(`${BASE}/login`, { waitUntil: "domcontentloaded", timeout: 120_000 });
  await page.waitForSelector('[name="login"]', { state: "visible", timeout: 60_000 }).catch(() => {});
  const response = await postLogin(page, email, password);
  if (response.status() === 302 || response.status() === 301) {
    await page.goto(`${BASE}/admin/dashboard`, { waitUntil: "domcontentloaded", timeout: 120_000 });
    return;
  }
  let data = {};
  try {
    data = await response.json();
  } catch {
    data = {};
  }
  if (!response.ok() || data.ok !== true) {
    throw new Error(`LOGIN_FAIL_${role}:${response.status()}`);
  }
  const dest = typeof data.redirect === "string" && data.redirect.startsWith("/")
    ? data.redirect
    : "/admin/dashboard";
  await page.goto(`${BASE}${dest}`, { waitUntil: "domcontentloaded", timeout: 120_000 });
}

export async function uiLogin(page, email, password) {
  await page.goto(`${BASE}/login`, { waitUntil: "domcontentloaded", timeout: 120_000 });
  const response = await postLogin(page, email, password);
  if (response.status() === 302 || response.status() === 301) {
    await page.goto(`${BASE}/`, { waitUntil: "domcontentloaded", timeout: 120_000 });
    return;
  }
  const data = await response.json().catch(() => ({}));
  if (!response.ok() || data.ok !== true) throw new Error(`LOGIN_FAIL:${response.status()}`);
  const dest = typeof data.redirect === "string" && data.redirect.startsWith("/") ? data.redirect : "/";
  await page.goto(`${BASE}${dest}`, { waitUntil: "domcontentloaded", timeout: 120_000 });
}

export async function loginAdmin(page) {
  await loginAs(page, ADMIN_EMAIL, "admin");
}

export async function readAdminToggleStates(page) {
  await page.goto(ADMIN_SETTINGS_URL, { waitUntil: "domcontentloaded", timeout: 120_000 });
  const fields = [
    "master_enabled",
    "lab_adapter_enabled",
    "rag_enabled",
    "human_handoff_enabled",
    "learning_queue_enabled",
    "internal_canary_enabled",
    "flight_search_read_only_enabled",
  ];
  const states = {};
  for (const field of fields) {
    states[field] = await page.locator(`#${field}`).isChecked().catch(() => false);
  }
  const body = await page.locator("body").innerText();
  return { states, bodySnippet: body.slice(0, 4000) };
}

export async function setAdminToggles(page, toggles) {
  await page.goto(ADMIN_SETTINGS_URL, { waitUntil: "domcontentloaded", timeout: 120_000 });
  for (const [field, on] of Object.entries(toggles)) {
    const loc = page.locator(`#${field}`);
    const checked = await loc.isChecked();
    if (on && !checked) await loc.check();
    if (!on && checked) await loc.uncheck();
  }
  await page.getByRole("button", { name: /save settings/i }).click();
  await page.waitForTimeout(1500);
}

export async function probeEffectiveReadOnly(page) {
  const res = await page.request.get(`${BASE}/laravel/api/public/ai/health`, {
    headers: { Accept: "application/json" },
  });
  let health = {};
  try {
    health = await res.json();
  } catch {
    health = {};
  }
  const session = await probeSession(page);
  const effective = health?.status?.effective ?? null;
  return { health, session, effective };
}

export async function waitForChatInputReady(page, timeoutMs = 180_000) {
  await waitForAskReady(page, timeoutMs);
}

function extractRouteFromMeta(meta) {
  const rec = meta?.search_record ?? meta?.shadow_record ?? null;
  const slots = meta?.intent?.slots ?? {};
  return {
    origin: rec?.origin ?? slots.origin ?? null,
    destination: rec?.destination ?? slots.destination ?? null,
    dialog_state: meta?.dialog_state ?? meta?.lab_state?.dialog_state ?? null,
    live_supplier_called: rec?.live_supplier_called === true,
    confirmation_before_search: meta?.confirmation_before_search === true || rec?.confirmed === true,
  };
}

function routeMatchesExpectation(meta, expectRoute) {
  const route = extractRouteFromMeta(meta);
  if (expectRoute?.origin && route.origin !== expectRoute.origin) return false;
  if (expectRoute?.destination && route.destination !== expectRoute.destination) return false;
  return true;
}

function recapMatchesExpectation(meta, expectRoute) {
  const route = extractRouteFromMeta(meta);
  if (!expectRoute?.origin || !expectRoute?.destination) return true;
  return route.origin === expectRoute.origin && route.destination === expectRoute.destination;
}

const SYNTHETIC_QA_LEAD = {
  name: "UAT Lead QA",
  email: "uat-lead-qa@jetpakistan.pk",
  phone: "+923001234567",
};

export function normalizeConfirmationStep(msg) {
  if (/^(yes|ji|haan|okay|correct)(\s+confirm)?$/i.test(msg.trim())) return "yes";
  if (/^(haan|ji)\s+confirm$/i.test(msg.trim())) return "haan";
  return msg;
}

export function isLeadCapturePending(payload = {}) {
  return (
    payload?.meta?.lead_capture_pending === true ||
    payload?.status === "lead_capture_required" ||
    payload?.mode === "LEAD_CAPTURE"
  );
}

async function readPanelText(page) {
  return page.getByTestId("ask-jetpakistan-messages").innerText().catch(() => "");
}

function panelPromptKind(text) {
  const lower = String(text).toLowerCase();
  if (/may i start with your name|what should i call you/i.test(lower)) return "name";
  if (/email address and contact number|best contact number|email address as well/i.test(lower)) return "contact";
  if (/is it okay for jetpakistan to contact you/i.test(lower)) return "consent";
  return null;
}

export async function completeLeadCaptureIfNeeded(page, options = {}) {
  const panel = page.locator('[data-testid="ask-jetpakistan-panel"]');
  const leadCapture = panel.locator('[data-testid="ask-jetpakistan-lead-capture"]');
  const lead = options.lead ?? SYNTHETIC_QA_LEAD;
  const timeoutMs = Number(options.leadTimeoutMs ?? 120_000);

  if (await leadCapture.isVisible().catch(() => false)) {
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
    const consent = panel.locator('[data-testid="ask-jetpakistan-lead-capture"] input[type="checkbox"]').first();
    if (await consent.isVisible().catch(() => false) && !(await consent.isChecked())) {
      await consent.check();
    }
    const responsePromise = page.waitForResponse(
      (res) => res.url().includes("/api/public/ai/lead") && res.request().method() === "POST",
      { timeout: timeoutMs },
    );
    await panel.getByRole("button", { name: /continue/i }).click();
    const response = await responsePromise;
    let payload = {};
    try {
      payload = await response.json();
    } catch {
      payload = {};
    }
    await page.waitForTimeout(1500);
    return {
      completed: true,
      status: response.status(),
      payload,
      reason: response.ok() ? "LEAD_SUBMITTED_LEGACY_FORM" : "LEAD_SUBMIT_FAILED",
    };
  }

  let lastPayload = options.initialPayload ?? {};
  const panelText = await readPanelText(page);
  const initialKind = panelPromptKind(panelText);
  if (!initialKind && !isLeadCapturePending(lastPayload)) {
    return { completed: false, reason: "NO_LEAD_GATE" };
  }

  const steps = [];
  for (let attempt = 0; attempt < 4; attempt += 1) {
    const text = await readPanelText(page);
    const kind = panelPromptKind(text);
    if (kind === "name") {
      const nameResult = await sendMessage(page, lead.name, { responseTimeoutMs: timeoutMs });
      steps.push({ step: "name", payload: nameResult.payload ?? {} });
      lastPayload = nameResult.payload ?? lastPayload;
      continue;
    }
    if (kind === "contact") {
      const contactResult = await sendMessage(page, `${lead.email} ${lead.phone}`, {
        responseTimeoutMs: timeoutMs,
      });
      steps.push({ step: "contact", payload: contactResult.payload ?? {} });
      lastPayload = contactResult.payload ?? lastPayload;
      continue;
    }
    if (kind === "consent") {
      const consentResult = await sendMessage(page, options.consentMessage ?? "yes", {
        responseTimeoutMs: timeoutMs,
      });
      steps.push({ step: "consent", payload: consentResult.payload ?? {} });
      lastPayload = consentResult.payload ?? lastPayload;
      break;
    }
    if (!isLeadCapturePending(lastPayload)) {
      break;
    }
  }

  return {
    completed: true,
    status: 200,
    payload: lastPayload,
    steps,
    reason: "LEAD_SUBMITTED_CONVERSATIONAL",
  };
}

export async function runConfirmedLiveSearch(page, caseId, steps, expectRoute, options = {}) {
  const started = Date.now();
  let pass = false;
  let error = null;
  let lastPayload = {};
  let visible = "";
  let firstAttemptResult = "FAIL";
  let confirmationBeforeSearch = false;
  let routeMeta = null;
  let isolationMeta = null;
  let firstChatRequestId = null;
  try {
    await waitForChatInputReady(page);
    const cleared = await clearConversation(page);
    if (/CLEAR_PREFLIGHT_INFRA_FAILURE|HARNESS_CONVERSATION_ID_NOT_ISOLATED/.test(String(cleared))) {
      throw cleared instanceof Error ? cleared : new Error(String(cleared));
    }
    const expectedConversationId = cleared?.conversation_id ?? null;
    isolationMeta = {
      old_conversation_id: cleared?.old_conversation_id ?? null,
      clear_response_new_id: cleared?.clear_response_new_id ?? null,
      session_storage_after_clear: cleared?.session_storage_after_clear ?? null,
      first_chat_request_id: null,
      skipped_clear: cleared?.skipped === true,
    };
    await waitForChatInputReady(page);

    for (let i = 0; i < steps.length; i++) {
      const msg = i === steps.length - 1 ? normalizeConfirmationStep(steps[i]) : steps[i];
      await waitForChatInputReady(page);
      const conversationIdAssertion =
        i === 0 && expectedConversationId
          ? page.waitForRequest(
              (req) =>
                req.url().includes("/api/public/ai/chat") && req.method() === "POST",
              { timeout: 180_000 },
            )
          : null;
      const result = await sendMessage(page, msg, { responseTimeoutMs: 180_000 });
      if (conversationIdAssertion) {
        const chatRequest = await conversationIdAssertion;
        let body = {};
        try {
          body = JSON.parse(chatRequest.postData() ?? "{}");
        } catch {
          body = {};
        }
        const requestId = body.conversation_id ?? null;
        firstChatRequestId = requestId;
        if (isolationMeta) isolationMeta.first_chat_request_id = requestId;
        if (requestId !== expectedConversationId) {
          throw new Error(
            `HARNESS_CONVERSATION_ID_NOT_ISOLATED:expected=${expectedConversationId}:actual=${requestId ?? "null"}`,
          );
        }
      }
      lastPayload = result.payload ?? {};
      visible = result.body;
      if (result.status >= 500) throw new Error(`HTTP_${result.status}`);
      if (isLeadCapturePending(result.payload)) {
        const lead = await completeLeadCaptureIfNeeded(page, { ...options, initialPayload: result.payload });
        if (!lead.completed || lead.status >= 400) {
          throw new Error(lead.reason ?? "LEAD_CAPTURE_FAILED");
        }
        lastPayload = lead.payload ?? lastPayload;
        visible = await page.getByTestId("ask-jetpakistan-messages").innerText().catch(() => visible);
      }

      const stepMeta = lastPayload.meta ?? {};
      const stepRoute = extractRouteFromMeta(stepMeta);
      if (stepRoute.dialog_state === "COLLECTING" && i === steps.length - 1) {
        throw new Error("COLLECTING_STALL");
      }
      if (i === steps.length - 2 && expectRoute) {
        if (stepRoute.dialog_state === "COLLECTING") {
          throw new Error("COLLECTING_STALL");
        }
        if (stepRoute.dialog_state === "AWAITING_CONFIRMATION" && !recapMatchesExpectation(stepMeta, expectRoute)) {
          throw new Error(`RECAP_ROUTE_MISMATCH:${stepRoute.origin ?? "?"}->${stepRoute.destination ?? "?"}`);
        }
      }
    }

    const metaAfter = lastPayload.meta ?? {};
    routeMeta = extractRouteFromMeta(metaAfter);
    if (routeMeta.dialog_state === "COLLECTING") {
      throw new Error("COLLECTING_STALL");
    }
    if (routeMeta.dialog_state === "AWAITING_CONFIRMATION") {
      throw new Error("CONFIRMATION_NOT_COMPLETED");
    }

    const recs = lastPayload.recommendations ?? [];
    const searchRecord = metaAfter.search_record ?? metaAfter.shadow_record ?? null;
    confirmationBeforeSearch =
      routeMeta.confirmation_before_search ||
      searchRecord?.confirmed === true ||
      /confirm\?/i.test(visible);
    const isLive =
      /live option|searched live availability|read-only/i.test(visible) ||
      routeMeta.live_supplier_called === true ||
      recs.some((r) => Array.isArray(r?.labels) && r.labels.includes("live-search"));
    if (!isLive) throw new Error("NOT_LIVE_SEARCH_RESPONSE");
    if (!routeMatchesExpectation(metaAfter, expectRoute)) {
      throw new Error(`ROUTE_SLOT_MISMATCH:${routeMeta.origin ?? "?"}->${routeMeta.destination ?? "?"}`);
    }

    pass = true;
    firstAttemptResult = "PASS";
  } catch (e) {
    error = e instanceof Error ? e.message : String(e);
    if (/CLEAR_PREFLIGHT_INFRA_FAILURE|HARNESS_CONVERSATION_ID_NOT_ISOLATED/.test(error)) {
      throw e instanceof Error ? e : new Error(error);
    }
    if (/FAB_UNAVAILABLE|CANARY_NOT_ELIGIBLE/i.test(error)) {
      error = error.includes("FAB_UNAVAILABLE") ? "FAB_UNAVAILABLE" : error;
    }
  }
  const screenshot = path.join(evidenceDir, `${caseId}${pass ? "" : "-fail"}.png`);
  await page.screenshot({ path: screenshot, fullPage: false }).catch(() => {});
  recordCase({
    phase: options.phase ?? "LIVE_SEARCH",
    case_id: caseId,
    pass_fail: pass ? "PASS" : "FAIL",
    first_attempt_result: firstAttemptResult,
    retry_attempts: 0,
    final_diagnostic_result: pass ? "PASS" : "FAIL",
    steps,
    expect_route: expectRoute,
    error,
    latency_ms: Date.now() - started,
    payload_meta: lastPayload?.meta ?? null,
    route_meta: routeMeta,
    confirmation_before_search: confirmationBeforeSearch,
    isolation: isolationMeta,
    first_chat_request_id: firstChatRequestId,
    screenshot,
  });
  return {
    pass,
    error,
    visible,
    firstAttemptResult,
    routeMeta,
    confirmationBeforeSearch,
    isolation: isolationMeta,
    firstChatRequestId,
  };
}

export {
  BASE,
  clearConversation,
  openAskPanel,
  postLogin,
  probeSession,
  sendMessage,
  sessionPreflight,
};
