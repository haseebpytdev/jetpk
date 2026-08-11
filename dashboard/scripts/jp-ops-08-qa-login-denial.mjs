/**
 * Prove suspended QA identities cannot authenticate (password+OTP path).
 * Never prints secrets.
 */
import { chromium } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { baseUrl } from "./jp-dash-03-acceptance/auth-storage.mjs";
import { loadQaPasswordFromVault } from "./jp-dash-03-acceptance/credential-vault.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../");

function loadOtpDemoCode() {
  if (process.env.OTP_DEMO_FIXED_CODE) return process.env.OTP_DEMO_FIXED_CODE.trim();
  const envPath = path.join(repoRoot, ".env");
  if (!fs.existsSync(envPath)) return null;
  const match = fs.readFileSync(envPath, "utf8").match(/^OTP_DEMO_FIXED_CODE=(.*)$/m);
  if (!match) return null;
  return match[1].trim().replace(/^["']|["']$/g, "");
}

async function parseJson(response) {
  const raw = (await response.text()).replace(/^\uFEFF/, "");
  try {
    return JSON.parse(raw);
  } catch {
    return { ok: false, message: "non_json" };
  }
}

const roles = [
  { role: "admin", email: "jp-dash-03-qa-admin@jetpakistan.pk" },
  { role: "staff", email: "jp-dash-03-qa-staff@jetpakistan.pk" },
  { role: "agent", email: "jp-dash-03-qa-agent@jetpakistan.pk" },
  { role: "customer", email: "jp-dash-03-qa-customer@jetpakistan.pk" },
];

const otp = loadOtpDemoCode();
const browser = await chromium.launch({ headless: true });
let allDenied = true;

for (const entry of roles) {
  const password = await loadQaPasswordFromVault(entry.role);
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await page.goto(`${baseUrl}/login`, { waitUntil: "domcontentloaded" });
  await page.request.get(`${baseUrl}/laravel/api/public/content/csrf-token`, {
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
  });
  const cookies = await ctx.cookies();
  const xsrf = cookies.find((c) => c.name === "XSRF-TOKEN");
  const loginResponse = await page.request.post(`${baseUrl}/laravel/login`, {
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
      "X-XSRF-TOKEN": xsrf ? decodeURIComponent(xsrf.value) : "",
    },
    form: {
      login: entry.email,
      password: password || "missing",
      remember: "1",
      client_slug: "jetpk",
    },
  });
  const data = await parseJson(loginResponse);
  const accepted = loginResponse.ok() && data.ok === true;
  if (accepted && data.requires_otp && otp) {
    const otpTokenCookies = await ctx.cookies();
    const otpXsrf = otpTokenCookies.find((c) => c.name === "XSRF-TOKEN");
    const otpResponse = await page.request.post(`${baseUrl}/laravel/login/otp`, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-XSRF-TOKEN": otpXsrf ? decodeURIComponent(otpXsrf.value) : "",
      },
      form: { otp, client_slug: "jetpk" },
    });
    const otpData = await parseJson(otpResponse);
    const otpOk = otpResponse.ok() && otpData.ok === true;
    console.log(`LOGIN_DENIAL|${entry.role}|${otpOk ? "no" : "yes"}|stage=otp|status=${otpResponse.status()}`);
    if (otpOk) allDenied = false;
  } else {
    console.log(
      `LOGIN_DENIAL|${entry.role}|${accepted ? "no" : "yes"}|stage=password|status=${loginResponse.status()}`,
    );
    if (accepted) allDenied = false;
  }
  await ctx.close();
}

await browser.close();
console.log(`QA_LOGIN_DENIAL_PROVEN=${allDenied ? "yes" : "no"}`);
process.exit(allDenied ? 0 : 1);
