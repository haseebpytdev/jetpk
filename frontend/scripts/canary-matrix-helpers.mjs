/**
 * JP-AI-PRODUCTION-CANARY-01 â shared browser matrix helpers (single authenticated context).
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
export const BASE = process.env.JP_CANARY_UAT_BASE ?? "https://jetpakistan.pk";
export const evidenceDir = path.resolve(__dirname, "../../docs/evidence/jp-ai-production-canary-01");
export const reportPath = path.join(evidenceDir, "browser-matrix-report.jsonl");
export const CHAT_ROUTE_PATTERN = "**/api/public/ai/chat";

export function getCanaryFaultToken() {
  return process.env.JP_CANARY_FAULT_TOKEN ?? process.env.OTA_AI_LAB_CANARY_FAULT_TOKEN ?? "";
}

export const metrics = {
  sessionLosses: 0,
  configHydrationRecoveries: 0,
  fabRecoveries: 0,
  loginCount: 0,
};

/** JP-AI-CONVERSATION-ISOLATION-CANONICAL-CLOSURE-10 — pre-case /clear throttle evidence */
export const clearThrottleMetrics = {
  clear_requests: 0,
  clear_429_count: 0,
  retry_after_values: [],
  bounded_retries: 0,
  preflight_failures: 0,
  throttle_wait_ms: 0,
};

export function resetClearThrottleMetrics() {
  clearThrottleMetrics.clear_requests = 0;
  clearThrottleMetrics.clear_429_count = 0;
  clearThrottleMetrics.retry_after_values = [];
  clearThrottleMetrics.bounded_retries = 0;
  clearThrottleMetrics.preflight_failures = 0;
  clearThrottleMetrics.throttle_wait_ms = 0;
}

export const CLEAR_RATE_LIMIT = "10/min";

function parseRetryAfterSeconds(response) {
  const raw = response.headers()?.["retry-after"] ?? response.headers()?.["Retry-After"];
  if (raw == null || raw === "") return null;
  const numeric = Number.parseInt(String(raw), 10);
  if (Number.isFinite(numeric) && numeric >= 0) return numeric;
  const dateMs = Date.parse(String(raw));
  if (!Number.isNaN(dateMs)) {
    return Math.max(0, Math.ceil((dateMs - Date.now()) / 1000));
  }
  return null;
}

function boundedThrottleWaitSeconds(retryAfterSeconds) {
  const base = retryAfterSeconds == null ? 61 : retryAfterSeconds + 1;
  return Math.min(base, 75);
}

async function sleepMs(ms) {
  await new Promise((resolve) => setTimeout(resolve, ms));
}

async function readSessionConversationId(page) {
  return page.evaluate(() => {
    try {
      return sessionStorage.getItem("jp_ai_conversation_id");
    } catch {
      return null;
    }
  });
}

async function triggerUiClear(page) {
  const clearResponsePromise = page.waitForResponse(
    (res) => res.url().includes("/api/public/ai/clear") && res.request().method() === "POST",
    { timeout: 90_000 },
  );
  await page.getByRole("button", { name: "Chat options" }).click({ timeout: 30_000 });
  await page.getByRole("menuitem", { name: "Clear conversation" }).click({ timeout: 30_000 });
  const clearResponse = await clearResponsePromise;
  clearThrottleMetrics.clear_requests += 1;
  let clearJson = {};
  try {
    clearJson = await clearResponse.json();
  } catch {
    clearJson = {};
  }
  const requestBody = (() => {
    try {
      return JSON.parse(clearResponse.request().postData() ?? "{}");
    } catch {
      return {};
    }
  })();
  return {
    status: clearResponse.status(),
    ok: clearResponse.ok(),
    conversation_id:
      typeof clearJson.conversation_id === "string" ? clearJson.conversation_id : null,
    request_conversation_id: requestBody.conversation_id ?? null,
    retry_after_seconds: parseRetryAfterSeconds(clearResponse),
  };
}

fs.mkdirSync(evidenceDir, { recursive: true });

export function appendCaseRecord(record) {
  fs.appendFileSync(reportPath, `${JSON.stringify(record)}\n`, "utf8");
}

export function extractAssistantTail(fullText, userInputs = []) {
  let text = fullText;
  for (const input of userInputs) {
    text = text.split(input).pop() ?? text;
  }
  return text.trim();
}

export function isGenericUnavailable(text) {
  return /AI assistant is temporarily unavailable|Too many requests|Please try again shortly/i.test(text);
}

export function assertAssistantSubstantive(text, inputs, options = {}) {
  const tail = extractAssistantTail(text, inputs);
  if (!tail.trim()) throw new Error("empty assistant response");
  if (!options.allowUnavailable && isGenericUnavailable(tail)) {
    throw new Error("generic unavailable assistant response");
  }
  return tail;
}

export function resetReportFile() {
  if (fs.existsSync(reportPath)) {
    fs.unlinkSync(reportPath);
  }
}

export async function fetchCsrfToken(page) {
  await page.request.get(`${BASE}/laravel/api/public/content/csrf-token`, {
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
  });
  const cookies = await page.context().cookies();
  const xsrf = cookies.find((c) => c.name === "XSRF-TOKEN");
  return xsrf ? decodeURIComponent(xsrf.value) : "";
}

export async function postLogin(page, email, password) {
  const loginOpts = {
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    form: { login: email, password, remember: "1", client_slug: "jetpk" },
    maxRedirects: 0,
    failOnStatusCode: false,
  };
  let csrf = await fetchCsrfToken(page);
  let response = await page.request.post(`${BASE}/laravel/login`, {
    ...loginOpts,
    headers: { ...loginOpts.headers, "X-XSRF-TOKEN": csrf },
  });
  if (response.status() === 419) {
    csrf = await fetchCsrfToken(page);
    response = await page.request.post(`${BASE}/laravel/login`, {
      ...loginOpts,
      headers: { ...loginOpts.headers, "X-XSRF-TOKEN": csrf },
    });
  }
  return response;
}

export async function probeSession(page) {
  return page.evaluate(async (base) => {
    const fetchJson = async (url) => {
      try {
        const res = await fetch(url, {
          credentials: "include",
          headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        });
        if (!res.ok) return null;
        return res.json();
      } catch {
        return null;
      }
    };

    const [health, config, bootstrap] = await Promise.all([
      fetchJson(`${base}/laravel/api/public/ai/health`),
      fetchJson(`${base}/laravel/api/public/content/config`),
      fetchJson(`${base}/laravel/api/public/auth/session`),
    ]);

    const authenticated = bootstrap?.authenticated === true;
    const assistantMode = health?.assistant_mode ?? config?.ai_assistant_mode ?? null;
    const configAiEnabled = config?.ai_assistant_enabled === true;
    const canaryEligible = assistantMode === "internal_canary" && configAiEnabled;

    return {
      authenticated,
      admin_identity: Boolean(bootstrap?.portal_type === "admin" || bootstrap?.role === "admin"),
      canary_eligible: canaryEligible,
      config_ai_enabled: configAiEnabled,
      assistant_mode: assistantMode,
      ai_assistant_enabled_for_session: configAiEnabled && authenticated,
    };
  }, BASE);
}

export async function waitForConfigHydration(page, timeoutMs = 90_000) {
  await page.waitForFunction(
    async (base) => {
      const response = await fetch(`${base}/laravel/api/public/content/config`, {
        credentials: "include",
        headers: { Accept: "application/json" },
      });
      if (!response.ok) return false;
      const json = await response.json();
      return json.ai_assistant_enabled === true;
    },
    BASE,
    { timeout: timeoutMs },
  );
}

export async function sessionPreflight(page) {
  const probe = await probeSession(page);
  const fab = page.getByTestId("ask-jetpakistan-fab");
  const panel = page.getByTestId("ask-jetpakistan-panel");
  const fabVisible = await fab.isVisible().catch(() => false);
  const panelVisible = await panel.isVisible().catch(() => false);

  return {
    session_valid: probe.authenticated === true,
    canary_eligible: probe.canary_eligible === true,
    config_ai_enabled: probe.config_ai_enabled === true,
    ai_assistant_enabled_for_session: probe.ai_assistant_enabled_for_session === true,
    fab_visible: fabVisible || panelVisible,
    ask_opened: panelVisible,
    probe,
  };
}

export async function recoverSession(page) {
  const probe = await probeSession(page);
  if (!probe.authenticated) {
    metrics.sessionLosses += 1;
    return { recovered: false, reason: "SESSION_LOST" };
  }

  if (!probe.config_ai_enabled) {
    await waitForConfigHydration(page, 45_000).catch(() => {});
    const afterWait = await probeSession(page);
    if (afterWait.config_ai_enabled) {
      metrics.configHydrationRecoveries += 1;
    } else {
      await page.reload({ waitUntil: "domcontentloaded", timeout: 120_000 });
      await waitForConfigHydration(page, 45_000).catch(() => {});
      const afterReload = await probeSession(page);
      if (afterReload.config_ai_enabled) {
        metrics.configHydrationRecoveries += 1;
      }
    }
  }

  await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded", timeout: 120_000 });
  await waitForConfigHydration(page, 60_000).catch(() => {});

  const fab = page.getByTestId("ask-jetpakistan-fab");
  const panel = page.getByTestId("ask-jetpakistan-panel");
  const hadFab = await fab.or(panel).isVisible().catch(() => false);

  if (!hadFab) {
    await page.reload({ waitUntil: "domcontentloaded", timeout: 120_000 });
    await waitForConfigHydration(page, 45_000).catch(() => {});
  }

  if (await fab.or(panel).isVisible().catch(() => false)) {
    if (!hadFab) {
      metrics.fabRecoveries += 1;
    }
    return { recovered: true, reason: "FAB_RECOVERED" };
  }

  const finalProbe = await probeSession(page);
  if (!finalProbe.authenticated) {
    metrics.sessionLosses += 1;
    return { recovered: false, reason: "SESSION_LOST" };
  }

  return { recovered: false, reason: "FAB_UNAVAILABLE" };
}

export async function openAskPanel(page) {
  await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded", timeout: 120_000 });
  await waitForConfigHydration(page, 90_000).catch(() => {});

  await page
    .getByTestId("ask-jetpakistan-fab")
    .or(page.getByTestId("ask-jetpakistan-panel"))
    .waitFor({ state: "visible", timeout: 120_000 })
    .catch(() => {});

  let preflight = await sessionPreflight(page);
  if (!preflight.fab_visible) {
    const recovery = await recoverSession(page);
    preflight = await sessionPreflight(page);
    if (!preflight.fab_visible && !recovery.recovered) {
      const err = new Error(
        `FAB not available (${recovery.reason}); eligible=${preflight.canary_eligible} config=${preflight.config_ai_enabled}`,
      );
      err.preflight = preflight;
      err.recovery = recovery;
      throw err;
    }
  }

  const panel = page.getByTestId("ask-jetpakistan-panel");
  const fab = page.getByTestId("ask-jetpakistan-fab");
  if (!(await panel.isVisible().catch(() => false))) {
    await fab.click({ timeout: 30_000 });
  }
  await panel.waitFor({ state: "visible", timeout: 120_000 });
  return preflight;
}

export async function waitForAskReady(page, timeoutMs = 120_000) {
  const preflight = await openAskPanel(page);
  if (!preflight.canary_eligible) {
    throw new Error(`CANARY_NOT_ELIGIBLE mode=${preflight.probe?.assistant_mode ?? "unknown"}`);
  }
  if (!preflight.fab_visible) {
    throw new Error("FAB_UNAVAILABLE");
  }

  const input = page
    .locator('[data-testid="ask-jetpakistan-panel"] input[type="text"], [data-testid="ask-jetpakistan-panel"] input')
    .first();
  await input.waitFor({ state: "visible", timeout: timeoutMs });
  await page.waitForFunction(
    () => {
      const el = document.querySelector(
        '[data-testid="ask-jetpakistan-panel"] input[type="text"], [data-testid="ask-jetpakistan-panel"] input',
      );
      return el && !el.disabled && el.getAttribute("aria-busy") !== "true";
    },
    { timeout: timeoutMs },
  );

  return preflight;
}

export async function waitForConversationIdHydrated(page, expectedId, timeoutMs = 30_000) {
  if (!expectedId) {
    throw new Error("HARNESS_CONVERSATION_ID_NOT_HYDRATED:missing_expected_id");
  }

  await page.waitForFunction(
    (id) => {
      try {
        return sessionStorage.getItem("jp_ai_conversation_id") === id;
      } catch {
        return false;
      }
    },
    expectedId,
    { timeout: timeoutMs },
  );
}

export async function clearConversation(page) {
  const panel = page.getByTestId("ask-jetpakistan-panel");
  if ((await panel.count()) === 0) {
    await openAskPanel(page);
  }

  const snapshot = await page.evaluate(() => {
    let storedId = null;
    try {
      storedId = sessionStorage.getItem("jp_ai_conversation_id");
    } catch {
      storedId = null;
    }
    const el = document.querySelector('[data-testid="ask-jetpakistan-messages"]');
    const messageText = (el?.textContent ?? "").trim();
    return { storedId, messageText };
  });

  const oldConversationId = snapshot.storedId ?? null;
  if (!snapshot.messageText && !oldConversationId) {
    return {
      conversation_id: null,
      skipped: true,
      old_conversation_id: null,
      clear_response_new_id: null,
      session_storage_after_clear: null,
    };
  }

  let attempt = await triggerUiClear(page);
  if (attempt.status === 429) {
    clearThrottleMetrics.clear_429_count += 1;
    const retryAfter = attempt.retry_after_seconds;
    clearThrottleMetrics.retry_after_values.push(retryAfter ?? 61);
    const waitSeconds = boundedThrottleWaitSeconds(retryAfter);
    const waitMs = waitSeconds * 1000;
    clearThrottleMetrics.throttle_wait_ms += waitMs;
    clearThrottleMetrics.bounded_retries += 1;
    await sleepMs(waitMs);
    attempt = await triggerUiClear(page);
  }

  if (attempt.status === 429 || !attempt.ok || !attempt.conversation_id) {
    clearThrottleMetrics.preflight_failures += 1;
    throw new Error(
      `CLEAR_PREFLIGHT_INFRA_FAILURE:status=${attempt.status} id=${attempt.conversation_id ?? "null"}`,
    );
  }

  const newId = attempt.conversation_id;
  if (oldConversationId && newId === oldConversationId) {
    clearThrottleMetrics.preflight_failures += 1;
    throw new Error("HARNESS_CONVERSATION_ID_NOT_ISOLATED:clear_new_equals_old");
  }

  await waitForConversationIdHydrated(page, newId);
  const sessionStorageId = await readSessionConversationId(page);
  if (sessionStorageId !== newId) {
    clearThrottleMetrics.preflight_failures += 1;
    throw new Error(
      `HARNESS_CONVERSATION_ID_NOT_ISOLATED:session=${sessionStorageId ?? "null"}:expected=${newId}`,
    );
  }

  await page.waitForFunction(
    () => {
      const el = document.querySelector('[data-testid="ask-jetpakistan-messages"]');
      return el && (el.textContent ?? "").trim().length === 0;
    },
    { timeout: 60_000 },
  ).catch(() => {});

  await waitForAskReady(page);

  return {
    conversation_id: newId,
    old_conversation_id: oldConversationId,
    clear_response_new_id: newId,
    session_storage_after_clear: sessionStorageId,
    clear_request_id: attempt.request_conversation_id,
    clear_preflight_rate_limited: clearThrottleMetrics.clear_429_count > 0,
  };
}

export async function withCanaryFaultMode(page, faultMode, fn) {
  const token = getCanaryFaultToken();
  if (!token) {
    throw new Error("MISSING_CANARY_FAULT_TOKEN");
  }

  await page.route(CHAT_ROUTE_PATTERN, async (route) => {
    const request = route.request();
    if (request.method() !== "POST") {
      await route.continue();
      return;
    }
    const headers = {
      ...request.headers(),
      "X-JP-AI-Canary-Fault-Mode": faultMode,
      "X-JP-AI-Canary-Fault-Token": token,
    };
    await route.continue({ headers });
  });

  try {
    return await fn();
  } finally {
    await page.unroute(CHAT_ROUTE_PATTERN).catch(() => {});
  }
}

export async function sendMessage(page, text, options = {}) {
  const sendOnce = async () => {
    const input = page
      .locator('[data-testid="ask-jetpakistan-panel"] input[type="text"], [data-testid="ask-jetpakistan-panel"] input')
      .first();
    await input.waitFor({ state: "visible", timeout: 60_000 });
    await page.waitForFunction(
      () => {
        const el = document.querySelector('[data-testid="ask-jetpakistan-panel"] input[type="text"], [data-testid="ask-jetpakistan-panel"] input');
        return el && !el.disabled && el.getAttribute("aria-busy") !== "true";
      },
      { timeout: Number(options.inputReadyTimeoutMs ?? 120_000) },
    );
    const priorText = await page.getByTestId("ask-jetpakistan-messages").innerText().catch(() => "");
    const priorLen = priorText.length;
    await input.fill(text);
    const responsePromise = page.waitForResponse(
      (res) => res.url().includes("/api/public/ai/chat") && res.request().method() === "POST",
      { timeout: Number(options.chatResponseTimeoutMs ?? 90_000) },
    );
    const started = Date.now();
    const sendButton = page.getByRole("button", { name: /send/i });
    await page.waitForFunction(
      () => {
        const btn = document.querySelector('[data-testid="ask-jetpakistan-panel"] button[type="submit"]');
        return btn && !btn.disabled && btn.getAttribute("aria-busy") !== "true";
      },
      { timeout: Number(options.sendReadyTimeoutMs ?? 120_000) },
    );
    let response;
    try {
      await sendButton.click({ timeout: Number(options.sendClickTimeoutMs ?? 120_000) });
      response = await responsePromise;
    } catch (error) {
      await responsePromise.catch(() => {});
      throw error;
    }
    let payload = {};
    try {
      payload = await response.json();
    } catch {
      payload = {};
    }
    const apiMessage = String(payload.message ?? "").trim();
    const timeoutMs = Number(options.responseTimeoutMs ?? 45_000);

    await page
      .waitForFunction(
        ({ snippet, minLen, apiLen }) => {
          const el = document.querySelector('[data-testid="ask-jetpakistan-messages"]');
          if (!el) return false;
          const text = el.textContent ?? "";
          if (text.length <= minLen) return false;
          if (apiLen > 0 && snippet) {
            const probe = snippet.slice(0, Math.min(24, snippet.length));
            return probe.length > 0 && text.includes(probe);
          }
          return text.length > minLen + 8;
        },
        { snippet: apiMessage, minLen: priorLen, apiLen: apiMessage.length },
        { timeout: timeoutMs },
      )
      .catch(async () => {
        await page.waitForFunction(
          () => {
            const busy = document.querySelector('[data-testid="ask-jetpakistan-panel"] [aria-busy="true"]');
            return !busy;
          },
          { timeout: 5000 },
        ).catch(() => {});
        await page.waitForTimeout(1200);
      });

    const messages = await page.getByTestId("ask-jetpakistan-messages").innerText();
    return {
      status: response.status(),
      body: messages,
      payload,
      latency_ms: Date.now() - started,
    };
  };

  if (options.faultMode) {
    return withCanaryFaultMode(page, options.faultMode, sendOnce);
  }

  return sendOnce();
}

export function classifyFailure(preflight, error, pass) {
  if (pass) return null;
  const msg = error instanceof Error ? error.message : String(error ?? "");
  if (preflight?.recovery?.reason === "SESSION_LOST" || /SESSION_LOST/i.test(msg)) {
    return "HARNESS_SESSION";
  }
  if (!preflight?.canary_eligible && preflight?.session_valid) {
    return "PRODUCT_AUTH";
  }
  if (!preflight?.config_ai_enabled || /hydration|config/i.test(msg)) {
    return "HARNESS_CONFIG_HYDRATION";
  }
  if (/FAB|selector|locator|visible/i.test(msg)) {
    return preflight?.canary_eligible ? "PRODUCT_UI" : "HARNESS_SELECTOR";
  }
  if (/timeout/i.test(msg)) {
    return preflight?.canary_eligible ? "HARNESS_TIMEOUT" : "HARNESS_CONFIG_HYDRATION";
  }
  if (/generic unavailable|rate_limited|Too many requests/i.test(msg)) {
    return "INFRASTRUCTURE";
  }
  if (/pattern|match|expect|unavailable assistant/i.test(msg)) {
    return "AI_BEHAVIOR";
  }
  return "OTHER";
}

export async function runIndependentCase(page, caseDef, assertFn) {
  const startedAt = new Date().toISOString();
  let preflight = {};
  let visible = "";
  let lastStatus = 0;
  let screenshotPath = "";
  let failureClass = null;
  let pass = false;
  let errorMessage = null;

  try {
    preflight = await openAskPanel(page);
    if (!preflight.canary_eligible && !preflight.config_ai_enabled) {
      throw new Error(`CANARY_NOT_ELIGIBLE mode=${preflight.probe?.assistant_mode}`);
    }
    if (caseDef.clear !== false) {
      await clearConversation(page);
    }
    for (const msg of caseDef.inputs) {
      const result = await sendMessage(page, msg, { faultMode: caseDef.faultMode });
      lastStatus = result.status;
      visible = result.body;
    }
    assertFn(visible, lastStatus, caseDef.inputs ?? caseDef.steps ?? []);
    pass = true;
    screenshotPath = path.join(evidenceDir, `${caseDef.id}.png`);
    await page.screenshot({ path: screenshotPath, fullPage: false, timeout: 10_000 }).catch(() => {});
  } catch (error) {
    errorMessage = error instanceof Error ? error.message : String(error);
    if (error?.preflight) preflight = { ...preflight, ...error.preflight };
    screenshotPath = path.join(evidenceDir, `${caseDef.id}-fail.png`);
    await page.screenshot({ path: screenshotPath, fullPage: false }).catch(() => {});
    failureClass = classifyFailure(preflight, error, false);
  }

  const record = {
    case_id: caseDef.id,
    started_at: startedAt,
    completed_at: new Date().toISOString(),
    session_valid: preflight.session_valid ?? false,
    canary_eligible: preflight.canary_eligible ?? false,
    config_ai_enabled: preflight.config_ai_enabled ?? false,
    fab_visible: preflight.fab_visible ?? false,
    ask_opened: true,
    input: caseDef.inputs,
    visible_response: visible.slice(-800),
    conversation_state: null,
    confirmation_state: null,
    route_visible: null,
    requested_action: null,
    tool_execution: null,
    rag_behavior: caseDef.extra?.rag_behavior ?? caseDef.rag_behavior ?? null,
    handoff_state: caseDef.extra?.handoff_state ?? caseDef.handoff_state ?? null,
    http_status: lastStatus,
    pass_fail: pass ? "PASS" : "FAIL",
    failure_class: failureClass,
    screenshot_path: screenshotPath,
    trace_path_if_failed: pass ? null : screenshotPath,
    notes: errorMessage,
  };
  appendCaseRecord(record);
  return { pass, record };
}
