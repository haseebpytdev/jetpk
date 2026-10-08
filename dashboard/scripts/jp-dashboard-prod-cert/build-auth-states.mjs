/**
 * Build local-only Playwright storage states via real production login.
 * Never logs cookies, session values, or passwords.
 */
import { createRequire } from "node:module";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { loadQaPasswordFromVault } from "../jp-dash-03-acceptance/credential-vault.mjs";
import {
  AUTH_ROLES,
  ensureStorageDir,
  logRememberCookieMetadata,
} from "../jp-dash-03-acceptance/auth-storage.mjs";
import { fetchProductionOtp, productionLogin } from "./login-helpers.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const baseUrl = process.env.JP_ACCEPTANCE_BASE_URL ?? "https://jetpakistan.pk";
const outDir = path.join(repoRoot, "tmp");
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const ROLE_MAP = {
  admin: "admin",
  staff: "staff",
  agent: "agent",
  customer: "customer",
  agent_staff: "agentStaff",
};

async function main() {
  fs.mkdirSync(outDir, { recursive: true });
  const otp = fetchProductionOtp();
  if (!otp) throw new Error("MISSING_OTP");

  const browser = await chromium.launch({ headless: true });
  const results = {};

  for (const [role, vaultRole] of Object.entries(ROLE_MAP)) {
    const config = AUTH_ROLES[role] ?? {
      defaultPath: path.join(outDir, `jp-dash-03-${role.replace("_", "-")}-storage-state.json`),
      dashboardPath: role === "agent_staff" ? "/agent/dashboard" : AUTH_ROLES[role]?.dashboardPath,
      qaLogin: `jp-dash-03-qa-${role.replace("_", "-")}@jetpakistan.pk`,
      dashboardPattern: role === "agent_staff" ? /\/agent\// : AUTH_ROLES[role]?.dashboardPattern,
    };

    const storagePath = role === "agent_staff"
      ? path.join(outDir, "jp-dash-03-agent-staff-storage-state.json")
      : ensureStorageDir(role);

    const password = loadQaPasswordFromVault(vaultRole);
    if (!password) throw new Error(`MISSING_PASSWORD:${role}`);

    const context = await browser.newContext();
    const page = await context.newPage();
    const email = role === "agent_staff"
      ? "jp-dash-03-qa-agent-staff@jetpakistan.pk"
      : config.qaLogin;

    await productionLogin(page, email, password, otp);

    const dashboardPath = role === "agent_staff" ? "/agent/dashboard" : config.dashboardPath;
    await page.goto(`${baseUrl}${dashboardPath}`, { waitUntil: "domcontentloaded", timeout: 60000 });
    await sleep(2000);

    const pattern = role === "agent_staff" ? /\/agent\// : config.dashboardPattern;
    if (!pattern.test(page.url())) {
      throw new Error(`DASHBOARD_REDIRECT_FAIL:${role}:${page.url()}`);
    }

    const cookies = await context.cookies();
    logRememberCookieMetadata(cookies, role.toUpperCase());
    await context.storageState({ path: storagePath });
    await context.close();

    results[role] = "PASS";
    console.log(`AUTH_STATE_${role}=PASS`);
    await sleep(2000);
  }

  await browser.close();
  console.log(`AUTH_STATES_BUILT=${Object.keys(results).length}`);
}

main().catch((err) => {
  console.error(String(err?.message || err));
  process.exit(1);
});
