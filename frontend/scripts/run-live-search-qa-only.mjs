/**
 * Live read-only search + soak QA (single login session).
 */
import { chromium } from "playwright";
import fs from "node:fs";
import { loadQaPasswordFromVault } from "../../dashboard/scripts/jp-dash-03-acceptance/credential-vault.mjs";
import {
  ADMIN_EMAIL,
  evidenceDir,
  loginAdmin,
  recordCase,
  resetReport,
  runConfirmedLiveSearch,
  summaryPath,
  clearConversation,
  openAskPanel,
  sendMessage,
} from "./live-readonly-qa-helpers.mjs";

const password = loadQaPasswordFromVault("admin");
const summary = { phase: "LIVE_SEARCH_QA_ONLY", started_at: new Date().toISOString(), live_pass: 0, live_fail: 0, stability: { total: 0, success: 0, timeout: 0, http_500: 0 } };

const LIVE_CASES = [
  { id: "LS-01", steps: ["LHE to JED 20 Dec", "one way", "1 adult", "yes"], route: { origin: "LHE", destination: "JED" } },
  { id: "LS-02", steps: ["LHE to DXB 22 Dec", "one way", "1 adult", "yes confirm"], route: { origin: "LHE", destination: "DXB" } },
  { id: "LS-03", steps: ["ISB to DXB 25 Dec", "one way", "2 adults", "yes"], route: { origin: "ISB", destination: "DXB" } },
  { id: "LS-04", steps: ["ISB to LHR 15 Jan", "one way", "1 adult", "yes"], route: { origin: "ISB", destination: "LHR" } },
  { id: "LS-05", steps: ["KHI to DXB 18 Dec", "one way", "1 adult", "yes"], route: { origin: "KHI", destination: "DXB" } },
  { id: "LS-06", steps: ["KHI to JED 28 Dec", "one way", "1 adult", "yes"], route: { origin: "KHI", destination: "JED" } },
  { id: "LS-07", steps: ["LHE to DXB 20 Dec return 27 Dec", "return", "1 adult", "yes"], route: { origin: "LHE", destination: "DXB" } },
  { id: "LS-08", steps: ["LHE to JED 10 Jan", "one way", "2 adults 1 child", "yes"], route: { origin: "LHE", destination: "JED" } },
  { id: "LS-09", steps: ["ISB to DXB 12 Dec morning", "one way", "1 adult", "yes"], route: { origin: "ISB", destination: "DXB" } },
  { id: "LS-10", steps: ["KHI to DXB 5 Jan direct", "one way", "1 adult", "yes"], route: { origin: "KHI", destination: "DXB" } },
  { id: "LS-11", steps: ["LHE to DXB 30 Dec PIA preferred", "one way", "1 adult", "yes"], route: { origin: "LHE", destination: "DXB" } },
  { id: "LS-12", steps: ["dubay se lahore 15 Jan", "one way", "1 adult", "haan confirm"], route: { origin: "DXB", destination: "LHE" } },
];

async function main() {
  if (!password) process.exit(2);
  resetReport();
  fs.mkdirSync(evidenceDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  try {
    await loginAdmin(page);
    for (const c of LIVE_CASES) {
      const r = await runConfirmedLiveSearch(page, c.id, c.steps, c.route);
      if (r.pass) summary.live_pass += 1;
      else summary.live_fail += 1;
      await page.waitForTimeout(10000);
    }
    const prompts = ["LHE to DXB 20 Dec", "one way", "1 adult", "baggage allowance", "yes", "no", "dubay se lahor"];
    for (let i = 0; i < 100; i++) {
      try {
        if (i % 10 === 0) {
          await openAskPanel(page);
          if (i % 20 === 0) await clearConversation(page);
        }
        const r = await sendMessage(page, prompts[i % prompts.length], { responseTimeoutMs: 120_000 });
        summary.stability.total += 1;
        if (r.status >= 500) summary.stability.http_500 += 1;
        else if (r.body?.trim()) summary.stability.success += 1;
      } catch {
        summary.stability.timeout += 1;
      }
      if (i % 8 === 7) await page.waitForTimeout(5000);
    }
  } finally {
    await browser.close();
    summary.completed_at = new Date().toISOString();
    fs.writeFileSync(summaryPath, JSON.stringify(summary, null, 2));
    console.log(JSON.stringify(summary, null, 2));
  }
}

main();
