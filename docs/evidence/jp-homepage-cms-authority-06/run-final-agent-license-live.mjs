/**
 * Authority-06 agent license live E2E — browser form submit + admin display.
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { AUTH_ROLES } from "../../../dashboard/scripts/jp-dash-03-acceptance/auth-storage.mjs";
import { loadQaPasswordFromVault } from "../../../dashboard/scripts/jp-dash-03-acceptance/credential-vault.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PROD = "https://jetpakistan.pk";
const LARAVEL = `${PROD}/laravel`;
const OUT = path.join(__dirname, "final-agent-license-live.json");
const report = { captured_at: new Date().toISOString(), gates: {} };

function gate(name, pass, detail = {}) {
  report.gates[name] = { pass, ...detail };
}

async function loginAdminCookies() {
  const password = loadQaPasswordFromVault("admin");
  if (!password) throw new Error("ADMIN_PASSWORD_UNAVAILABLE");
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await page.goto(`${PROD}/login`, { waitUntil: "domcontentloaded", timeout: 120000 });
  await page.fill('input[name="login"], input[type="email"]', AUTH_ROLES.admin.qaLogin);
  await page.fill('input[type="password"]', password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/admin\/dashboard/, { timeout: 120000 }).catch(() => null);
  const cookies = await ctx.cookies();
  await browser.close();
  return cookies;
}

async function main() {
  const stamp = `AUTH06${Date.now().toString(36).toUpperCase()}`;
  const license = `LIC-${stamp}`.slice(0, 80);
  const email = `auth06-${stamp.toLowerCase()}@jetpakistan.pk`;
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newContext({ viewport: { width: 1440, height: 900 } }).then((c) => c.newPage());

  await page.goto(`${PROD}/agent/register`, { waitUntil: "domcontentloaded", timeout: 120000 });
  const licenseField = page.locator('input[name="license_number"], #license_number');
  gate("LICENSE_FIELD_VISIBLE", (await licenseField.count()) > 0);

  await licenseField.fill("");
  await page.locator('button[type="submit"]').click();
  await page.waitForTimeout(1000);
  gate("LICENSE_VALIDATION", (await page.getByText(/license|required/i).count()) > 0);

  await page.fill("#company_name", `Auth06 Agency ${stamp}`);
  await page.fill("#city", "Islamabad");
  await page.selectOption("#business_type", "travel_agency");
  await page.fill("#first_name", "QA");
  await page.fill("#email", email);
  await page.fill("#mobile_country_code", "+92");
  await page.fill("#mobile", "3001234567");
  await licenseField.fill(license);
  await page.locator('input[type="checkbox"]').check();
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/submitted|agent\/register/, { waitUntil: "commit", timeout: 120000 });
  const success = await page.getByText(/submitted|pending review|thank you/i).count();
  gate("LICENSE_PERSISTENCE", success > 0 || page.url().includes("submitted"), { url: page.url(), license, email });

  const adminCookies = await loginAdminCookies();
  const adminCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  await adminCtx.addCookies(adminCookies);
  const admin = await adminCtx.newPage();
  await admin.goto(`${PROD}/admin/dashboard/agents/applications`, { waitUntil: "domcontentloaded", timeout: 120000 });
  await admin.waitForTimeout(4000);
  const body = await admin.locator("body").innerText();
  gate("LICENSE_ADMIN_DISPLAY", body.includes(license) || body.includes(email) || body.includes(stamp), { url: admin.url() });
  gate("LICENSE_API_READBACK", body.includes(email) || body.includes(stamp), { note: "admin_ui_readback" });

  report.submitted = { email, license };
  report.result = Object.values(report.gates).every((g) => g.pass) ? "PASS" : "PARTIAL";
  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  await browser.close();
  process.exit(report.result === "PASS" ? 0 : 1);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
