/**
 * JP-APP-PERF-CLOSURE-01R — separate cold vs warm navigation (ordinary pages).
 * Do not mix into warm P95.
 */
import { chromium } from "playwright";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.JP_BASE_URL || "https://jetpakistan.pk";
const N = Number(process.env.JP_COLD_N || 8);

const routes = [
  { to: "/", usable: '[data-testid="search-module"], [data-testid="homepage-public-hero"], main' },
  { to: "/login", usable: 'input[type="password"], form' },
  { to: "/about-us", usable: "main, h1" },
  { to: "/groups", usable: "main, h1" },
];

function pct(arr, p) {
  const a = arr.filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.ceil((p / 100) * a.length) - 1)];
}

const browser = await chromium.launch({ headless: true });
const cold = [];
const warm = [];

for (const route of routes) {
  // COLD: fresh context each sample
  const coldShells = [];
  const coldUsables = [];
  for (let i = 0; i < N; i++) {
    const context = await browser.newContext({
      viewport: { width: 1440, height: 900 },
      userAgent: "Mozilla/5.0 JP-APP-PERF-CLOSURE-01R-COLD",
    });
    const page = await context.newPage();
    const t0 = Date.now();
    await page.goto(BASE + route.to, { waitUntil: "domcontentloaded", timeout: 120000 });
    coldShells.push(Date.now() - t0);
    try {
      await page.waitForSelector(route.usable, { timeout: 30000 });
      coldUsables.push(Date.now() - t0);
    } catch {
      coldUsables.push(null);
    }
    await context.close();
  }

  // WARM: one context, discard first
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    userAgent: "Mozilla/5.0 JP-APP-PERF-CLOSURE-01R-WARM",
  });
  const page = await context.newPage();
  await page.goto(BASE + route.to, { waitUntil: "domcontentloaded", timeout: 120000 });
  const warmShells = [];
  const warmUsables = [];
  for (let i = 0; i < N; i++) {
    const t0 = Date.now();
    await page.goto(BASE + route.to, { waitUntil: "domcontentloaded", timeout: 120000 });
    warmShells.push(Date.now() - t0);
    try {
      await page.waitForSelector(route.usable, { timeout: 20000 });
      warmUsables.push(Date.now() - t0);
    } catch {
      warmUsables.push(null);
    }
  }
  await context.close();

  cold.push({
    route: route.to,
    COLD_SHELL_P95: pct(coldShells, 95),
    COLD_USABLE_P95: pct(coldUsables, 95),
  });
  warm.push({
    route: route.to,
    WARM_SHELL_P95: pct(warmShells, 95),
    WARM_USABLE_P95: pct(warmUsables, 95),
  });
  console.log(
    route.to,
    "cold_usable_p95",
    pct(coldUsables, 95),
    "warm_usable_p95",
    pct(warmUsables, 95),
  );
}

const out = {
  phase: "JP-APP-PERF-CLOSURE-01R",
  measured_at: new Date().toISOString(),
  n: N,
  cold,
  warm,
  COLD_POST_DEPLOY_P95_MS: Math.max(...cold.map((c) => c.COLD_USABLE_P95 || 0)),
  WARM_BROWSER_NAV_P95_MS: Math.max(...warm.map((w) => w.WARM_USABLE_P95 || 0)),
};
fs.writeFileSync(path.join(__dirname, "cold-warm-nav.json"), JSON.stringify(out, null, 2));
console.log(JSON.stringify({ COLD_P95: out.COLD_POST_DEPLOY_P95_MS, WARM_P95: out.WARM_BROWSER_NAV_P95_MS }, null, 2));
await browser.close();
