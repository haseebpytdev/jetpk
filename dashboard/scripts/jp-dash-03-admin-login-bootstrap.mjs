/**
 * One-time headed login bootstrap for JP-DASH-03 production Admin acceptance.
 * Requests remember=true via the legitimate Remember me control.
 * Saves authenticated storageState locally — never commit the output file.
 *
 * Usage: node scripts/jp-dash-03-admin-login-bootstrap.mjs
 */
import { chromium } from "@playwright/test";
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import {
  AUTH_ROLES,
  ensureStorageDir,
  logRememberCookieMetadata,
} from "./jp-dash-03-acceptance/auth-storage.mjs";
import { startAcceptanceSessionKeepalive } from "./jp-dash-03-acceptance/session-keepalive.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const dashboardRoot = path.resolve(__dirname, "../");
const storagePath = ensureStorageDir("admin");
const adminConfig = AUTH_ROLES.admin;

const INTERACTIVE_TIMEOUT_MS = Number(process.env.JP_ADMIN_LOGIN_TIMEOUT_MS ?? 30 * 60 * 1000);

function runAcceptanceChain() {
  console.log("ACCEPTANCE_CHAIN_START");
  startAcceptanceSessionKeepalive();

  const crawl = spawnSync("node", ["scripts/jp-dash-03-production-crawl.mjs"], {
    cwd: dashboardRoot,
    stdio: "inherit",
    shell: false,
  });

  if (crawl.status !== 0) {
    console.error("ACCEPTANCE_CHAIN_CRAWL_FAIL");
    process.exit(crawl.status ?? 1);
  }

  const checkpoint12 = spawnSync("npm", ["run", "test:checkpoint-12"], {
    cwd: dashboardRoot,
    stdio: "inherit",
    shell: true,
  });

  if (checkpoint12.status !== 0) {
    console.error("ACCEPTANCE_CHAIN_CHECKPOINT_12_FAIL");
    process.exit(checkpoint12.status ?? 1);
  }

  console.log("ACCEPTANCE_CHAIN_PASS");
}

async function ensureRememberRequested(page) {
  const rememberCheckbox = page.locator('input[name="remember"][type="checkbox"]');
  if ((await rememberCheckbox.count()) === 0) {
    console.log("ADMIN_REMEMBER_REQUESTED=no_control");
    return false;
  }

  await rememberCheckbox.check({ force: true });
  console.log("ADMIN_REMEMBER_REQUESTED=yes");
  return true;
}

async function main() {
  fs.mkdirSync(path.dirname(storagePath), { recursive: true });

  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();

  console.log("WAITING_FOR_ADMIN_LOGIN");
  console.log("Complete Platform Admin login at the headed browser (OTP if required).");
  console.log("Remember me will be checked automatically before you submit credentials.");
  console.log(`Interactive wait up to ${Math.round(INTERACTIVE_TIMEOUT_MS / 60000)} minutes.`);

  await page.goto(adminConfig.loginUrl, { waitUntil: "domcontentloaded", timeout: 120_000 });
  await ensureRememberRequested(page);

  let loginDetected = false;
  const urlPoller = setInterval(() => {
    const current = page.url();
    if (adminConfig.dashboardPattern.test(current) && !loginDetected) {
      loginDetected = true;
      console.log("ADMIN_LOGIN_DETECTED");
    }
  }, 2000);

  try {
    await page.waitForURL(adminConfig.dashboardPattern, { timeout: INTERACTIVE_TIMEOUT_MS });
    console.log("ADMIN_LOGIN_DETECTED");
    await page.waitForSelector("[data-testid='dashboard-portal-label']", { timeout: 120_000 });

    const portalLabel = await page.getByTestId("dashboard-portal-label").textContent();
    if (!portalLabel || /preview/i.test(portalLabel)) {
      console.error("ADMIN_PLAYWRIGHT_SESSION=FAIL");
      process.exit(1);
    }

    if (!adminConfig.portalLabelPattern.test(portalLabel)) {
      console.error("ADMIN_PLAYWRIGHT_SESSION=FAIL");
      console.error("Authenticated user is not Platform Admin.");
      process.exit(1);
    }

    logRememberCookieMetadata(await context.cookies(), "ADMIN");
    await context.storageState({ path: storagePath });
    await browser.close();
    clearInterval(urlPoller);

    console.log("ADMIN_PLAYWRIGHT_SESSION=READY");
    runAcceptanceChain();
  } catch (error) {
    clearInterval(urlPoller);
    await browser.close();
    console.error("ADMIN_PLAYWRIGHT_SESSION=FAIL");
    if (error instanceof Error && error.message.includes("Timeout")) {
      console.error("Interactive login window expired before /admin/dashboard was reached.");
    } else if (error instanceof Error) {
      console.error(error.message);
    }
    process.exit(1);
  }
}

main().catch((error) => {
  console.error("ADMIN_PLAYWRIGHT_SESSION=FAIL");
  console.error(error instanceof Error ? error.message : String(error));
  process.exit(1);
});
