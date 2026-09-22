/**
 * JP-AI-LIVE-SEARCH-PERFORMANCE-PROFILING-15 — per-turn segment timing (measure only).
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
} from "./canary-matrix-helpers.mjs";
import { loginAdmin, completeLeadCaptureIfNeeded, isLeadCapturePending } from "./live-readonly-qa-helpers.mjs";

const evidenceDir = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../../docs/evidence/jp-ai-live-search-profile-15",
);
const summaryPath = path.join(evidenceDir, "profile-15-summary.json");
const MARKER = `PROFILE15-${Date.now()}`;
const STEPS = ["LHE to DXB 22 Dec", "one way", "1 adult", "yes confirm"];
const SYNTHETIC_LEAD = { name: "UAT Lead QA", email: "uat05-lead-qa@jetpakistan.pk", phone: "03001234567" };

function captureVpsResources() {
  try {
    const out = execSync(
      'ssh -o BatchMode=yes pkjetp@185.215.166.176 "free -m; uptime; ps aux | grep -E ollama|ai-lab-gateway | grep -v grep"',
      { encoding: "utf8", timeout: 30_000 },
    );
    const mem = out.match(/Mem:\s+(\d+)\s+\d+\s+\d+\s+\d+\s+\d+\s+(\d+)/);
    const swap = out.match(/Swap:\s+\d+\s+(\d+)/);
    const load = out.match(/load average:\s*(.+)/);
    return {
      VPS_RAM_TOTAL: mem ? Number(mem[1]) : null,
      VPS_RAM_AVAILABLE: mem ? Number(mem[2]) : null,
      VPS_SWAP_USED: swap ? Number(swap[1]) : null,
      SYSTEM_LOAD: load ? load[1].trim() : null,
      RESOURCE_PRESSURE: mem && Number(mem[2]) > 1024 ? "NO" : "YES",
    };
  } catch (e) {
    return { error: e instanceof Error ? e.message : String(e) };
  }
}

async function main() {
  if (!loadQaPasswordFromVault("admin")) process.exit(2);
  fs.mkdirSync(evidenceDir, { recursive: true });

  const browser = await chromium.launch({ headless: true });
  if (!storageStateExists("admin")) {
    const b = await browser.newContext();
    const p = await b.newPage();
    await loginAdmin(p);
    await b.storageState({ path: getStoragePath("admin") });
    await b.close();
  }

  const context = await browser.newContext({ storageState: getStoragePath("admin") });
  const page = await context.newPage();
  const chatResponses = [];

  page.on("response", async (res) => {
    if (!res.url().includes("/api/public/ai/chat") || res.request().method() !== "POST") return;
    let payload = {};
    try {
      payload = await res.json();
    } catch {
      payload = {};
    }
    chatResponses.push({
      ts: Date.now(),
      status: res.status(),
      gateway_ms: payload?.meta?.gateway_ms ?? payload?.meta?.lab?.gateway_ms ?? null,
      dialog_state: payload?.meta?.dialog_state ?? null,
      live_supplier_called: payload?.meta?.search_record?.live_supplier_called ?? null,
      intent: payload?.meta?.intent ?? null,
    });
  });

  const segments = [];
  const mark = (name, extra = {}) => segments.push({ segment: name, ts: Date.now(), ...extra });

  mark("T0_START");
  const resourcesBefore = captureVpsResources();
  await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded", timeout: 120_000 });
  await openAskPanel(page);
  mark("T11_PANEL_OPEN");

  const coldStart = Date.now();
  await sendMessage(page, "What payment methods do you accept?", { chatResponseTimeoutMs: 180_000 });
  const coldMs = Date.now() - coldStart;
  mark("T12_COLD_AI_RESPONSE", { ms: coldMs });

  const warmStart = Date.now();
  await sendMessage(page, "baggage allowance", { chatResponseTimeoutMs: 120_000 });
  const warmMs = Date.now() - warmStart;
  mark("T12_WARM_AI_RESPONSE", { ms: warmMs });

  await clearConversation(page);
  await waitForAskReady(page);
  mark("T1_SEARCH_FLOW_START");

  const stepTimings = [];
  const searchFlowStart = Date.now();
  for (let i = 0; i < STEPS.length; i += 1) {
    const stepStart = Date.now();
    const result = await sendMessage(page, STEPS[i], { chatResponseTimeoutMs: 600_000 });
    const stepMs = Date.now() - stepStart;
    if (isLeadCapturePending(result.payload)) {
      const lead = await completeLeadCaptureIfNeeded(page, { lead: SYNTHETIC_LEAD, initialPayload: result.payload });
      if (!lead.completed) throw new Error(lead.reason ?? "LEAD_CAPTURE_FAILED");
    }
    stepTimings.push({
      step: i + 1,
      message: STEPS[i],
      ms: stepMs,
      dialog_state: result.payload?.meta?.dialog_state ?? null,
      live_supplier_called: result.payload?.meta?.search_record?.live_supplier_called ?? null,
      status: result.status,
    });
    mark(`T_STEP_${i + 1}`, { ms: stepMs });
  }
  const totalMs = Date.now() - searchFlowStart;
  mark("T12_SEARCH_COMPLETE", { ms: totalMs });

  const resourcesAfter = captureVpsResources();

  const summary = {
    phase: "JP-AI-LIVE-SEARCH-PERFORMANCE-PROFILING-15",
    marker: MARKER,
    completed_at: new Date().toISOString(),
    COLD_AI_MS: coldMs,
    WARM_AI_MS: warmMs,
    TOTAL_MS: totalMs,
    step_timings: stepTimings,
    chat_responses: chatResponses,
    segments,
    resources_before: resourcesBefore,
    resources_after: resourcesAfter,
    decomposition: {
      MODEL_COLD_MS: coldMs,
      MODEL_WARM_MS: warmMs,
      SEARCH_MULTI_TURN_MS: totalMs,
      CONFIRM_STEP_MS: stepTimings[3]?.ms ?? null,
      PRECEDING_TURNS_MS: stepTimings.slice(0, 3).reduce((s, t) => s + t.ms, 0),
    },
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
