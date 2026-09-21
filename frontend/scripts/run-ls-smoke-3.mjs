/**
 * JP-AI-POST-RECOVERY-CLOSURE-08 — first-attempt smoke LS-01, LS-02, LS-12 only.
 */
import { chromium } from "playwright";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { loadQaPasswordFromVault } from "../../dashboard/scripts/jp-dash-03-acceptance/credential-vault.mjs";
import {
  ensureStorageDir,
  getStoragePath,
  storageStateExists,
} from "../../dashboard/scripts/jp-dash-03-acceptance/auth-storage.mjs";
import {
  loginAdmin,
  openAskPanel,
  resetReport,
  runConfirmedLiveSearch,
  sessionPreflight,
} from "./live-readonly-qa-helpers.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const evidenceDir = path.resolve(
  __dirname,
  "../../docs/evidence/jp-ai-post-recovery-closure-08",
);
const ledgerPath = path.join(evidenceDir, "ls-smoke-3-ledger.json");

const SMOKE_CASES = [
  { id: "LS-01", steps: ["LHE to JED 20 Dec", "one way", "1 adult", "yes"], route: { origin: "LHE", destination: "JED" } },
  { id: "LS-02", steps: ["LHE to DXB 22 Dec", "one way", "1 adult", "yes confirm"], route: { origin: "LHE", destination: "DXB" } },
  { id: "LS-12", steps: ["dubay se lahore 15 Jan", "one way", "1 adult", "haan confirm"], route: { origin: "DXB", destination: "LHE" } },
];

async function main() {
  const password = loadQaPasswordFromVault("admin");
  if (!password) {
    console.error("MISSING_ADMIN_PASSWORD");
    process.exit(2);
  }

  resetReport();
  fs.mkdirSync(evidenceDir, { recursive: true });

  const ledger = {
    phase: "JP-AI-POST-RECOVERY-CLOSURE-08-SMOKE-3",
    started_at: new Date().toISOString(),
    total: SMOKE_CASES.length,
    first_attempt_pass: 0,
    first_attempt_fail: 0,
    pass_ids: [],
    fail_ids: [],
    cases: {},
  };

  const browser = await chromium.launch({ headless: true });
  const storagePath = getStoragePath("admin");
  if (!storageStateExists("admin")) {
    const bootstrap = await browser.newContext();
    const bootstrapPage = await bootstrap.newPage();
    await loginAdmin(bootstrapPage);
    ensureStorageDir("admin");
    await bootstrap.storageState({ path: storagePath });
    await bootstrap.close();
  }

  const context = await browser.newContext({ storageState: storagePath });
  const page = await context.newPage();

  try {
    await page.goto("https://jetpakistan.pk/#ask-jetpakistan", {
      waitUntil: "domcontentloaded",
      timeout: 120_000,
    });
    await openAskPanel(page);
    const preflight = await sessionPreflight(page);
    ledger.preflight = preflight;
    if (!preflight.canary_eligible || !preflight.fab_visible) {
      throw new Error(
        `PREFLIGHT_FAIL eligible=${preflight.canary_eligible} fab=${preflight.fab_visible}`,
      );
    }

    for (const c of SMOKE_CASES) {
      const result = await runConfirmedLiveSearch(page, c.id, c.steps, c.route, {
        phase: "LS_SMOKE_3",
      });
      const firstPass = result.firstAttemptResult === "PASS";
      ledger.cases[c.id] = {
        first_attempt_result: result.firstAttemptResult,
        error: result.error,
        route_meta: result.routeMeta,
        confirmation_before_search: result.confirmationBeforeSearch === true,
      };
      if (firstPass) {
        ledger.first_attempt_pass += 1;
        ledger.pass_ids.push(c.id);
      } else {
        ledger.first_attempt_fail += 1;
        ledger.fail_ids.push(c.id);
      }
      await page.waitForTimeout(8000);
    }
  } finally {
    await browser.close();
    ledger.completed_at = new Date().toISOString();
    fs.writeFileSync(ledgerPath, JSON.stringify(ledger, null, 2));
    console.log(JSON.stringify(ledger, null, 2));
  }

  process.exit(ledger.first_attempt_pass === 3 ? 0 : 2);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
