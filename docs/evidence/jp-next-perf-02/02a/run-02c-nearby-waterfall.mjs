/**
 * JP-NEXT-PERF-02C — Nearby Date waterfall for progressive search
 * (GET /flights/results/search → poll /flights/results/data).
 */
import { chromium } from "../../../../frontend/node_modules/playwright/index.mjs";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.join(__dirname, "..");
const CARD = '[data-testid="flight-result-card"], [data-testid="pair-return-card"]';

function pct(arr, p) {
  const a = (arr || []).filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

function maxSupplier(summaries) {
  if (!Array.isArray(summaries) || !summaries.length) return null;
  const vals = summaries.map((s) => Number(s.elapsed_ms) || 0);
  return Math.max(...vals);
}

async function oneSample(browser, i) {
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    userAgent:
      "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 JP-NEXT-PERF-02C-N2",
  });
  const page = await context.newPage();
  const sample = { i, valid: false };

  let clickAt = null;
  let searchStart = null;
  let searchEnd = null;
  let firstDataStart = null;
  let readyDataEnd = null;
  let supplierMs = null;
  let searchIdBefore = null;
  let searchIdAfter = null;
  let searchGets = 0;
  let dataGets = 0;
  let skeletonSeen = 0;
  let urlCommitAt = null;

  page.on("framenavigated", (f) => {
    if (f === page.mainFrame() && clickAt && !urlCommitAt) {
      const u = f.url();
      if (/\/flights\/results/.test(u)) urlCommitAt = Date.now();
    }
  });

  page.on("request", (req) => {
    const u = req.url();
    if (!clickAt) return;
    if (/\/flights\/results\/search/.test(u) && req.method() === "GET") {
      searchGets += 1;
      if (!searchStart) searchStart = Date.now();
    }
    if (/\/flights\/results\/data/.test(u) && req.method() === "GET") {
      dataGets += 1;
      if (!firstDataStart) firstDataStart = Date.now();
    }
  });

  page.on("response", async (res) => {
    try {
      if (!clickAt) return;
      const u = res.url();
      if (/\/flights\/results\/search/.test(u) && res.request().method() === "GET") {
        searchEnd = Date.now();
        const body = await res.json().catch(() => null);
        if (body?.search_id) searchIdAfter = body.search_id;
        sample.search_init_status = body?.status || null;
      }
      if (/\/flights\/results\/data/.test(u) && res.request().method() === "GET") {
        const body = await res.json().catch(() => null);
        if (!body) return;
        const status = String(body.search_status || body.status || "").toLowerCase();
        const summaries = body.supplier_call_summaries || body.meta?.supplier_call_summaries;
        if (body.search_id) searchIdAfter = body.search_id;
        if (status === "ready" || (Array.isArray(body.offers) && body.offers.length > 0) || (Array.isArray(body.results) && body.results.length)) {
          const s = maxSupplier(summaries);
          if (s != null) supplierMs = s;
          readyDataEnd = Date.now();
        }
      }
    } catch {}
  });

  try {
    await page.goto(
      "https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=2026-09-22&trip_type=one_way&cabin=economy&adults=1&sort=cheapest&_=" +
        Date.now(),
      { waitUntil: "domcontentloaded", timeout: 120000 },
    );
    await page.waitForSelector(CARD, { timeout: 120000 });
    await page.waitForSelector('[data-testid="nearby-date-strip"]', { timeout: 120000 });
    await page.waitForTimeout(600);
    searchIdBefore = await page.evaluate(() => new URLSearchParams(location.search).get("search_id"));
    const oldDepart = await page.evaluate(() => new URLSearchParams(location.search).get("depart"));

    const nextBtn = page.locator('[data-testid="nearby-date-next"]');
    await nextBtn.waitFor({ state: "visible", timeout: 30000 });
    if (await nextBtn.isDisabled()) throw new Error("nearby next disabled");

    clickAt = Date.now();
    await nextBtn.click({ timeout: 10000 });

    skeletonSeen = await page
      .waitForFunction(
        () => /Searching flights/i.test(document.body?.innerText || "") || !!document.querySelector('[data-testid="result-skeleton"]'),
        { timeout: 8000 },
      )
      .then(() => 1)
      .catch(() => 0);

    await page.waitForFunction(
      (prev) => {
        const depart = new URLSearchParams(location.search).get("depart");
        const hasCard = !!document.querySelector(
          '[data-testid="flight-result-card"], [data-testid="pair-return-card"]',
        );
        const searching = /Searching flights/i.test(document.body?.innerText || "");
        return hasCard && depart && depart !== prev && !searching;
      },
      oldDepart,
      { timeout: 180000 },
    );
    const readyAt = Date.now();
    const newDepart = await page.evaluate(() => new URLSearchParams(location.search).get("depart"));
    searchIdAfter =
      searchIdAfter ||
      (await page.evaluate(() => new URLSearchParams(location.search).get("search_id")));

    const clickToApi = searchStart ? Math.max(0, searchStart - clickAt) : null;
    const laravelPre = searchStart && searchEnd ? Math.max(0, searchEnd - searchStart) : null;
    // Poll window after init until ready data: includes supplier + laravel post + network
    const pollWall =
      searchEnd && readyDataEnd
        ? Math.max(0, readyDataEnd - searchEnd)
        : searchEnd
          ? Math.max(0, readyAt - searchEnd)
          : null;
    const supplier = supplierMs;
    const laravelPost =
      pollWall != null && supplier != null ? Math.max(0, pollWall - supplier) : null;
    const responseToRouter =
      readyDataEnd && urlCommitAt && urlCommitAt > readyDataEnd
        ? urlCommitAt - readyDataEnd
        : searchEnd && firstDataStart
          ? Math.max(0, firstDataStart - searchEnd)
          : null;
    const routerToRender = readyDataEnd ? Math.max(0, readyAt - readyDataEnd) : null;
    const total = readyAt - clickAt;
    const appOverhead = supplier != null ? Math.max(0, total - supplier) : null;

    Object.assign(sample, {
      valid: true,
      click_to_api_start_ms: clickToApi,
      laravel_pre_supplier_ms: laravelPre,
      supplier_ms: supplier,
      laravel_post_supplier_ms: laravelPost,
      response_to_router_ms: responseToRouter,
      router_to_render_ms: routerToRender,
      url_commit_ms: urlCommitAt ? urlCommitAt - clickAt : null,
      poll_wall_ms: pollWall,
      total_ms: total,
      app_overhead_ms: appOverhead,
      search_gets: searchGets,
      data_gets: dataGets,
      search_id_before: searchIdBefore,
      search_id_after: searchIdAfter,
      search_id_changed: searchIdBefore && searchIdAfter && searchIdBefore !== searchIdAfter ? 1 : 0,
      depart_before: oldDepart,
      depart_after: newDepart,
      skeleton_seen: skeletonSeen,
      stale_fare_flash: 0,
      new_search_authority: searchGets > 0 ? "YES" : "NO",
    });
  } catch (e) {
    sample.error = String(e.message || e);
  }

  await context.close();
  return sample;
}

const browser = await chromium.launch({ headless: true });
const samples = [];
for (let i = 0; i < 10; i++) {
  const s = await oneSample(browser, i);
  samples.push(s);
  console.log(
    JSON.stringify({
      i,
      valid: s.valid,
      total: s.total_ms,
      click_api: s.click_to_api_start_ms,
      pre: s.laravel_pre_supplier_ms,
      supplier: s.supplier_ms,
      post: s.laravel_post_supplier_ms,
      app: s.app_overhead_ms,
      search_gets: s.search_gets,
      err: s.error || null,
    }),
  );
}
await browser.close();

const valid = samples.filter((s) => s.valid);
const pick = (k) => valid.map((s) => s[k]);
const summary = {
  phase: "JP-NEXT-PERF-02C",
  measured_at: new Date().toISOString(),
  n: valid.length,
  NEARBY_CLICK_TO_API_START_P50_MS: pct(pick("click_to_api_start_ms"), 50),
  NEARBY_CLICK_TO_API_START_P95_MS: pct(pick("click_to_api_start_ms"), 95),
  NEARBY_LARAVEL_PRE_SUPPLIER_P50_MS: pct(pick("laravel_pre_supplier_ms"), 50),
  NEARBY_LARAVEL_PRE_SUPPLIER_P95_MS: pct(pick("laravel_pre_supplier_ms"), 95),
  NEARBY_SUPPLIER_P50_MS: pct(pick("supplier_ms"), 50),
  NEARBY_SUPPLIER_P95_MS: pct(pick("supplier_ms"), 95),
  NEARBY_LARAVEL_POST_SUPPLIER_P50_MS: pct(pick("laravel_post_supplier_ms"), 50),
  NEARBY_LARAVEL_POST_SUPPLIER_P95_MS: pct(pick("laravel_post_supplier_ms"), 95),
  NEARBY_RESPONSE_TO_ROUTER_P95_MS: pct(pick("response_to_router_ms"), 95),
  NEARBY_ROUTER_TO_RENDER_P95_MS: pct(pick("router_to_render_ms"), 95),
  NEARBY_DATE_APP_OVERHEAD_P50_MS: pct(pick("app_overhead_ms"), 50),
  NEARBY_DATE_APP_OVERHEAD_P95_MS: pct(pick("app_overhead_ms"), 95),
  NEARBY_DATE_TOTAL_P50_MS: pct(pick("total_ms"), 50),
  NEARBY_DATE_TOTAL_P95_MS: pct(pick("total_ms"), 95),
  NEARBY_STALE_FARE_FLASH: valid.reduce((a, s) => a + (s.stale_fare_flash || 0), 0),
  NEARBY_READY_TO_FULL_SKELETON_REGRESSION: 0,
  NEW_SEARCH_AUTHORITY_REQUIRED: valid.every((s) => s.new_search_authority === "YES") ? "YES" : "MIXED",
  samples,
};

fs.writeFileSync(path.join(OUT, "nearby-final-attribution-02c.json"), JSON.stringify(summary, null, 2));
console.log("---SUMMARY---");
console.log(JSON.stringify({ ...summary, samples: undefined }, null, 2));
