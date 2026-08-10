/**
 * One-time headed login bootstrap for JP-DASH-03 production Admin acceptance.
 * Saves authenticated storageState locally — never commit the output file.
 *
 * After successful login, automatically runs production crawl + acceptance tests.
 *
 * Usage: node scripts/jp-dash-03-admin-login-bootstrap.mjs
 */
import { chromium } from "@playwright/test";
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { startAcceptanceSessionKeepalive } from "./jp-dash-03-acceptance/session-keepalive.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const dashboardRoot = path.resolve(__dirname, "../");
const repoRoot = path.resolve(__dirname, "../../");
const storagePath = path.join(repoRoot, "tmp/jp-dash-03-admin-storage-state.json");

const baseUrl = process.env.JP_ACCEPTANCE_BASE_URL ?? "https://jetpakistan.pk";
const loginUrl = `${baseUrl}/login`;
const dashboardPattern = /\/admin\/dashboard/;
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

  const tests = spawnSync("npm", ["run", "test:production-acceptance"], {
    cwd: dashboardRoot,
    stdio: "inherit",
    shell: true,
  });

  if (tests.status !== 0) {
    console.error("ACCEPTANCE_CHAIN_TESTS_FAIL");
    process.exit(tests.status ?? 1);
  }

  console.log("ACCEPTANCE_CHAIN_PASS");
}

async function main() {
  fs.mkdirSync(path.dirname(storagePath), { recursive: true });

  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();

  console.log("WAITING_FOR_ADMIN_LOGIN");
  console.log("Complete Platform Admin login at the headed browser (OTP if required).");
  console.log(`Interactive wait up to ${Math.round(INTERACTIVE_TIMEOUT_MS / 60000)} minutes.`);

  await page.goto(loginUrl, { waitUntil: "domcontentloaded", timeout: 120_000 });

  let loginDetected = false;
  const urlPoller = setInterval(() => {
    const current = page.url();
    if (dashboardPattern.test(current) && !loginDetected) {
      loginDetected = true;
      console.log("ADMIN_LOGIN_DETECTED");
    }
  }, 2000);

  try {
    await page.waitForURL(dashboardPattern, { timeout: INTERACTIVE_TIMEOUT_MS });
    console.log("ADMIN_LOGIN_DETECTED");
    await page.waitForSelector("[data-testid='dashboard-portal-label']", { timeout: 120_000 });

    const portalLabel = await page.getByTestId("dashboard-portal-label").textContent();
    if (!portalLabel || /preview/i.test(portalLabel)) {
      console.error("ADMIN_PLAYWRIGHT_SESSION=FAIL");
      process.exit(1);
    }

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
