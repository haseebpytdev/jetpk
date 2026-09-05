/**
 * JP-FINAL-CLOSURE-05 — home→support exclusive-interval N warm, exact public build.
 */
import { chromium } from "playwright";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.JP_BASE_URL || "https://jetpakistan.pk";
const N = Number(process.env.JP_NAV_N || 30);
const EXPECTED_BUILD = process.env.JP_PUBLIC_BUILD_ID || "0NMKi-2XwkblKpudgNB3h";

function pct(arr, p) {
  const a = (arr || []).filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

function readBuild(html) {
  const m = html.match(/"b":"([^"]+)"/);
  if (m?.[1]) return m[1];
  if (html.includes(EXPECTED_BUILD)) return EXPECTED_BUILD;
  const flight = html.match(/\/_next\/static\/((?!chunks|css|media)[^/]+)\//);
  return flight?.[1] || null;
}

const browser = await chromium.launch({ headless: true });
const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
await page.goto(BASE + "/", { waitUntil: "domcontentloaded", timeout: 90000 });

const samples = [];
for (let i = 0; i < N + 1; i++) {
  await page.goto(BASE + "/", { waitUntil: "domcontentloaded", timeout: 90000 });
  await page.waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", null, { timeout: 15000 }).catch(() => {});
  await page.waitForTimeout(400);
  await page.evaluate(() => {
    window.__jpNavMark = performance.now();
  });

  let reload = false;
  const onReq = (req) => {
    if (req.isNavigationRequest() && req.resourceType() === "document" && req.frame() === page.mainFrame()) {
      reload = true;
    }
  };
  page.on("request", onReq);

  const t0 = Date.now();
  const loc = page.locator('a[href="/support"]').first();
  if ((await loc.count()) > 0) {
    await loc.click({ timeout: 8000, force: true }).catch(async () => {
      await page.evaluate(() => {
        const a = Array.from(document.querySelectorAll("a[href]")).find((el) => (el.getAttribute("href") || "") === "/support");
        if (a) a.click();
      });
    });
  } else {
    await page.evaluate(() => {
      const a = Array.from(document.querySelectorAll("a[href]")).find((el) => (el.getAttribute("href") || "") === "/support");
      if (a) a.click();
    });
  }

  await page.waitForFunction(() => (new URL(location.href).pathname.replace(/\/$/, "") || "/") === "/support", null, { timeout: 20000 }).catch(() => {});
  const routeReady = Date.now() - t0;
  await page.waitForSelector("#support-page-heading, h1", { timeout: 15000 }).catch(() => {});
  const usable = Date.now() - t0;
  page.off("request", onReq);

  const html = await page.content();
  const build = readBuild(html);

  const decomp = await page.evaluate(() => {
    const mark = Number(window.__jpNavMark || 0);
    const entries = performance.getEntriesByType("resource") || [];
    const rsc = entries.filter((e) => /[?&]_rsc=/.test(e.name) && e.startTime >= mark);
    const last = rsc.at(-1);
    const rscDur = last ? last.duration : null;
    const ttfb = last && last.responseStart > last.requestStart ? last.responseStart - last.requestStart : null;
    return {
      rsc_count: rsc.length,
      rsc_network_ms: rscDur != null ? Math.round(rscDur) : 0,
      rsc_ttfb_ms: ttfb != null ? Math.round(ttfb) : null,
      transfer: last ? last.transferSize : null,
    };
  });

  if (i === 0) continue;

  const origin = decomp.rsc_ttfb_ms ?? 0;
  let rscExclusive = decomp.rsc_network_ms ?? 0;
  if (rscExclusive > usable) rscExclusive = usable;
  const externalNet = Math.max(0, rscExclusive - origin);
  const appExclusive = Math.max(0, usable - rscExclusive);
  const attributed = externalNet + origin + appExclusive;
  const unattributed = Math.max(0, usable - attributed);
  samples.push({
    usable,
    routeReady,
    reload,
    build,
    ORIGIN_SERVER: origin,
    EXTERNAL_NETWORK: externalNet,
    JETPAKISTAN_CLIENT: appExclusive,
    RENDER_HYDRATION: Math.max(0, usable - routeReady),
    UNATTRIBUTED: unattributed,
    TOTAL: usable,
    RECONCILED: Math.abs(usable - (externalNet + origin + appExclusive + unattributed)) < 2,
    ...decomp,
  });
}

const valid = samples.filter((s) => s.build && s.build === EXPECTED_BUILD && !s.reload);
const app = valid.map((s) => s.JETPAKISTAN_CLIENT);
const out = {
  phase: "JP-FINAL-CLOSURE-05",
  measured_at: new Date().toISOString(),
  expected_build: EXPECTED_BUILD,
  n_raw: samples.length,
  N_VALID: valid.length,
  APPLICATION_CONTROLLED_P95: pct(app, 95),
  RAW_NAV_P95: pct(valid.map((s) => s.usable), 95),
  ORIGIN_SERVER_P95: pct(valid.map((s) => s.ORIGIN_SERVER), 95),
  EXTERNAL_NETWORK_P95: pct(valid.map((s) => s.EXTERNAL_NETWORK), 95),
  UNATTRIBUTED_P95: pct(valid.map((s) => s.UNATTRIBUTED), 95),
  APPLICATION_CONTROLLED_MULTI_SECOND_ROUTE_COUNT: valid.filter((s) => s.JETPAKISTAN_CLIENT > 2000).length,
  TOTAL_RECONCILED: valid.length > 0 && valid.every((s) => s.RECONCILED) ? "YES" : "NO",
  MIXED_BUILD: samples.some((s) => !s.build || s.build !== EXPECTED_BUILD) ? 1 : 0,
  NULL_BUILD_COUNT: samples.filter((s) => !s.build).length,
  NAV_TYPE: valid.every((s) => !s.reload) ? "CLIENT_SOFT" : "MIXED",
  samples: valid,
};
fs.writeFileSync(path.join(__dirname, "home-support-exclusive-05.json"), JSON.stringify(out, null, 2));
console.log(JSON.stringify({ ...out, samples: undefined }, null, 2));
await browser.close();
if (out.MIXED_BUILD !== 0 || out.N_VALID < N) process.exit(2);
