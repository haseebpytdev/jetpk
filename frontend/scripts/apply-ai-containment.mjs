/**
 * Phase 0: switch live AI from public rollout to internal_canary containment.
 */
import { chromium } from "playwright";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { loadQaPasswordFromVault } from "../../dashboard/scripts/jp-dash-03-acceptance/credential-vault.mjs";
import { BASE, postLogin } from "./canary-matrix-helpers.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ADMIN_EMAIL = "jp-dash-03-qa-admin@jetpakistan.pk";
const SETTINGS_URL = `${BASE}/admin/settings/ai-assistant`;
const evidenceDir = path.resolve(__dirname, "../../docs/evidence/jp-ai-post-recovery-closure-08");

async function loginAdmin(page) {
  const password = loadQaPasswordFromVault("admin");
  if (!password) throw new Error("MISSING_ADMIN_PASSWORD");
  await page.goto(`${BASE}/login`, { waitUntil: "domcontentloaded", timeout: 120_000 });
  const response = await postLogin(page, ADMIN_EMAIL, password);
  if (![200, 302, 301].includes(response.status())) {
    throw new Error(`LOGIN_FAIL status=${response.status()}`);
  }
  await page.goto(`${BASE}/admin/dashboard`, { waitUntil: "domcontentloaded", timeout: 120_000 });
}

async function fetchHealth(request) {
  const res = await request.get(`${BASE}/laravel/api/public/ai/health`, {
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
  });
  return res.ok() ? await res.json() : { ok: false, status: res.status() };
}

async function fetchConfig(request) {
  const res = await request.get(`${BASE}/laravel/api/public/content/config`, {
    headers: { Accept: "application/json" },
  });
  return res.ok() ? await res.json() : { ok: false, status: res.status() };
}

function snapshot(health, config, probe) {
  return {
    assistant_mode: health?.assistant_mode ?? health?.status?.mode,
    public_enabled: health?.status?.public_enabled,
    internal_canary_enabled: health?.status?.internal_canary_enabled,
    audience_mode: health?.status?.admin?.audience_mode,
    config_ai_enabled: config?.ai_assistant_enabled,
    config_mode: config?.ai_assistant_mode,
    canary_eligible: probe?.canary_eligible,
  };
}

async function probeSession(request) {
  const [health, config, session] = await Promise.all([
    fetchHealth(request),
    fetchConfig(request),
    request.get(`${BASE}/laravel/api/public/auth/session`, {
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    }).then((r) => (r.ok() ? r.json() : {})),
  ]);
  const assistantMode = health?.assistant_mode ?? config?.ai_assistant_mode;
  return {
    authenticated: session?.authenticated === true,
    canary_eligible: assistantMode === "internal_canary" && config?.ai_assistant_enabled === true,
    config_ai_enabled: config?.ai_assistant_enabled === true,
    assistant_mode: assistantMode,
  };
}

async function main() {
  fs.mkdirSync(evidenceDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const adminContext = await browser.newContext();
  const page = await adminContext.newPage();
  await loginAdmin(page);

  const before = snapshot(
    await fetchHealth(page.request),
    await fetchConfig(page.request),
    await probeSession(page.request),
  );

  await page.goto(SETTINGS_URL, { waitUntil: "domcontentloaded", timeout: 120_000 });
  const csrf = await page.locator('input[name="_token"]').first().inputValue();

  const patchRes = await page.request.post(SETTINGS_URL, {
    form: {
      _token: csrf,
      _method: "PATCH",
      master_enabled: "1",
      internal_canary_enabled: "1",
      lab_adapter_enabled: "0",
      rag_enabled: "1",
      human_handoff_enabled: "1",
      learning_queue_enabled: "1",
      flight_search_read_only_enabled: "0",
    },
    maxRedirects: 0,
    failOnStatusCode: false,
  });

  await page.goto(SETTINGS_URL, { waitUntil: "networkidle", timeout: 120_000 }).catch(() => {});
  const afterAdmin = snapshot(
    await fetchHealth(page.request),
    await fetchConfig(page.request),
    await probeSession(page.request),
  );

  const anonContext = await browser.newContext();
  const anonPage = await anonContext.newPage();
  const anonConfig = await fetchConfig(anonPage.request);

  await browser.close();

  const report = {
    phase: "JP-AI-CONTAINMENT-PHASE-0",
    ts: new Date().toISOString(),
    patch_status: patchRes.status(),
    before,
    after_admin: afterAdmin,
    anonymous: {
      ai_assistant_enabled: anonConfig?.ai_assistant_enabled,
      ai_assistant_mode: anonConfig?.ai_assistant_mode,
    },
    containment: {
      GENERAL_PUBLIC_NEW_AI:
        afterAdmin.public_enabled === true || afterAdmin.assistant_mode === "public" ? "YES" : "NO",
      ANONYMOUS_NEW_AI:
        anonConfig?.ai_assistant_enabled === true && anonConfig?.ai_assistant_mode === "public"
          ? "YES"
          : "NO",
      PUBLIC_ROLLOUT_OFF:
        afterAdmin.public_enabled === false && afterAdmin.assistant_mode !== "public" ? "YES" : "NO",
    },
  };

  fs.writeFileSync(path.join(evidenceDir, "containment-phase-0.json"), JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));

  const ok =
    report.containment.GENERAL_PUBLIC_NEW_AI === "NO" &&
    report.containment.ANONYMOUS_NEW_AI === "NO" &&
    report.containment.PUBLIC_ROLLOUT_OFF === "YES";

  process.exit(ok ? 0 : 2);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
