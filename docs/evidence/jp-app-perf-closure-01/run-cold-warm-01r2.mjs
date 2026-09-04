/**
 * JP-APP-PERF-CLOSURE-01R2 — Cold vs warm browser shells (read-only).
 * Separate percentiles. No supplier mutations.
 */
import { chromium } from "playwright";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.JP_BASE_URL || "https://jetpakistan.pk";
const N = Number(process.env.JP_COLD_N || 8);

const routes = [
  { name: "home", path: "/", usable: '[data-testid="search-module"], [data-testid="homepage-public-hero"], main' },
  { name: "login", path: "/login", usable: 'input[type="password"], form' },
  { name: "register", path: "/register", usable: "form, input[type='email']" },
  { name: "about", path: "/about-us", usable: "main, h1" },
  { name: "groups", path: "/groups", usable: '[data-testid="groups-landing-page"], main, h1' },
];

function pct(arr, p) {
  const a = arr.filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.ceil((p / 100) * a.length) - 1)];
}

async function measure(browser, route, mode) {
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    userAgent: `Mozilla/5.0 JP-APP-PERF-CLOSURE-01R2-${mode}`,
  });
  const page = await context.newPage();
  const t0 = Date.now();
  await page.goto(BASE + route.path, { waitUntil: "domcontentloaded", timeout: 120000 });
  const shell = Date.now() - t0;
  await page.waitForSelector(route.usable, { timeout: 60000 });
  const usable = Date.now() - t0;
  await context.close();
  return { shell, usable };
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const cold = {};
  const warm = {};

  for (const route of routes) {
    cold[route.name] = { shells: [], usables: [] };
    warm[route.name] = { shells: [], usables: [] };

    // cold: fresh context each sample
    for (let i = 0; i < N; i++) {
      const m = await measure(browser, route, "COLD");
      cold[route.name].shells.push(m.shell);
      cold[route.name].usables.push(m.usable);
      console.log(JSON.stringify({ mode: "cold", route: route.name, i, ...m }));
    }

    // warm: reuse one context, discard first
    const ctx = await browser.newContext({
      viewport: { width: 1440, height: 900 },
      userAgent: "Mozilla/5.0 JP-APP-PERF-CLOSURE-01R2-WARM",
    });
    const page = await ctx.newPage();
    for (let i = 0; i < N + 1; i++) {
      const t0 = Date.now();
      await page.goto(BASE + route.path, { waitUntil: "domcontentloaded", timeout: 120000 });
      const shell = Date.now() - t0;
      await page.waitForSelector(route.usable, { timeout: 60000 });
      const usable = Date.now() - t0;
      if (i > 0) {
        warm[route.name].shells.push(shell);
        warm[route.name].usables.push(usable);
      }
      console.log(JSON.stringify({ mode: "warm", route: route.name, i, shell, usable }));
    }
    await ctx.close();
  }

  const summarize = (bucket) => {
    const out = {};
    for (const [name, v] of Object.entries(bucket)) {
      out[name] = {
        SHELL_P50: pct(v.shells, 50),
        SHELL_P95: pct(v.shells, 95),
        USABLE_P50: pct(v.usables, 50),
        USABLE_P95: pct(v.usables, 95),
      };
    }
    return out;
  };

  const coldS = summarize(cold);
  const warmS = summarize(warm);
  const allColdShell = Object.values(cold).flatMap((v) => v.shells);
  const allColdUsable = Object.values(cold).flatMap((v) => v.usables);
  const allWarmShell = Object.values(warm).flatMap((v) => v.shells);
  const allWarmUsable = Object.values(warm).flatMap((v) => v.usables);

  const worstCold = Math.max(...allColdUsable);
  const out = {
    phase: "JP-APP-PERF-CLOSURE-01R2",
    kind: "cold_warm_browser",
    measured_at: new Date().toISOString(),
    n: N,
    COLD_BROWSER: coldS,
    WARM_BROWSER: warmS,
    COLD_BROWSER_P50: pct(allColdUsable, 50),
    COLD_BROWSER_P95: pct(allColdUsable, 95),
    WARM_BROWSER_P50: pct(allWarmUsable, 50),
    WARM_BROWSER_P95: pct(allWarmUsable, 95),
    COLD_SHELL_P95: pct(allColdShell, 95),
    WARM_SHELL_P95: pct(allWarmShell, 95),
    COLD_APPLICATION_DEFECT_REMAINING: worstCold >= 10000 ? "YES" : "NO",
  };
  fs.writeFileSync(path.join(__dirname, "cold-warm-01r2.json"), JSON.stringify(out, null, 2));
  console.log(JSON.stringify(out, null, 2));
  await browser.close();
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
