/**
 * JP-AI-LIVE-SEARCH-MATRIX-CLOSURE-08 — strict first-attempt LS-01..LS-12 certification.
 * Retries are diagnostic only; certification uses first_attempt_result only.
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
  ADMIN_EMAIL,
  BASE,
  evidenceDir,
  loginAdmin,
  recordCase,
  resetReport,
  runConfirmedLiveSearch,
  openAskPanel,
  sessionPreflight,
} from "./live-readonly-qa-helpers.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ledgerPath = path.join(evidenceDir, "ls-strict-matrix-ledger.json");

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

function classifyStability(error) {
  const msg = String(error ?? "");
  if (/HTTP_5\d\d/.test(msg)) return "HTTP_500";
  if (/FAB_UNAVAILABLE|FAB not available/i.test(msg)) return "FAB_UNAVAILABLE";
  if (/AI assistant is temporarily unavailable|AI_UNAVAILABLE/i.test(msg)) return "AI_UNAVAILABLE";
  if (/COLLECTING_STALL/.test(msg)) return "COLLECTING_STALL";
  if (/STATE|ROUTE_SLOT_MISMATCH|ROUTE_VISIBLE_MISMATCH|RECAP_ROUTE_MISMATCH/.test(msg)) return "STATE_LOSS";
  if (/ENOTFOUND|DNS|getaddrinfo/i.test(msg)) return "DNS_ERRORS";
  if (/NOT_LIVE_SEARCH_RESPONSE|EMPTY/.test(msg)) return "EMPTY_RESPONSE";
  return null;
}

async function main() {
  const password = loadQaPasswordFromVault("admin");
  if (!password) {
    console.error("MISSING_ADMIN_PASSWORD");
    process.exit(2);
  }

  resetReport();
  fs.mkdirSync(evidenceDir, { recursive: true });

  const ledger = {
    phase: "JP-AI-LIVE-SEARCH-MATRIX-CLOSURE-08",
    started_at: new Date().toISOString(),
    total: LIVE_CASES.length,
    first_attempt_pass: 0,
    first_attempt_fail: 0,
    retries_used_for_certification: 0,
    pass_ids: [],
    fail_ids: [],
    cases: {},
    stability: {
      HTTP_500: 0,
      AI_UNAVAILABLE: 0,
      FAB_UNAVAILABLE: 0,
      DNS_ERRORS: 0,
      STATE_LOSS: 0,
      COLLECTING_STALL: 0,
      EMPTY_RESPONSE: 0,
      DUPLICATE_RESPONSE: 0,
    },
    route: {
      hidden_wrong_route_search: 0,
      route_state_bleed: 0,
      lhr_to_lhe_regression: 0,
    },
    confirmation: {
      before_search: 0,
      search_without_confirmation: 0,
    },
    suppliers: {
      search_calls: 0,
      mutations: 0,
    },
    conversation_isolation: "API_CLEAR_PER_CASE",
    state_reset_method: "POST_/api/public/ai/clear+sessionStorage",
    fab_readiness: "waitForAskReady",
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
    await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded", timeout: 120_000 });
    await openAskPanel(page);
    const preflight = await sessionPreflight(page);
    if (!preflight.canary_eligible || !preflight.fab_visible) {
      throw new Error(
        `PREFLIGHT_FAIL eligible=${preflight.canary_eligible} fab=${preflight.fab_visible} config=${preflight.config_ai_enabled}`,
      );
    }

    for (const c of LIVE_CASES) {
      const result = await runConfirmedLiveSearch(page, c.id, c.steps, c.route, {
        phase: "LS_STRICT",
      });
      const firstPass = result.firstAttemptResult === "PASS";
      ledger.cases[c.id] = {
        first_attempt_result: result.firstAttemptResult,
        retry_attempts: 0,
        final_diagnostic_result: firstPass ? "PASS" : "FAIL",
        error: result.error,
        route_meta: result.routeMeta,
        confirmation_before_search: result.confirmationBeforeSearch === true,
      };

      if (firstPass) {
        ledger.first_attempt_pass += 1;
        ledger.pass_ids.push(c.id);
        ledger.suppliers.search_calls += 1;
        if (result.confirmationBeforeSearch) ledger.confirmation.before_search += 1;
        else ledger.confirmation.search_without_confirmation += 1;
      } else {
        ledger.first_attempt_fail += 1;
        ledger.fail_ids.push(c.id);
        const bucket = classifyStability(result.error);
        if (bucket) ledger.stability[bucket] += 1;
        if (/ROUTE_SLOT_MISMATCH|ROUTE_VISIBLE_MISMATCH|RECAP_ROUTE_MISMATCH/.test(String(result.error))) {
          ledger.route.route_state_bleed += 1;
        }
        if (c.id === "LS-04" && /LHE/.test(String(result.routeMeta?.destination))) {
          ledger.route.lhr_to_lhe_regression += 1;
        }
        if (result.routeMeta?.live_supplier_called && !firstPass) {
          ledger.route.hidden_wrong_route_search += 1;
        }
      }

      await page.waitForTimeout(8000);
    }
  } finally {
    await browser.close();
    ledger.completed_at = new Date().toISOString();
    ledger.read_only_search_ready =
      ledger.first_attempt_pass === 12 &&
      ledger.first_attempt_fail === 0 &&
      ledger.route.hidden_wrong_route_search === 0 &&
      ledger.route.route_state_bleed === 0 &&
      ledger.stability.HTTP_500 === 0 &&
      ledger.stability.AI_UNAVAILABLE === 0 &&
      ledger.stability.FAB_UNAVAILABLE === 0 &&
      ledger.stability.STATE_LOSS === 0 &&
      ledger.stability.COLLECTING_STALL === 0;

    fs.writeFileSync(ledgerPath, JSON.stringify(ledger, null, 2));
    console.log(JSON.stringify(ledger, null, 2));
  }

  process.exit(ledger.first_attempt_pass === 12 ? 0 : 2);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
