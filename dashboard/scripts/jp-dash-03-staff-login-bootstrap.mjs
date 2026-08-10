/**
 * One-time headed login bootstrap for JP-DASH-03 production Staff acceptance.
 * Requests remember=true via the legitimate Remember me control.
 * Saves to tmp/jp-dash-03-staff-storage-state.json — never commit.
 *
 * Usage: node scripts/jp-dash-03-staff-login-bootstrap.mjs
 */
import { chromium } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import {
  AUTH_ROLES,
  ensureStorageDir,
  logRememberCookieMetadata,
} from "./jp-dash-03-acceptance/auth-storage.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const storagePath = ensureStorageDir("staff");
const staffConfig = AUTH_ROLES.staff;

const INTERACTIVE_TIMEOUT_MS = Number(process.env.JP_STAFF_LOGIN_TIMEOUT_MS ?? 30 * 60 * 1000);

async function ensureRememberRequested(page) {
  const rememberCheckbox = page.locator('input[name="remember"][type="checkbox"]');
  if ((await rememberCheckbox.count()) === 0) {
    console.log("STAFF_REMEMBER_REQUESTED=no_control");
    return false;
  }

  await rememberCheckbox.check({ force: true });
  console.log("STAFF_REMEMBER_REQUESTED=yes");
  return true;
}

async function main() {
  fs.mkdirSync(path.dirname(storagePath), { recursive: true });

  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();

  console.log("WAITING_FOR_STAFF_LOGIN");
  console.log("Complete Staff login at the headed browser (OTP if required).");
  console.log("Remember me will be checked automatically before you submit credentials.");
  console.log(`Interactive wait up to ${Math.round(INTERACTIVE_TIMEOUT_MS / 60000)} minutes.`);

  await page.goto(staffConfig.loginUrl, { waitUntil: "domcontentloaded", timeout: 120_000 });
  await ensureRememberRequested(page);

  try {
    await page.waitForURL(staffConfig.dashboardPattern, { timeout: INTERACTIVE_TIMEOUT_MS });
    console.log("STAFF_LOGIN_DETECTED");
    await page.waitForSelector("[data-testid='dashboard-portal-label']", { timeout: 120_000 });

    const portalLabel = await page.getByTestId("dashboard-portal-label").textContent();
    if (!portalLabel || /preview/i.test(portalLabel)) {
      console.error("STAFF_PLAYWRIGHT_SESSION=FAIL");
      process.exit(1);
    }

    if (!staffConfig.portalLabelPattern.test(portalLabel)) {
      console.error("STAFF_PLAYWRIGHT_SESSION=FAIL");
      console.error("Authenticated user is not Staff portal.");
      process.exit(1);
    }

    logRememberCookieMetadata(await context.cookies(), "STAFF");
    await context.storageState({ path: storagePath });
    await browser.close();

    console.log("STAFF_PLAYWRIGHT_SESSION=READY");
  } catch (error) {
    await browser.close();
    console.error("STAFF_PLAYWRIGHT_SESSION=FAIL");
    if (error instanceof Error && error.message.includes("Timeout")) {
      console.error("Interactive login window expired before /staff/dashboard was reached.");
    } else if (error instanceof Error) {
      console.error(error.message);
    }
    process.exit(1);
  }
}

main().catch((error) => {
  console.error("STAFF_PLAYWRIGHT_SESSION=FAIL");
  console.error(error instanceof Error ? error.message : String(error));
  process.exit(1);
});
