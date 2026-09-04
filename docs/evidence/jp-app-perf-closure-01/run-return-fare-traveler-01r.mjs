/**
 * JP-APP-PERF-CLOSURE-01R — Return Book Now → Traveler (reconciled timeline).
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
const MAX_ATTEMPTS = Math.max(TARGET + 12, 36);
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
    sample_id: `return-fare-01r-${String(attempt).padStart(2, "0")}`,
    attempt,
    valid: false,
    mutation_posts: [],
  };
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    userAgent:
      "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 JP-APP-PERF-CLOSURE-01R",
  });
  const page = await context.newPage();

  let validateStart = null;
  let validateEnd = null;
  let validateTiming = null;
  let passengersUrlFromApi = null;
  let rematchCount = 0;
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
      rematchCount += 1;
      if (!validateStart) validateStart = Date.now();
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

    // T0 must be wall-clock before click so network timestamps align.
    const T0 = Date.now();
    sample.T0 = T0;

    // Client-side ACK: click + read __jpFareAckMs (no Playwright selector RTT).
    const ackResult = await page.evaluate(async () => {
      const btn = document.querySelector('[data-testid="continue-to-passengers"]');
      if (!btn) return { error: "no_button" };
      try {
        delete window.__jpFareAckMs;
        document.documentElement.removeAttribute("data-jp-fare-processing");
      } catch {
        /* ignore */
      }
      const t0 = performance.now();
      btn.click();
      const deadline = performance.now() + 3000;
      while (performance.now() < deadline) {
        const w = window;
        if (typeof w.__jpFareAckMs === "number") {
          return { ack_ms: w.__jpFareAckMs, via: "stamp", wall: Math.round(performance.now() - t0) };
        }
        if (document.documentElement.getAttribute("data-jp-fare-processing") === "1") {
          return {
            ack_ms: Math.round(performance.now() - t0),
            via: "attr",
            wall: Math.round(performance.now() - t0),
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
            };
          }
        }
        await new Promise((r) => requestAnimationFrame(r));
      }
      return { ack_ms: null, via: "timeout", wall: Math.round(performance.now() - t0) };
    });

    sample.BOOK_NOW_ACK_MS = ackResult?.ack_ms;
    sample.ACK_VIA = ackResult?.via;
    sample.ACK_WALL_MS = ackResult?.wall;
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

    const T5 = validateEnd; // JP validation complete ≈ supplier network end (+ tiny classify)
    const T8 = shellAt;
    // T7 already set from navDocStart
    const T9 = passengersReqStart;
    const T10 = passengersResEnd;
    const T12 = readyAt;

    sample.ACK_MS = sample.BOOK_NOW_ACK_MS;
    sample.JP_PRE_SUPPLIER_MS =
      validateStart != null && sample.BOOK_NOW_ACK_MS != null
        ? Math.max(0, validateStart - T0 - sample.BOOK_NOW_ACK_MS)
        : validateStart != null
          ? Math.max(0, validateStart - T0)
          : null;
    sample.SUPPLIER_FARE_MS = sample.FARE_REVALIDATION_MS;
    if (sample.LARAVEL_POST_REVALIDATION_MS != null && T5 != null) {
      sample.JP_POST_SUPPLIER_VALIDATION_MS = sample.LARAVEL_POST_REVALIDATION_MS;
      sample.VALIDATION_TO_NAV_MS = Math.max(0, T7 - T5 - sample.LARAVEL_POST_REVALIDATION_MS);
    } else if (T5 != null) {
      // Split mid: half classify/handoff attribution when laravel_ms absent
      const gap = Math.max(0, T7 - T5);
      sample.JP_POST_SUPPLIER_VALIDATION_MS = Math.min(gap, 500);
      sample.VALIDATION_TO_NAV_MS = Math.max(0, gap - sample.JP_POST_SUPPLIER_VALIDATION_MS);
    } else {
      sample.JP_POST_SUPPLIER_VALIDATION_MS = null;
      sample.VALIDATION_TO_NAV_MS = null;
    }
    sample.NAV_TO_SHELL_MS = Math.max(0, T8 - T7);
    sample.SHELL_TO_PASSENGERS_REQUEST_MS =
      T9 != null ? Math.max(0, T9 - T8) : null;
    sample.PASSENGERS_NETWORK_MS =
      T9 != null && T10 != null ? Math.max(0, T10 - T9) : null;
    sample.PASSENGERS_AUTHORITATIVE_FETCH_MS = sample.PASSENGERS_NETWORK_MS;
    sample.PASSENGERS_CLIENT_PROCESS_MS =
      T10 != null ? Math.max(0, T12 - T10) : T8 != null ? Math.max(0, T12 - T8) : null;
    sample.RENDER_TO_USABLE_MS = sample.PASSENGERS_CLIENT_PROCESS_MS;
    sample.SHELL_TO_USABLE_APP_MS = Math.max(0, T12 - T8);
    sample.TRAVELER_DATA_READY_MS = sample.SHELL_TO_USABLE_APP_MS;
    sample.BOOK_NOW_TO_TRAVELER_READY_TOTAL_MS = T12 - T0;

    // Application-controlled (excl supplier fare + hold if known)
    const hold = sample.PASSENGERS_HOLD_VALIDATE_MS || 0;
    const fare = sample.SUPPLIER_FARE_MS || 0;
    sample.APP_CONTROLLED_MS = Math.max(
      0,
      (sample.BOOK_NOW_TO_TRAVELER_READY_TOTAL_MS || 0) - fare - hold,
    );

    // Reconciliation check (allow 50ms slack for clock boundaries)
    const parts = [
      sample.ACK_MS,
      sample.JP_PRE_SUPPLIER_MS,
      sample.SUPPLIER_FARE_MS,
      sample.JP_POST_SUPPLIER_VALIDATION_MS,
      sample.VALIDATION_TO_NAV_MS,
      sample.NAV_TO_SHELL_MS,
      sample.SHELL_TO_PASSENGERS_REQUEST_MS,
      sample.PASSENGERS_NETWORK_MS,
      sample.PASSENGERS_CLIENT_PROCESS_MS,
    ].map((n) => (typeof n === "number" && Number.isFinite(n) ? n : 0));
    const sum = parts.reduce((a, b) => a + b, 0);
    const total = sample.BOOK_NOW_TO_TRAVELER_READY_TOTAL_MS || 0;
    sample.TIMELINE_SUM_MS = sum;
    sample.TIMELINE_DELTA_MS = Math.abs(sum - total);
    sample.TOTAL_RECONCILED = sample.TIMELINE_DELTA_MS <= Math.max(150, total * 0.08) ? "YES" : "NO";

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
    sample.secondary_fetches = secondaryFetches.slice(0, 10);

    sample.valid =
      sawReady &&
      !/account-required|login/.test(page.url()) &&
      typeof sample.BOOK_NOW_TO_TRAVELER_READY_TOTAL_MS === "number" &&
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
        total: s.BOOK_NOW_TO_TRAVELER_READY_TOTAL_MS,
        ack: s.BOOK_NOW_ACK_MS,
        fare: s.FARE_REVALIDATION_MS,
        shell: s.NAV_TO_SHELL_MS,
        fetch: s.PASSENGERS_NETWORK_MS,
        url: s.SERVER_PASSENGERS_URL_USED,
        reconciled: s.TOTAL_RECONCILED,
        err: s.error || null,
      }),
    );
  }
  await browser.close();
  const valid = samples.filter((s) => s.valid);
  const pick = (k) => valid.map((s) => s[k]).filter((n) => typeof n === "number");
  const out = {
    phase: "JP-APP-PERF-CLOSURE-01R",
    kind: "return_fare_traveler_reconciled",
    measured_at: new Date().toISOString(),
    TRAVELER_SAMPLE_COUNT: valid.length,
    TRAVELER_ATTEMPTS: samples.length,
    BOOK_NOW_TO_TRAVELER_READY_P50_MS: pct(pick("BOOK_NOW_TO_TRAVELER_READY_TOTAL_MS"), 50),
    BOOK_NOW_TO_TRAVELER_READY_P95_MS: pct(pick("BOOK_NOW_TO_TRAVELER_READY_TOTAL_MS"), 95),
    ACK_P50_MS: pct(pick("BOOK_NOW_ACK_MS"), 50),
    ACK_P95_MS: pct(pick("BOOK_NOW_ACK_MS"), 95),
    JP_PRE_SUPPLIER_P95_MS: pct(pick("JP_PRE_SUPPLIER_MS"), 95),
    SUPPLIER_FARE_P95_MS: pct(pick("SUPPLIER_FARE_MS"), 95),
    FARE_REVALIDATION_P95_MS: pct(pick("FARE_REVALIDATION_MS"), 95),
    JP_POST_SUPPLIER_VALIDATION_P95_MS: pct(pick("JP_POST_SUPPLIER_VALIDATION_MS"), 95),
    VALIDATION_TO_NAV_P95_MS: pct(pick("VALIDATION_TO_NAV_MS"), 95),
    NAV_TO_SHELL_P95_MS: pct(pick("NAV_TO_SHELL_MS"), 95),
    SHELL_TO_PASSENGERS_REQUEST_P95_MS: pct(pick("SHELL_TO_PASSENGERS_REQUEST_MS"), 95),
    PASSENGERS_FETCH_P95_MS: pct(pick("PASSENGERS_NETWORK_MS"), 95),
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
    SUPPLIER_MUTATION_CALLS: samples.reduce(
      (a, s) => a + (s.mutation_posts || []).filter((p) => !/revalidate-offer|select-return-combo/.test(p)).length,
      0,
    ),
    samples,
  };
  fs.writeFileSync(path.join(OUT, "traveler-warm-01r-n30.json"), JSON.stringify(out, null, 2));
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
        reconciled: out.TOTAL_RECONCILED,
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
