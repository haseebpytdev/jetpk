/**
 * JP-AI-CONVERSATION-ISOLATION-POST-MERGE-CERTIFICATION-11 — ISO-01..03 isolation-only gate.
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
  waitForConfigHydration,
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
    establish: ["LHE to JED 20 Dec"],
    afterClear: "LHE to DXB 22 Dec",
    expectAfterClear: { origin: "LHE", destination: "DXB" },
  },
  {
    id: "ISO-02",
    establish: ["LHE to DXB 22 Dec"],
    afterClear: "dubay se lahore 15 Jan",
    expectAfterClear: { origin: "DXB", destination: "LHE" },
  },
  {
    id: "ISO-03",
    establish: ["dubay se lahore 15 Jan"],
    afterClear: "ISB to LHR 15 Jan",
    expectAfterClear: { origin: "ISB", destination: "LHR" },
  },
];

const SEND_OPTS = {
  responseTimeoutMs: 180_000,
  chatResponseTimeoutMs: 180_000,
  inputReadyTimeoutMs: 180_000,
  sendReadyTimeoutMs: 180_000,
  sendClickTimeoutMs: 180_000,
};

function routeFromMeta(meta) {
  const slots = meta?.intent?.slots ?? {};
  return {
    origin: slots.origin ?? null,
    destination: slots.destination ?? null,
  };
}

function writeLedger(ledger) {
  fs.mkdirSync(evidenceDir, { recursive: true });
  fs.writeFileSync(ledgerPath, JSON.stringify(ledger, null, 2));
}

async function waitForPanelIdle(page, timeoutMs = 180_000) {
  await page.waitForFunction(
    () => {
      const busy = document.querySelector('[data-testid="ask-jetpakistan-panel"] [aria-busy="true"]');
      const input = document.querySelector('[data-testid="ask-jetpakistan-panel"] input');
      return !busy && input && !input.disabled && input.getAttribute("aria-busy") !== "true";
    },
    { timeout: timeoutMs },
  );
}

async function sendFirstChatAfterClear(page, text, expectedConversationId) {
  let firstChatRequestId = null;
  const onChatRequest = (req) => {
    if (firstChatRequestId) return;
    if (!req.url().includes("/api/public/ai/chat") || req.method() !== "POST") return;
    try {
      const body = JSON.parse(req.postData() ?? "{}");
      firstChatRequestId = body.conversation_id ?? null;
    } catch {
      /* ignore */
    }
  };
  page.on("request", onChatRequest);
  const result = await sendMessage(page, text, SEND_OPTS);
  page.off("request", onChatRequest);
  if (expectedConversationId && firstChatRequestId !== expectedConversationId) {
    throw new Error("HARNESS_CONVERSATION_ID_NOT_ISOLATED");
  }
  return { firstChatRequestId, payload: result.payload ?? {}, status: result.status };
}

async function main() {
  const password = loadQaPasswordFromVault("admin");
  if (!password) {
    console.error("MISSING_ADMIN_PASSWORD");
    process.exit(2);
  }

  resetClearThrottleMetrics();
  const ledger = {
    phase: "JP-AI-CONVERSATION-ISOLATION-POST-MERGE-CERTIFICATION-11-ISO",
    started_at: new Date().toISOString(),
    total: ISO_CASES.length,
    pass: 0,
    fail: 0,
    route_state_bleed: 0,
    clear_id_mismatch: 0,
    clear_preflight_failure: 0,
    cases: {},
  };
  writeLedger(ledger);

  const browser = await chromium.launch({
    headless: true,
    args: ["--disable-dev-shm-usage"],
  });
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
    await waitForConfigHydration(page, 90_000).catch(() => {});
    await openAskPanel(page);
    const preflight = await sessionPreflight(page);
    ledger.preflight = preflight;
    if (!preflight.canary_eligible || !preflight.fab_visible) {
      throw new Error(`PREFLIGHT_FAIL eligible=${preflight.canary_eligible} fab=${preflight.fab_visible}`);
    }
    writeLedger(ledger);

    for (const iso of ISO_CASES) {
      let casePass = true;
      let caseError = null;
      let isolation = null;
      try {
        await page.goto(`${BASE}/#ask-jetpakistan`, { waitUntil: "domcontentloaded", timeout: 120_000 });
        await waitForConfigHydration(page, 60_000).catch(() => {});
        await openAskPanel(page);

        for (const step of iso.establish) {
          await waitForPanelIdle(page);
          await sendMessage(page, step, SEND_OPTS);
          await waitForPanelIdle(page);
        }

        const cleared = await clearConversation(page);
        isolation = {
          old_conversation_id: cleared.old_conversation_id ?? null,
          clear_response_new_id: cleared.clear_response_new_id ?? null,
          session_storage_after_clear: cleared.session_storage_after_clear ?? null,
        };

        const after = await sendFirstChatAfterClear(
          page,
          iso.afterClear,
          cleared.clear_response_new_id ?? null,
        );
        isolation.first_chat_request_id = after.firstChatRequestId;

        const route = routeFromMeta(after.payload?.meta);
        if (after.status >= 500) {
          throw new Error(`HTTP_${after.status}`);
        }
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
        if (/CLEAR_PREFLIGHT_INFRA_FAILURE/.test(caseError)) {
          ledger.clear_preflight_failure += 1;
          ledger.aborted = true;
          ledger.abort_reason = caseError;
          ledger.cases[iso.id] = { pass: false, error: caseError, isolation };
          ledger.fail += 1;
          writeLedger(ledger);
          break;
        }
      }

      ledger.cases[iso.id] = { pass: casePass, error: caseError, isolation };
      if (casePass) ledger.pass += 1;
      else ledger.fail += 1;
      writeLedger(ledger);
    }
  } catch (e) {
    ledger.aborted = true;
    ledger.abort_reason = e instanceof Error ? e.message : String(e);
  } finally {
    await browser.close().catch(() => {});
    ledger.clear_throttle = { ...clearThrottleMetrics };
    ledger.completed_at = new Date().toISOString();
    writeLedger(ledger);
    console.log(JSON.stringify(ledger, null, 2));
  }

  process.exit(ledger.pass === 3 && ledger.fail === 0 && !ledger.aborted ? 0 : 2);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
