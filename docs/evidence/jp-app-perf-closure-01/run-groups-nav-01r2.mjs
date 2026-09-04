/**
 * JP-APP-PERF-CLOSURE-01R2 — Groups click classification probe (N=12 warm).
 */
import { chromium } from "playwright";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.JP_BASE_URL || "https://jetpakistan.pk";
const N = Number(process.env.JP_NAV_N || 12);

function pct(arr, p) {
  const a = arr.filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.ceil((p / 100) * a.length) - 1)];
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    userAgent: "Mozilla/5.0 JP-APP-PERF-CLOSURE-01R2-GROUPS",
  });
  const page = await ctx.newPage();
  const samples = [];

  await page.goto(BASE + "/", { waitUntil: "domcontentloaded", timeout: 120000 });
  // warm
  for (let i = 0; i < 2; i++) {
    await page.goto(BASE + "/", { waitUntil: "domcontentloaded", timeout: 90000 });
    await page.waitForTimeout(400);
  }

  for (let i = 0; i < N; i++) {
    await page.goto(BASE + "/", { waitUntil: "domcontentloaded", timeout: 90000 });
    await page.waitForTimeout(500);
    let docHits = 0;
    let rscHits = 0;
    const onReq = (req) => {
      const u = req.url();
      if (req.resourceType() === "document" && /\/groups(?:\?|$)/.test(u) && !/laravel/.test(u)) docHits += 1;
      if (/\/groups/i.test(u) && (req.headers()["rsc"] === "1" || /_rsc=|next-router/.test(u))) rscHits += 1;
    };
    page.on("request", onReq);
    const t0 = Date.now();
    const link = page.locator('nav a[href="/groups"], a[href="/groups"]').first();
    await link.click({ timeout: 15000 });
    await page.waitForURL(/\/groups/, { waitUntil: "domcontentloaded", timeout: 60000 });
    const shellAt = Date.now();
    await page.waitForSelector('[data-testid="groups-landing-page"], main h1, main', { timeout: 60000 });
    const usableAt = Date.now();
    page.off("request", onReq);
    // Prefer RSC evidence over document when both seen (prefetch/document race).
    const navType =
      rscHits > 0 && docHits === 0
        ? "CLIENT_SOFT_RSC"
        : docHits > 0 && rscHits === 0
          ? "HARD_DOCUMENT"
          : docHits > 0 && rscHits > 0
            ? "MIXED"
            : "CLIENT_SOFT";
    samples.push({
      i,
      docHits,
      rscHits,
      navType,
      shell_ms: shellAt - t0,
      usable_ms: usableAt - t0,
      url: page.url(),
    });
    console.log(JSON.stringify(samples[samples.length - 1]));
  }

  const shell = samples.map((s) => s.shell_ms);
  const usable = samples.map((s) => s.usable_ms);
  const hard = samples.filter((s) => s.navType === "HARD_DOCUMENT").length;
  const out = {
    phase: "JP-APP-PERF-CLOSURE-01R2",
    kind: "groups_nav_probe",
    measured_at: new Date().toISOString(),
    N: samples.length,
    GROUPS_NAV_TYPE: hard === samples.length ? "HARD_REQUIRED" : hard === 0 ? "CLIENT_SOFT" : "MIXED",
    GROUPS_FULL_DOCUMENT_RELOAD: hard / samples.length,
    GROUPS_WARM_SHELL_P50: pct(shell, 50),
    GROUPS_WARM_SHELL_P95: pct(shell, 95),
    GROUPS_WARM_USABLE_P50: pct(usable, 50),
    GROUPS_WARM_USABLE_P95: pct(usable, 95),
    samples,
  };
  fs.writeFileSync(path.join(__dirname, "groups-nav-01r2.json"), JSON.stringify(out, null, 2));
  console.log(JSON.stringify({ summary: out }, null, 2));
  await browser.close();
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
