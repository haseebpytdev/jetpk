/** UAT-04: single LS-12 browser proof with route metadata capture */
import { chromium } from "playwright";
import { getStoragePath } from "../../dashboard/scripts/jp-dash-03-acceptance/auth-storage.mjs";
import { clearConversation, openAskPanel, sendMessage } from "./canary-matrix-helpers.mjs";
import { completeLeadCaptureIfNeeded, waitForChatInputReady } from "./live-readonly-qa-helpers.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const evidenceDir = path.resolve(__dirname, "../../docs/evidence/jp-ai-live-readonly-final-qa-01");
fs.mkdirSync(evidenceDir, { recursive: true });

const steps = ["dubay se lahore 15 Jan", "one way", "1 adult", "haan"];
const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ storageState: getStoragePath("admin") });
const page = await ctx.newPage();

let lastPayload = {};
let visible = "";
let error = null;

try {
  await openAskPanel(page);
  await clearConversation(page);
  await waitForChatInputReady(page, 180_000);
  for (const msg of steps) {
    await waitForChatInputReady(page, 180_000);
    const result = await sendMessage(page, msg, { responseTimeoutMs: 240_000 });
    lastPayload = result.payload ?? {};
    visible = result.body;
    if (result.status >= 500) throw new Error(`HTTP_${result.status}`);
    if (result.payload?.status === "lead_capture_required" || result.payload?.mode === "LEAD_CAPTURE") {
      const lead = await completeLeadCaptureIfNeeded(page, { leadTimeoutMs: 180_000 });
      if (!lead.completed || lead.status >= 400) throw new Error(lead.reason ?? "LEAD_CAPTURE_FAILED");
      lastPayload = lead.payload ?? lastPayload;
      visible = await page.getByTestId("ask-jetpakistan-messages").innerText().catch(() => visible);
    }
  }
} catch (e) {
  error = e instanceof Error ? e.message : String(e);
}

const meta = lastPayload.meta ?? {};
const slots = meta.intent?.slots ?? {};
const searchRecord = meta.search_record ?? null;
const report = {
  case_id: "LS-12-UAT04",
  pass_fail: error ? "FAIL" : "PASS",
  error,
  visible_snippet: visible.slice(0, 1200),
  slots,
  search_record: searchRecord,
  dialog_state: meta.dialog_state,
  lab_sha: meta.lab_sha,
};
const out = path.join(evidenceDir, "uat04-ls12-proof.json");
fs.writeFileSync(out, JSON.stringify(report, null, 2));
await page.screenshot({ path: path.join(evidenceDir, "uat04-ls12-proof.png"), fullPage: false }).catch(() => {});
console.log(JSON.stringify(report, null, 2));
await browser.close();
process.exit(error ? 2 : 0);
