/**
 * Authority-06 R3 — PublicShell bootstrap contention A/B (browser intercept only).
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.JP_BASE_URL || "https://jetpakistan.pk";
const N = Number(process.env.JP_NAV_N || 20);
const OUT = path.join(__dirname, "publicshell-ab-contention.json");

const ANON_SESSION = JSON.stringify({ authenticated: false });

const ROUTES = [
  { name: "home_to_login", href: "/login", testid: "header-login-cta", usable: '[data-testid="login-form"] input[type="password"]' },
  {
    name: "home_to_support",
    href: "/support",
    dropdown: { menu: "Support", label: "Help Center" },
    usable: "#support-form-heading",
  },
];

const COHORTS = [
  { id: "baseline", sessionIntercept: false, configIntercept: false },
  { id: "session_intercept", sessionIntercept: true, configIntercept: false },
  { id: "config_intercept", sessionIntercept: false, configIntercept: true },
  { id: "both_intercept", sessionIntercept: true, configIntercept: true },
];

function pct(arr, p) {
  const a = (arr || []).filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

async function clickNav(page, route) {
  if (route.testid) {
    const cta = page.getByTestId(route.testid);
    if (await cta.isVisible()) {
      await cta.click({ timeout: 8000 });
      return;
    }
  }
  if (route.dropdown) {
    const direct = page.locator(`a[href="${route.href}"]`).first();
    if (await direct.count()) {
      try {
        await direct.click({ timeout: 5000, force: true });
        return;
      } catch {
        /* menu */
      }
    }
    const primary = page.getByRole("navigation", { name: "Primary" });
    await primary.getByRole("button", { name: route.dropdown.menu }).click({ timeout: 8000, force: true });
    await page.getByRole("link", { name: route.dropdown.label, exact: true }).click({ timeout: 8000, force: true });
    return;
  }
  await page.locator(`a[href="${route.href}"]`).first().click({ timeout: 8000, force: true });
}

async function setupCohort(page, cohort, cachedConfigBody) {
  if (!cohort.sessionIntercept && !cohort.configIntercept) return;
  await page.route("**/laravel/api/public/auth/session**", async (route) => {
    if (!cohort.sessionIntercept) return route.continue();
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: ANON_SESSION,
    });
  });
  await page.route("**/laravel/api/public/content/config**", async (route) => {
    if (!cohort.configIntercept) return route.continue();
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: cachedConfigBody,
    });
  });
}

async function measureCohort(browser, route, cohort, cachedConfigBody) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  await setupCohort(page, cohort, cachedConfigBody);
  const samples = [];
  for (let i = 0; i < N + 1; i += 1) {
    await page.goto(`${BASE}/`, { waitUntil: "domcontentloaded", timeout: 120000 });
    await page.waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", null, { timeout: 20000 }).catch(() => {});
    const clickTs = Date.now();
    await clickNav(page, route);
    await page.waitForSelector(route.usable, { timeout: 30000, state: "visible" }).catch(() => null);
    samples.push(Date.now() - clickTs);
  }
  await context.close();
  const usable = samples.slice(1);
  return { cohort: cohort.id, n: usable.length, USABLE_P50: pct(usable, 50), USABLE_P95: pct(usable, 95), samples: usable };
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const bootstrap = await browser.newContext();
  const bootstrapPage = await bootstrap.newPage();
  const configRes = await bootstrapPage.goto(`${BASE}/laravel/api/public/content/config`, { timeout: 60000 });
  const cachedConfigBody = configRes?.ok() ? await configRes.text() : "{}";
  await bootstrap.close();

  const matrix = {};
  for (const route of ROUTES) {
    matrix[route.name] = {};
    for (const cohort of COHORTS) {
      const result = await measureCohort(browser, route, cohort, cachedConfigBody);
      matrix[route.name][cohort.id] = result;
      console.log(`${route.name} ${cohort.id} P95=${result.USABLE_P95}`);
    }
  }
  await browser.close();

  const loginBase = matrix.home_to_login.baseline.USABLE_P95;
  const loginSession = matrix.home_to_login.session_intercept.USABLE_P95;
  const loginConfig = matrix.home_to_login.config_intercept.USABLE_P95;
  const loginBoth = matrix.home_to_login.both_intercept.USABLE_P95;
  const supportBase = matrix.home_to_support.baseline.USABLE_P95;
  const supportSession = matrix.home_to_support.session_intercept.USABLE_P95;
  const supportConfig = matrix.home_to_support.config_intercept.USABLE_P95;
  const supportBoth = matrix.home_to_support.both_intercept.USABLE_P95;

  const material = (base, variant) => base != null && variant != null && base - variant >= 200;

  const out = {
    phase: "AUTHORITY-06-R3-PUBLICSHELL-AB",
    captured_at: new Date().toISOString(),
    production_sha: "8c50fc61967e51c5b576375b55ae7701d122c121",
    diagnostic_only: true,
    BASELINE_LOGIN_P95: loginBase,
    SESSION_INTERCEPT_LOGIN_P95: loginSession,
    CONFIG_INTERCEPT_LOGIN_P95: loginConfig,
    BOTH_INTERCEPT_LOGIN_P95: loginBoth,
    BASELINE_SUPPORT_P95: supportBase,
    SESSION_INTERCEPT_SUPPORT_P95: supportSession,
    CONFIG_INTERCEPT_SUPPORT_P95: supportConfig,
    BOTH_INTERCEPT_SUPPORT_P95: supportBoth,
    SESSION_BOOTSTRAP_CONTENTION: material(loginBase, loginSession) || material(supportBase, supportSession) ? "PROVEN" : "NOT_PROVEN",
    PUBLIC_CONFIG_CONTENTION: material(loginBase, loginConfig) || material(supportBase, supportConfig) ? "PROVEN" : "NOT_PROVEN",
    COMBINED_CONTENTION: material(loginBase, loginBoth) || material(supportBase, supportBoth) ? "PROVEN" : "NOT_PROVEN",
    matrix,
  };
  fs.writeFileSync(OUT, JSON.stringify(out, null, 2));
  console.log(JSON.stringify(out, null, 2));
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
