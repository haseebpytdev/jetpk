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
} from "./canary-matrix-helpers.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
export const evidenceDir = path.resolve(__dirname, "../../docs/evidence/jp-ai-live-readonly-final-qa-01");
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
  const input = page
    .locator('[data-testid="ask-jetpakistan-panel"] input[type="text"], [data-testid="ask-jetpakistan-panel"] input')
    .first();
  await input.waitFor({ state: "visible", timeout: timeoutMs });
  await page.waitForFunction(
    () => {
      const el = document.querySelector('[data-testid="ask-jetpakistan-panel"] input[type="text"], [data-testid="ask-jetpakistan-panel"] input');
      return el && !el.disabled && el.getAttribute("aria-busy") !== "true";
    },
    { timeout: timeoutMs },
  );
}

function normalizeConfirmationStep(msg) {
  if (/^(yes|ji|haan|okay|correct)(\s+confirm)?$/i.test(msg.trim())) return "yes";
  if (/^(haan|ji)\s+confirm$/i.test(msg.trim())) return "haan";
  return msg;
}

export async function runConfirmedLiveSearch(page, caseId, steps, expectRoute) {
  const started = Date.now();
  let pass = false;
  let error = null;
  let lastPayload = {};
  let visible = "";
  try {
    await openAskPanel(page);
    await clearConversation(page);
    await waitForChatInputReady(page);
    for (let i = 0; i < steps.length; i++) {
      const msg = i === steps.length - 1 ? normalizeConfirmationStep(steps[i]) : steps[i];
      await waitForChatInputReady(page);
      const result = await sendMessage(page, msg, { responseTimeoutMs: 180_000 });
      lastPayload = result.payload ?? {};
      visible = result.body;
      if (result.status >= 500) throw new Error(`HTTP_${result.status}`);
    }
    const meta = lastPayload.meta ?? {};
    if (meta.dialog_state === "AWAITING_CONFIRMATION") {
      await waitForChatInputReady(page);
      const retry = await sendMessage(page, "yes", { responseTimeoutMs: 180_000 });
      lastPayload = retry.payload ?? lastPayload;
      visible = retry.body;
    }
    const metaAfter = lastPayload.meta ?? {};
    const recs = lastPayload.recommendations ?? [];
    const searchRecord = metaAfter.search_record ?? metaAfter.shadow_record ?? null;
    const isLive =
      /live option|searched live availability|read-only/i.test(visible) ||
      searchRecord?.live_supplier_called === true ||
      recs.some((r) => Array.isArray(r?.labels) && r.labels.includes("live-search"));
    if (!isLive) throw new Error("NOT_LIVE_SEARCH_RESPONSE");
    if (expectRoute?.origin && !new RegExp(expectRoute.origin, "i").test(visible)) {
      throw new Error("ROUTE_ORIGIN_MISMATCH");
    }
    if (expectRoute?.destination && !new RegExp(expectRoute.destination, "i").test(visible)) {
      throw new Error("ROUTE_DEST_MISMATCH");
    }
    pass = true;
  } catch (e) {
    error = e instanceof Error ? e.message : String(e);
  }
  const screenshot = path.join(evidenceDir, `${caseId}${pass ? "" : "-fail"}.png`);
  await page.screenshot({ path: screenshot, fullPage: false }).catch(() => {});
  recordCase({
    phase: "LIVE_SEARCH",
    case_id: caseId,
    pass_fail: pass ? "PASS" : "FAIL",
    steps,
    expect_route: expectRoute,
    error,
    latency_ms: Date.now() - started,
    payload_meta: lastPayload?.meta ?? null,
    screenshot,
  });
  return { pass, error, visible };
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
