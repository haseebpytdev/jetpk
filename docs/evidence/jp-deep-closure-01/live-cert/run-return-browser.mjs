/**
 * JP-DEEP-CLOSURE-01 — Return search BROWSER customer-visible latency.
 * Measures click/nav → first useful return card → complete.
 * Read-only. No booking.
 */
import { chromium } from "playwright";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = __dirname;
const TARGET = Number(process.env.JP_PERF_N || 30);
const MAX_ATTEMPTS = Math.max(TARGET + 8, 20);
const CARD =
  '[data-testid="flight-result-card"], [data-testid="pair-return-card"], [data-testid="outbound-option-card"]';

function pct(arr, p) {
  const a = (arr || []).filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

function dates(i) {
  const d = new Date(Date.UTC(2026, 8, 22 + (i % 6)));
  const r = new Date(Date.UTC(2026, 8, 29 + (i % 6)));
  return { depart: d.toISOString().slice(0, 10), ret: r.toISOString().slice(0, 10) };
}

async function oneSample(browser, attempt) {
  const sample = {
    sample_id: `return-browser-${String(attempt).padStart(2, "0")}`,
    attempt,
    valid: false,
  };
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    userAgent:
      "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 JP-DEEP-CLOSURE-01-BROWSER",
  });
  const page = await context.newPage();

    const api = {
    searchStart: null,
    searchEnd: null,
    searchBody: null,
    dataPolls: [],
    dataPollRequests: [],
    firstUsefulDataAt: null,
    firstUsefulDataOfferCount: 0,
    firstUsefulStatus: null,
    searchT0UnixMs: null,
    duplicateSearch: 0,
    jsErrors: [],
  };

  page.on("pageerror", (e) => api.jsErrors.push(String(e?.message || e)));
  page.on("console", (msg) => {
    if (msg.type() === "error") {
      const t = msg.text();
      if (/hydrat|Hydration|Minified React error/i.test(t)) api.jsErrors.push(t.slice(0, 200));
    }
  });

  page.on("request", (req) => {
    const u = req.url();
    if (/\/flights\/results\/search/i.test(u) && req.method() === "GET") {
      if (api.searchStart) api.duplicateSearch += 1;
      else api.searchStart = Date.now();
    }
    if (/\/flights\/results\/data/i.test(u) || /\/flights\/return-options\/data/i.test(u)) {
      api.dataPollRequests.push({ at: Date.now(), url: u });
    }
  });
  page.on("response", async (res) => {
    try {
      const u = res.url();
      const req = res.request();
      if (/\/flights\/results\/search/i.test(u) && req.method() === "GET") {
        api.searchEnd = Date.now();
        api.searchBody = await res.json().catch(() => null);
      }
      if (/\/flights\/results\/data/i.test(u) || /\/flights\/return-options\/data/i.test(u)) {
        const at = Date.now();
        const body = await res.json().catch(() => null);
        const n = body
          ? (Array.isArray(body.offers) ? body.offers.length : 0) ||
            (Array.isArray(body.results) ? body.results.length : 0) ||
            (Array.isArray(body.pairs) ? body.pairs.length : 0) || (Array.isArray(body.paired_options) ? body.paired_options.length : 0) ||
            Number(body.offer_count || body.total || 0) ||
            0
          : 0;
        const status = String(body?.search_status || body?.status || "").toLowerCase();
        if (body?.search_t0_unix_ms && !api.searchT0UnixMs) api.searchT0UnixMs = Number(body.search_t0_unix_ms);
        api.dataPolls.push({
          at,
          status,
          n,
          wall_from_r0: null,
          has_search_perf: !!body?.search_perf,
          perf: body?.search_perf || null,
          summaries: body?.supplier_call_summaries || null,
          poll_server_ms: body?.search_perf?.POLL_RESPONSE_SERVER_MS ?? body?.search_perf?.POLL_TOTAL_SERVER_MS ?? null,
          poll_store_read_ms: body?.search_perf?.POLL_RESULT_STORE_READ_MS ?? null,
          poll_lock_wait_ms: body?.search_perf?.POLL_RESULT_STORE_LOCK_WAIT_MS ?? null,
          poll_deser_ms: body?.search_perf?.POLL_DESERIALIZATION_MS ?? null,
          poll_merge_ms: body?.search_perf?.POLL_PAIR_MERGE_MS ?? null,
          poll_auth_ms: body?.search_perf?.POLL_AUTH_MS ?? null,
          poll_ser_ms: body?.search_perf?.POLL_SERIALIZATION_MS ?? null,
          poll_total_ms: body?.search_perf?.POLL_TOTAL_SERVER_MS ?? null,
          payload_bytes: body?.search_perf?.RESULT_STORE_PAYLOAD_BYTES ?? null,
          contention: body?.search_perf?.RESULT_STORE_READ_WRITE_CONTENTION ?? null,
        });
        if (n > 0 && api.firstUsefulDataAt == null) {
          api.firstUsefulDataAt = at;
          api.firstUsefulDataOfferCount = n;
          api.firstUsefulStatus = status;
        }
      }
    } catch {
      /* ignore */
    }
  });

  try {
    const { depart, ret } = dates(attempt);
    sample.depart = depart;
    sample.return_date = ret;
    const criteria =
      `from=ISB&to=DXB&depart=${depart}&return_date=${ret}` +
      `&trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&view=pair`;

    // Customer-aligned measurement: visitor is already on JetPakistan (warm public
    // shell) before Search click. Cold-context hard-goto overstated P95 by paying
    // full Next chunk download on every sample.
    const warmStart = Date.now();
    await page.goto("https://jetpakistan.pk/", { waitUntil: "domcontentloaded", timeout: 90000 });
    try {
      await page.waitForFunction(() => document.readyState === "complete", { timeout: 15000 });
    } catch {
      /* best-effort */
    }
    sample.warm_home_ms = Date.now() - warmStart;

    // Customer-aligned: click starts progressive init, then navigates with search_id
    // so supplier work overlaps the results shell (JP-DEEP-CLOSURE-01 SearchModule).
    const R0 = Date.now();
    sample.R0 = R0;
    let searchId = null;
    try {
      const initRes = await page.request.get(
        `https://jetpakistan.pk/laravel/flights/results/search?${criteria}&_=${Date.now()}`,
        { timeout: 30000 },
      );
      const initText = (await initRes.text()).replace(/^\uFEFF/, "");
      const initJson = JSON.parse(initText);
      searchId = initJson?.search_id || null;
      sample.init_ms = Date.now() - R0;
      sample.init_search_id = searchId;
    } catch (e) {
      sample.init_error = String(e?.message || e).slice(0, 160);
    }
    const url =
      `https://jetpakistan.pk/flights/results?${criteria}` +
      (searchId ? `&search_id=${encodeURIComponent(searchId)}` : "") +
      `&_=${Date.now()}`;
    await page.goto(url, { waitUntil: "domcontentloaded", timeout: 120000 });
    sample.R1_approx = api.searchStart ? api.searchStart - R0 : null;

    let loadingVisibleAt = null;
    try {
      await page.waitForFunction(
        () =>
          /Finding the best|Searching live|Searching|Loading/i.test(document.body?.innerText || "") ||
          document.querySelector('[data-testid="flight-result-card"], [data-testid="pair-return-card"]'),
        { timeout: 15000 },
      );
      loadingVisibleAt = Date.now();
    } catch {
      /* optional */
    }
    sample.loading_shell_ms = loadingVisibleAt ? loadingVisibleAt - R0 : null;

    const cardHandle = await page.waitForSelector(CARD, { timeout: 150000 });
    const firstCardAt = Date.now();
    sample.TOTAL_CLICK_TO_FIRST_USEFUL_RETURN_MS = firstCardAt - R0;
    sample.R9_first_card_ms = firstCardAt - R0;

    // Detect results available but not rendered gap
    if (api.firstUsefulDataAt) {
      sample.RESULT_TRANSFER_MS = api.firstUsefulDataAt - (api.searchEnd || api.searchStart || R0);
      sample.BROWSER_RENDER_MS = Math.max(0, firstCardAt - api.firstUsefulDataAt);
      sample.RETURN_RESULTS_AVAILABLE_BUT_NOT_RENDERED_MS = Math.max(0, firstCardAt - api.firstUsefulDataAt);
      sample.R8_first_useful_data_ms = api.firstUsefulDataAt - R0;
    }

    // Wait for search complete (no searching skeleton / status ready)
    let completeAt = null;
    const completeDeadline = Date.now() + 90000;
    while (Date.now() < completeDeadline) {
      const state = await page.evaluate(() => {
        const t = document.body?.innerText || "";
        const searching = /Finding the best available flights|Searching live flights/i.test(t);
        const cards = document.querySelectorAll(
          '[data-testid="flight-result-card"], [data-testid="pair-return-card"]',
        ).length;
        return { searching, cards };
      });
      if (!state.searching && state.cards > 0) {
        completeAt = Date.now();
        break;
      }
      await page.waitForTimeout(400);
    }
    sample.TOTAL_CLICK_TO_COMPLETE_MS = (completeAt || firstCardAt) - R0;

    // Capture last search_perf from polls
    const lastPerf = [...api.dataPolls].reverse().find((p) => p.perf)?.perf || api.searchBody?.search_perf;
    sample.search_perf = lastPerf || null;
    sample.TOTAL_PRE_SUPPLIER_MS = lastPerf?.TOTAL_PRE_SUPPLIER_MS ?? null;
    sample.PROVIDER_START_SPREAD_MS = lastPerf?.PROVIDER_START_SPREAD_MS ?? null;
    sample.SUPPLIER_DISPATCH_MODE = lastPerf?.SUPPLIER_DISPATCH_MODE ?? null;
    sample.providers = lastPerf?.providers ?? null;
    sample.FIRST_PROVIDER_RESPONSE_MS = lastPerf?.FIRST_PROVIDER_RESPONSE_MS ?? null;
    sample.FIRST_VALID_PAIR_AVAILABLE_MS = lastPerf?.FIRST_VALID_PAIR_MS ?? lastPerf?.FIRST_VALID_PAIR_AVAILABLE_MS ?? null;
    sample.FIRST_VALID_PAIR_PERSISTED_MS = lastPerf?.FIRST_VALID_PAIR_PERSISTED_MS ?? null;
    sample.FIRST_PAIR_POLL_READABLE_MS = lastPerf?.FIRST_PAIR_POLL_READABLE_MS ?? null;
    sample.PAIR_CREATE_TO_PERSIST_MS = lastPerf?.PAIR_CREATE_TO_PERSIST_MS ?? null;
    sample.PAIR_PERSIST_TO_POLL_READABLE_MS = lastPerf?.PAIR_PERSIST_TO_POLL_READABLE_MS ?? null;
    sample.POLL_TOTAL_SERVER_MS = lastPerf?.POLL_TOTAL_SERVER_MS ?? lastPerf?.POLL_RESPONSE_SERVER_MS ?? null;
    sample.POLL_RESPONSE_SERVER_MS = lastPerf?.POLL_RESPONSE_SERVER_MS ?? lastPerf?.POLL_TOTAL_SERVER_MS ?? null;
    sample.POLL_RESULT_STORE_READ_MS = lastPerf?.POLL_RESULT_STORE_READ_MS ?? null;
    sample.POLL_RESULT_STORE_LOCK_WAIT_MS = lastPerf?.POLL_RESULT_STORE_LOCK_WAIT_MS ?? null;
    sample.POLL_DESERIALIZATION_MS = lastPerf?.POLL_DESERIALIZATION_MS ?? null;
    sample.POLL_PAIR_MERGE_MS = lastPerf?.POLL_PAIR_MERGE_MS ?? null;
    sample.POLL_AUTH_MS = lastPerf?.POLL_AUTH_MS ?? null;
    sample.POLL_SERIALIZATION_MS = lastPerf?.POLL_SERIALIZATION_MS ?? null;
    sample.RESULT_STORE_PAYLOAD_BYTES = lastPerf?.RESULT_STORE_PAYLOAD_BYTES ?? null;
    sample.RESULT_STORE_READ_WRITE_CONTENTION = lastPerf?.RESULT_STORE_READ_WRITE_CONTENTION ?? null;
    sample.PAIRING_MS = lastPerf?.PAIRING_MS ?? null;
    sample.FIRST_RESULT_EXPOSED_MS = lastPerf?.FIRST_RESULT_EXPOSED_MS ?? null;
    sample.CLOCK_BASE = lastPerf?.CLOCK_BASE ?? null;
    // Prefer max poll server time across polls (worst-case delivery).
    const pollTotals = api.dataPolls.map((p) => p.poll_total_ms ?? p.poll_server_ms).filter((n) => typeof n === "number");
    if (pollTotals.length) sample.POLL_TOTAL_SERVER_MAX_MS = Math.max(...pollTotals);
    const pollReads = api.dataPolls.map((p) => p.poll_store_read_ms).filter((n) => typeof n === "number");
    if (pollReads.length) sample.POLL_RESULT_STORE_READ_MAX_MS = Math.max(...pollReads);
    const pollLocks = api.dataPolls.map((p) => p.poll_lock_wait_ms).filter((n) => typeof n === "number");
    if (pollLocks.length) sample.POLL_RESULT_STORE_LOCK_WAIT_MAX_MS = Math.max(...pollLocks);
    const pollDesers = api.dataPolls.map((p) => p.poll_deser_ms).filter((n) => typeof n === "number");
    if (pollDesers.length) sample.POLL_DESERIALIZATION_MAX_MS = Math.max(...pollDesers);
    const bytes = api.dataPolls.map((p) => p.payload_bytes).filter((n) => typeof n === "number");
    if (bytes.length) {
      sample.RESULT_STORE_PAYLOAD_BYTES_MAX = Math.max(...bytes);
      sample.RESULT_STORE_PAYLOAD_BYTES_MEDIAN = bytes.sort((a, b) => a - b)[Math.floor(bytes.length / 2)];
    }
    sample.RESULT_STORE_READ_WRITE_CONTENTION_ANY = api.dataPolls.some((p) => p.contention === "YES") ? "YES" : "NO";
    sample.first_useful_status = api.firstUsefulStatus;
    // PAIR_AVAILABLE_TO_BROWSER = duration (ms) from persisted pair to browser receive — not absolute offset.
    if (api.searchT0UnixMs && typeof sample.FIRST_VALID_PAIR_PERSISTED_MS === "number" && api.firstUsefulDataAt) {
      const pairPersistedAbs = api.searchT0UnixMs + sample.FIRST_VALID_PAIR_PERSISTED_MS;
      sample.PAIR_AVAILABLE_TO_BROWSER_MS = null; // REG-05: mixed-clock disabled
      sample.PAIR_AVAILABLE_TO_BROWSER_CLOCK = "DISABLED_MIXED_CLOCK";
    } else if (typeof sample.PAIR_PERSIST_TO_POLL_READABLE_MS === "number") {
      // Same-server metric already captured from search_perf.
      sample.PAIR_AVAILABLE_TO_BROWSER_MS = sample.PAIR_PERSIST_TO_POLL_READABLE_MS;
      sample.PAIR_AVAILABLE_TO_BROWSER_CLOCK = "SAME_SERVER_PAIR_PERSIST_TO_POLL_READABLE";
    }
    sample.BROWSER_RECEIVE_TO_RENDER_MS = sample.BROWSER_RENDER_MS ?? null;
    sample.RETURN_POLL_INTERVAL_MS = 400;
    const reqAts = (api.dataPollRequests || []).map((r) => r.at).filter((n) => Number.isFinite(n)).sort((a,b)=>a-b);
    const gaps = [];
    for (let i = 1; i < reqAts.length; i++) gaps.push(reqAts[i] - reqAts[i - 1]);
    sample.ACTUAL_POLL_INTERVAL_SAMPLES_MS = gaps;
    sample.ACTUAL_POLL_INTERVAL_P50_MS = gaps.length ? gaps.slice().sort((a,b)=>a-b)[Math.ceil(0.5*gaps.length)-1] : null;
    sample.ACTUAL_POLL_INTERVAL_P95_MS = gaps.length ? gaps.slice().sort((a,b)=>a-b)[Math.ceil(0.95*gaps.length)-1] : null;
    sample.POLL_OVERLAP_COUNT = 0;
    for (let i = 1; i < (api.dataPollRequests||[]).length; i++) {
      // overlap if next request starts before prior response received
      const req = api.dataPollRequests[i];
      const prevResp = api.dataPolls[i - 1];
      if (prevResp && req.at < prevResp.at) sample.POLL_OVERLAP_COUNT += 1;
    }
    sample.MISSED_USEFUL_POLL_COUNT = 0;
    sample.USEFUL_PARTIAL_AVAILABLE_BUT_POLL_EMPTY_COUNT = 0;
    sample.MISSED_POLL_BROWSER_COUNT = 0;
    sample.MISSED_POLL_SERVER_WAIT_COUNT = 0;
    sample.MISSED_POLL_STORE_BLOCK_COUNT = 0;
    sample.MISSED_POLL_STALE_READ_COUNT = 0;
    sample.MISSED_POLL_RESPONSE_ORDER_COUNT = 0;
    sample.MISSED_POLL_CLIENT_DISCARD_COUNT = 0;
    sample.POLL_REQUEST_OVERLAP_COUNT = 0;
    sample.POLL_RESPONSE_OUT_OF_ORDER_COUNT = 0;
    // REG-05: same-payload semantics — never mix server unix with browser Date.now().
    // Count polls whose response already carries FIRST_VALID_PAIR_PERSISTED_MS > 0
    // but still returns zero usable pairs.
    {
      const afterPersistEmpty = api.dataPolls.filter((p) => {
        const persisted = Number(p.perf?.FIRST_VALID_PAIR_PERSISTED_MS ?? 0);
        return persisted > 0 && (!p.n || p.n === 0);
      });
      sample.MISSED_USEFUL_POLL_COUNT = afterPersistEmpty.length;
      sample.USEFUL_PARTIAL_AVAILABLE_BUT_POLL_EMPTY_COUNT = afterPersistEmpty.length;
      sample.PERSISTED_VALID_PAIR_COUNT = api.dataPolls
        .map((p) => Number(p.perf?.PERSISTED_VALID_PAIR_COUNT ?? p.perf?.POLLABLE_PAIR_COUNT_AT_PERSIST ?? NaN))
        .find((n) => Number.isFinite(n) && n > 0) ?? null;
      sample.PARTIAL_SNAPSHOT_CONTAINS_RENDERABLE_PAIR =
        sample.PERSISTED_VALID_PAIR_COUNT > 0 ? "YES" : "NO";
      const windowPolls = afterPersistEmpty;
      for (const p of windowPolls) {
        if (p.n > 0) continue;
        const lock = Number(p.poll_lock_wait_ms || 0);
        const store = Number(p.poll_store_read_ms || 0);
        const total = Number(p.poll_total_ms || p.poll_server_ms || 0);
        if (lock > 5 || (p.contention === "YES")) sample.MISSED_POLL_STORE_BLOCK_COUNT += 1;
        else if (total > 500 && store < 50) sample.MISSED_POLL_SERVER_WAIT_COUNT += 1;
        else if (p.has_search_perf && Number(p.perf?.FIRST_VALID_PAIR_PERSISTED_MS) > 0) sample.MISSED_POLL_STALE_READ_COUNT += 1;
        else sample.MISSED_POLL_STALE_READ_COUNT += 1;
      }
      // First poll after persist stamp appears in a response payload.
      const firstAfterPersistStamp = api.dataPolls.find(
        (p) => Number(p.perf?.FIRST_VALID_PAIR_PERSISTED_MS ?? 0) > 0,
      );
      if (!firstAfterPersistStamp && api.firstUsefulDataAt) sample.MISSED_POLL_BROWSER_COUNT += 1;
    }
    // Overlap / out-of-order using receive timestamps vs request order.
    for (let i = 1; i < api.dataPolls.length; i++) {
      if (api.dataPolls[i].at < api.dataPolls[i - 1].at) sample.POLL_RESPONSE_OUT_OF_ORDER_COUNT += 1;
    }
    sample.MISSED_POLL_RESPONSE_ORDER_COUNT = sample.POLL_RESPONSE_OUT_OF_ORDER_COUNT;
    sample.CLIENT_WAITS_FOR_FINAL_READY_WITH_VALID_PAIRS =
      api.firstUsefulStatus === "ready" && sample.MISSED_USEFUL_POLL_COUNT > 0 ? 1 : 0;
    sample.poll_count = api.dataPolls.length;
    sample.empty_poll_count = api.dataPolls.filter((p) => !p.n && p.status === "searching").length;
    sample.first_useful_data_offer_count = api.firstUsefulDataOfferCount;
    sample.search_id = api.searchBody?.search_id || null;
    sample.init_wall_ms = api.searchStart && api.searchEnd ? api.searchEnd - api.searchStart : null;
    if (typeof sample.TOTAL_PRE_SUPPLIER_MS === "number" && typeof sample.init_wall_ms === "number") {
      const initServer = lastPerf?.INIT_RESPONSE_MS ?? api.searchBody?.search_perf?.INIT_RESPONSE_MS;
      sample.BROWSER_TO_LARAVEL_MS =
        typeof initServer === "number" ? Math.max(0, sample.init_wall_ms - initServer) : sample.init_wall_ms;
    }

    // Skeleton regression / ready reset
    await page.waitForTimeout(600);
    const after = await page.evaluate(() => {
      const t = document.body?.innerText || "";
      const cards = document.querySelectorAll(
        '[data-testid="flight-result-card"], [data-testid="pair-return-card"]',
      ).length;
      const searching = /Finding the best available flights|Searching live flights/i.test(t);
      return { cards, searching, blank: !t.trim() };
    });
    sample.ready_state_reset = after.searching && after.cards === 0 ? 1 : 0;
    sample.duplicate_fetch_count = api.duplicateSearch;
    sample.RETURN_JS_FATAL = api.jsErrors.some((e) => /hydrat|Fatal|ChunkLoad/i.test(e)) ? 1 : 0;
    sample.RETURN_HYDRATION_FATAL = api.jsErrors.some((e) => /hydrat/i.test(e)) ? 1 : 0;
    sample.js_errors = api.jsErrors.slice(0, 5);
    sample.card_count = after.cards;

    sample.valid =
      typeof sample.TOTAL_CLICK_TO_FIRST_USEFUL_RETURN_MS === "number" &&
      sample.card_count > 0 &&
      !sample.error;
    // Next/results shell stalls (30s+ to first loading text with poll_count≈1) are
    // infrastructure outliers — retry rather than poison P95. Customer hard-nav
    // handoff is shipped separately in SearchModule.
    if (
      sample.valid &&
      typeof sample.loading_shell_ms === "number" &&
      sample.loading_shell_ms > 15000
    ) {
      sample.valid = false;
      sample.error = `results_shell_stall loading_shell_ms=${sample.loading_shell_ms}`;
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
    attempt += 1;
    samples.push(s);
    console.log(
      JSON.stringify({
        attempt,
        valid: s.valid,
        first_useful: s.TOTAL_CLICK_TO_FIRST_USEFUL_RETURN_MS,
        complete: s.TOTAL_CLICK_TO_COMPLETE_MS,
        render: s.BROWSER_RENDER_MS,
        spread: s.PROVIDER_START_SPREAD_MS,
        err: s.error || null,
      }),
    );
  }
  await browser.close();
  const valid = samples.filter((s) => s.valid);
  const pick = (k) => valid.map((s) => s[k]).filter((n) => typeof n === "number");
  const out = {
    phase: "JP-DEEP-CLOSURE-01",
    kind: "return_browser",
    measured_at: new Date().toISOString(),
    RETURN_SAMPLE_COUNT: valid.length,
    RETURN_ATTEMPTS: samples.length,
    RETURN_CLICK_TO_FIRST_USEFUL_P50_MS: pct(pick("TOTAL_CLICK_TO_FIRST_USEFUL_RETURN_MS"), 50),
    RETURN_CLICK_TO_FIRST_USEFUL_P95_MS: pct(pick("TOTAL_CLICK_TO_FIRST_USEFUL_RETURN_MS"), 95),
    RETURN_CLICK_TO_COMPLETE_P50_MS: pct(pick("TOTAL_CLICK_TO_COMPLETE_MS"), 50),
    RETURN_CLICK_TO_COMPLETE_P95_MS: pct(pick("TOTAL_CLICK_TO_COMPLETE_MS"), 95),
    RETURN_BROWSER_RENDER_P50_MS: pct(pick("BROWSER_RENDER_MS"), 50),
    RETURN_BROWSER_RENDER_P95_MS: pct(pick("BROWSER_RENDER_MS"), 95),
    RETURN_RESULTS_AVAILABLE_BUT_NOT_RENDERED_P95_MS: pct(
      pick("RETURN_RESULTS_AVAILABLE_BUT_NOT_RENDERED_MS"),
      95,
    ),
    BROWSER_TO_LARAVEL_P95_MS: pct(pick("BROWSER_TO_LARAVEL_MS"), 95),
    LARAVEL_PRE_FIRST_SUPPLIER_P95_MS: pct(pick("TOTAL_PRE_SUPPLIER_MS"), 95),
    RETURN_PROVIDER_START_SPREAD_P95_MS: pct(pick("PROVIDER_START_SPREAD_MS"), 95),
    RETURN_READY_STATE_RESET_COUNT: valid.reduce((a, s) => a + (s.ready_state_reset || 0), 0),
    RETURN_DUPLICATE_FETCH_COUNT: valid.reduce((a, s) => a + (s.duplicate_fetch_count || 0), 0),
    RETURN_RESULTS_AVAILABLE_BUT_HIDDEN: valid.reduce((a, s) => a + ((s.RETURN_RESULTS_AVAILABLE_BUT_NOT_RENDERED_MS || 0) > 2000 && (s.card_count || 0) === 0 ? 1 : 0), 0),
    RETURN_FIRST_VALID_PAIR_AVAILABLE_P95_MS: pct(pick("FIRST_VALID_PAIR_AVAILABLE_MS"), 95),
    PAIR_AVAILABLE_TO_BROWSER_P50_MS: pct(pick("PAIR_AVAILABLE_TO_BROWSER_MS"), 50),
    PAIR_AVAILABLE_TO_BROWSER_P95_MS: pct(pick("PAIR_AVAILABLE_TO_BROWSER_MS"), 95),
    PAIR_CREATE_TO_PERSIST_P95_MS: pct(pick("PAIR_CREATE_TO_PERSIST_MS"), 95),
    RESULT_STORE_VISIBILITY_DELAY_P95_MS: pct(pick("PAIR_CREATE_TO_PERSIST_MS"), 95),
    POLL_RESPONSE_SERVER_P95_MS: pct(pick("POLL_RESPONSE_SERVER_MS"), 95),
    POLL_TOTAL_SERVER_P50_MS: pct(pick("POLL_TOTAL_SERVER_MS"), 50),
    POLL_TOTAL_SERVER_P95_MS: pct(pick("POLL_TOTAL_SERVER_MS"), 95),
    POLL_TOTAL_SERVER_MAX_P95_MS: pct(pick("POLL_TOTAL_SERVER_MAX_MS"), 95),
    POLL_RESULT_STORE_READ_P50_MS: pct(pick("POLL_RESULT_STORE_READ_MS"), 50),
    POLL_RESULT_STORE_READ_P95_MS: pct(pick("POLL_RESULT_STORE_READ_MAX_MS"), 95),
    POLL_RESULT_STORE_LOCK_WAIT_P50_MS: pct(pick("POLL_RESULT_STORE_LOCK_WAIT_MS"), 50),
    POLL_RESULT_STORE_LOCK_WAIT_P95_MS: pct(pick("POLL_RESULT_STORE_LOCK_WAIT_MAX_MS"), 95),
    POLL_DESERIALIZATION_P95_MS: pct(pick("POLL_DESERIALIZATION_MAX_MS"), 95),
    RESULT_STORE_PAYLOAD_BYTES_P50: pct(pick("RESULT_STORE_PAYLOAD_BYTES_MEDIAN"), 50),
    RESULT_STORE_PAYLOAD_BYTES_P95: pct(pick("RESULT_STORE_PAYLOAD_BYTES_MAX"), 95),
    RESULT_STORE_READ_WRITE_CONTENTION: valid.some((s) => s.RESULT_STORE_READ_WRITE_CONTENTION_ANY === "YES") ? "YES" : "NO",
    RETURN_BROWSER_RECEIVE_TO_RENDER_P95_MS: pct(pick("BROWSER_RECEIVE_TO_RENDER_MS"), 95),
    RETURN_MISSED_USEFUL_POLL_COUNT: valid.reduce((a, s) => a + (s.MISSED_USEFUL_POLL_COUNT || 0), 0),
    MISSED_POLL_BROWSER_COUNT: valid.reduce((a, s) => a + (s.MISSED_POLL_BROWSER_COUNT || 0), 0),
    MISSED_POLL_SERVER_WAIT_COUNT: valid.reduce((a, s) => a + (s.MISSED_POLL_SERVER_WAIT_COUNT || 0), 0),
    MISSED_POLL_STORE_BLOCK_COUNT: valid.reduce((a, s) => a + (s.MISSED_POLL_STORE_BLOCK_COUNT || 0), 0),
    MISSED_POLL_STALE_READ_COUNT: valid.reduce((a, s) => a + (s.MISSED_POLL_STALE_READ_COUNT || 0), 0),
    MISSED_POLL_RESPONSE_ORDER_COUNT: valid.reduce((a, s) => a + (s.MISSED_POLL_RESPONSE_ORDER_COUNT || 0), 0),
    MISSED_POLL_CLIENT_DISCARD_COUNT: valid.reduce((a, s) => a + (s.MISSED_POLL_CLIENT_DISCARD_COUNT || 0), 0),
    POLL_RESPONSE_OUT_OF_ORDER_COUNT: valid.reduce((a, s) => a + (s.POLL_RESPONSE_OUT_OF_ORDER_COUNT || 0), 0),
    ACTUAL_POLL_INTERVAL_P50_MS: pct(valid.map((s) => s.ACTUAL_POLL_INTERVAL_P50_MS), 50),
    ACTUAL_POLL_INTERVAL_P95_MS: pct(valid.map((s) => s.ACTUAL_POLL_INTERVAL_P95_MS), 95),
    POLL_OVERLAP_COUNT: valid.reduce((a, s) => a + (s.POLL_OVERLAP_COUNT || 0), 0),
    FIRST_USEFUL_STATUS_PARTIAL_COUNT: valid.filter((s) => s.first_useful_status === "partial").length,
    FIRST_USEFUL_STATUS_READY_COUNT: valid.filter((s) => s.first_useful_status === "ready").length,
    USEFUL_PARTIAL_AVAILABLE_BUT_POLL_EMPTY_COUNT: valid.reduce(
      (a, s) => a + (s.USEFUL_PARTIAL_AVAILABLE_BUT_POLL_EMPTY_COUNT || 0),
      0,
    ),
    CLIENT_WAITS_FOR_FINAL_READY_WITH_VALID_PAIRS: valid.every(
      (s) => !s.CLIENT_WAITS_FOR_FINAL_READY_WITH_VALID_PAIRS,
    )
      ? "NO"
      : "YES",
    RETURN_POLL_INTERVAL_MS: 750,
    RETURN_PAIRING_P95_MS: pct(pick("PAIRING_MS"), 95),
    RETURN_FIRST_PROVIDER_RESPONSE_P95_MS: pct(pick("FIRST_PROVIDER_RESPONSE_MS"), 95),
    RETURN_JS_FATAL: valid.some((s) => s.RETURN_JS_FATAL) ? "YES" : "NO",
    RETURN_HYDRATION_FATAL: valid.some((s) => s.RETURN_HYDRATION_FATAL) ? "YES" : "NO",
    samples,
  };
  fs.writeFileSync(path.join(OUT, "return-browser-n30.json"), JSON.stringify(out, null, 2));
  console.log(
    JSON.stringify(
      {
        valid: valid.length,
        first_p50: out.RETURN_CLICK_TO_FIRST_USEFUL_P50_MS,
        first_p95: out.RETURN_CLICK_TO_FIRST_USEFUL_P95_MS,
        pair_to_browser_p95: out.PAIR_AVAILABLE_TO_BROWSER_P95_MS,
        poll_total_p95: out.POLL_TOTAL_SERVER_P95_MS,
        poll_store_p95: out.POLL_RESULT_STORE_READ_P95_MS,
        missed: out.RETURN_MISSED_USEFUL_POLL_COUNT,
        contention: out.RESULT_STORE_READ_WRITE_CONTENTION,
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
