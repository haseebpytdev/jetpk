/**
 * Authority-06 R4 — production A/B (read-only).
 * Cohort A: current production prefetch behavior.
 * Cohort B: abort non-destination _rsc prefetches until navigation click (simulates no programmatic prefetch).
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.JP_BASE_URL || "https://jetpakistan.pk";
const COHORT = (process.env.JP_COHORT || "A").toUpperCase();
const OUT = path.join(__dirname, `r4-prefetch-ab-production-${COHORT.toLowerCase()}.json`);

const ROUTES = [
  { name: "home_to_login", href: "/login", n: 30, testid: "header-login-cta", interactiveSelector: '[data-testid="login-form"] input[type="password"]:not([disabled])' },
  { name: "home_to_groups", href: "/groups", n: 20, click: "primary", interactiveSelector: '[data-testid="groups-landing-page"], main h1' },
  { name: "home_to_about", href: "/about-us", n: 20, interactiveSelector: "main h1" },
  { name: "home_to_contact", href: "/contact", n: 20, dropdown: { menu: "Support", label: "Contact Us" }, interactiveSelector: "main h1" },
  { name: "home_to_support", href: "/support", n: 20, dropdown: { menu: "Support", label: "Help Center" }, interactiveSelector: "#support-form-heading" },
];
const AUTH = ["/login", "/register"];

function pct(arr, p) {
  const a = (arr || []).filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}
function summarize(samples) {
  const pick = (k) => samples.map((s) => s[k]).filter((n) => typeof n === "number" && Number.isFinite(n));
  return {
    n: samples.length,
    TOTAL_USABLE_P50: pct(pick("TOTAL_USABLE_MS"), 50),
    TOTAL_USABLE_P95: pct(pick("TOTAL_USABLE_MS"), 95),
    RSC_END_TO_ROUTE_COMMIT_P95: pct(pick("RSC_END_TO_ROUTE_COMMIT_MS"), 95),
    ROUTE_COMMIT_TO_USABLE_P95: pct(pick("ROUTE_COMMIT_TO_USABLE_MS"), 95),
    DUPLICATE_RSC_COUNT_P95: pct(pick("DUPLICATE_RSC_COUNT"), 95),
    PROGRAMMATIC_PREFETCH_RSC_COUNT_P95: pct(pick("PROGRAMMATIC_PREFETCH_RSC_COUNT"), 95),
    OVERLAPPING_CHUNK_COUNT_P95: pct(pick("OVERLAPPING_CHUNK_COUNT"), 95),
    UNRELATED_AUTH_PREFETCH_RATE: samples.filter((s) => s.UNRELATED_AUTH_PREFETCH_ACTIVE_DURING_NAV === "YES").length / Math.max(1, samples.length),
  };
}
async function clickNav(page, route) {
  if (route.testid) return page.getByTestId(route.testid).click({ timeout: 8000 });
  if (route.dropdown) {
    const direct = page.locator(`a[href="${route.href}"]`).first();
    if (await direct.count()) {
      try { await direct.click({ timeout: 5000, force: true }); return; } catch { /* */ }
    }
    const primary = page.getByRole("navigation", { name: "Primary" });
    await primary.getByRole("button", { name: route.dropdown.menu }).click({ timeout: 8000, force: true });
    await page.getByRole("link", { name: route.dropdown.label, exact: true }).click({ timeout: 8000, force: true });
    return;
  }
  if (route.click === "primary") return page.locator('nav[aria-label="Primary"] a[href="/groups"]').first().click({ timeout: 8000 });
  return page.locator(`a[href="${route.href}"]`).first().click({ timeout: 8000, force: true });
}
function pathOf(url) {
  try { return new URL(url).pathname.replace(/\/$/, "") || "/"; } catch { return null; }
}

async function measureSample(page, route, cohort) {
  let clicked = false;
  const dest = route.href.replace(/\/$/, "") || "/";
  const handler = async (pwRoute) => {
    const url = pwRoute.request().url();
    if (cohort === "B" && !clicked && /[?&]_rsc=/.test(url)) {
      const p = pathOf(url);
      if (p && p !== dest) return pwRoute.abort();
    }
    return pwRoute.continue();
  };
  await page.route("**/*", handler);

  const rscLog = [];
  const chunks = [];
  page.on("request", (req) => {
    const url = req.url();
    const ts = Date.now();
    if (/[?&]_rsc=/.test(url)) rscLog.push({ path: pathOf(url), ts, phase: "start" });
    if (/\/_next\/static\/chunks\//.test(url)) chunks.push({ url, start: ts, end: null });
  });
  page.on("response", (res) => {
    const url = res.url();
    const ts = Date.now();
    if (/[?&]_rsc=/.test(url)) rscLog.push({ path: pathOf(url), ts, phase: "end" });
    if (/\/_next\/static\/chunks\//.test(url)) {
      const c = chunks.find((x) => x.url === url && x.end == null);
      if (c) c.end = ts;
    }
  });

  const clickTs = Date.now();
  await clickNav(page, route);
  clicked = true;
  await page.waitForSelector(route.interactiveSelector, { timeout: 30000, state: "visible" }).catch(() => null);
  const endTs = Date.now();

  await page.unroute("**/*", handler);

  const starts = rscLog.filter((e) => e.phase === "start");
  const ends = rscLog.filter((e) => e.phase === "end");
  const destEnds = ends.filter((e) => e.path === dest);
  const lastDestEnd = destEnds.at(-1)?.ts ?? null;
  const routeCommit = await page.evaluate(() => performance.now()).then(() => endTs - clickTs);
  const rscEndToCommit = lastDestEnd != null ? Math.max(0, endTs - lastDestEnd) : null;
  const dup = starts.map((s) => s.path).length - new Set(starts.map((s) => s.path)).size;
  const prefetch = starts.filter((s) => s.path && s.path !== dest && s.ts <= clickTs + 500);
  const unrelatedAuth = starts.filter((s) => s.path && AUTH.includes(s.path) && s.path !== dest);

  return {
    TOTAL_USABLE_MS: endTs - clickTs,
    RSC_END_TO_ROUTE_COMMIT_MS: rscEndToCommit,
    ROUTE_COMMIT_TO_USABLE_MS: routeCommit,
    DUPLICATE_RSC_COUNT: dup,
    PROGRAMMATIC_PREFETCH_RSC_COUNT: prefetch.length,
    OVERLAPPING_CHUNK_COUNT: chunks.filter((c) => c.start <= endTs && (c.end ?? endTs) >= clickTs).length,
    UNRELATED_AUTH_PREFETCH_ACTIVE_DURING_NAV: unrelatedAuth.length > 0 ? "YES" : "NO",
  };
}

async function measureRoute(browser, route, cohort) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  const samples = [];
  for (let i = 0; i < route.n + 1; i += 1) {
    await page.goto(`${BASE}/`, { waitUntil: "domcontentloaded", timeout: 120000 });
    await page.waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", null, { timeout: 20000 }).catch(() => {});
    try { samples.push(await measureSample(page, route, cohort)); } catch (e) { samples.push({ error: String(e?.message || e) }); }
  }
  await context.close();
  const valid = samples.slice(1).filter((s) => !s.error && s.TOTAL_USABLE_MS != null);
  return { route: route.name, ...summarize(valid), samples: valid };
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const matrix = [];
  for (const route of ROUTES) matrix.push(await measureRoute(browser, route, COHORT));
  await browser.close();
  const worst = Math.max(...matrix.map((m) => m.TOTAL_USABLE_P95 || 0));
  const out = { phase: "AUTHORITY-06-R4-PRODUCTION-AB", cohort: COHORT, base: BASE, captured_at: new Date().toISOString(), production_sha: "8c50fc61967e51c5b576375b55ae7701d122c121", matrix, LOCAL_TRUE_SOFT_NAV_WORST_P95: worst };
  fs.writeFileSync(OUT, JSON.stringify(out, null, 2));
  console.log(JSON.stringify({ cohort: COHORT, worst, matrix: matrix.map((m) => ({ r: m.route, n: m.n, p95: m.TOTAL_USABLE_P95 })) }, null, 2));
}

main().catch((e) => { console.error(e); process.exit(1); });
