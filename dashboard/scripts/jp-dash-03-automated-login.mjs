/**
 * Automated QA login for JP-DASH-03 (headless, OTP-off acceptance window).
 * Usage: node scripts/jp-dash-03-automated-login.mjs [admin|staff|agent|customer|all]
 */
import { chromium } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import {
  AUTH_ROLES,
  baseUrl,
  ensureStorageDir,
  logRememberCookieMetadata,
} from "./jp-dash-03-acceptance/auth-storage.mjs";
import { loadQaPasswordFromVault } from "./jp-dash-03-acceptance/credential-vault.mjs";

const rolesArg = (process.argv[2] ?? "all").toLowerCase();
const roles =
  rolesArg === "all" ? ["admin", "staff", "agent", "customer"] : [rolesArg];

function sanitizeUrl(url) {
  try {
    const parsed = new URL(url);
    for (const key of ["password", "login", "otp"]) {
      if (parsed.searchParams.has(key)) {
        parsed.searchParams.set(key, "[redacted]");
      }
    }
    return parsed.toString();
  } catch {
    return "[invalid-url]";
  }
}

async function ensureRememberRequested(page, prefix) {
  const rememberCheckbox = page.locator('input[name="remember"][type="checkbox"]');
  if ((await rememberCheckbox.count()) === 0) {
    console.log(`${prefix}_REMEMBER_REQUESTED=no_control`);
    return false;
  }

  await rememberCheckbox.check({ force: true });
  console.log(`${prefix}_REMEMBER_REQUESTED=yes`);
  return true;
}

async function fetchCsrfToken(page) {
  await page.request.get(`${baseUrl}/laravel/api/public/content/csrf-token`, {
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
  });

  const cookies = await page.context().cookies();
  const xsrf = cookies.find((cookie) => cookie.name === "XSRF-TOKEN");
  return xsrf ? decodeURIComponent(xsrf.value) : null;
}

function readOtpDemoCode() {
  const candidates = [
    process.env.OTP_DEMO_FIXED_CODE,
    (() => {
      try {
        const envPath = path.resolve(process.cwd(), "../.env");
        const text = fs.readFileSync(envPath, "utf8");
        const match = text.match(/^OTP_DEMO_FIXED_CODE=(.*)$/m);
        return match?.[1]?.trim().replace(/^["']|["']$/g, "") ?? null;
      } catch {
        return null;
      }
    })(),
    (() => {
      try {
        const envPath = path.resolve(process.cwd(), ".env");
        const text = fs.readFileSync(envPath, "utf8");
        const match = text.match(/^OTP_DEMO_FIXED_CODE=(.*)$/m);
        return match?.[1]?.trim().replace(/^["']|["']$/g, "") ?? null;
      } catch {
        return null;
      }
    })(),
  ];
  for (const value of candidates) {
    if (typeof value === "string" && value.length > 0) {
      return value;
    }
  }
  return "";
}

async function parseJsonResponse(response) {
  const raw = (await response.text()).replace(/^\uFEFF/, "");
  const contentType = response.headers()["content-type"] ?? "";
  if (!contentType.includes("application/json") && !raw.trim().startsWith("{")) {
    return { ok: false, reason: `unexpected_content_type:${response.status()}`, data: null };
  }
  try {
    return { ok: true, reason: null, data: JSON.parse(raw) };
  } catch {
    return { ok: false, reason: `json_parse_failed:${response.status()}`, data: null };
  }
}

async function submitLogin(page, login, password) {
  // Always refresh CSRF immediately before POST to avoid intermittent 419.
  let csrfToken = await fetchCsrfToken(page);
  if (!csrfToken) {
    return { ok: false, reason: "csrf_unavailable" };
  }

  async function postLogin(token) {
    return page.request.post(`${baseUrl}/laravel/login`, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-XSRF-TOKEN": token,
      },
      form: {
        login,
        password,
        remember: "1",
        client_slug: "jetpk",
      },
    });
  }

  let response = await postLogin(csrfToken);
  if (response.status() === 419) {
    csrfToken = await fetchCsrfToken(page);
    if (!csrfToken) {
      return { ok: false, reason: "csrf_unavailable_after_419" };
    }
    response = await postLogin(csrfToken);
  }

  const parsed = await parseJsonResponse(response);
  if (!parsed.ok || !parsed.data) {
    return { ok: false, reason: parsed.reason ?? "login_parse_failed" };
  }

  const data = parsed.data;
  if (!response.ok() || data.ok !== true) {
    return { ok: false, reason: `login_rejected:${response.status()}` };
  }

  if (data.requires_otp) {
    const otpCode = readOtpDemoCode();
    if (!otpCode) {
      return { ok: false, reason: "otp_required_no_demo_code" };
    }
    csrfToken = await fetchCsrfToken(page);
    const otpResponse = await page.request.post(`${baseUrl}/laravel/login/otp`, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-XSRF-TOKEN": csrfToken ?? "",
      },
      form: {
        otp: otpCode,
        client_slug: "jetpk",
      },
    });
    const otpParsed = await parseJsonResponse(otpResponse);
    if (!otpParsed.ok || !otpParsed.data || otpParsed.data.ok !== true) {
      return { ok: false, reason: `otp_rejected:${otpResponse.status()}` };
    }
    const redirectPath =
      typeof otpParsed.data.redirect === "string" &&
      otpParsed.data.redirect !== "" &&
      otpParsed.data.redirect !== "/"
        ? otpParsed.data.redirect
        : null;
    return { ok: true, redirect: redirectPath };
  }

  const redirectPath =
    typeof data.redirect === "string" && data.redirect !== "" && data.redirect !== "/"
      ? data.redirect
      : null;

  return { ok: true, redirect: redirectPath };
}

async function loginRole(role) {
  const roleConfig = AUTH_ROLES[role];
  if (!roleConfig) {
    console.error(`QA_${role.toUpperCase()}_AUTH=FAIL`);
    console.error(`Unknown role: ${role}`);
    return false;
  }

  const prefix = role.toUpperCase();
  const password = loadQaPasswordFromVault(role);
  if (!password) {
    console.error(`${prefix}_PLAYWRIGHT_SESSION=FAIL`);
    console.error(`QA ${role} password unavailable (env or Credential Manager).`);
    return false;
  }

  const storagePath = ensureStorageDir(role);
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  try {
    await page.goto(roleConfig.loginUrl, { waitUntil: "domcontentloaded", timeout: 120_000 });
    await page.waitForSelector('[name="login"]', { state: "visible", timeout: 60_000 });
    await ensureRememberRequested(page, prefix);

    const loginResult = await submitLogin(page, roleConfig.qaLogin, password);
    if (!loginResult.ok) {
      console.error(`${prefix}_PLAYWRIGHT_SESSION=FAIL`);
      console.error(loginResult.reason ?? "login_failed");
      return false;
    }

    const destination = loginResult.redirect ?? roleConfig.dashboardPath;
    await page.goto(`${baseUrl}${destination}`, {
      waitUntil: "domcontentloaded",
      timeout: 120_000,
    });

    if (page.url().includes("/login/otp")) {
      console.error(`${prefix}_PLAYWRIGHT_SESSION=FAIL`);
      console.error("OTP still required — set OTA_CLIENT_REQUIRE_LOGIN_OTP=false on production.");
      return false;
    }

    const portalSelectors = {
      admin: "[data-testid='dashboard-portal-label']",
      staff: "[data-testid='dashboard-portal-label']",
      agent: "[data-testid='portal-sidebar'], [data-testid='agent-dashboard-overview']",
      customer: "[data-testid='customer-dashboard-overview'], [data-testid='portal-sidebar']",
    };

    await page.waitForSelector(portalSelectors[role] ?? "[data-testid='dashboard-portal-label']", {
      timeout: 120_000,
    });

    if (role === "admin" || role === "staff") {
      const portalLabel = await page.getByTestId("dashboard-portal-label").textContent();
      if (!portalLabel || /preview/i.test(portalLabel)) {
        console.error(`${prefix}_PLAYWRIGHT_SESSION=FAIL`);
        return false;
      }

      if (!roleConfig.portalLabelPattern.test(portalLabel)) {
        console.error(`${prefix}_PLAYWRIGHT_SESSION=FAIL`);
        console.error(`Authenticated user is not ${role} portal.`);
        return false;
      }
    } else if (!roleConfig.dashboardPattern.test(page.url())) {
      console.error(`${prefix}_PLAYWRIGHT_SESSION=FAIL`);
      console.error(`Expected ${role} dashboard URL; got ${sanitizeUrl(page.url())}`);
      return false;
    }

    logRememberCookieMetadata(await context.cookies(), prefix);
    await context.storageState({ path: storagePath });
    console.log(`${prefix}_PLAYWRIGHT_SESSION=READY`);
    console.log(`QA_${role.toUpperCase()}_AUTH=PASS`);
    return true;
  } catch (error) {
    console.error(`${prefix}_PLAYWRIGHT_SESSION=FAIL`);
    if (error instanceof Error) {
      console.error(error.message.replace(page.url(), sanitizeUrl(page.url())));
    }
    return false;
  } finally {
    await browser.close();
  }
}

async function main() {
  let failed = false;
  for (const role of roles) {
    const ok = await loginRole(role);
    if (!ok) {
      failed = true;
    }
  }

  process.exit(failed ? 1 : 0);
}

main().catch((error) => {
  console.error("QA_AUTOMATED_LOGIN=FAIL");
  console.error(error instanceof Error ? error.message : String(error));
  process.exit(1);
});
