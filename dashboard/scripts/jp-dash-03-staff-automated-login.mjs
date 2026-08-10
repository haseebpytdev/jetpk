/**
 * Automated Staff login for JP-DASH-03 QA identity (headless).
 * Password from env or Windows Credential Manager. OTP from env or local handoff file.
 * Saves tmp/jp-dash-03-staff-storage-state.json — never commit.
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
import { loadQaPasswordFromVault } from "./jp-dash-03-acceptance/credential-vault.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../..");
const storagePath = ensureStorageDir("staff");
const staffConfig = AUTH_ROLES.staff;
const QA_LOGIN = "jp-dash-03-qa-staff@jetpakistan.pk";
const OTP_HANDOFF_FILE = path.join(repoRoot, "tmp/jp-dash-03-staff-otp-handoff.txt");

const OTP_POLL_MS = 2000;
const OTP_WAIT_MS = Number(process.env.JP_STAFF_OTP_WAIT_MS ?? 10 * 60 * 1000);

function readOtpHandoff() {
  if (process.env.JP_DASH_03_QA_STAFF_OTP) {
    const code = process.env.JP_DASH_03_QA_STAFF_OTP.trim();
    if (/^\d{6}$/.test(code)) {
      return code;
    }
  }

  if (!fs.existsSync(OTP_HANDOFF_FILE)) {
    return null;
  }

  const raw = fs.readFileSync(OTP_HANDOFF_FILE, "utf8").trim();
  if (/^\d{6}$/.test(raw)) {
    try {
      fs.unlinkSync(OTP_HANDOFF_FILE);
    } catch {
      /* ignore */
    }
    return raw;
  }

  return null;
}

async function waitForOtp() {
  const deadline = Date.now() + OTP_WAIT_MS;
  while (Date.now() < deadline) {
    const otp = readOtpHandoff();
    if (otp) {
      return otp;
    }
    await new Promise((resolve) => setTimeout(resolve, OTP_POLL_MS));
  }
  return null;
}

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
  const password = loadQaPasswordFromVault("staff");
  if (!password) {
    console.error("STAFF_PLAYWRIGHT_SESSION=FAIL");
    console.error("QA staff password unavailable (set JP_DASH_03_QA_STAFF_PASSWORD or Credential Manager).");
    process.exit(1);
  }

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  try {
    await page.goto(staffConfig.loginUrl, { waitUntil: "domcontentloaded", timeout: 120_000 });
    await page.waitForSelector('[name="login"]', { state: "visible", timeout: 60_000 });
    await page.waitForSelector('[name="password"]', { state: "visible", timeout: 60_000 });
    await ensureRememberRequested(page);

    await page.locator('[name="login"]').fill(QA_LOGIN);
    await page.locator('[name="password"]').fill(password);
    await Promise.all([
      page.waitForURL(/\/login\/otp|\/staff\/dashboard/, { timeout: 120_000 }),
      page.locator('button[type="submit"], input[type="submit"]').first().click(),
    ]);

    if (page.url().includes("/login/otp")) {
      console.log("STAFF_OTP_REQUIRED=yes");
      console.log("Paste OTP into tmp/jp-dash-03-staff-otp-handoff.txt or set JP_DASH_03_QA_STAFF_OTP.");

      const otp = await waitForOtp();
      if (!otp) {
        console.error("STAFF_PLAYWRIGHT_SESSION=FAIL");
        console.error("OTP handoff timed out.");
        process.exit(1);
      }

      await page.locator('[name="otp"]').fill(otp);
      await Promise.all([
        page.waitForURL(staffConfig.dashboardPattern, { timeout: 120_000 }),
        page.locator('form[action*="/login/otp"]').evaluate((form) => {
          form.submit();
        }),
      ]);
    }

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
    console.log("STAFF_PLAYWRIGHT_SESSION=READY");
  } catch (error) {
    console.error("STAFF_PLAYWRIGHT_SESSION=FAIL");
    if (error instanceof Error) {
      console.error(error.message);
    }
    process.exit(1);
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.error("STAFF_PLAYWRIGHT_SESSION=FAIL");
  console.error(error instanceof Error ? error.message : String(error));
  process.exit(1);
});
