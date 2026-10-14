/**
 * Build local-only Playwright storage states for all 13 JP-DASH-FINAL-11 identities.
 * Never logs passwords, OTP, cookies, or storageState contents.
 */
import { createRequire } from "node:module";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { logRememberCookieMetadata } from "../jp-dash-03-acceptance/auth-storage.mjs";
import { fetchProductionOtp, productionLogin } from "../jp-dashboard-prod-cert/login-helpers.mjs";
import { FINAL11_AUTH_ROLES, FINAL11_ROLE_KEYS, BASE_URL, repoRoot } from "./final-11-auth-roles.mjs";
import { loadDash03Password, loadFinal11Password } from "./final-11-credentials.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function passwordForRole(role) {
  if (role.passwordSource === "dash03") {
    const pw = loadDash03Password(role.dash03VaultRole);
    if (!pw) throw new Error(`MISSING_PASSWORD:${role.qaIdentityKey}`);
    return pw;
  }
  const pw = loadFinal11Password(role.final11PasswordKey);
  if (!pw) throw new Error(`MISSING_PASSWORD:${role.qaIdentityKey}`);
  return pw;
}

async function validateDestination(page, role) {
  await sleep(1500);
  const url = page.url();
  const body = await page.locator("body").innerText().catch(() => "");

  if (role.qaIdentityKey === "legacy_agency_admin") {
    const legacyOk =
      url.includes("/account/legacy") ||
      /legacy account|no longer supported|account type is no longer supported/i.test(body);
    if (!legacyOk) {
      throw new Error(`DESTINATION_FAIL:${role.authResultKey}:${url}`);
    }
    return;
  }

  if (!role.destinationPattern.test(url) && !role.destinationPattern.test(body)) {
    throw new Error(`DESTINATION_FAIL:${role.authResultKey}:${url}`);
  }
}

async function main() {
  fs.mkdirSync(path.join(repoRoot, "tmp"), { recursive: true });
  const otp = fetchProductionOtp();
  if (!otp) {
    throw new Error("MISSING_OTP");
  }

  const onlyRole = process.env.JP_FINAL11_AUTH_ONLY?.trim();
  const roleKeys = onlyRole ? [onlyRole] : FINAL11_ROLE_KEYS;
  if (onlyRole && !FINAL11_AUTH_ROLES[onlyRole]) {
    throw new Error(`JP_FINAL11_AUTH_ONLY invalid: ${onlyRole}`);
  }

  const browser = await chromium.launch({ headless: true });
  let built = 0;
  let failures = 0;

  console.log("AUTH_STATES_EXPECTED=13");

  for (const roleKey of roleKeys) {
    const role = FINAL11_AUTH_ROLES[roleKey];
    const storagePath = path.join(repoRoot, role.storageFile);
    fs.mkdirSync(path.dirname(storagePath), { recursive: true });

    try {
      const password = passwordForRole(role);
      const context = await browser.newContext();
      const page = await context.newPage();
      await productionLogin(page, role.email, password, otp);
      await page.goto(`${BASE_URL}${role.postLoginPath}`, { waitUntil: "domcontentloaded", timeout: 90000 });
      await validateDestination(page, role);
      const cookies = await context.cookies();
      logRememberCookieMetadata(cookies, role.authResultKey);
      await context.storageState({ path: storagePath });
      await context.close();
      built += 1;
      console.log(`${role.authResultKey}=PASS`);
    } catch (err) {
      failures += 1;
      console.log(`${role.authResultKey}=FAIL`);
      console.error(String(err?.message || err));
    }
    await sleep(2000);
  }

  await browser.close();

  console.log(`AUTH_STATES_BUILT=${built}`);
  console.log(`AUTH_STATE_BUILD_FAILURES=${failures}`);

  if (failures > 0) {
    process.exit(1);
  }
}

main().catch((err) => {
  console.error(String(err?.message || err));
  process.exit(1);
});
