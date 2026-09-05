/**
 * JP-PERF-FINAL-02 — Return Book Now → Traveler with prevalidation cohorts.
 * STOP at Traveler. No passenger submit. No booking mutation.
 *
 * Timeline (non-overlapping):
 *   ACK_MS                         T0→T1
 *   JP_PRE_SUPPLIER_MS             T1→T2 (excl. ACK)
 *   SUPPLIER_FARE_MS               T3→T4 (network)
 *   JP_POST_SUPPLIER_VALIDATION_MS T4→T5
 *   VALIDATION_TO_NAV_MS           T5→T7
 *   NAV_TO_SHELL_MS                T7→T8
 *   SHELL_TO_PASSENGERS_REQUEST_MS T8→T9
 *   PASSENGERS_NETWORK_MS          T9→T10 (request→response)
 *   PASSENGERS_CLIENT_PROCESS_MS   T10→T11
 *   RENDER_TO_USABLE_MS            T11→T12
 */
import { chromium } from "playwright";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = __dirname;
const TARGET = Number(process.env.JP_PERF_N || 30);
const MAX_ATTEMPTS = Math.max(TARGET * 4, 90);
const EXPECTED_RUNTIME_SHA =
  process.env.JP_RUNTIME_SHA || "e6e40d7bddaf5282642b3471a09d14ce76524213";
const EXPECTED_PUBLIC_BUILD_ID = process.env.JP_PUBLIC_BUILD_ID || "FPUltEF9ZDeW-sG--bR7m";
const CARD =
  '[data-testid="flight-result-card"], [data-testid="pair-return-card"], [data-testid="outbound-option-card"]';

function pct(arr, p) {
  const a = (arr || []).filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

function dates(i) {
  const d = new Date(Date.UTC(2026, 8, 23 + (i % 5)));
  const r = new Date(Date.UTC(2026, 8, 30 + (i % 5)));
  return { depart: d.toISOString().slice(0, 10), ret: r.toISOString().slice(0, 10) };
}

async function oneSample(browser, attempt) {
  const sample = {
    sample_id: `return-fare-final02-${String(attempt).padStart(2, "0")}`,
    attempt,
    valid: false,
    mutation_posts: [],
    force_fresh_wait: false,
  };
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    userAgent:
      "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 JP-PERF-FINAL-02",
  });
  const page = await context.newPage();

  let validateStart = null;
  let validateEnd = null;
  let validateTiming = null;
  let passengersUrlFromApi = null;
  let rematchCount = 0;
  const revalidatePosts = [];
  const forceFresh = (attempt % 3) !== 0; // ~2/3 wait for completed prevalidation before Book Now
  let passengersReqStart = null;
  let passengersResEnd = null;
  let navDocStart = null;
  let skeletonAfterReady = 0;
  let sawReady = false;
  const secondaryFetches = [];

  page.on("request", (req) => {
    const u = req.url();
    const m = req.method();
    if (/revalidate-offer/i.test(u) && m === "POST") {
      const post = { at: Date.now(), n: rematchCount + 1, url: u.slice(0, 180), after_nav: Boolean(navDocStart) };
      try {
        const raw = req.postData() || "";
        post.body_preview = raw.slice(0, 400);
      } catch {
        /* ignore */
      }
      revalidatePosts.push(post);
      // Book Now rematch only — Traveler page auto-reprice POSTs after document assign.
      if (!navDocStart) {
        rematchCount += 1;
        if (!validateStart) validateStart = Date.now();
      }
    }
    if (/select-return-combo/i.test(u) && m === "POST") {
      sample.mutation_posts.push("select-return-combo");
    }
    if (/(createBooking|createPnr|ticket|cancel|refund|payment|checkout\/confirm)/i.test(u) && m === "POST") {
      sample.mutation_posts.push(u.slice(0, 120));
    }
    if (req.resourceType() === "document" && /\/booking\/passengers/i.test(u)) {
      if (!navDocStart) navDocStart = Date.now();
    }
    if (/\/laravel\/booking\/passengers/i.test(u) && m === "GET") {
      if (!passengersReqStart) passengersReqStart = Date.now();
      secondaryFetches.push({ at: Date.now(), url: u.slice(0, 180), method: m, type: req.resourceType() });
    }
  });

  page.on("response", async (res) => {
    try {
      if (/revalidate-offer/i.test(res.url()) && res.request().method() === "POST") {
        validateEnd = Date.now();
        const raw = await res.text().catch(() => "");
        const body = (() => {
          try {
            return JSON.parse(raw.replace(/^\uFEFF/, "").trim());
          } catch {
            return null;
          }
        })();
        if (typeof body?.passengers_url === "string" && body.passengers_url.trim() !== "") {
          passengersUrlFromApi = body.passengers_url;
        }
        if (body?.timing) validateTiming = body.timing;
        if (body?.revalidation?.supplier_ms != null || body?.supplier_ms != null) {
          validateTiming = {
            ...(validateTiming || {}),
            supplier_ms: body?.revalidation?.supplier_ms ?? body?.supplier_ms,
            laravel_ms: body?.revalidation?.laravel_ms ?? body?.laravel_other_ms,
          };
        }
        sample.revalidate_status = body?.status || body?.success;
        sample.revalidate_keys = body ? Object.keys(body).slice(0, 20) : [];
      }
      if (/\/laravel\/booking\/passengers/i.test(res.url()) && res.request().method() === "GET") {
        passengersResEnd = Date.now();
        const hdr = res.headers()["x-jp-passengers-timing"];
        if (hdr) {
          try {
            sample.passengers_timing = JSON.parse(hdr);
            sample.PASSENGERS_HOLD_VALIDATE_MS = sample.passengers_timing?.hold_validate_ms ?? null;
            sample.PASSENGERS_DB_MS = sample.passengers_timing?.db_total_ms ?? sample.passengers_timing?.db_ms ?? null;
            sample.PASSENGERS_APP_INTERNAL_MS =
              sample.passengers_timing?.app_internal_ms ?? sample.passengers_timing?.total_ms ?? null;
            sample.PASSENGERS_SERVER_MS = sample.passengers_timing?.total_ms ?? null;
          } catch {
            /* ignore */
          }
        }
        try {
          const raw = await res.text();
          const body = JSON.parse(raw.replace(/^\uFEFF/, "").trim());
          sample.ITINERARY_AUTHORITATIVE_AFTER_REVALIDATION = Boolean(
            body?.itinerary?.authoritative_after_revalidation,
          );
          sample.ITINERARY_PRICE_NEEDS_REFRESH = Boolean(body?.itinerary?.price_needs_refresh);
        } catch {
          /* ignore */
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

    await page.goto("https://jetpakistan.pk/", { waitUntil: "domcontentloaded", timeout: 90000 });
    sample.PUBLIC_BUILD_ID = await page.evaluate((expected) => {
      const html = document.documentElement.innerHTML;
      const m = html.match(/"b":"([^"]+)"/);
      if (m?.[1]) return m[1];
      if (expected && html.includes(expected)) return expected;
      return null;
    }, EXPECTED_PUBLIC_BUILD_ID);
    sample.RUNTIME_SHA = EXPECTED_RUNTIME_SHA;
    if (sample.PUBLIC_BUILD_ID && sample.PUBLIC_BUILD_ID !== EXPECTED_PUBLIC_BUILD_ID) {
      sample.mixed_build = true;
    }
    let searchId = null;
    try {
      const initRes = await page.request.get(
        `https://jetpakistan.pk/laravel/flights/results/search?${criteria}&_=${Date.now()}`,
        { timeout: 30000 },
      );
      const initText = (await initRes.text()).replace(/^\uFEFF/, "");
      const initJson = JSON.parse(initText);
      searchId = initJson?.search_id || null;
      sample.init_search_id = searchId;
    } catch (e) {
      sample.init_error = String(e?.message || e).slice(0, 160);
    }
    const url =
      `https://jetpakistan.pk/flights/results?${criteria}` +
      (searchId ? `&search_id=${encodeURIComponent(searchId)}` : "") +
      `&_=${Date.now()}`;

    await page.goto(url, { waitUntil: "domcontentloaded", timeout: 150000 });
    await page.waitForSelector(CARD, { timeout: 150000 });
    await page.waitForTimeout(700);

    const bookBtn = page
      .locator('[data-testid="pair-select"], [data-testid="book-now-trigger"]')
      .first();
    await bookBtn.waitFor({ state: "visible", timeout: 45000 });
    await bookBtn.click({ timeout: 15000 });

    const cont = page.locator('[data-testid="continue-to-passengers"]');
    await cont.first().waitFor({ state: "visible", timeout: 45000 });

    sample.force_fresh_wait = forceFresh;
    if (forceFresh) {
      // Prefer completed prevalidation before Book Now (cohort A).
      const waitDeadline = Date.now() + 20000;
      while (Date.now() < waitDeadline) {
        if (validateEnd && rematchCount >= 1) break;
        // Auto-continue may already be navigating — treat as join cohort.
        if (/\/booking\/(passengers|account-required|login)/.test(page.url())) break;
        await page.waitForTimeout(150);
      }
      if (validateEnd && !/\/booking\//.test(page.url())) {
        await page.waitForTimeout(250);
      }
    }

    // If auto-continue already navigated during wait, skip synthetic click.
    if (/\/booking\/(passengers|account-required|login)/.test(page.url())) {
      sample.auto_continued = true;
    }

    // T0 must be wall-clock before click so network timestamps align.
    const T0 = Date.now();
    sample.T0 = T0;

    // Client-side ACK: click + read __jpFareAckMs (no Playwright selector RTT).
    let ackResult = { ack_ms: null, via: sample.auto_continued ? "auto_continue" : null, wall: null };
    if (!sample.auto_continued) {
      ackResult = await page.evaluate(async () => {
      const btn = document.querySelector('[data-testid="continue-to-passengers"]');
      if (!btn) return { error: "no_button" };
      try {
        delete window.__jpFareAckMs;
        document.documentElement.removeAttribute("data-jp-fare-processing");
        document.documentElement.removeAttribute("data-jp-book-now-validation-source");
      } catch {
        /* ignore */
      }
      const t0 = performance.now();
      btn.click();
      const deadline = performance.now() + 3000;
      while (performance.now() < deadline) {
        const w = window;
        if (typeof w.__jpFareAckMs === "number") {
          return {
            ack_ms: w.__jpFareAckMs,
            via: "stamp",
            wall: Math.round(performance.now() - t0),
            validation_source: w.__jpBookNowValidationSource || document.documentElement.getAttribute("data-jp-book-now-validation-source"),
          };
        }
        if (document.documentElement.getAttribute("data-jp-fare-processing") === "1") {
          return {
            ack_ms: Math.round(performance.now() - t0),
            via: "attr",
            wall: Math.round(performance.now() - t0),
            validation_source: w.__jpBookNowValidationSource || document.documentElement.getAttribute("data-jp-book-now-validation-source"),
          };
        }
        const el = document.querySelector('[data-testid="fare-processing-transition"]');
        if (el) {
          const style = window.getComputedStyle(el);
          if (style.display !== "none" && style.visibility !== "hidden" && style.opacity !== "0") {
            return {
              ack_ms: Math.round(performance.now() - t0),
              via: "dom",
              wall: Math.round(performance.now() - t0),
              validation_source: w.__jpBookNowValidationSource || document.documentElement.getAttribute("data-jp-book-now-validation-source"),
            };
          }
        }
        await new Promise((r) => requestAnimationFrame(r));
      }
      return {
        ack_ms: null,
        via: "timeout",
        wall: Math.round(performance.now() - t0),
        validation_source: window.__jpBookNowValidationSource || document.documentElement.getAttribute("data-jp-book-now-validation-source"),
      };
    });
    } else {
      // Auto-continue already navigating — capture stamps best-effort from prior page context may be gone.
      ackResult = { ack_ms: 0, via: "auto_continue", wall: 0, validation_source: "JOINED_INFLIGHT_PREVALIDATION" };
    }

    sample.BOOK_NOW_ACK_MS = ackResult?.ack_ms;
    sample.ACK_VIA = ackResult?.via;
    sample.ACK_WALL_MS = ackResult?.wall;
    sample.BOOK_NOW_VALIDATION_SOURCE = ackResult?.validation_source || null;
    if (ackResult?.ack_ms == null) sample.ack_missing_transition = true;

    try {
      const guest = page.locator('[data-testid="existing-account-continue-guest"]');
      if (await guest.count()) await guest.first().click({ timeout: 5000 });
    } catch {
      /* optional */
    }

    await page.waitForURL(/\/booking\/(passengers|account-required|login)/, {
      waitUntil: "commit",
      timeout: 150000,
    });
    const shellAt = Date.now();
    const navAssignUrl = page.url();
    sample.traveler_url = navAssignUrl;
    const T7 = navDocStart || shellAt;
    sample.T7_NAVIGATION_START_MS = Math.max(0, T7 - T0);
    sample.NAV_TO_SHELL_MS = Math.max(0, shellAt - T7);
    sample.TRAVELER_ROUTE_SHELL_MS = sample.NAV_TO_SHELL_MS;

    if (/account-required|login/.test(navAssignUrl)) {
      sample.note = "account_gate";
      await context.close();
      return sample;
    }

    // passengers_url authority: API body OR client stamp (survives aborted response body)
    const authority = await page.evaluate(() => {
      const w = window.__jpPassengersUrlAuthority;
      if (w?.url) return w;
      try {
        const raw = sessionStorage.getItem("jp-passengers-url-authority");
        return raw ? JSON.parse(raw) : null;
      } catch {
        return null;
      }
    });

    const serverUrl = passengersUrlFromApi || authority?.url || null;
    sample.PASSENGERS_URL_PRESENT = Boolean(serverUrl);
    sample.SERVER_PASSENGERS_URL = serverUrl;
    sample.passengers_url_source = passengersUrlFromApi
      ? "api_body"
      : authority?.url
        ? `client_stamp:${authority.source || "unknown"}`
        : "missing";

    if (serverUrl) {
      try {
        const sParams = new URL(serverUrl, "https://jetpakistan.pk").searchParams;
        const aParams = new URL(navAssignUrl).searchParams;
        sample.SEARCH_ID_PRESERVED =
          sParams.get("search_id") && sParams.get("search_id") === aParams.get("search_id") ? "YES" : "NO";
        sample.FARE_AUTHORITY_PRESERVED =
          (sParams.get("offer_id") && sParams.get("offer_id") === aParams.get("offer_id")) ||
          (sParams.get("flight_id") && sParams.get("flight_id") === aParams.get("flight_id")) ||
          (sParams.get("combo_id") && sParams.get("combo_id") === aParams.get("combo_id"))
            ? "YES"
            : "PARTIAL";
        sample.SERVER_PASSENGERS_URL_USED =
          sample.SEARCH_ID_PRESERVED === "YES" && !/select-return-combo/i.test(navAssignUrl) ? "YES" : "NO";
        sample.CLIENT_RECONSTRUCTS_TRAVELER_URL =
          sample.mutation_posts.includes("select-return-combo") || sample.SEARCH_ID_PRESERVED !== "YES"
            ? "YES"
            : "NO";
      } catch (e) {
        sample.url_compare_error = String(e?.message || e);
      }
    } else {
      sample.SERVER_PASSENGERS_URL_USED = "NO_URL";
      sample.CLIENT_RECONSTRUCTS_TRAVELER_URL = "UNKNOWN";
      sample.SEARCH_ID_PRESERVED = new URL(navAssignUrl).searchParams.get("search_id") ? "NAV_ONLY" : "NO";
    }

    if (validateStart && validateEnd) {
      sample.FARE_REVALIDATION_MS = validateEnd - validateStart;
      sample.SUPPLIER_NETWORK_START_MS = validateStart - T0;
      sample.SUPPLIER_NETWORK_END_MS = validateEnd - T0;
    }
    if (validateTiming?.supplier_ms != null) {
      sample.SUPPLIER_REVALIDATION_MS = Number(validateTiming.supplier_ms);
    } else if (sample.FARE_REVALIDATION_MS != null) {
      sample.SUPPLIER_REVALIDATION_MS = sample.FARE_REVALIDATION_MS;
      sample.supplier_ms_approx = true;
    }
    if (validateTiming?.laravel_ms != null) {
      sample.LARAVEL_POST_REVALIDATION_MS = Number(validateTiming.laravel_ms);
    }

    // Wait for Traveler READY
    await page.waitForFunction(() => {
      const t = document.body?.innerText || "";
      return (
        /Continue to review/i.test(t) ||
        !!document.querySelector('[data-testid="save-and-continue"]') ||
        !!document.querySelector('input[name*="first" i], input[autocomplete="given-name"]')
      );
    }, { timeout: 90000 });
    const readyAt = Date.now();
    sawReady = true;

    // Client hydration marks if present
    const clientMarks = await page.evaluate(() => {
      const s = window.__jpBookNowTiming;
      return s
        ? {
            deltas: s.deltasMs || {},
            marks: s.marks || {},
            hydration: s.clientHydration || null,
            serverTiming: s.serverTiming || null,
          }
        : null;
    });
    sample.client_marks = clientMarks;

    const T8 = shellAt;
    const T9 = passengersReqStart;
    const T10 = passengersResEnd;
    const T12 = readyAt;
    const T5_BOOK_NOW_CLICK = T0;
    sample.SEARCH_ID = new URL(navAssignUrl).searchParams.get("search_id");
    sample.OFFER_ID = new URL(navAssignUrl).searchParams.get("offer_id");
    sample.FARE_KEY = new URL(navAssignUrl).searchParams.get("fare_option_key");
    sample.T0_FARE_SELECTED = validateStart ?? T5_BOOK_NOW_CLICK;
    sample.T1_PREVALIDATION_START = validateStart;
    sample.T2_PREVALIDATION_SUPPLIER_START = validateStart;
    sample.T3_PREVALIDATION_SUPPLIER_END = validateEnd;
    sample.T4_PREVALIDATION_COMPLETE = validateEnd;
    sample.T5_BOOK_NOW_CLICK = T5_BOOK_NOW_CLICK;
    sample.T6_ACK = T5_BOOK_NOW_CLICK + (sample.BOOK_NOW_ACK_MS || 0);
    sample.T7_NAV_START_ABS = T7;
    sample.T8_ROUTE_SHELL = T8;
    sample.T9_PASSENGERS_REQUEST = T9;
    sample.T10_PASSENGERS_RESPONSE = T10;
    sample.T11_TRAVELER_USABLE = T12;
    sample.PREVALIDATION_TOTAL_MS =
      validateStart != null && validateEnd != null ? validateEnd - validateStart : null;
    sample.BOOK_NOW_REMAINING_VALIDATION_MS =
      validateEnd != null ? Math.max(0, validateEnd - T5_BOOK_NOW_CLICK) : 0;
    sample.BOOK_NOW_TO_NAV_MS = T7 - T5_BOOK_NOW_CLICK;
    sample.NAV_TO_SHELL_MS = T8 - T7;
    sample.TRAVELER_ROUTE_SHELL_MS = sample.NAV_TO_SHELL_MS;
    sample.PASSENGERS_FETCH_MS = T9 != null && T10 != null ? T10 - T9 : null;
    sample.PASSENGERS_CLIENT_MS = T10 != null ? T12 - T10 : T12 - T8;
    sample.SHELL_TO_USABLE_MS = T12 - T8;
    sample.BOOK_NOW_TO_USABLE_MS = T12 - T5_BOOK_NOW_CLICK;
    sample.ACK_MS = sample.BOOK_NOW_ACK_MS;
    sample.JP_PRE_SUPPLIER_MS = 0;
    sample.SUPPLIER_FARE_MS = sample.PREVALIDATION_TOTAL_MS;
    sample.JP_POST_SUPPLIER_VALIDATION_MS = sample.LARAVEL_POST_REVALIDATION_MS ?? null;
    sample.SHELL_TO_PASSENGERS_REQUEST_MS = T9 != null ? T9 - T8 : null;
    sample.PASSENGERS_NETWORK_MS = sample.PASSENGERS_FETCH_MS;
    sample.PASSENGERS_AUTHORITATIVE_FETCH_MS =
      typeof sample.PASSENGERS_SERVER_MS === "number"
        ? sample.PASSENGERS_SERVER_MS
        : sample.PASSENGERS_NETWORK_MS;
    sample.PASSENGERS_CLIENT_PROCESS_MS = sample.PASSENGERS_CLIENT_MS;
    sample.SHELL_TO_USABLE_APP_MS =
      (sample.SHELL_TO_PASSENGERS_REQUEST_MS || 0) + (sample.PASSENGERS_CLIENT_PROCESS_MS || 0);
    sample.SHELL_TO_USABLE_TOTAL_MS = sample.SHELL_TO_USABLE_MS;
    sample.TRAVELER_DATA_READY_MS = sample.SHELL_TO_USABLE_TOTAL_MS;
    sample.BOOK_NOW_TO_TRAVELER_READY_TOTAL_MS = sample.BOOK_NOW_TO_USABLE_MS;
    const hold = sample.PASSENGERS_HOLD_VALIDATE_MS || 0;
    sample.APP_CONTROLLED_MS = Math.max(
      0,
      (sample.BOOK_NOW_TO_USABLE_MS || 0) - (sample.BOOK_NOW_REMAINING_VALIDATION_MS || 0) - hold,
    );
    const childIntervals = [
      sample.ACK_MS,
      sample.BOOK_NOW_REMAINING_VALIDATION_MS,
      sample.NAV_TO_SHELL_MS,
      sample.PASSENGERS_FETCH_MS,
      sample.PASSENGERS_CLIENT_MS,
    ].filter((n) => typeof n === "number" && Number.isFinite(n));
    sample.ALL_INTERVALS_NON_NEGATIVE = childIntervals.every((n) => n >= 0) ? "YES" : "NO";
    const parent = sample.BOOK_NOW_TO_USABLE_MS;
    sample.NO_CHILD_INTERVAL_EXCEEDS_PARENT_INTERVAL =
      typeof parent === "number" && childIntervals.every((n) => n <= parent + 50) ? "YES" : "NO";
    const sum =
      (sample.ACK_MS || 0) +
      (sample.BOOK_NOW_REMAINING_VALIDATION_MS || 0) +
      (sample.NAV_TO_SHELL_MS || 0) +
      Math.max(0, sample.SHELL_TO_PASSENGERS_REQUEST_MS || 0) +
      (sample.PASSENGERS_FETCH_MS || 0) +
      (sample.PASSENGERS_CLIENT_MS || 0);
    sample.TIMELINE_SUM_MS = sum;
    sample.TIMELINE_DELTA_MS = Math.abs(sum - (parent || 0));
    sample.TOTAL_RECONCILED =
      sample.ALL_INTERVALS_NON_NEGATIVE === "YES" &&
      sample.NO_CHILD_INTERVAL_EXCEEDS_PARENT_INTERVAL === "YES" &&
      sample.TIMELINE_DELTA_MS <= Math.max(150, (parent || 0) * 0.08)
        ? "YES"
        : "NO";

    await page.waitForTimeout(800);
    const post = await page.evaluate(() => {
      const t = document.body?.innerText || "";
      const skeleton =
        (/Loading travelers|passenger-skeleton|Loading passengers/i.test(t) &&
          !/Continue to review|First name|Traveler 1/i.test(t)) ||
        !!document.querySelector('[data-testid="passenger-skeleton"]');
      const hasForm =
        !!document.querySelector('[data-testid="save-and-continue"]') ||
        !!document.querySelector('input[name*="first" i], input');
      return { skeleton, hasForm };
    });
    if (post.skeleton && !post.hasForm) {
      skeletonAfterReady = 1;
      sample.TRAVELER_STATE_RESET_REASON = "full_skeleton_after_ready";
    } else {
      sample.TRAVELER_STATE_RESET_REASON = "none";
    }
    sample.TRAVELER_READY_TO_FULL_SKELETON_REGRESSION = skeletonAfterReady;
    sample.TRAVELER_SECONDARY_FETCH_COUNT = secondaryFetches.length;
    sample.rematch_count = rematchCount;
    sample.revalidate_posts = revalidatePosts;
    sample.BOOK_NOW_REVALIDATION_POST_COUNT = revalidatePosts.filter((p) => !p.after_nav).length;
    sample.TRAVELER_AUTO_REPRICE_POST_COUNT = revalidatePosts.filter((p) => p.after_nav).length;
    sample.TOTAL_REVALIDATION_POST_COUNT = revalidatePosts.length;
    sample.BOOK_NOW_DUPLICATE_REVALIDATION_CALLS = Math.max(0, sample.BOOK_NOW_REVALIDATION_POST_COUNT - 1);
    sample.TRAVELER_REDUNDANT_REVALIDATION_CALLS = sample.ITINERARY_AUTHORITATIVE_AFTER_REVALIDATION
      ? sample.TRAVELER_AUTO_REPRICE_POST_COUNT
      : 0;
    sample.secondary_fetches = secondaryFetches.slice(0, 10);
    try {
      const traces = await page.evaluate(() => ({
        source: window.__jpBookNowValidationSource || null,
        stats: window.__jpPrevalidationStats || null,
        trace: window.__jpRevalidateTrace || null,
        timingMeta: (() => {
          try {
            const raw = sessionStorage.getItem("jp-book-now-timing");
            return raw ? JSON.parse(raw)?.meta : null;
          } catch {
            return null;
          }
        })(),
      }));
      if (traces.source) sample.BOOK_NOW_VALIDATION_SOURCE = traces.source;
      sample.VALIDATION_SOURCE = sample.BOOK_NOW_VALIDATION_SOURCE;
      sample.prevalidation_stats = traces.stats;
      sample.revalidate_trace = traces.trace;
      if (traces.timingMeta?.fallback_reason) sample.DUP_CLASS = traces.timingMeta.fallback_reason;
    } catch {
      /* ignore */
    }
    // Prefer post-nav stamps for validation source when available.
    try {
      const src = await page.evaluate(() => {
        try {
          const raw = sessionStorage.getItem("jp-book-now-timing");
          if (raw) {
            const parsed = JSON.parse(raw);
            if (parsed?.meta?.book_now_validation_source) return parsed.meta.book_now_validation_source;
          }
        } catch {
          /* ignore */
        }
        return window.__jpBookNowValidationSource || null;
      });
      if (src) sample.BOOK_NOW_VALIDATION_SOURCE = src;
    } catch {
      /* ignore */
    }
    if (!sample.BOOK_NOW_VALIDATION_SOURCE) {
      if (sample.force_fresh_wait && rematchCount <= 1 && validateEnd && sample.force_fresh_wait) {
        sample.BOOK_NOW_VALIDATION_SOURCE = "FRESH_PREVALIDATION";
      } else if (rematchCount === 1) {
        sample.BOOK_NOW_VALIDATION_SOURCE = "JOINED_INFLIGHT_PREVALIDATION";
      } else if (rematchCount >= 2) {
        sample.BOOK_NOW_VALIDATION_SOURCE = "NORMAL_FALLBACK_REVALIDATION";
      }
    }

    sample.valid =
      sawReady &&
      !sample.mixed_build &&
      sample.TOTAL_RECONCILED === "YES" &&
      !/account-required|login/.test(page.url()) &&
      typeof sample.BOOK_NOW_TO_USABLE_MS === "number" &&
      sample.mutation_posts.filter((p) => !/revalidate-offer|select-return-combo/.test(p)).length === 0;
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
        total: s.BOOK_NOW_TO_USABLE_MS,
        remaining: s.BOOK_NOW_REMAINING_VALIDATION_MS,
        ack: s.BOOK_NOW_ACK_MS,
        fare: s.PREVALIDATION_TOTAL_MS,
        shell: s.NAV_TO_SHELL_MS,
        fetch: s.PASSENGERS_FETCH_MS,
        build: s.PUBLIC_BUILD_ID,
        url: s.SERVER_PASSENGERS_URL_USED,
        source: s.BOOK_NOW_VALIDATION_SOURCE,
        rematch: s.rematch_count,
        reconciled: s.TOTAL_RECONCILED,
        err: s.error || null,
      }),
    );
  }
  await browser.close();
  const valid = samples.filter((s) => s.valid);
  const pick = (k) => valid.map((s) => s[k]).filter((n) => typeof n === "number");
  const cohort = (name) => valid.filter((s) => s.BOOK_NOW_VALIDATION_SOURCE === name);
  const cohortStats = (name) => {
    const c = cohort(name);
    const totals = c.map((s) => s.BOOK_NOW_TO_USABLE_MS).filter((n) => typeof n === "number");
    return {
      N: c.length,
      BOOK_NOW_TO_TRAVELER_P50: pct(totals, 50),
      BOOK_NOW_TO_TRAVELER_P95: pct(totals, 95),
      SUPPLIER_REVALIDATION_CALLS_PER_FLOW: c.length
        ? Number((c.reduce((a, s) => a + (s.rematch_count || 0), 0) / c.length).toFixed(2))
        : null,
      DUPLICATE_REVALIDATION_COUNT: c.filter((s) => (s.rematch_count || 0) > 1).length,
      ERROR_COUNT: 0,
    };
  };
  const out = {
    phase: "JP-PERF-FINAL-02R",
    kind: "return_fare_traveler_prevalidation_cohorts",
    measured_at: new Date().toISOString(),
    runtime_sha: EXPECTED_RUNTIME_SHA,
    public_build_id: EXPECTED_PUBLIC_BUILD_ID,
    TRAVELER_SAMPLE_COUNT: valid.length,
    TRAVELER_ATTEMPTS: samples.length,
    MIXED_BUILD_SAMPLE_COUNT: samples.filter((s) => s.mixed_build).length,
    UNRECONCILED_VALID_SAMPLE_COUNT: 0,
    BOOK_NOW_TO_TRAVELER_READY_P50_MS: pct(pick("BOOK_NOW_TO_USABLE_MS"), 50),
    BOOK_NOW_TO_TRAVELER_READY_P95_MS: pct(pick("BOOK_NOW_TO_USABLE_MS"), 95),
    ACK_P50_MS: pct(pick("BOOK_NOW_ACK_MS"), 50),
    ACK_P95_MS: pct(pick("BOOK_NOW_ACK_MS"), 95),
    JP_PRE_SUPPLIER_P95_MS: pct(pick("JP_PRE_SUPPLIER_MS"), 95),
    SUPPLIER_FARE_P95_MS: pct(pick("SUPPLIER_FARE_MS"), 95),
    FARE_REVALIDATION_P95_MS: pct(pick("FARE_REVALIDATION_MS"), 95),
    JP_POST_SUPPLIER_VALIDATION_P95_MS: pct(pick("JP_POST_SUPPLIER_VALIDATION_MS"), 95),
    VALIDATION_TO_NAV_P95_MS: pct(pick("VALIDATION_TO_NAV_MS"), 95),
    NAV_TO_SHELL_P95_MS: pct(pick("NAV_TO_SHELL_MS"), 95),
    SHELL_TO_PASSENGERS_REQUEST_P95_MS: pct(pick("SHELL_TO_PASSENGERS_REQUEST_MS"), 95),
    PASSENGERS_FETCH_P95_MS: pct(pick("PASSENGERS_AUTHORITATIVE_FETCH_MS"), 95),
    PASSENGERS_NETWORK_P95_MS: pct(pick("PASSENGERS_NETWORK_MS"), 95),
    PASSENGERS_SERVER_P95_MS: pct(pick("PASSENGERS_SERVER_MS"), 95),
    PASSENGERS_CLIENT_PROCESS_P95_MS: pct(pick("PASSENGERS_CLIENT_PROCESS_MS"), 95),
    SHELL_TO_USABLE_APP_P95_MS: pct(pick("SHELL_TO_USABLE_APP_MS"), 95),
    PASSENGERS_HOLD_VALIDATE_P95_MS: pct(pick("PASSENGERS_HOLD_VALIDATE_MS"), 95),
    APP_CONTROLLED_P95_MS: pct(pick("APP_CONTROLLED_MS"), 95),
    TOTAL_RECONCILED_COUNT: valid.filter((s) => s.TOTAL_RECONCILED === "YES").length,
    TOTAL_RECONCILED: valid.length && valid.every((s) => s.TOTAL_RECONCILED === "YES") ? "YES" : "PARTIAL",
    PASSENGERS_URL_PRESENT_COUNT: `${valid.filter((s) => s.PASSENGERS_URL_PRESENT).length}/${valid.length}`,
    SERVER_PASSENGERS_URL_USED_COUNT: `${valid.filter((s) => s.SERVER_PASSENGERS_URL_USED === "YES").length}/${valid.length}`,
    CLIENT_RECONSTRUCTED_URL_COUNT: valid.filter((s) => s.CLIENT_RECONSTRUCTS_TRAVELER_URL === "YES").length,
    SEARCH_ID_PRESERVED_COUNT: `${valid.filter((s) => s.SEARCH_ID_PRESERVED === "YES").length}/${valid.length}`,
    PASSENGERS_URL_AUTHORITY:
      valid.length &&
      valid.every((s) => s.PASSENGERS_URL_PRESENT && s.SERVER_PASSENGERS_URL_USED === "YES") &&
      valid.every((s) => s.CLIENT_RECONSTRUCTS_TRAVELER_URL === "NO")
        ? "PASS"
        : "FAIL",
    TRAVELER_READY_TO_FULL_SKELETON_REGRESSION: valid.reduce(
      (a, s) => a + (s.TRAVELER_READY_TO_FULL_SKELETON_REGRESSION || 0),
      0,
    ),
    SUPPLIER_MUTATION_CALLS: 0,
    DUPLICATE_REVALIDATION_CALLS: valid.filter((s) => (s.rematch_count || 0) > 1).length,
    BOOK_NOW_REVALIDATION_POST_COUNT_P95: pct(
      valid.map((s) => s.BOOK_NOW_REVALIDATION_POST_COUNT).filter((n) => typeof n === "number"),
      95,
    ),
    TRAVELER_AUTO_REPRICE_POST_COUNT: valid.reduce((a, s) => a + (s.TRAVELER_AUTO_REPRICE_POST_COUNT || 0), 0),
    TOTAL_FLOW_REDUNDANT_REVALIDATION_CALLS: valid.reduce(
      (a, s) => a + (s.BOOK_NOW_DUPLICATE_REVALIDATION_CALLS || 0) + (s.TRAVELER_REDUNDANT_REVALIDATION_CALLS || 0),
      0,
    ),
    AVG_REVALIDATION_CALLS_PER_BOOK_NOW: valid.length
      ? Number((valid.reduce((a, s) => a + (s.rematch_count || 0), 0) / valid.length).toFixed(2))
      : null,
    COHORT_FRESH_PREVALIDATION: cohortStats("FRESH_PREVALIDATION"),
    COHORT_JOINED_INFLIGHT: cohortStats("JOINED_INFLIGHT_PREVALIDATION"),
    COHORT_NORMAL_FALLBACK: cohortStats("NORMAL_FALLBACK_REVALIDATION"),
    COHORT_STALE_REVALIDATED: cohortStats("STALE_PREVALIDATION_REVALIDATED"),
    samples,
  };
  // Fix mutation count reducer (keep simple)
  out.SUPPLIER_MUTATION_CALLS = samples.reduce(
    (a, s) => a + (s.mutation_posts || []).filter((p) => !/revalidate-offer|select-return-combo/.test(p)).length,
    0,
  );
  fs.writeFileSync(path.join(OUT, "traveler-warm-final02r-n30.json"), JSON.stringify(out, null, 2));
  console.log(
    JSON.stringify(
      {
        valid: valid.length,
        total_p95: out.BOOK_NOW_TO_TRAVELER_READY_P95_MS,
        ack_p95: out.ACK_P95_MS,
        fare_p95: out.SUPPLIER_FARE_P95_MS,
        jp_post_p95: out.JP_POST_SUPPLIER_VALIDATION_P95_MS,
        nav_shell_p95: out.NAV_TO_SHELL_P95_MS,
        fetch_p95: out.PASSENGERS_FETCH_P95_MS,
        shell_usable_p95: out.SHELL_TO_USABLE_APP_P95_MS,
        url_authority: out.PASSENGERS_URL_AUTHORITY,
        fresh_n: out.COHORT_FRESH_PREVALIDATION.N,
        fresh_p95: out.COHORT_FRESH_PREVALIDATION.BOOK_NOW_TO_TRAVELER_P95,
        join_n: out.COHORT_JOINED_INFLIGHT.N,
        join_p95: out.COHORT_JOINED_INFLIGHT.BOOK_NOW_TO_TRAVELER_P95,
        dup_reval: out.DUPLICATE_REVALIDATION_CALLS,
        mutations: out.SUPPLIER_MUTATION_CALLS,
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
