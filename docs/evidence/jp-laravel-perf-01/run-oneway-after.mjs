/**
 * JP-LARAVEL-PERF-01 after: init + poll until READY search_perf (no UI wait).
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.join(__dirname);
const TARGET = Number(process.env.JP_PERF_N || 30);
const MAX_ATTEMPTS = Math.max(TARGET + 10, 45);
const LABEL = process.env.JP_PERF_LABEL || "after";

function pct(arr, p) {
  const a = (arr || []).filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

function departOffset(i) {
  const d = new Date(Date.UTC(2026, 8, 24 + (i % 7)));
  return d.toISOString().slice(0, 10);
}

async function oneSample(browser, attempt) {
  const sample_id = `oneway-lp01-${LABEL}-${String(attempt).padStart(2, "0")}`;
  const context = await browser.newContext({
    userAgent:
      "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 JP-LARAVEL-PERF-01-AFTER",
  });
  const page = await context.newPage();
  const sample = { sample_id, attempt, valid: false, label: LABEL };
  try {
    const depart = departOffset(attempt);
    sample.depart = depart;
    const t0 = Date.now();
    const initRes = await page.request.get(
      `https://jetpakistan.pk/laravel/flights/results/search?from=LHE&to=DXB&depart=${depart}&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0&_=${Date.now()}-${attempt}`,
      { timeout: 90000 },
    );
    const init_wall_ms = Date.now() - t0;
    const initBody = await initRes.json().catch(() => null);
    sample.http_status = initRes.status();
    sample.search_init_status = initBody?.status || null;
    sample.search_id = initBody?.search_id || null;
    sample.init_wall_ms = init_wall_ms;
    sample.init_search_perf = initBody?.search_perf || null;
    sample.INIT_RESPONSE_MS = initBody?.search_perf?.INIT_RESPONSE_MS ?? null;

    if (!sample.search_id) {
      sample.error = "no_search_id";
      return sample;
    }

    let readyPerf = null;
    let summaries = null;
    const pollStart = Date.now();
    for (let i = 0; i < 60; i++) {
      await page.waitForTimeout(750);
      const dataRes = await page.request.get(
        `https://jetpakistan.pk/laravel/flights/results/data?search_id=${sample.search_id}&page=1`,
        { timeout: 30000 },
      );
      const body = await dataRes.json().catch(() => null);
      if (!body) continue;
      const status = String(body.search_status || body.status || "").toLowerCase();
      if (body.search_perf) readyPerf = body.search_perf;
      if (Array.isArray(body.supplier_call_summaries)) summaries = body.supplier_call_summaries;
      if (status === "ready" || status === "empty" || status === "failed") {
        sample.poll_status = status;
        sample.poll_wall_ms = Date.now() - pollStart;
        break;
      }
    }

    const perf = readyPerf || sample.init_search_perf;
    sample.search_perf = perf;
    sample.supplier_call_summaries = summaries;
    sample.TOTAL_PRE_SUPPLIER_MS = perf?.TOTAL_PRE_SUPPLIER_MS ?? null;
    sample.FIRST_PROVIDER_NETWORK_START_MS = perf?.FIRST_PROVIDER_NETWORK_START_MS ?? null;
    sample.LAST_ELIGIBLE_PROVIDER_NETWORK_START_MS = perf?.LAST_ELIGIBLE_PROVIDER_NETWORK_START_MS ?? null;
    sample.PROVIDER_START_SPREAD_MS = perf?.PROVIDER_START_SPREAD_MS ?? null;
    sample.PRE_SUPPLIER_DB_QUERY_COUNT = perf?.PRE_SUPPLIER_DB_QUERY_COUNT ?? null;
    sample.PRE_SUPPLIER_DB_TOTAL_MS = perf?.PRE_SUPPLIER_DB_TOTAL_MS ?? null;
    sample.REQUEST_VALIDATION_MS = perf?.REQUEST_VALIDATION_MS ?? null;
    sample.AUTH_CONTEXT_MS = perf?.AUTH_CONTEXT_MS ?? null;
    sample.PROVIDER_REGISTRY_MS = perf?.PROVIDER_REGISTRY_MS ?? null;
    sample.PROVIDER_ELIGIBILITY_MS = perf?.PROVIDER_ELIGIBILITY_MS ?? null;
    sample.PRE_SEARCH_SUPPLIER_AUTH_NETWORK_MS = perf?.PRE_SEARCH_SUPPLIER_AUTH_NETWORK_MS ?? null;
    sample.SUPPLIER_DISPATCH_MODE = perf?.SUPPLIER_DISPATCH_MODE ?? null;
    sample.providers = perf?.providers ?? null;
    sample.valid =
      sample.search_init_status === "searching" &&
      typeof sample.init_wall_ms === "number" &&
      !!sample.search_id &&
      typeof sample.TOTAL_PRE_SUPPLIER_MS === "number";
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
        INIT_RESPONSE_MS: s.INIT_RESPONSE_MS,
        TOTAL_PRE_SUPPLIER_MS: s.TOTAL_PRE_SUPPLIER_MS,
        FIRST_PROVIDER_NETWORK_START_MS: s.FIRST_PROVIDER_NETWORK_START_MS,
        PROVIDER_START_SPREAD_MS: s.PROVIDER_START_SPREAD_MS,
        status: s.poll_status || s.search_init_status,
      }),
    );
  }
  await browser.close();
  const valid = samples.filter((s) => s.valid);
  const pick = (k) => valid.map((s) => s[k]).filter((n) => typeof n === "number");
  const out = {
    phase: "JP-LARAVEL-PERF-01",
    label: LABEL,
    measured_at: new Date().toISOString(),
    ONEWAY_SAMPLE_COUNT: valid.length,
    ONEWAY_ATTEMPTS: samples.length,
    ONEWAY_PRE_SUPPLIER_INIT_WALL_P50_MS: pct(pick("init_wall_ms"), 50),
    ONEWAY_PRE_SUPPLIER_INIT_WALL_P95_MS: pct(pick("init_wall_ms"), 95),
    ONEWAY_SERVER_INIT_RESPONSE_P50_MS: pct(pick("INIT_RESPONSE_MS"), 50),
    ONEWAY_SERVER_INIT_RESPONSE_P95_MS: pct(pick("INIT_RESPONSE_MS"), 95),
    ONEWAY_SERVER_TOTAL_PRE_SUPPLIER_P50_MS: pct(pick("TOTAL_PRE_SUPPLIER_MS"), 50),
    ONEWAY_SERVER_TOTAL_PRE_SUPPLIER_P95_MS: pct(pick("TOTAL_PRE_SUPPLIER_MS"), 95),
    FIRST_PROVIDER_NETWORK_START_P50_MS: pct(pick("FIRST_PROVIDER_NETWORK_START_MS"), 50),
    FIRST_PROVIDER_NETWORK_START_P95_MS: pct(pick("FIRST_PROVIDER_NETWORK_START_MS"), 95),
    LAST_ELIGIBLE_PROVIDER_NETWORK_START_P50_MS: pct(pick("LAST_ELIGIBLE_PROVIDER_NETWORK_START_MS"), 50),
    LAST_ELIGIBLE_PROVIDER_NETWORK_START_P95_MS: pct(pick("LAST_ELIGIBLE_PROVIDER_NETWORK_START_MS"), 95),
    PROVIDER_START_SPREAD_P50_MS: pct(pick("PROVIDER_START_SPREAD_MS"), 50),
    PROVIDER_START_SPREAD_P95_MS: pct(pick("PROVIDER_START_SPREAD_MS"), 95),
    REQUEST_VALIDATION_P95_MS: pct(pick("REQUEST_VALIDATION_MS"), 95),
    AUTH_CONTEXT_P95_MS: pct(pick("AUTH_CONTEXT_MS"), 95),
    PROVIDER_REGISTRY_P95_MS: pct(pick("PROVIDER_REGISTRY_MS"), 95),
    PROVIDER_ELIGIBILITY_P95_MS: pct(pick("PROVIDER_ELIGIBILITY_MS"), 95),
    PRE_SUPPLIER_DB_QUERY_COUNT_P95: pct(pick("PRE_SUPPLIER_DB_QUERY_COUNT"), 95),
    PRE_SUPPLIER_DB_TOTAL_P95_MS: pct(pick("PRE_SUPPLIER_DB_TOTAL_MS"), 95),
    PRE_SEARCH_SUPPLIER_AUTH_NETWORK_P95_MS: pct(pick("PRE_SEARCH_SUPPLIER_AUTH_NETWORK_MS"), 95),
    SUPPLIER_DISPATCH_MODE: valid[0]?.SUPPLIER_DISPATCH_MODE || null,
    samples,
  };
  const file = path.join(OUT, `${LABEL}-n${TARGET}.json`);
  fs.writeFileSync(file, JSON.stringify(out, null, 2));
  console.log("WROTE", file);
  console.log(JSON.stringify({
    ONEWAY_PRE_SUPPLIER_INIT_WALL_P50_MS: out.ONEWAY_PRE_SUPPLIER_INIT_WALL_P50_MS,
    ONEWAY_PRE_SUPPLIER_INIT_WALL_P95_MS: out.ONEWAY_PRE_SUPPLIER_INIT_WALL_P95_MS,
    ONEWAY_SERVER_TOTAL_PRE_SUPPLIER_P50_MS: out.ONEWAY_SERVER_TOTAL_PRE_SUPPLIER_P50_MS,
    ONEWAY_SERVER_TOTAL_PRE_SUPPLIER_P95_MS: out.ONEWAY_SERVER_TOTAL_PRE_SUPPLIER_P95_MS,
    ONEWAY_SERVER_INIT_RESPONSE_P50_MS: out.ONEWAY_SERVER_INIT_RESPONSE_P50_MS,
    ONEWAY_SERVER_INIT_RESPONSE_P95_MS: out.ONEWAY_SERVER_INIT_RESPONSE_P95_MS,
    FIRST_PROVIDER_NETWORK_START_P95_MS: out.FIRST_PROVIDER_NETWORK_START_P95_MS,
    PROVIDER_START_SPREAD_P95_MS: out.PROVIDER_START_SPREAD_P95_MS,
  }, null, 2));
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
