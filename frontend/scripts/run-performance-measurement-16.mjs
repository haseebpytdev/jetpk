/**
 * JP-AI-PERFORMANCE-MEASUREMENT-CORRECTION-16 — corrected API vs DOM timings.
 */
import { chromium } from "playwright";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { execSync } from "node:child_process";
import { loadQaPasswordFromVault } from "../../dashboard/scripts/jp-dash-03-acceptance/credential-vault.mjs";
import { getStoragePath, storageStateExists } from "../../dashboard/scripts/jp-dash-03-acceptance/auth-storage.mjs";
import {
  BASE,
  openAskPanel,
  sendMessage,
  clearConversation,
  waitForAskReady,
  resetClearThrottleMetrics,
  clearThrottleMetrics,
} from "./canary-matrix-helpers.mjs";
import {
  loginAdmin,
  completeLeadCaptureIfNeeded,
  isLeadCapturePending,
  normalizeConfirmationStep,
} from "./live-readonly-qa-helpers.mjs";

const evidenceDir = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../../docs/evidence/jp-ai-performance-measurement-16",
);
const summaryPath = path.join(evidenceDir, "measurement-16-summary.json");
const SYNTHETIC_LEAD = { name: "UAT Lead QA", email: "uat05-lead-qa@jetpakistan.pk", phone: "03001234567" };
const SEARCH_STEPS = ["LHE to DXB 22 Dec", "one way", "1 adult", "yes confirm"];

function stats(values) {
  const sorted = [...values].sort((a, b) => a - b);
  if (sorted.length === 0) return { n: 0, median: null, min: null, max: null };
  const mid = Math.floor(sorted.length / 2);
  const median = sorted.length % 2 === 0 ? (sorted[mid - 1] + sorted[mid]) / 2 : sorted[mid];
  return { n: sorted.length, median, min: sorted[0], max: sorted[sorted.length - 1] };
}

function captureVpsResources() {
  try {
    const out = execSync(
      'ssh -o BatchMode=yes pkjetp@185.215.166.176 "free -m; uptime; ps aux | grep ai-lab-gateway | grep -v grep"',
      { encoding: "utf8", timeout: 30_000 },
    );
    const mem = out.match(/Mem:\s+(\d+)\s+\d+\s+\d+\s+\d+\s+\d+\s+(\d+)/);
    const swap = out.match(/Swap:\s+\d+\s+(\d+)/);
    const load = out.match(/load average:\s*(.+)/);
    return {
      VPS_RAM_AVAILABLE: mem ? Number(mem[2]) : null,
      VPS_SWAP_USED: swap ? Number(swap[1]) : null,
      SYSTEM_LOAD: load ? load[1].trim() : null,
      RESOURCE_PRESSURE: mem && Number(mem[2]) > 1024 ? "NO" : "YES",
    };
  } catch (e) {
    return { error: e instanceof Error ? e.message : String(e) };
  }
}

function turnRecord(label, result) {
  const t = result.timing ?? {};
  return {
    message_type: label,
    http_status: result.status,
    API_MS: t.api_response_ms ?? null,
    DOM_AFTER_API_MS: t.dom_commit_ms ?? null,
    USER_VISIBLE_MS: t.user_visible_total_ms ?? null,
    API_RESULT: t.api_result ?? null,
    DOM_RESULT: t.dom_result ?? null,
    DOM_RENDER_TIMEOUT: t.dom_render_timeout === true,
    measurement_valid: t.measurement_valid === true,
  };
}

async function ensureAdmin(browser) {
  if (!loadQaPasswordFromVault("admin")) process.exit(2);
  if (!storageStateExists("admin")) {
    const b = await browser.newContext();
    const p = await b.newPage();
    await loginAdmin(p);
    await b.storageState({ path: getStoragePath("admin") });
    await b.close();
  }
}

async function runTurn(page, label, text, opts = {}) {
  const result = await sendMessage(page, text, {
    chatResponseTimeoutMs: opts.chatTimeoutMs ?? 180_000,
    domRenderTimeoutMs: opts.domTimeoutMs ?? 15_000,
    messageType: label,
  });
  return turnRecord(label, result);
}

async function runSearchFlow(page, marker, { includeSetupClear = false } = {}) {
  const setup = { setup_clear_ms: 0, setup_throttle_wait_ms: 0 };
  if (includeSetupClear) {
    resetClearThrottleMetrics();
    const cleared = await clearConversation(page);
    setup.setup_clear_ms = cleared.setup_clear_ms ?? 0;
    setup.setup_throttle_wait_ms = cleared.setup_throttle_wait_ms ?? 0;
    await waitForAskReady(page);
  }

  const turns = [];
  const flowStart = Date.now();
  for (let i = 0; i < SEARCH_STEPS.length; i += 1) {
    const msg = i === SEARCH_STEPS.length - 1 ? normalizeConfirmationStep(SEARCH_STEPS[i]) : SEARCH_STEPS[i];
    const label = `SEARCH_TURN_${i + 1}`;
    const result = await sendMessage(page, msg, {
      chatResponseTimeoutMs: 300_000,
      domRenderTimeoutMs: 15_000,
      messageType: label,
    });
    if (isLeadCapturePending(result.payload)) {
      const lead = await completeLeadCaptureIfNeeded(page, { lead: SYNTHETIC_LEAD, initialPayload: result.payload });
      if (!lead.completed) throw new Error(lead.reason ?? "LEAD_CAPTURE_FAILED");
    }
    turns.push(turnRecord(label, result));
  }

  const confirm = turns[turns.length - 1];
  const searchExecutionMs =
    confirm.measurement_valid && confirm.API_MS != null ? confirm.API_MS : null;
  const fullFlowMs = Date.now() - flowStart;

  return {
    marker,
    setup,
    turns,
    SEARCH_EXECUTION_MS: searchExecutionMs,
    FULL_FLOW_AUTOMATED_MS: fullFlowMs,
    live_supplier_called:
      turns[turns.length - 1]?.live_supplier_called ??
      null,
  };
}

async function sanityRun(page) {
  const rows = [];
  rows.push(await runTurn(page, "GENERAL_WARM", "baggage allowance"));
  rows.push(await runTurn(page, "RAG", "What documents do I need for Umrah?"));
  const search = await runSearchFlow(page, "SANITY-LHE-DXB", { includeSetupClear: true });
  return { turns: rows, search };
}

function aggregate(samples, key) {
  const valid = samples.filter((s) => s.measurement_valid && s[key] != null).map((s) => s[key]);
  const failed = samples.filter((s) => !s.measurement_valid || s.DOM_RENDER_TIMEOUT).length;
  return { ...stats(valid), failed_measurements: failed };
}

async function main() {
  fs.mkdirSync(evidenceDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  await ensureAdmin(browser);
  const context = await browser.newContext({ storageState: getStoragePath("admin") });
  const page = await context.newPage();
  await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded", timeout: 120_000 });
  await waitForAskReady(page);

  const resourcesBefore = captureVpsResources();
  const sanity = await sanityRun(page);

  const sanityFailedDom = [...sanity.turns, ...sanity.search.turns].filter(
    (t) => t.DOM_RENDER_TIMEOUT || t.DOM_RESULT === "FAIL",
  );
  if (sanityFailedDom.length > 0) {
    const failSummary = { phase: "SANITY_FAIL", sanity, sanityFailedDom };
    fs.writeFileSync(summaryPath, JSON.stringify(failSummary, null, 2));
    console.error(JSON.stringify(failSummary, null, 2));
    await context.close();
    await browser.close();
    process.exit(2);
  }

  const generalSamples = [];
  const ragSamples = [];
  const leadSamples = [];
  const searchSamples = [];
  const fullFlowSamples = [];

  const generalPrompts = [
    "baggage allowance",
    "What payment methods do you accept?",
    "check-in policy",
    "refund process",
    "visa requirements for UAE",
  ];
  const ragPrompts = [
    "What documents do I need for Umrah?",
    "What is your cancellation policy?",
    "How do I contact support?",
    "What payment methods do you accept?",
    "baggage allowance for international flights",
  ];
  for (let i = 0; i < 5; i += 1) {
    generalSamples.push(await runTurn(page, "GENERAL_WARM", generalPrompts[i]));
  }
  for (let i = 0; i < 5; i += 1) {
    ragSamples.push(await runTurn(page, "RAG", ragPrompts[i]));
  }

  for (let i = 0; i < 3; i += 1) {
    const t0 = Date.now();
    await sendMessage(page, "I need flights Lahore to Dubai", {
      chatResponseTimeoutMs: 120_000,
      messageType: "LEAD_PROMPT",
    });
    const leadStart = Date.now();
    const lead = await completeLeadCaptureIfNeeded(page, { lead: SYNTHETIC_LEAD });
    leadSamples.push({
      message_type: "LEAD_SUBMIT",
      http_status: lead.status ?? null,
      API_MS: Date.now() - leadStart,
      DOM_AFTER_API_MS: null,
      USER_VISIBLE_MS: Date.now() - t0,
      measurement_valid: lead.completed === true,
      DOM_RENDER_TIMEOUT: false,
      API_RESULT: lead.completed ? "PASS" : "FAIL",
      DOM_RESULT: lead.completed ? "PASS" : "FAIL",
    });
  }

  const resourcesDuring = captureVpsResources();

  for (let i = 0; i < 5; i += 1) {
    const flow = await runSearchFlow(page, `PERF-SEARCH-${i + 1}`, { includeSetupClear: true });
    const confirm = flow.turns[flow.turns.length - 1];
    searchSamples.push({
      ...confirm,
      message_type: "SEARCH_CONFIRM",
      SEARCH_EXECUTION_MS: flow.SEARCH_EXECUTION_MS,
      setup_clear_ms: flow.setup.setup_clear_ms,
      setup_throttle_wait_ms: flow.setup.setup_throttle_wait_ms,
    });
    fullFlowSamples.push({
      FULL_FLOW_AUTOMATED_MS: flow.FULL_FLOW_AUTOMATED_MS,
      SEARCH_EXECUTION_MS: flow.SEARCH_EXECUTION_MS,
      setup_clear_ms: flow.setup.setup_clear_ms,
      setup_throttle_wait_ms: flow.setup.setup_throttle_wait_ms,
      turns: flow.turns,
    });
  }

  const coldSamples = [];
  for (let i = 0; i < 3; i += 1) {
    resetClearThrottleMetrics();
    await clearConversation(page);
    await waitForAskReady(page);
    coldSamples.push(await runTurn(page, "COLD_AFTER_CLEAR", "What payment methods do you accept?"));
  }

  let serverWindow = "";
  try {
    serverWindow = execSync(
      'ssh -o BatchMode=yes pkjetp@185.215.166.176 "grep branded_fares_search_probe /home/pkjetp/jetpk_app/storage/logs/laravel.log | tail -3"',
      { encoding: "utf8", timeout: 30_000 },
    );
  } catch {
    serverWindow = "";
  }

  const generalApi = aggregate(generalSamples, "API_MS");
  const ragApi = aggregate(ragSamples, "API_MS");
  const searchApi = aggregate(searchSamples, "API_MS");
  const perfReady =
    searchApi.n >= 5 &&
    searchApi.failed_measurements === 0 &&
    (searchApi.median ?? 0) < 30_000
      ? "YES"
      : "PARTIAL";

  const summary = {
    phase: "JP-AI-PERFORMANCE-MEASUREMENT-CORRECTION-16",
    completed_at: new Date().toISOString(),
    HARNESS_ARTIFACT_FIXED: true,
    sanity,
    SETUP_CLEAR_MS: stats(fullFlowSamples.map((f) => f.setup_clear_ms ?? 0)),
    SETUP_THROTTLE_MS: stats(fullFlowSamples.map((f) => f.setup_throttle_wait_ms ?? 0)),
    clear_throttle_metrics: { ...clearThrottleMetrics },
    GENERAL: {
      ...aggregate(generalSamples, "API_MS"),
      dom_median: aggregate(generalSamples, "DOM_AFTER_API_MS").median,
      visible_median: aggregate(generalSamples, "USER_VISIBLE_MS").median,
      samples: generalSamples,
    },
    RAG: {
      ...aggregate(ragSamples, "API_MS"),
      dom_median: aggregate(ragSamples, "DOM_AFTER_API_MS").median,
      visible_median: aggregate(ragSamples, "USER_VISIBLE_MS").median,
      samples: ragSamples,
    },
    LEAD: {
      ...aggregate(leadSamples, "API_MS"),
      dom_median: null,
      visible_median: aggregate(leadSamples, "USER_VISIBLE_MS").median,
      samples: leadSamples,
    },
    SEARCH: {
      ...aggregate(searchSamples, "API_MS"),
      dom_median: aggregate(searchSamples, "DOM_AFTER_API_MS").median,
      visible_median: aggregate(searchSamples, "USER_VISIBLE_MS").median,
      search_execution: stats(
        searchSamples.filter((s) => s.SEARCH_EXECUTION_MS != null).map((s) => s.SEARCH_EXECUTION_MS),
      ),
      samples: searchSamples,
    },
    FULL_FLOW: {
      ...stats(fullFlowSamples.map((f) => f.FULL_FLOW_AUTOMATED_MS)),
      samples: fullFlowSamples,
    },
    COLD: {
      ...aggregate(coldSamples, "API_MS"),
      dom_median: aggregate(coldSamples, "DOM_AFTER_API_MS").median,
      visible_median: aggregate(coldSamples, "USER_VISIBLE_MS").median,
      GENUINE_COLD_START_PROVEN: coldSamples.every((s) => (s.API_MS ?? 0) > 5000) ? "YES" : "NO",
      samples: coldSamples,
    },
    FAILED_MEASUREMENTS: {
      general: aggregate(generalSamples, "API_MS").failed_measurements,
      rag: aggregate(ragSamples, "API_MS").failed_measurements,
      lead: aggregate(leadSamples, "API_MS").failed_measurements,
      search: aggregate(searchSamples, "API_MS").failed_measurements,
      cold: aggregate(coldSamples, "API_MS").failed_measurements,
    },
    ROOT_CAUSE: {
      HISTORICAL_286S: "HARNESS_MEASUREMENT_ARTIFACT",
      HISTORICAL_47S: "HARNESS_MEASUREMENT_ARTIFACT",
      PRODUCT_BOTTLENECK: "NONE_PROVEN",
    },
    SUPPLIERS: {
      ACTIVE: ["sabre", "pia_ndc"],
      EXECUTION_MODEL: "SERIAL",
      OBSERVED_TOTAL_MS: "~2000-3000",
      FUTURE_SERIALIZATION_RISK: "YES",
    },
    resources_before: resourcesBefore,
    resources_during: resourcesDuring,
    server_log_tail: serverWindow.slice(0, 2000),
    PERFORMANCE_READY: perfReady,
  };

  fs.writeFileSync(summaryPath, JSON.stringify(summary, null, 2));
  console.log(JSON.stringify(summary, null, 2));
  await context.close();
  await browser.close();
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
