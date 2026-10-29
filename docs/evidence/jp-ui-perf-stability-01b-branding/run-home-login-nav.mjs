/**
 * Warm home → /login soft-nav benchmark for JP-UI-PERF-01B auth SSR gate.
 * Captures median/p95 and Laravel public_config HIT/MISS from Next stdout file if provided.
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.JP_01B_BASE_URL ?? "http://127.0.0.1:3021";
const N = Number(process.env.JP_01B_NAV_N ?? "12");
const OUT = path.join(__dirname, "home-login-nav.json");
const LOG_PATH = process.env.JP_01B_NEXT_LOG ?? "";

function pct(arr, p) {
  const a = [...arr].filter(Number.isFinite).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

async function waitRouterReady(page) {
  await page.waitForLoadState("networkidle", { timeout: 30000 }).catch(() => null);
  await page
    .waitForFunction(
      () => Boolean(document.querySelector('[data-testid="site-logo-link"], header a[href="/"]')),
      { timeout: 15000 },
    )
    .catch(() => null);
  await page.waitForTimeout(200);
}

function pathOf(urlOrPath) {
  try {
    if (urlOrPath.startsWith("http")) return new URL(urlOrPath).pathname.replace(/\/$/, "") || "/";
    return urlOrPath.replace(/\/$/, "") || "/";
  } catch {
    return urlOrPath;
  }
}

const samples = [];
const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({
  viewport: { width: 1440, height: 900 },
  serviceWorkers: "block",
});
const page = await ctx.newPage();

await page.goto(`${BASE}/`, { waitUntil: "domcontentloaded", timeout: 120000 });
await waitRouterReady(page);

for (let i = 0; i < N; i++) {
  if (pathOf(page.url()) !== "/") {
    await page.goto(`${BASE}/`, { waitUntil: "domcontentloaded", timeout: 120000 });
    await waitRouterReady(page);
  }

  const link = page.locator('header a[href="/login"]').first();
  const visible = await link.isVisible().catch(() => false);
  if (!visible) {
    samples.push({ i, ok: false, reason: "login_link_missing" });
    continue;
  }

  const t0 = Date.now();
  await Promise.all([
    page.waitForURL((url) => pathOf(url.toString()) === "/login", { timeout: 20000 }),
    link.click({ timeout: 8000 }),
  ]);
  await page
    .waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", { timeout: 15000 })
    .catch(() => null);
  const ms = Date.now() - t0;
  const logoSrc = await page
    .locator('[data-testid="jetpakistan-header-logo"]')
    .first()
    .getAttribute("src")
    .catch(() => null);
  samples.push({ i, ok: true, ms, logoSrc });

  // Soft back home via logo when possible.
  const home = page.locator('[data-testid="site-logo-link"], header a[href="/"]').first();
  if (await home.isVisible().catch(() => false)) {
    await Promise.all([
      page.waitForURL((url) => pathOf(url.toString()) === "/", { timeout: 15000 }).catch(() => null),
      home.click({ timeout: 8000 }).catch(() => null),
    ]);
  }
}

await browser.close();

const okMs = samples.filter((s) => s.ok).map((s) => s.ms);
const summary = {
  base: BASE,
  n: N,
  ok: okMs.length,
  median: pct(okMs, 50),
  p95: pct(okMs, 95),
  samples,
};

if (LOG_PATH && fs.existsSync(LOG_PATH)) {
  const log = fs.readFileSync(LOG_PATH, "utf8");
  const lines = log
    .split(/\r?\n/)
    .map((line) => {
      try {
        return JSON.parse(line);
      } catch {
        return null;
      }
    })
    .filter((row) => row && row.scope === "jp-public-content" && row.resource === "public_config");
  summary.configLog = {
    total: lines.length,
    hits: lines.filter((r) => r.CACHE_STATUS === "HIT").length,
    misses: lines.filter((r) => r.CACHE_STATUS === "MISS").length,
    bypass: lines.filter((r) => r.CACHE_STATUS === "BYPASS").length,
  };
}

fs.writeFileSync(OUT, JSON.stringify(summary, null, 2));
console.log(JSON.stringify(summary, null, 2));
