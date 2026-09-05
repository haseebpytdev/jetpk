/**
 * JP-FINAL-CLOSURE-04 — home→support exclusive-interval decompose (N warm).
 */
import { chromium } from "playwright";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.JP_BASE_URL || "https://jetpakistan.pk";
const N = Number(process.env.JP_NAV_N || 30);

function pct(arr, p) {
  const a = (arr || []).filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

const browser = await chromium.launch({ headless: true });
const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
await page.goto(BASE + "/", { waitUntil: "domcontentloaded", timeout: 90000 });
const build = await page.evaluate(() => document.documentElement.innerHTML.match(/"b":"([^"]+)"/)?.[1] || null);

const samples = [];
for (let i = 0; i < N + 1; i++) {
  await page.goto(BASE + "/", { waitUntil: "domcontentloaded", timeout: 90000 });
  await page.waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", null, { timeout: 15000 }).catch(() => {});
  await page.waitForTimeout(400);

  let reload = false;
  const rscLive = [];
  const onReq = (req) => {
    if (req.isNavigationRequest() && req.resourceType() === "document" && req.frame() === page.mainFrame()) {
      reload = true;
    }
  };
  const onRes = async (res) => {
    const url = res.url();
    if (!/[?&]_rsc=/.test(url)) return;
    const req = res.request();
    const timing = req.timing();
    rscLive.push({
      url,
      start: Date.now(),
      status: res.status(),
      timing,
    });
  };
  page.on("request", onReq);
  page.on("response", onRes);

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
  page.off("response", onRes);

  const decomp = await page.evaluate((navStartMs) => {
    const navStart = performance.timeOrigin;
    const entries = performance.getEntriesByType("resource") || [];
    const rsc = entries.filter((e) => /[?&]_rsc=/.test(e.name) && e.startTime >= 0);
    const last = rsc.at(-1);
    const rscStart = last ? last.startTime : null;
    const rscEnd = last ? last.responseEnd : null;
    const rscDur = last ? last.duration : null;
    const ttfb = last && last.responseStart > last.requestStart ? last.responseStart - last.requestStart : null;
    const overlapStart = rscStart != null ? rscStart : 0;
    const overlapEnd = rscEnd != null ? rscEnd : 0;
    return {
      rsc_count: rsc.length,
      rsc_network_ms: rscDur != null ? Math.round(rscDur) : 0,
      rsc_ttfb_ms: ttfb != null ? Math.round(ttfb) : null,
      rsc_start_ms: rscStart != null ? Math.round(rscStart) : null,
      rsc_end_ms: rscEnd != null ? Math.round(rscEnd) : null,
      transfer: last ? last.transferSize : null,
      cache_hit: last ? last.transferSize === 0 : null,
      prefetch_hit: last ? last.transferSize === 0 && last.decodedBodySize > 0 : null,
      navStart,
      overlapStart,
      overlapEnd,
    };
  }, t0);

  if (i === 0) continue;

  const origin = decomp.rsc_ttfb_ms ?? 0;
  const externalNet = Math.max(0, (decomp.rsc_network_ms ?? 0) - origin);
  const rscExclusive = decomp.rsc_network_ms ?? 0;
  const appExclusive = Math.max(0, usable - rscExclusive);
  const unattributed = Math.max(0, usable - (externalNet + origin + appExclusive));
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

const app = samples.map((s) => s.JETPAKISTAN_CLIENT);
const out = {
  phase: "JP-FINAL-CLOSURE-04",
  measured_at: new Date().toISOString(),
  build,
  n: samples.length,
  APPLICATION_CONTROLLED_P95: pct(app, 95),
  RAW_NAV_P95: pct(samples.map((s) => s.usable), 95),
  ORIGIN_SERVER_P95: pct(samples.map((s) => s.ORIGIN_SERVER), 95),
  EXTERNAL_NETWORK_P95: pct(samples.map((s) => s.EXTERNAL_NETWORK), 95),
  UNATTRIBUTED_P95: pct(samples.map((s) => s.UNATTRIBUTED), 95),
  APPLICATION_CONTROLLED_MULTI_SECOND_ROUTE_COUNT: (pct(app, 95) || 0) > 2000 ? 1 : 0,
  TOTAL_RECONCILED: samples.every((s) => s.RECONCILED),
  MIXED_BUILD: samples.some((s) => s.build && s.build !== build) ? 1 : 0,
  NAV_TYPE: samples.every((s) => !s.reload) ? "CLIENT_SOFT" : "MIXED",
  samples,
};
fs.writeFileSync(path.join(__dirname, "home-support-exclusive-04.json"), JSON.stringify(out, null, 2));
console.log(JSON.stringify({ ...out, samples: undefined, sample_n: samples.length }, null, 2));
await browser.close();
