/**
 * One-time admin login for JP-AI production canary UAT.
 * Writes storage state outside Git (tmp/).
 */
import { chromium } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { loadQaPasswordFromVault } from "../../dashboard/scripts/jp-dash-03-acceptance/credential-vault.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../");
const storagePath =
  process.env.JP_AI_CANARY_STORAGE_STATE ??
  path.join(repoRoot, "tmp/jp-ai-canary-admin-storage-state.json");
const baseUrl = process.env.JP_CANARY_UAT_BASE ?? "https://jetpakistan.pk";
const adminEmail = "jp-dash-03-qa-admin@jetpakistan.pk";

async function fetchCsrfToken(page) {
  await page.request.get(`${baseUrl}/laravel/api/public/content/csrf-token`, {
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
  });
  const cookies = await page.context().cookies();
  const xsrf = cookies.find((c) => c.name === "XSRF-TOKEN");
  return xsrf ? decodeURIComponent(xsrf.value) : "";
}

async function postLogin(page, login, password, csrf) {
  return page.request.post(`${baseUrl}/laravel/login`, {
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
      "X-XSRF-TOKEN": csrf,
    },
    form: { login, password, remember: "1", client_slug: "jetpk" },
  });
}

export default async function globalSetup() {
  const password = loadQaPasswordFromVault("admin");
  if (!password) {
    throw new Error("Canary admin password unavailable from vault/env");
  }

  fs.mkdirSync(path.dirname(storagePath), { recursive: true });

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  await page.goto(`${baseUrl}/login`, { waitUntil: "domcontentloaded", timeout: 120_000 });
  await page.waitForSelector('[name="login"]', { state: "visible", timeout: 60_000 });

  let csrf = await fetchCsrfToken(page);
  let response = await postLogin(page, adminEmail, password, csrf);
  if (response.status() === 419) {
    csrf = await fetchCsrfToken(page);
    response = await postLogin(page, adminEmail, password, csrf);
  }

  const data = await response.json();
  if (!response.ok() || data.ok !== true) {
    await browser.close();
    throw new Error(`Canary login failed: HTTP ${response.status()}`);
  }

  const dest = typeof data.redirect === "string" && data.redirect ? data.redirect : "/admin/dashboard";
  await page.goto(`${baseUrl}${dest}`, { waitUntil: "domcontentloaded", timeout: 120_000 });
  await page.waitForSelector("[data-testid='dashboard-portal-label']", { timeout: 120_000 });

  await page.goto(`${baseUrl}/`, { waitUntil: "domcontentloaded", timeout: 120_000 });
  await page.waitForFunction(
    async (base) => {
      const response = await fetch(`${base}/laravel/api/public/content/config`, {
        credentials: "include",
        headers: { Accept: "application/json" },
      });
      if (!response.ok) return false;
      const json = await response.json();
      return json.ai_assistant_enabled === true;
    },
    baseUrl,
    { timeout: 120_000 },
  );
  await page.goto(`${baseUrl}/#ask-jetpakistan`, { waitUntil: "domcontentloaded", timeout: 120_000 });
  await page.waitForSelector('[data-testid="ask-jetpakistan-fab"], [data-testid="ask-jetpakistan-panel"]', {
    timeout: 120_000,
  });

  await context.storageState({ path: storagePath });
  await browser.close();

  process.env.JP_AI_CANARY_STORAGE_STATE = storagePath;
  console.log(`CANARY_ADMIN_STORAGE_STATE=${storagePath}`);
}
