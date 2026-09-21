/**
 * JP-AI-CONVERSATION-ISOLATION-CANONICAL-CLOSURE-10 — ISO-01..03 isolation-only gate.
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
  BASE,
  clearThrottleMetrics,
  resetClearThrottleMetrics,
  sendMessage,
  waitForAskReady,
} from "./canary-matrix-helpers.mjs";
import {
  clearConversation,
  loginAdmin,
  openAskPanel,
  sessionPreflight,
} from "./live-readonly-qa-helpers.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const evidenceDir = path.resolve(
  __dirname,
  "../../docs/evidence/jp-ai-conversation-isolation-canonical-10",
);
const ledgerPath = path.join(evidenceDir, "isolation-certification-ledger.json");

const ISO_CASES = [
  {
    id: "ISO-01",
    establish: ["LHE to JED 20 Dec", "one way", "1 adult"],
    afterClear: "LHE to DXB 22 Dec",
    expectAfterClear: { origin: "LHE", destination: "DXB" },
  },
  {
    id: "ISO-02",
    establish: ["LHE to DXB 22 Dec", "one way", "1 adult"],
    afterClear: "dubay se lahore 15 Jan",
    expectAfterClear: { origin: "DXB", destination: "LHE" },
  },
  {
    id: "ISO-03",
    establish: ["dubay se lahore 15 Jan", "one way", "1 adult"],
    afterClear: "ISB to LHR 15 Jan",
    expectAfterClear: { origin: "ISB", destination: "LHR" },
  },
];

function routeFromMeta(meta) {
  const slots = meta?.intent?.slots ?? {};
  return {
    origin: slots.origin ?? null,
    destination: slots.destination ?? null,
  };
}

async function main() {
  resetClearThrottleMetrics();
  fs.mkdirSync(evidenceDir, { recursive: true });

  const ledger = {
    phase: "JP-AI-CONVERSATION-ISOLATION-CANONICAL-CLOSURE-10-ISO",
    started_at: new Date().toISOString(),
    total: ISO_CASES.length,
    pass: 0,
    fail: 0,
    route_state_bleed: 0,
    clear_id_mismatch: 0,
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

  const page = await (await browser.newContext({ storageState: storagePath })).newPage();

  try {
    await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded", timeout: 120_000 });
    await openAskPanel(page);
    const preflight = await sessionPreflight(page);
    if (!preflight.canary_eligible || !preflight.fab_visible) {
      throw new Error(`PREFLIGHT_FAIL eligible=${preflight.canary_eligible} fab=${preflight.fab_visible}`);
    }

    for (const iso of ISO_CASES) {
      let casePass = true;
      let caseError = null;
      let isolation = null;
      try {
        for (const step of iso.establish) {
          await waitForAskReady(page);
          await sendMessage(page, step, { responseTimeoutMs: 120_000 });
        }

        const cleared = await clearConversation(page);
        isolation = {
          old_conversation_id: cleared.old_conversation_id ?? null,
          clear_response_new_id: cleared.clear_response_new_id ?? null,
          session_storage_after_clear: cleared.session_storage_after_clear ?? null,
        };

        const chatAssertion = page.waitForRequest(
          (req) => req.url().includes("/api/public/ai/chat") && req.method() === "POST",
          { timeout: 120_000 },
        );
        await waitForAskReady(page);
        const after = await sendMessage(page, iso.afterClear, { responseTimeoutMs: 120_000 });
        const chatRequest = await chatAssertion;
        const body = JSON.parse(chatRequest.postData() ?? "{}");
        isolation.first_chat_request_id = body.conversation_id ?? null;

        if (
          cleared.clear_response_new_id &&
          isolation.first_chat_request_id !== cleared.clear_response_new_id
        ) {
          ledger.clear_id_mismatch += 1;
          throw new Error("HARNESS_CONVERSATION_ID_NOT_ISOLATED");
        }

        const route = routeFromMeta(after.payload?.meta);
        if (
          route.origin !== iso.expectAfterClear.origin ||
          route.destination !== iso.expectAfterClear.destination
        ) {
          ledger.route_state_bleed += 1;
          throw new Error(`ROUTE_BLEED:${route.origin ?? "?"}->${route.destination ?? "?"}`);
        }
      } catch (e) {
        casePass = false;
        caseError = e instanceof Error ? e.message : String(e);
        if (/CLEAR_PREFLIGHT_INFRA_FAILURE/.test(caseError)) throw e;
      }

      ledger.cases[iso.id] = {
        pass: casePass,
        error: caseError,
        isolation,
      };
      if (casePass) ledger.pass += 1;
      else ledger.fail += 1;
    }
  } catch (e) {
    ledger.aborted = true;
    ledger.abort_reason = e instanceof Error ? e.message : String(e);
  } finally {
    await browser.close();
    ledger.clear_throttle = { ...clearThrottleMetrics };
    ledger.completed_at = new Date().toISOString();
    fs.writeFileSync(ledgerPath, JSON.stringify(ledger, null, 2));
    console.log(JSON.stringify(ledger, null, 2));
  }

  process.exit(ledger.pass === 3 && ledger.fail === 0 ? 0 : 2);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
