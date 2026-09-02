/**
 * JP-NEXT-PERF-02C — One Way attribution (progressive GET /flights/results/search).
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
  return Math.max(...summaries.map((s) => Number(s.elapsed_ms) || 0));
}

async function oneSample(browser, i) {
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    userAgent:
      "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 JP-NEXT-PERF-02C-OW2",
  });
  const page = await context.newPage();
  const sample = { i, valid: false };

  let navStart = null;
  let searchStart = null;
  let searchEnd = null;
  let readyDataEnd = null;
  let supplierMs = null;

  page.on("request", (req) => {
    const u = req.url();
    if (/\/flights\/results\/search/.test(u) && req.method() === "GET" && !searchStart) {
      searchStart = Date.now();
    }
  });

  page.on("response", async (res) => {
    try {
      const u = res.url();
      if (/\/flights\/results\/search/.test(u) && res.request().method() === "GET") {
        searchEnd = Date.now();
        const body = await res.json().catch(() => null);
        sample.search_init_status = body?.status || null;
      }
      if (/\/flights\/results\/data/.test(u) && res.request().method() === "GET") {
        const body = await res.json().catch(() => null);
        if (!body) return;
        const status = String(body.search_status || body.status || "").toLowerCase();
        const summaries = body.supplier_call_summaries || body.meta?.supplier_call_summaries;
        if (status === "ready" || (Array.isArray(body.offers) && body.offers.length > 0)) {
          const s = maxSupplier(summaries);
          if (s != null) supplierMs = s;
          readyDataEnd = Date.now();
        }
      }
    } catch {}
  });

  try {
    navStart = Date.now();
    await page.goto(
      "https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=2026-09-24&trip_type=one_way&cabin=economy&adults=1&sort=cheapest&_=" +
        Date.now(),
      { waitUntil: "domcontentloaded", timeout: 120000 },
    );
    await page.waitForSelector(CARD, { timeout: 180000 });
    const readyAt = Date.now();

    const laravelPre = searchStart && searchEnd ? Math.max(0, searchEnd - searchStart) : null;
    const pollWall =
      searchEnd && readyDataEnd
        ? Math.max(0, readyDataEnd - searchEnd)
        : searchEnd
          ? Math.max(0, readyAt - searchEnd)
          : null;
    const supplier = supplierMs;
    const laravelPost =
      pollWall != null && supplier != null ? Math.max(0, pollWall - supplier) : null;
    const laravelNonSupplier =
      laravelPre != null || laravelPost != null
        ? Math.max(0, (laravelPre || 0) + (laravelPost || 0))
        : null;
    const nextOverhead = searchStart ? Math.max(0, searchStart - navStart) + Math.max(0, readyAt - (readyDataEnd || searchEnd || navStart)) : Math.max(0, readyAt - navStart);

    Object.assign(sample, {
      valid: true,
      total_ms: readyAt - navStart,
      laravel_pre_supplier_ms: laravelPre,
      supplier_ms: supplier,
      laravel_post_supplier_ms: laravelPost,
      laravel_non_supplier_ms: laravelNonSupplier,
      next_overhead_ms: nextOverhead,
      poll_wall_ms: pollWall,
      click_to_search_ms: searchStart && navStart ? searchStart - navStart : null,
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
      pre: s.laravel_pre_supplier_ms,
      supplier: s.supplier_ms,
      post: s.laravel_post_supplier_ms,
      ns: s.laravel_non_supplier_ms,
      next: s.next_overhead_ms,
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
  ONEWAY_LARAVEL_PRE_SUPPLIER_P50_MS: pct(pick("laravel_pre_supplier_ms"), 50),
  ONEWAY_LARAVEL_PRE_SUPPLIER_P95_MS: pct(pick("laravel_pre_supplier_ms"), 95),
  ONEWAY_SUPPLIER_P50_MS: pct(pick("supplier_ms"), 50),
  ONEWAY_SUPPLIER_P95_MS: pct(pick("supplier_ms"), 95),
  ONEWAY_LARAVEL_POST_SUPPLIER_P50_MS: pct(pick("laravel_post_supplier_ms"), 50),
  ONEWAY_LARAVEL_POST_SUPPLIER_P95_MS: pct(pick("laravel_post_supplier_ms"), 95),
  ONEWAY_LARAVEL_NON_SUPPLIER_P50_MS: pct(pick("laravel_non_supplier_ms"), 50),
  ONEWAY_LARAVEL_NON_SUPPLIER_P95_MS: pct(pick("laravel_non_supplier_ms"), 95),
  ONEWAY_NEXT_OVERHEAD_P50_MS: pct(pick("next_overhead_ms"), 50),
  ONEWAY_NEXT_OVERHEAD_P95_MS: pct(pick("next_overhead_ms"), 95),
  ONEWAY_TOTAL_P50_MS: pct(pick("total_ms"), 50),
  ONEWAY_TOTAL_P95_MS: pct(pick("total_ms"), 95),
  note:
    "Progressive search: Laravel pre = GET /flights/results/search wall. Supplier = max(supplier_call_summaries.elapsed_ms) on READY data. Laravel post = poll wall − supplier (includes queue/normalization residual).",
  samples,
};

fs.writeFileSync(path.join(OUT, "oneway-final-attribution-02c.json"), JSON.stringify(summary, null, 2));
console.log("---SUMMARY---");
console.log(JSON.stringify({ ...summary, samples: undefined }, null, 2));
