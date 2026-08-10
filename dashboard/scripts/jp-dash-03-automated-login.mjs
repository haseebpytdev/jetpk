/**
 * Automated QA login for JP-DASH-03 (headless, OTP-off acceptance window).
 * Usage: node scripts/jp-dash-03-automated-login.mjs [admin|staff|agent|customer|all]
 */
import { chromium } from "@playwright/test";
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

async function submitLogin(page, login, password) {
  const csrfToken = await fetchCsrfToken(page);
  if (!csrfToken) {
    return { ok: false, reason: "csrf_unavailable" };
  }

  const response = await page.request.post(`${baseUrl}/laravel/login`, {
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
      "X-XSRF-TOKEN": csrfToken,
    },
    form: {
      login,
      password,
      remember: "1",
      client_slug: "jetpk",
    },
  });

  const contentType = response.headers()["content-type"] ?? "";
  if (!contentType.includes("application/json")) {
    return { ok: false, reason: `unexpected_content_type:${response.status()}` };
  }

  const data = await response.json();
  if (!response.ok() || data.ok !== true) {
    return { ok: false, reason: `login_rejected:${response.status()}` };
  }

  if (data.requires_otp) {
    return { ok: false, reason: "otp_required" };
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
