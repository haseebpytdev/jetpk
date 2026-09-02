/**
 * JP-LARAVEL-PERF-01 — fast One Way search-init / pre-supplier probe.
 * MODE=init (default): only measures GET /flights/results/search wall + search_perf.
 * MODE=full: also waits for READY poll (slower).
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.join(__dirname);
const TARGET = Number(process.env.JP_PERF_N || 20);
const MAX_ATTEMPTS = Math.max(TARGET + 10, 35);
const LABEL = process.env.JP_PERF_LABEL || "before";
const MODE = process.env.JP_PERF_MODE || "init";

function pct(arr, p) {
  const a = (arr || []).filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

function departOffset(i) {
  const d = new Date(Date.UTC(2026, 8, 24 + (i % 7)));
  return d.toISOString().slice(0, 10);
}

const isSearchUrl = (u) => /\/(?:laravel\/)?flights\/results\/search(?:\?|$)/.test(u);
const isDataUrl = (u) => /\/(?:laravel\/)?flights\/results\/data(?:\?|$)/.test(u);

async function oneSample(browser, attempt) {
  const sample_id = `oneway-lp01-${LABEL}-${String(attempt).padStart(2, "0")}`;
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    userAgent:
      "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 JP-LARAVEL-PERF-01",
  });
  const page = await context.newPage();
  const sample = { sample_id, attempt, valid: false, label: LABEL, mode: MODE };

  let api_start_ts = null;
  let laravel_received_ts = null;
  let browser_response_ts = null;
  let initPerf = null;
  let readyPerf = null;
  let supplierSummaries = null;
  let readyResolve = null;
  const readyPromise = new Promise((r) => {
    readyResolve = r;
  });

  page.on("request", (req) => {
    if (isSearchUrl(req.url()) && req.method() === "GET" && !api_start_ts) {
      api_start_ts = Date.now();
    }
  });

  page.on("response", async (res) => {
    try {
      const u = res.url();
      if (isSearchUrl(u) && res.request().method() === "GET") {
        laravel_received_ts = Date.now();
        const body = await res.json().catch(() => null);
        sample.search_init_status = body?.status || null;
        sample.search_id = body?.search_id || null;
        if (body?.search_perf && typeof body.search_perf === "object") initPerf = body.search_perf;
      }
      if (MODE === "full" && isDataUrl(u) && res.request().method() === "GET") {
        const body = await res.json().catch(() => null);
        if (!body) return;
        const status = String(body.search_status || body.status || "").toLowerCase();
        const visible =
          (body.offers || []).length +
          (body.outbound_options || []).length +
          (body.paired_options || []).length;
        if (body.search_perf && typeof body.search_perf === "object") readyPerf = body.search_perf;
        if (Array.isArray(body.supplier_call_summaries)) supplierSummaries = body.supplier_call_summaries;
        if (status === "ready" && visible > 0) {
          browser_response_ts = Date.now();
          if (readyResolve) readyResolve("ready");
        }
      }
    } catch {}
  });

  try {
    const depart = departOffset(attempt);
    sample.depart = depart;
    const url = `https://jetpakistan.pk/laravel/flights/results/search?from=LHE&to=DXB&depart=${depart}&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0&_=${Date.now()}-${attempt}`;
    if (MODE === "init") {
      api_start_ts = Date.now();
      const res = await page.request.get(url, { timeout: 60000 });
      laravel_received_ts = Date.now();
      const body = await res.json().catch(() => null);
      sample.search_init_status = body?.status || null;
      sample.search_id = body?.search_id || null;
      sample.http_status = res.status();
      if (body?.search_perf && typeof body.search_perf === "object") initPerf = body.search_perf;
    } else {
      await page.goto(
        `https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=${depart}&trip_type=one_way&cabin=economy&adults=1&sort=cheapest&_=${Date.now()}-${attempt}`,
        { waitUntil: "domcontentloaded", timeout: 120000 },
      );
      await Promise.race([readyPromise, page.waitForTimeout(90000).then(() => "timeout")]);
    }

    const init_wall_ms =
      api_start_ts != null && laravel_received_ts != null
        ? Math.max(0, laravel_received_ts - api_start_ts)
        : null;
    const perf = readyPerf || initPerf;
    sample.valid =
      (sample.search_init_status === "searching" || sample.search_init_status === "ready") &&
      init_wall_ms != null &&
      !!sample.search_id;
    sample.init_wall_ms = init_wall_ms;
    sample.TOTAL_PRE_SUPPLIER_MS =
      perf && typeof perf.TOTAL_PRE_SUPPLIER_MS === "number" ? perf.TOTAL_PRE_SUPPLIER_MS : null;
    sample.INIT_RESPONSE_MS = perf?.INIT_RESPONSE_MS ?? null;
    sample.search_perf = perf || null;
    sample.supplier_call_summaries = supplierSummaries;
    sample.browser_ready_ms =
      api_start_ts != null && browser_response_ts != null
        ? Math.max(0, browser_response_ts - api_start_ts)
        : null;
    if (perf?.providers) sample.providers = perf.providers;
    if (perf?.FIRST_PROVIDER_NETWORK_START_MS != null) {
      sample.FIRST_PROVIDER_NETWORK_START_MS = perf.FIRST_PROVIDER_NETWORK_START_MS;
    }
    if (perf?.PROVIDER_START_SPREAD_MS != null) {
      sample.PROVIDER_START_SPREAD_MS = perf.PROVIDER_START_SPREAD_MS;
    }
  } catch (e) {
    sample.error = String(e?.message || e);
  } finally {
    await context.close();
  }
  return sample;
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const samples = [];
  let attempt = 0;
  while (samples.filter((s) => s.valid).length < TARGET && attempt < MAX_ATTEMPTS) {
    const s = await oneSample(browser, attempt);
    attempt++;
    samples.push(s);
    console.log(
      JSON.stringify({
        attempt,
        valid: s.valid,
        init_wall_ms: s.init_wall_ms,
        TOTAL_PRE_SUPPLIER_MS: s.TOTAL_PRE_SUPPLIER_MS,
        INIT_RESPONSE_MS: s.INIT_RESPONSE_MS,
        status: s.search_init_status,
      }),
    );
  }
  await browser.close();

  const valid = samples.filter((s) => s.valid);
  const pick = (k) => valid.map((s) => s[k]).filter((n) => typeof n === "number");

  const out = {
    phase: "JP-LARAVEL-PERF-01",
    label: LABEL,
    mode: MODE,
    measured_at: new Date().toISOString(),
    ONEWAY_SAMPLE_COUNT: valid.length,
    ONEWAY_ATTEMPTS: samples.length,
    ONEWAY_PRE_SUPPLIER_INIT_WALL_P50_MS: pct(pick("init_wall_ms"), 50),
    ONEWAY_PRE_SUPPLIER_INIT_WALL_P95_MS: pct(pick("init_wall_ms"), 95),
    ONEWAY_SERVER_TOTAL_PRE_SUPPLIER_P50_MS: pct(pick("TOTAL_PRE_SUPPLIER_MS"), 50),
    ONEWAY_SERVER_TOTAL_PRE_SUPPLIER_P95_MS: pct(pick("TOTAL_PRE_SUPPLIER_MS"), 95),
    ONEWAY_SERVER_INIT_RESPONSE_P50_MS: pct(pick("INIT_RESPONSE_MS"), 50),
    ONEWAY_SERVER_INIT_RESPONSE_P95_MS: pct(pick("INIT_RESPONSE_MS"), 95),
    FIRST_PROVIDER_NETWORK_START_P50_MS: pct(pick("FIRST_PROVIDER_NETWORK_START_MS"), 50),
    FIRST_PROVIDER_NETWORK_START_P95_MS: pct(pick("FIRST_PROVIDER_NETWORK_START_MS"), 95),
    PROVIDER_START_SPREAD_P50_MS: pct(pick("PROVIDER_START_SPREAD_MS"), 50),
    PROVIDER_START_SPREAD_P95_MS: pct(pick("PROVIDER_START_SPREAD_MS"), 95),
    note:
      "init_wall_ms = browser/API wall of search-init (02D-compatible). Server TOTAL_PRE_SUPPLIER_MS requires deployed search_perf.",
    samples,
  };

  const file = path.join(OUT, `${LABEL}-n${TARGET}.json`);
  fs.writeFileSync(file, JSON.stringify(out, null, 2));
  console.log("WROTE", file);
  console.log(
    JSON.stringify(
      {
        ONEWAY_PRE_SUPPLIER_INIT_WALL_P50_MS: out.ONEWAY_PRE_SUPPLIER_INIT_WALL_P50_MS,
        ONEWAY_PRE_SUPPLIER_INIT_WALL_P95_MS: out.ONEWAY_PRE_SUPPLIER_INIT_WALL_P95_MS,
        ONEWAY_SERVER_TOTAL_PRE_SUPPLIER_P50_MS: out.ONEWAY_SERVER_TOTAL_PRE_SUPPLIER_P50_MS,
        ONEWAY_SERVER_TOTAL_PRE_SUPPLIER_P95_MS: out.ONEWAY_SERVER_TOTAL_PRE_SUPPLIER_P95_MS,
      },
      null,
      2,
    ),
  );
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
