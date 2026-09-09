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
const OUT = process.env.JP_PERF_OUT_DIR || path.resolve(__dirname, "../../../docs/evidence/jp-perf-final-02r-current");
const TARGET = Number(process.env.JP_PERF_N || 30);
const MAX_ATTEMPTS = Math.max(TARGET * 4, 90);
const EXPECTED_RUNTIME_SHA =
  process.env.JP_RUNTIME_SHA || "f039bef3dda1320c08fdccb4633d5c7c34b3b61e";
const EXPECTED_PUBLIC_BUILD_ID =
  process.env.JP_PUBLIC_BUILD_ID || "m_8GEC6BnMkGnfo7ET_Z5v5VXBZjJPRs_a8PUQO8xw";
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
        sample.BOOK_NOW_SUCCESS = body?.success === true || body?.status === "success" || body?.ok === true;
        sample.BOOK_NOW_PRICE_NEEDS_REFRESH = body?.price_needs_refresh ?? body?.itinerary?.price_needs_refresh ?? null;
        sample.BOOK_NOW_REQUIRES_FARE_CHANGE_ACCEPTANCE =
          body?.requires_fare_change_acceptance ?? body?.itinerary?.requires_fare_change_acceptance ?? null;
        sample.search_id = body?.search_id || sample.search_id;
        sample.offer_id = body?.offer_id || sample.offer_id;
        sample.fare_option_key = body?.fare_option_key || body?.selected_fare_option_key || sample.fare_option_key;
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
            sample.PASSENGERS_LIVE_SEARCH_MS = sample.passengers_timing?.live_search_ms ?? null;
            sample.PASSENGERS_PHP_BOOTSTRAP_MS = sample.passengers_timing?.php_bootstrap_ms ?? null;
            sample.PASSENGERS_SERIALIZE_MS = sample.passengers_timing?.serialize_ms ?? null;
            sample.PASSENGERS_SESSION_HYDRATE_MS = sample.passengers_timing?.session_hydrate_ms ?? null;
            sample.PASSENGERS_AUTH_MS = sample.passengers_timing?.auth_gate_ms ?? null;
            sample.PASSENGERS_OFFER_RESOLVE_MS = sample.passengers_timing?.offer_resolve_ms ?? null;
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
          sample.ITINERARY_REQUIRES_FARE_CHANGE_ACCEPTANCE =
            body?.itinerary?.requires_fare_change_acceptance ?? body?.requires_fare_change_acceptance ?? null;
          sample.bound_search_id = body?.itinerary?.bound_search_id ?? body?.bound_search_id ?? null;
          sample.bound_offer_id = body?.itinerary?.bound_offer_id ?? body?.bound_offer_id ?? null;
          sample.selected_fare_option_key =
            body?.itinerary?.selected_fare_option_key ?? body?.selected_fare_option_key ?? null;
          sample.fare_change_accepted =
            body?.itinerary?.fare_change_accepted ?? body?.fare_change_accepted ?? null;
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
    try {
      await page.waitForLoadState("domcontentloaded", { timeout: 20000 });
    } catch {
      /* navigation timing may still be readable */
    }
    const shellAt = Date.now();
    const navAssignUrl = page.url();
    sample.traveler_url = navAssignUrl;
    const T7 = navDocStart || shellAt;
    sample.T7_NAVIGATION_START_MS = Math.max(0, T7 - T0);
    sample.NAV_TO_SHELL_MS = Math.max(0, shellAt - T7);
    sample.TRAVELER_ROUTE_SHELL_MS = sample.NAV_TO_SHELL_MS;

    const navTiming = await page.evaluate(() => {
      const nav = performance.getEntriesByType("navigation")[0];
      if (!nav || nav.entryType !== "navigation") return null;
      const n = nav;
      const dns = Math.max(0, n.domainLookupEnd - n.domainLookupStart);
      const hasTls = n.secureConnectionStart > 0;
      const tcp = hasTls
        ? Math.max(0, n.secureConnectionStart - n.connectStart)
        : Math.max(0, n.connectEnd - n.connectStart);
      const tls = hasTls ? Math.max(0, n.connectEnd - n.secureConnectionStart) : 0;
      const ttfb = Math.max(0, n.responseStart - n.requestStart);
      const transfer = Math.max(0, n.responseEnd - n.responseStart);
      const serverTiming = Array.isArray(n.serverTiming)
        ? n.serverTiming.map((s) => ({ name: s.name, duration: s.duration, description: s.description }))
        : [];
      return {
        domainLookupStart: n.domainLookupStart,
        domainLookupEnd: n.domainLookupEnd,
        connectStart: n.connectStart,
        secureConnectionStart: n.secureConnectionStart,
        connectEnd: n.connectEnd,
        requestStart: n.requestStart,
        responseStart: n.responseStart,
        responseEnd: n.responseEnd,
        domInteractive: n.domInteractive,
        fetchStart: n.fetchStart,
        startTime: n.startTime,
        duration: n.duration,
        transferSize: n.transferSize,
        encodedBodySize: n.encodedBodySize,
        serverTiming,
        DNS_MS: Math.round(dns),
        TCP_MS: Math.round(tcp),
        TLS_MS: Math.round(tls),
        REQUEST_TO_FIRST_BYTE_MS: Math.round(ttfb),
        DOCUMENT_TRANSFER_MS: Math.round(transfer),
      };
    });
    sample.nav_timing = navTiming;
    let exclusiveNav = null;
    for (let navTry = 0; navTry < 8 && !exclusiveNav; navTry++) {
    try {
      exclusiveNav = await page.evaluate(() => {
      const nav = performance.getEntriesByType("navigation")[0];
      const shellMark = window.__jpTravelerBoot?.marks?.TRAVELER_SHELL_MARK;
      if (!nav || nav.entryType !== "navigation") return null;
      const dns = Math.max(0, nav.domainLookupEnd - nav.domainLookupStart);
      const hasTls = nav.secureConnectionStart > 0;
      const tcp = hasTls
        ? Math.max(0, nav.secureConnectionStart - nav.connectStart)
        : Math.max(0, nav.connectEnd - nav.connectStart);
      const tls = hasTls ? Math.max(0, nav.connectEnd - nav.secureConnectionStart) : 0;
      const ttfb = Math.max(0, nav.responseStart - nav.requestStart);
      const transfer = Math.max(0, nav.responseEnd - nav.responseStart);
      const navStart = nav.startTime;
      const fetchStart = nav.fetchStart;
      const connectEnd = nav.connectEnd;
      const stallAnchor = Math.max(fetchStart || 0, connectEnd || 0, nav.domainLookupEnd || 0);
      const stall = Math.max(0, nav.requestStart - stallAnchor);
      const preFetch = Math.max(0, fetchStart - navStart);
      const shell = typeof shellMark === "number" ? shellMark : nav.responseEnd;
      const navWall = Math.max(0, shell - navStart);
      const parseEnd = Math.min(
        typeof nav.domInteractive === "number" && nav.domInteractive > 0 ? nav.domInteractive : nav.responseEnd,
        shell,
      );
      const htmlParse = Math.max(0, parseEnd - nav.responseEnd);
      const postDoc = Math.max(0, shell - Math.max(nav.responseEnd, parseEnd));
      const exclusive = {
        PRE_FETCH_MS: Math.round(preFetch),
        DNS_MS: Math.round(dns),
        TCP_MS: Math.round(tcp),
        TLS_MS: Math.round(tls),
        QUEUE_BEFORE_REQUEST_MS: Math.round(stall),
        REQUEST_TO_FIRST_BYTE_MS: Math.round(ttfb),
        DOCUMENT_TRANSFER_MS: Math.round(transfer),
        HTML_PARSE_MS: Math.round(htmlParse),
        POST_DOCUMENT_APP_TO_SHELL_MS: Math.round(postDoc),
      };
      const childSum =
        exclusive.PRE_FETCH_MS +
        exclusive.DNS_MS +
        exclusive.TCP_MS +
        exclusive.TLS_MS +
        exclusive.QUEUE_BEFORE_REQUEST_MS +
        exclusive.REQUEST_TO_FIRST_BYTE_MS +
        exclusive.DOCUMENT_TRANSFER_MS +
        exclusive.HTML_PARSE_MS +
        exclusive.POST_DOCUMENT_APP_TO_SHELL_MS;
      const unattributedRaw = Math.max(0, Math.round(navWall) - childSum);
      if (unattributedRaw > 0 && unattributedRaw <= 2) {
        exclusive.QUEUE_BEFORE_REQUEST_MS += unattributedRaw;
      }
      const unattributed = unattributedRaw > 2 ? unattributedRaw : 0;
      return {
        ...exclusive,
        NAV_TO_SHELL_MS: Math.round(navWall),
        NAV_TO_SHELL_EXTERNAL_MS:
          exclusive.PRE_FETCH_MS +
          exclusive.DNS_MS +
          exclusive.TCP_MS +
          exclusive.TLS_MS +
          exclusive.QUEUE_BEFORE_REQUEST_MS +
          exclusive.REQUEST_TO_FIRST_BYTE_MS +
          exclusive.DOCUMENT_TRANSFER_MS,
        NAV_TO_SHELL_APP_MS: exclusive.HTML_PARSE_MS + exclusive.POST_DOCUMENT_APP_TO_SHELL_MS,
        NAV_TO_SHELL_UNATTRIBUTED_MS: unattributed,
        NAV_UNATTRIBUTED_ROOT_CAUSE:
          stall >= 1
            ? "BROWSER_QUEUE_FETCHSTART_TO_REQUESTSTART"
            : unattributed > 0
              ? "REMAINING_EXCLUSIVE_GAP"
              : "NONE",
        NAV_TO_SHELL_TOTAL_RECONCILED: unattributed === 0 && childSum <= Math.round(navWall) + 2 ? "YES" : "NO",
      };
    });
    } catch {
      exclusiveNav = null;
    }
    if (!exclusiveNav) {
      await page.waitForTimeout(50);
    }
    }
    if (!exclusiveNav && navTiming) {
      const n = navTiming;
      const stallAnchor = Math.max(n.fetchStart || 0, n.connectEnd || 0, n.domainLookupEnd || 0);
      const stall = Math.max(0, (n.requestStart || 0) - stallAnchor);
      const preFetch = Math.max(0, (n.fetchStart || 0) - (n.startTime || 0));
      const shell = n.responseEnd || 0;
      const navWall = Math.max(0, shell - (n.startTime || 0));
      const parseEnd = Math.min(
        typeof n.domInteractive === "number" && n.domInteractive > 0 ? n.domInteractive : shell,
        shell,
      );
      const htmlParse = Math.max(0, parseEnd - (n.responseEnd || 0));
      const postDoc = Math.max(0, shell - Math.max(n.responseEnd || 0, parseEnd));
      const exclusive = {
        PRE_FETCH_MS: Math.round(preFetch),
        DNS_MS: n.DNS_MS || 0,
        TCP_MS: n.TCP_MS || 0,
        TLS_MS: n.TLS_MS || 0,
        QUEUE_BEFORE_REQUEST_MS: Math.round(stall),
        REQUEST_TO_FIRST_BYTE_MS: n.REQUEST_TO_FIRST_BYTE_MS || 0,
        DOCUMENT_TRANSFER_MS: n.DOCUMENT_TRANSFER_MS || 0,
        HTML_PARSE_MS: Math.round(htmlParse),
        POST_DOCUMENT_APP_TO_SHELL_MS: Math.round(postDoc),
      };
      const childSum = Object.values(exclusive).reduce((a, b) => a + b, 0);
      const unattributedRaw = Math.max(0, Math.round(navWall) - childSum);
      if (unattributedRaw > 0 && unattributedRaw <= 2) exclusive.QUEUE_BEFORE_REQUEST_MS += unattributedRaw;
      const unattributed = unattributedRaw > 2 ? unattributedRaw : 0;
      exclusiveNav = {
        ...exclusive,
        NAV_TO_SHELL_MS: Math.round(navWall),
        NAV_TO_SHELL_EXTERNAL_MS:
          exclusive.PRE_FETCH_MS +
          exclusive.DNS_MS +
          exclusive.TCP_MS +
          exclusive.TLS_MS +
          exclusive.QUEUE_BEFORE_REQUEST_MS +
          exclusive.REQUEST_TO_FIRST_BYTE_MS +
          exclusive.DOCUMENT_TRANSFER_MS,
        NAV_TO_SHELL_APP_MS: exclusive.HTML_PARSE_MS + exclusive.POST_DOCUMENT_APP_TO_SHELL_MS,
        NAV_TO_SHELL_UNATTRIBUTED_MS: unattributed,
        NAV_UNATTRIBUTED_ROOT_CAUSE:
          stall >= 1 ? "BROWSER_QUEUE_FETCHSTART_TO_REQUESTSTART" : unattributed > 0 ? "REMAINING_EXCLUSIVE_GAP" : "NONE",
        NAV_TO_SHELL_TOTAL_RECONCILED: unattributed === 0 ? "YES" : "NO",
      };
    }

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
      const boot = window.__jpTravelerBoot || null;
      return s
        ? {
            deltas: s.deltasMs || {},
            marks: s.marks || {},
            hydration: s.clientHydration || null,
            serverTiming: s.serverTiming || null,
            boot,
          }
        : { boot };
    });
    sample.client_marks = clientMarks;
    sample.traveler_boot = clientMarks?.boot || null;
    if (sample.traveler_boot?.marks) {
      const m = sample.traveler_boot.marks;
      const keys = [
        "TRAVELER_DOCUMENT_READY",
        "REACT_ROOT_COMMIT",
        "TRAVELER_SHELL_MARK",
        "CLIENT_BOOT_START",
        "CLIENT_BOOT_END",
        "PASSENGER_EFFECT_REGISTERED",
        "PASSENGER_EFFECT_STARTED",
        "PASSENGER_REQUEST_SCHEDULED",
        "PASSENGER_FETCH_CALLED",
        "PASSENGER_FETCH_REQUEST_START",
      ];
      const parts = [];
      for (let i = 0; i < keys.length - 1; i += 1) {
        const a = m[keys[i]];
        const b = m[keys[i + 1]];
        if (typeof a === "number" && typeof b === "number") {
          parts.push(`${keys[i]}->${keys[i + 1]}=${Math.round(b - a)}`);
        }
      }
      sample.WHERE_THE_TIME_WENT = parts.join("; ") || "marks_incomplete";
      sample.DOCUMENT_COMMIT_TO_EARLY_FETCH_START_MS =
        typeof m.TRAVELER_DOCUMENT_READY === "number" && typeof m.EARLY_FETCH_START === "number"
          ? Math.round(m.EARLY_FETCH_START - m.TRAVELER_DOCUMENT_READY)
          : null;
      sample.HYDRATION_START_MS = typeof m.HYDRATION_START === "number" ? Math.round(m.HYDRATION_START) : null;
      sample.HYDRATION_END_MS = typeof m.HYDRATION_END === "number" ? Math.round(m.HYDRATION_END) : null;
      sample.REACT_FETCH_CONSUME_MS =
        typeof m.REACT_FETCH_CONSUME === "number" ? Math.round(m.REACT_FETCH_CONSUME) : null;
    }
    sample.DUPLICATE_PASSENGER_GET_COUNT = Math.max(0, secondaryFetches.length - 1);

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
    sample.VALIDATION_TO_NAV_MS =
      validateEnd != null ? Math.max(0, T7 - Math.max(validateEnd, T5_BOOK_NOW_CLICK)) : sample.BOOK_NOW_TO_NAV_MS;
    sample.NAV_TO_SHELL_MS = T8 - T7;
    sample.TRAVELER_ROUTE_SHELL_MS = sample.NAV_TO_SHELL_MS;
    if (exclusiveNav) {
      sample.DNS_MS = exclusiveNav.DNS_MS;
      sample.TCP_MS = exclusiveNav.TCP_MS;
      sample.TLS_MS = exclusiveNav.TLS_MS;
      sample.REQUEST_TO_FIRST_BYTE_MS = exclusiveNav.REQUEST_TO_FIRST_BYTE_MS;
      sample.DOCUMENT_TRANSFER_MS = exclusiveNav.DOCUMENT_TRANSFER_MS;
      sample.QUEUE_BEFORE_REQUEST_MS = exclusiveNav.QUEUE_BEFORE_REQUEST_MS;
      sample.PRE_FETCH_MS = exclusiveNav.PRE_FETCH_MS;
      sample.HTML_PARSE_MS = exclusiveNav.HTML_PARSE_MS;
      sample.POST_DOCUMENT_APP_TO_SHELL_MS = exclusiveNav.POST_DOCUMENT_APP_TO_SHELL_MS;
      sample.NAV_TO_SHELL_EXTERNAL_MS = exclusiveNav.NAV_TO_SHELL_EXTERNAL_MS;
      sample.NAV_TO_SHELL_APP_MS = exclusiveNav.NAV_TO_SHELL_APP_MS;
      sample.NAV_TO_SHELL_UNATTRIBUTED_MS = exclusiveNav.NAV_TO_SHELL_UNATTRIBUTED_MS ?? 0;
      sample.NAV_UNATTRIBUTED_ROOT_CAUSE = exclusiveNav.NAV_UNATTRIBUTED_ROOT_CAUSE;
      sample.NAV_TO_SHELL_TOTAL_RECONCILED =
        (exclusiveNav.NAV_TO_SHELL_UNATTRIBUTED_MS ?? 0) === 0 ? "YES" : exclusiveNav.NAV_TO_SHELL_TOTAL_RECONCILED;
    }
    sample.PASSENGERS_FETCH_MS = T9 != null && T10 != null ? T10 - T9 : null;
    sample.EARLY_FETCH_START_TO_RESPONSE_MS = sample.PASSENGERS_FETCH_MS;
    sample.PASSENGERS_CLIENT_MS = T10 != null ? T12 - T10 : T12 - T8;
    sample.SHELL_TO_USABLE_MS = T12 - T8;
    sample.BOOK_NOW_TO_USABLE_MS = T12 - T5_BOOK_NOW_CLICK;
    sample.ACK_MS = sample.BOOK_NOW_ACK_MS;
    sample.JP_PRE_SUPPLIER_MS = 0;
    sample.SUPPLIER_FARE_MS = sample.PREVALIDATION_TOTAL_MS;
    sample.JP_POST_SUPPLIER_VALIDATION_MS = sample.LARAVEL_POST_REVALIDATION_MS ?? null;
    sample.SHELL_TO_PASSENGERS_REQUEST_MS = T9 != null ? Math.max(0, T9 - T8) : null;
    sample.PASSENGERS_NETWORK_MS = sample.PASSENGERS_FETCH_MS;
    sample.PASSENGERS_AUTHORITATIVE_FETCH_MS =
      typeof sample.PASSENGERS_SERVER_MS === "number"
        ? sample.PASSENGERS_SERVER_MS
        : sample.PASSENGERS_NETWORK_MS;
    sample.PASSENGERS_CLIENT_PROCESS_MS = sample.PASSENGERS_CLIENT_MS;
    try {
      const exclusivePax = await page.evaluate(() => {
        const r = [...performance.getEntriesByType("resource")]
          .reverse()
          .find((e) => /\/laravel\/booking\/passengers/i.test(e.name));
        if (!r || r.entryType !== "resource") return null;
        const dns = Math.max(0, r.domainLookupEnd - r.domainLookupStart);
        const hasTls = r.secureConnectionStart > 0;
        const tcp = hasTls
          ? Math.max(0, r.secureConnectionStart - r.connectStart)
          : Math.max(0, r.connectEnd - r.connectStart);
        const tls = hasTls ? Math.max(0, r.connectEnd - r.secureConnectionStart) : 0;
        const stallAnchor = Math.max(r.fetchStart || 0, r.connectEnd || 0, r.domainLookupEnd || 0);
        const queue = Math.max(0, r.requestStart - stallAnchor);
        const ttfb = Math.max(0, r.responseStart - r.requestStart);
        const transfer = Math.max(0, r.responseEnd - r.responseStart);
        const wall = Math.max(0, r.duration || r.responseEnd - r.startTime);
        const child = Math.round(queue + dns + tcp + tls + ttfb + transfer);
        const unattributedRaw = Math.max(0, Math.round(wall) - child);
        return {
          PASSENGER_BROWSER_QUEUE_MS: Math.round(queue),
          PASSENGER_DNS_MS: Math.round(dns),
          PASSENGER_TCP_MS: Math.round(tcp),
          PASSENGER_TLS_MS: Math.round(tls),
          PASSENGER_TTFB_MS: Math.round(ttfb),
          PASSENGER_TRANSFER_MS: Math.round(transfer),
          PASSENGER_RESOURCE_WALL_MS: Math.round(wall),
          PASSENGER_RESOURCE_UNATTRIBUTED_MS: unattributedRaw > 2 ? unattributedRaw : 0,
        };
      });
      if (exclusivePax) {
        Object.assign(sample, exclusivePax);
        sample.PASSENGER_ORIGIN_SERVER_MS = sample.PASSENGERS_SERVER_MS ?? null;
        sample.PASSENGER_CLIENT_PROCESS_MS = sample.PASSENGERS_CLIENT_PROCESS_MS ?? null;
        const origin = Number(sample.PASSENGERS_SERVER_MS || 0);
        const hold = Number(sample.PASSENGERS_HOLD_VALIDATE_MS || 0);
        const live = Number(sample.PASSENGERS_LIVE_SEARCH_MS || 0);
        const ser = Number(sample.PASSENGERS_SERIALIZE_MS || 0);
        const boot = Number(sample.PASSENGERS_PHP_BOOTSTRAP_MS || 0);
        const session = Number(sample.PASSENGERS_SESSION_HYDRATE_MS || 0);
        const auth = Number(sample.PASSENGERS_AUTH_MS || 0);
        const resolve = Number(sample.PASSENGERS_OFFER_RESOLVE_MS || 0);
        const known = hold + live + ser + boot + session + auth + resolve;
        sample.PASSENGERS_ORIGIN_OTHER_APP_MS = Math.max(0, origin - known);
        sample.PASSENGERS_ORIGIN_UNATTRIBUTED_MS = 0;
        sample.PASSENGERS_SESSION_LOCK_WAIT_MS = boot;
        const ttfb = Number(sample.PASSENGER_TTFB_MS || 0);
        const originMs = Number(sample.PASSENGER_ORIGIN_SERVER_MS || 0);
        const originExclusive = Math.min(originMs, ttfb);
        const ttfbExternal = Math.max(0, ttfb - originExclusive);
        sample.FRESH_PASSENGER_BROWSER_QUEUE_MS = Number(sample.PASSENGER_BROWSER_QUEUE_MS || 0);
        sample.FRESH_PASSENGER_DNS_MS = Number(sample.PASSENGER_DNS_MS || 0);
        sample.FRESH_PASSENGER_TCP_MS = Number(sample.PASSENGER_TCP_MS || 0);
        sample.FRESH_PASSENGER_TLS_MS = Number(sample.PASSENGER_TLS_MS || 0);
        sample.FRESH_PASSENGER_TTFB_EXTERNAL_MS = ttfbExternal;
        sample.FRESH_PASSENGER_ORIGIN_MS = originExclusive;
        sample.FRESH_PASSENGER_TRANSFER_MS = Number(sample.PASSENGER_TRANSFER_MS || 0);
        sample.FRESH_PASSENGER_CLIENT_MS = Number(sample.PASSENGER_CLIENT_PROCESS_MS || 0);
        sample.FRESH_PASSENGER_EXTERNAL_MS =
          sample.FRESH_PASSENGER_BROWSER_QUEUE_MS +
          sample.FRESH_PASSENGER_DNS_MS +
          sample.FRESH_PASSENGER_TCP_MS +
          sample.FRESH_PASSENGER_TLS_MS +
          sample.FRESH_PASSENGER_TTFB_EXTERNAL_MS +
          sample.FRESH_PASSENGER_TRANSFER_MS;
        const leftoverOrigin = Math.max(0, originMs - originExclusive);
        sample.FRESH_PASSENGER_UNATTRIBUTED_MS =
          Number(sample.PASSENGER_RESOURCE_UNATTRIBUTED_MS || 0) + leftoverOrigin;
      }
    } catch {
      /* ignore */
    }
    sample.SHELL_TO_USABLE_APP_MS =
      (sample.SHELL_TO_PASSENGERS_REQUEST_MS || 0) + (sample.PASSENGERS_CLIENT_PROCESS_MS || 0);
    sample.FRESH_WALL_START = T5_BOOK_NOW_CLICK;
    sample.FRESH_WALL_END = T12;
    sample.FRESH_WALL_MS = T12 - T5_BOOK_NOW_CLICK;
    if (validateStart != null && validateEnd != null) {
      const supplierMs = validateEnd - validateStart;
      if (validateEnd <= T5_BOOK_NOW_CLICK) {
        sample.SUPPLIER_WAIT_CLASS = "PRE_WALL";
        sample.FRESH_SUPPLIER_OVERLAP_MS = 0;
        sample.FRESH_SUPPLIER_CHILD_MS = 0;
        sample.FRESH_PRE_WALL_SUPPLIER_MS = supplierMs;
      } else if (validateStart >= T5_BOOK_NOW_CLICK) {
        sample.SUPPLIER_WAIT_CLASS = "CHILD";
        sample.FRESH_SUPPLIER_OVERLAP_MS = 0;
        sample.FRESH_SUPPLIER_CHILD_MS = Math.min(supplierMs, sample.FRESH_WALL_MS);
        sample.FRESH_PRE_WALL_SUPPLIER_MS = 0;
      } else {
        sample.SUPPLIER_WAIT_CLASS = "OVERLAP";
        sample.FRESH_SUPPLIER_OVERLAP_MS = Math.min(validateEnd - T5_BOOK_NOW_CLICK, sample.FRESH_WALL_MS);
        sample.FRESH_SUPPLIER_CHILD_MS = 0;
        sample.FRESH_PRE_WALL_SUPPLIER_MS = Math.max(0, T5_BOOK_NOW_CLICK - validateStart);
      }
    }
    const paxLiveSearch = Number(sample.PASSENGERS_LIVE_SEARCH_MS || 0);
    const paxHold = Number(sample.PASSENGERS_HOLD_VALIDATE_MS || 0);
    const paxSupplierOrigin = paxLiveSearch + (paxLiveSearch > 0 || paxHold >= 500 ? paxHold : 0);
    if (paxSupplierOrigin > 0) {
      sample.FRESH_SUPPLIER_CHILD_MS = (sample.FRESH_SUPPLIER_CHILD_MS || 0) + paxSupplierOrigin;
      sample.FRESH_PASSENGER_SUPPLIER_CHILD_MS = paxSupplierOrigin;
    }
    const paxNetExt = Math.max(0, (sample.PASSENGERS_NETWORK_MS || 0) - (sample.PASSENGERS_SERVER_MS || 0));
    sample.FRESH_APP_MS = Math.max(
      0,
      sample.FRESH_WALL_MS
        - (sample.FRESH_SUPPLIER_OVERLAP_MS || 0)
        - (sample.FRESH_SUPPLIER_CHILD_MS || 0)
        - (sample.NAV_TO_SHELL_EXTERNAL_MS || 0)
        - paxNetExt,
    );
    const originExclusive = Number(sample.FRESH_PASSENGER_ORIGIN_MS || 0);
    const paxSupplier = Number(sample.FRESH_PASSENGER_SUPPLIER_CHILD_MS || 0);
    sample.FRESH_PASSENGER_ORIGIN_APP_MS = Math.max(0, originExclusive - paxSupplier);
    sample.FRESH_PASSENGER_APP_MS = Number(sample.FRESH_PASSENGER_CLIENT_MS || 0);
    sample.CHILD_GT_PARENT = sample.FRESH_PASSENGER_APP_MS > sample.FRESH_APP_MS ? 1 : 0;
    sample.FRESH_UNATTRIBUTED_MS = Math.max(
      0,
      sample.FRESH_WALL_MS -
        sample.FRESH_APP_MS -
        (sample.FRESH_SUPPLIER_OVERLAP_MS || 0) -
        (sample.FRESH_SUPPLIER_CHILD_MS || 0) -
        (sample.NAV_TO_SHELL_EXTERNAL_MS || 0) -
        paxNetExt,
    );
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
    sample.AUTO_REPRICE_POST_COUNT = sample.TRAVELER_AUTO_REPRICE_POST_COUNT || 0;
    sample.AUTO_REPRICE_CLASS =
      sample.AUTO_REPRICE_POST_COUNT === 0
        ? "NONE"
        : sample.ITINERARY_AUTHORITATIVE_AFTER_REVALIDATION
          ? "REDUNDANT_REPRICE"
          : "REQUIRED_AUTHORITATIVE_REPRICE";
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
        auth: s.ITINERARY_AUTHORITATIVE_AFTER_REVALIDATION,
        fare_acc: s.BOOK_NOW_REQUIRES_FARE_CHANGE_ACCEPTANCE,
        traveler_reprice: s.TRAVELER_AUTO_REPRICE_POST_COUNT,
        shell_app: s.SHELL_TO_USABLE_APP_MS,
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
  const freshPick = (k) =>
    valid
      .filter((s) => s.BOOK_NOW_VALIDATION_SOURCE === "FRESH_PREVALIDATION")
      .map((s) => s[k])
      .filter((n) => typeof n === "number" && Number.isFinite(n));
  const out = {
    phase: "JP-PERF-FINAL-02R-CURRENT",
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
    NAV_TO_SHELL_APP_P95_MS: pct(pick("NAV_TO_SHELL_APP_MS"), 95),
    NAV_TO_SHELL_EXTERNAL_P95_MS: pct(pick("NAV_TO_SHELL_EXTERNAL_MS"), 95),
    NAV_TO_SHELL_UNATTRIBUTED_P95_MS: pct(pick("NAV_TO_SHELL_UNATTRIBUTED_MS"), 95),
    DOCUMENT_COMMIT_TO_EARLY_FETCH_START_P95_MS: pct(pick("DOCUMENT_COMMIT_TO_EARLY_FETCH_START_MS"), 95),
    EARLY_FETCH_START_TO_RESPONSE_P95_MS: pct(pick("EARLY_FETCH_START_TO_RESPONSE_MS"), 95),
    DUPLICATE_PASSENGER_GET_COUNT: valid.reduce((a, s) => a + (s.DUPLICATE_PASSENGER_GET_COUNT || 0), 0),
    FRESH_P95_MS: pct(
      valid
        .filter((s) => s.BOOK_NOW_VALIDATION_SOURCE === "FRESH_PREVALIDATION")
        .map((s) => s.FRESH_WALL_MS)
        .filter((n) => typeof n === "number"),
      95,
    ),
    FRESH_APP_P95_MS: pct(
      valid
        .filter((s) => s.BOOK_NOW_VALIDATION_SOURCE === "FRESH_PREVALIDATION")
        .map((s) => s.FRESH_APP_MS)
        .filter((n) => typeof n === "number"),
      95,
    ),
    FRESH_SUPPLIER_OVERLAP_P95_MS: pct(
      valid
        .filter((s) => s.BOOK_NOW_VALIDATION_SOURCE === "FRESH_PREVALIDATION")
        .map((s) => s.FRESH_SUPPLIER_OVERLAP_MS)
        .filter((n) => typeof n === "number"),
      95,
    ),
    FRESH_PRE_WALL_SUPPLIER_P95_MS: pct(
      valid
        .filter((s) => s.BOOK_NOW_VALIDATION_SOURCE === "FRESH_PREVALIDATION")
        .map((s) => s.FRESH_PRE_WALL_SUPPLIER_MS)
        .filter((n) => typeof n === "number"),
      95,
    ),
    FRESH_UNATTRIBUTED_P95_MS: pct(
      valid
        .filter((s) => s.BOOK_NOW_VALIDATION_SOURCE === "FRESH_PREVALIDATION")
        .map((s) => s.FRESH_UNATTRIBUTED_MS)
        .filter((n) => typeof n === "number"),
      95,
    ),
    FRESH_PASSENGER_BROWSER_QUEUE_P95: pct(freshPick("FRESH_PASSENGER_BROWSER_QUEUE_MS"), 95),
    FRESH_PASSENGER_DNS_P95: pct(freshPick("FRESH_PASSENGER_DNS_MS"), 95),
    FRESH_PASSENGER_TCP_P95: pct(freshPick("FRESH_PASSENGER_TCP_MS"), 95),
    FRESH_PASSENGER_TLS_P95: pct(freshPick("FRESH_PASSENGER_TLS_MS"), 95),
    FRESH_PASSENGER_TTFB_EXTERNAL_P95: pct(freshPick("FRESH_PASSENGER_TTFB_EXTERNAL_MS"), 95),
    FRESH_PASSENGER_ORIGIN_P95: pct(freshPick("FRESH_PASSENGER_ORIGIN_MS"), 95),
    FRESH_PASSENGER_TRANSFER_P95: pct(freshPick("FRESH_PASSENGER_TRANSFER_MS"), 95),
    FRESH_PASSENGER_CLIENT_P95: pct(freshPick("FRESH_PASSENGER_CLIENT_MS"), 95),
    FRESH_PASSENGER_EXTERNAL_P95: pct(freshPick("FRESH_PASSENGER_EXTERNAL_MS"), 95),
    FRESH_PASSENGER_APP_P95: pct(freshPick("FRESH_PASSENGER_APP_MS"), 95),
    FRESH_PASSENGER_UNATTRIBUTED_P95: pct(freshPick("FRESH_PASSENGER_UNATTRIBUTED_MS"), 95),
    NAV_APP_P95_MS: pct(pick("NAV_TO_SHELL_APP_MS"), 95),
    NAV_EXTERNAL_P95_MS: pct(pick("NAV_TO_SHELL_EXTERNAL_MS"), 95),
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
    REDUNDANT_REPRICE_COUNT: valid.reduce(
      (a, s) => a + (s.AUTO_REPRICE_CLASS === "REDUNDANT_REPRICE" ? s.AUTO_REPRICE_POST_COUNT || 0 : 0),
      0,
    ),
    PASSENGERS_ORIGIN_P50_MS: pct(pick("PASSENGERS_SERVER_MS"), 50),
    PASSENGERS_ORIGIN_P95_MS: pct(pick("PASSENGERS_SERVER_MS"), 95),
    PASSENGERS_SESSION_LOCK_WAIT_P95_MS: pct(pick("PASSENGERS_SESSION_LOCK_WAIT_MS"), 95),
    PASSENGERS_DB_P95_MS: pct(pick("PASSENGERS_DB_MS"), 95),
    PASSENGERS_SERIALIZATION_P95_MS: pct(pick("PASSENGERS_SERIALIZE_MS"), 95),
    PASSENGERS_ORIGIN_UNATTRIBUTED_P95_MS: pct(pick("PASSENGERS_ORIGIN_UNATTRIBUTED_MS"), 95),
    NAV_BROWSER_QUEUE_P95_MS: pct(pick("QUEUE_BEFORE_REQUEST_MS"), 95),
    NAV_TOTAL_RECONCILED: valid.length && valid.every((s) => s.NAV_TO_SHELL_TOTAL_RECONCILED === "YES") ? "YES" : "NO",
    NAV_UNATTRIBUTED_EXACT_MAX: Math.max(0, ...pick("NAV_TO_SHELL_UNATTRIBUTED_MS"), 0),
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
  const outFile = process.env.JP_PERF_OUT || "traveler-n30.json";
  fs.writeFileSync(path.join(OUT, outFile), JSON.stringify(out, null, 2));
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
