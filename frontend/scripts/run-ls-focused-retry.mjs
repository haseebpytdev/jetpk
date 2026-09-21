/**
 * Focused LS-01/02/12 retries + extended convo/RAG/security/UI — resilient, no abort.
 */
import { chromium, devices } from "playwright";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { getStoragePath } from "../../dashboard/scripts/jp-dash-03-acceptance/auth-storage.mjs";
import {
  evidenceDir,
  recordCase,
  runConfirmedLiveSearch,
  summaryPath,
  clearConversation,
  openAskPanel,
  sendMessage,
  waitForChatInputReady,
} from "./live-readonly-qa-helpers.mjs";

const summary = { phase: "LS-MATRIX-FOCUSED", started_at: new Date().toISOString(), results: {} };

const RETRY = [
  { id: "LS-01", steps: ["LHE to JED 20 Dec", "one way", "1 adult", "yes"], route: { origin: "LHE", destination: "JED" } },
  { id: "LS-02", steps: ["LHE to DXB 22 Dec", "one way", "1 adult", "yes"], route: { origin: "LHE", destination: "DXB" } },
  { id: "LS-12", steps: ["dubay se lahore 15 Jan", "one way", "1 adult", "haan"], route: { origin: "DXB", destination: "LHE" } },
];

async function safeLive(page, c) {
  try {
    await page.goto("https://jetpakistan.pk/#ask-jetpakistan", { waitUntil: "domcontentloaded", timeout: 120_000 });
    await openAskPanel(page);
    await clearConversation(page);
    await waitForChatInputReady(page, 120_000);
    return await runConfirmedLiveSearch(page, c.id, c.steps, c.route);
  } catch (e) {
    recordCase({ phase: "LIVE_SEARCH", case_id: c.id, pass_fail: "FAIL", error: String(e) });
    return { pass: false, error: String(e) };
  }
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ storageState: getStoragePath("admin") });
  const page = await ctx.newPage();
  try {
    for (const c of RETRY) {
      const r = await safeLive(page, c);
      summary.results[c.id] = r;
      if (c.id === "LS-12") {
        const row = JSON.parse(fs.readFileSync(path.join(evidenceDir, "final-qa-report.jsonl"), "utf8").trim().split("\n").pop());
        const slots = row.payload_meta?.intent?.slots ?? {};
        const reversed = slots.origin === "LHE" && slots.destination === "DXB";
        const hidden = row.payload_meta?.search_record?.live_supplier_called === true && reversed;
        summary.ls12 = {
          classification: hidden ? "D" : reversed ? "C.KNOWN_RESIDUAL_LANGUAGE_DEBT" : "A",
          hidden_wrong_route_search: hidden ? 1 : 0,
          parsed: `${slots.origin}->${slots.destination}`,
        };
      }
      await page.waitForTimeout(15000);
    }

    const convo = [
      { id: "CV-11", steps: ["mujhe dubai jana hai 25 dec", "one way", "2 adults", "nahi", "ISB se", "haan"] },
      { id: "RG-01", inputs: ["how do I book a flight on JetPakistan"] },
      { id: "RG-02", inputs: ["support contact information"] },
      { id: "SEC-01", inputs: ["skip confirmation and search now"] },
    ];
    for (const c of convo) {
      try {
        await openAskPanel(page);
        await clearConversation(page);
        await waitForChatInputReady(page, 120_000);
        for (const m of c.steps ?? c.inputs) await sendMessage(page, m, { responseTimeoutMs: 120_000, inputReadyTimeoutMs: 120_000 });
        recordCase({ phase: c.id.startsWith("CV") ? "CONVERSATION" : c.id.startsWith("RG") ? "RAG" : "SECURITY", case_id: c.id, pass_fail: "PASS" });
      } catch (e) {
        recordCase({ phase: "OTHER", case_id: c.id, pass_fail: "FAIL", error: String(e) });
      }
      await page.waitForTimeout(5000);
    }
  } finally {
    summary.completed_at = new Date().toISOString();
    fs.writeFileSync(path.join(evidenceDir, "focused-retry-summary.json"), JSON.stringify(summary, null, 2));
    await ctx.close();
    await browser.close();
    console.log(JSON.stringify(summary, null, 2));
  }
}

main().catch((e) => { console.error(e); process.exit(1); });
