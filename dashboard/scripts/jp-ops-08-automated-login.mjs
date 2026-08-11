/**
 * JP-OPS-08 automated QA login via JSON bridge + OTP_DEMO (OTP remains required).
 * Never prints passwords or OTP. Storage states remain under tmp/.
 */
import { chromium } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import {
  AUTH_ROLES,
  baseUrl,
  ensureStorageDir,
  logRememberCookieMetadata,
} from "./jp-dash-03-acceptance/auth-storage.mjs";
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

async function xsrfToken(page) {
  await page.request.get(`${baseUrl}/laravel/api/public/content/csrf-token`, {
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
  });
  const cookies = await page.context().cookies();
  const xsrf = cookies.find((cookie) => cookie.name === "XSRF-TOKEN");
  return xsrf ? decodeURIComponent(xsrf.value) : null;
}

async function parseJson(response) {
  const raw = (await response.text()).replace(/^\uFEFF/, "");
  return JSON.parse(raw);
}

async function loginWithOtpApi(page, login, password, otp) {
  const token = await xsrfToken(page);
  if (!token) return { ok: false, reason: "csrf_unavailable" };

  const loginResponse = await page.request.post(`${baseUrl}/laravel/login`, {
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
      "X-XSRF-TOKEN": token,
    },
    form: { login, password, remember: "1", client_slug: "jetpk" },
  });

  let data;
  try {
    data = await parseJson(loginResponse);
  } catch {
    return { ok: false, reason: `login_non_json:${loginResponse.status()}` };
  }

  if (!loginResponse.ok() || data.ok !== true) {
    return {
      ok: false,
      reason: `login_rejected:${loginResponse.status()}:${typeof data.message === "string" ? data.message.slice(0, 80) : "no_msg"}`,
    };
  }

  if (data.requires_otp) {
    const otpToken = (await xsrfToken(page)) ?? token;
    const otpResponse = await page.request.post(`${baseUrl}/laravel/login/otp`, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-XSRF-TOKEN": otpToken,
      },
      form: { otp, client_slug: "jetpk" },
    });
    let otpData;
    try {
      otpData = await parseJson(otpResponse);
    } catch {
      return { ok: false, reason: `otp_non_json:${otpResponse.status()}` };
    }
    if (!otpResponse.ok() || otpData.ok !== true) {
      return {
        ok: false,
        reason: `otp_rejected:${otpResponse.status()}:${typeof otpData.message === "string" ? otpData.message.slice(0, 80) : "no_msg"}`,
      };
    }
    return {
      ok: true,
      redirect: typeof otpData.redirect === "string" ? otpData.redirect : null,
    };
  }

  return {
    ok: true,
    redirect: typeof data.redirect === "string" ? data.redirect : null,
  };
}

async function loginRole(role, otp) {
  const roleConfig = AUTH_ROLES[role];
  const prefix = role.toUpperCase();
  const password = loadQaPasswordFromVault(role);
  if (!password) {
    console.error(`${prefix}_PLAYWRIGHT_SESSION=FAIL`);
    console.error("password_missing");
    return false;
  }

  const storagePath = ensureStorageDir(role);
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  try {
    await page.goto(roleConfig.loginUrl, { waitUntil: "domcontentloaded", timeout: 120_000 });
    const result = await loginWithOtpApi(page, roleConfig.qaLogin, password, otp);
    if (!result.ok) {
      console.error(`${prefix}_PLAYWRIGHT_SESSION=FAIL`);
      console.error(result.reason ?? "login_failed");
      return false;
    }

    const destination = result.redirect ?? roleConfig.dashboardPath;
    await page.goto(`${baseUrl}${destination}`, { waitUntil: "domcontentloaded", timeout: 120_000 });
    if (page.url().includes("/login")) {
      console.error(`${prefix}_PLAYWRIGHT_SESSION=FAIL`);
      console.error("still_on_login");
      return false;
    }

    if (role === "admin" || role === "staff") {
      await page.waitForSelector("[data-testid='dashboard-portal-label']", { timeout: 120_000 });
    }

    logRememberCookieMetadata(await context.cookies(), prefix);
    await context.storageState({ path: storagePath });
    console.log(`${prefix}_PLAYWRIGHT_SESSION=READY`);
    return true;
  } catch (error) {
    console.error(`${prefix}_PLAYWRIGHT_SESSION=FAIL`);
    console.error(error instanceof Error ? error.message : String(error));
    return false;
  } finally {
    await browser.close();
  }
}

const rolesArg = (process.argv[2] ?? "all").toLowerCase();
const roles = rolesArg === "all" ? ["admin", "staff", "agent", "customer"] : [rolesArg];
const otp = loadOtpDemoCode();
if (!otp) {
  console.error("OTP_DEMO_FIXED_CODE missing locally");
  process.exit(1);
}

let ok = true;
for (const role of roles) {
  // eslint-disable-next-line no-await-in-loop
  ok = (await loginRole(role, otp)) && ok;
}
process.exit(ok ? 0 : 1);
