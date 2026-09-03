/**
 * JP-DEEP-CLOSURE-01 — Return Book Now → Traveler READY.
 * STOP at Traveler. No passenger submit. No booking mutation.
 */
import { chromium } from "playwright";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = __dirname;
const TARGET = Number(process.env.JP_PERF_N || 30);
const MAX_ATTEMPTS = Math.max(TARGET + 10, 24);
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
    sample_id: `return-fare-${String(attempt).padStart(2, "0")}`,
    attempt,
    valid: false,
    mutation_posts: [],
  };
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    userAgent:
      "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 JP-DEEP-CLOSURE-01-FARE",
  });
  const page = await context.newPage();

  let validateStart = null;
  let validateEnd = null;
  let validateTiming = null;
  let passengersUrlFromApi = null;
  let rematchCount = 0;
  let navAssignUrl = null;
  let secondaryFetches = [];
  let skeletonAfterReady = 0;
  let sawReady = false;
  const marks = [];

  page.on("console", (msg) => {
    const t = msg.text();
    if (/jp-book-now|T2_revalidate|T3_revalidate|T5_router|passengers_url|hard_assign/i.test(t)) {
      marks.push({ t: Date.now(), text: t.slice(0, 300) });
    }
  });

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
    if (/\/laravel\/booking\/passengers/i.test(u) && (m === "GET" || m === "POST")) {
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
        const hdr = res.headers()["x-jp-passengers-timing"];
        if (hdr) {
          try {
            sample.passengers_timing = JSON.parse(hdr);
            sample.PASSENGERS_HOLD_VALIDATE_MS = sample.passengers_timing?.hold_validate_ms ?? null;
            sample.PASSENGERS_DB_MS = sample.passengers_timing?.db_total_ms ?? sample.passengers_timing?.db_ms ?? null;
            sample.PASSENGERS_DB_QUERY_COUNT = sample.passengers_timing?.db_query_count ?? null;
            sample.PASSENGERS_DB_SLOWEST_QUERY_MS = sample.passengers_timing?.db_slowest_query_ms ?? null;
            sample.PASSENGERS_DB_DUPLICATE_QUERY_COUNT = sample.passengers_timing?.db_duplicate_query_count ?? null;
            sample.PASSENGERS_APP_INTERNAL_MS = sample.passengers_timing?.app_internal_ms ?? sample.passengers_timing?.total_ms ?? null;
            sample.PASSENGERS_LARAVEL_TOTAL_MS = sample.passengers_timing?.total_ms ?? null;
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

    // Warm home + progressive init (same as Return browser cert) so search_id is
    // available and results shell is not cold-initing without overlap.
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

    // Return paired cards use pair-select; one-way uses book-now-trigger.
    const bookBtn = page
      .locator('[data-testid="pair-select"], [data-testid="book-now-trigger"]')
      .first();
    await bookBtn.waitFor({ state: "visible", timeout: 45000 });

    const T0 = Date.now();
    sample.T0 = T0;
    await bookBtn.click({ timeout: 15000 });
    // ACK = time until processing transition paints (not click() return).
    try {
      await page.waitForSelector('[data-testid="fare-processing-transition"]', {
        state: "visible",
        timeout: 5000,
      });
      sample.BOOK_NOW_ACK_MS = Date.now() - T0;
    } catch {
      sample.BOOK_NOW_ACK_MS = Date.now() - T0;
      sample.ack_missing_transition = true;
    }

    // Fare drawer / continue
    const cont = page.locator('[data-testid="continue-to-passengers"]');
    try {
      await cont.first().waitFor({ state: "visible", timeout: 45000 });
      await cont.first().click({ timeout: 15000 });
    } catch {
      await page
        .locator(
          'button:has-text("Continue to passengers"), button:has-text("Select fare"), button:has-text("Book"), button:has-text("Confirm")',
        )
        .first()
        .click({ timeout: 20000 });
    }

    try {
      const guest = page.locator('[data-testid="existing-account-continue-guest"]');
      if (await guest.count()) await guest.first().click({ timeout: 5000 });
    } catch {
      /* optional */
    }

    const tNavWait = Date.now();
    await page.waitForURL(/\/booking\/(passengers|account-required|login)/, {
      waitUntil: "commit",
      timeout: 150000,
    });
    const shellAt = Date.now();
    sample.T7_nav_begin_approx = tNavWait - T0;
    sample.TRAVELER_ROUTE_SHELL_MS = shellAt - (validateEnd || tNavWait);
    sample.RESPONSE_TO_NAVIGATION_MS =
      validateEnd != null ? Math.max(0, tNavWait - validateEnd) : null;
    navAssignUrl = page.url();
    sample.traveler_url = navAssignUrl;

    if (/account-required|login/.test(navAssignUrl)) {
      sample.note = "account_gate";
      await context.close();
      return sample;
    }

    // Prove passengers_url usage
    if (passengersUrlFromApi) {
      try {
        const serverPath = new URL(passengersUrlFromApi, "https://jetpakistan.pk").pathname +
          new URL(passengersUrlFromApi, "https://jetpakistan.pk").search;
        const actual = new URL(navAssignUrl);
        const actualPath = actual.pathname + actual.search;
        sample.SERVER_PASSENGERS_URL = passengersUrlFromApi;
        sample.SERVER_PASSENGERS_URL_USED =
          actualPath.includes("search_id=") &&
          (actual.href.includes(new URL(passengersUrlFromApi, "https://jetpakistan.pk").searchParams.get("search_id") || "___") ||
            decodeURIComponent(actual.href).includes(
              new URL(passengersUrlFromApi, "https://jetpakistan.pk").searchParams.get("search_id") || "___",
            ));
        // Stronger: compare search_id + offer identity params
        const sParams = new URL(passengersUrlFromApi, "https://jetpakistan.pk").searchParams;
        const aParams = actual.searchParams;
        sample.SEARCH_ID_PRESERVED =
          sParams.get("search_id") && sParams.get("search_id") === aParams.get("search_id") ? "YES" : "NO";
        sample.FARE_AUTHORITY_PRESERVED =
          (sParams.get("offer_id") && sParams.get("offer_id") === aParams.get("offer_id")) ||
          (sParams.get("flight_id") && sParams.get("flight_id") === aParams.get("flight_id")) ||
          (sParams.get("combo_id") && sParams.get("combo_id") === aParams.get("combo_id"))
            ? "YES"
            : "PARTIAL";
        sample.CLIENT_RECONSTRUCTS_TRAVELER_URL =
          sample.SEARCH_ID_PRESERVED === "YES" && !/select-return-combo/i.test(actual.pathname)
            ? "NO"
            : sample.mutation_posts.includes("select-return-combo")
              ? "YES"
              : "UNKNOWN";
      } catch (e) {
        sample.url_compare_error = String(e?.message || e);
      }
    } else {
      sample.SERVER_PASSENGERS_URL_USED = "NO_URL_IN_RESPONSE";
      sample.CLIENT_RECONSTRUCTS_TRAVELER_URL = "UNKNOWN";
    }

    if (validateStart && validateEnd) {
      sample.FARE_REVALIDATION_MS = validateEnd - validateStart;
      sample.T1_validate_start_ms = validateStart - T0;
    }
    // Parse supplier_ms from console marks if API body lacked it
    const t3 = marks.find((m) => /T3_revalidate_response/.test(m.text));
    if (t3) {
      const sm = t3.text.match(/supplier_ms["\s:]+(\d+)/);
      const lm = t3.text.match(/laravel_other_ms["\s:]+(\d+)/);
      if (sm) sample.SUPPLIER_REVALIDATION_MS = Number(sm[1]);
      if (lm) sample.LARAVEL_POST_REVALIDATION_MS = Number(lm[1]);
    }
    if (validateTiming?.supplier_ms != null) {
      sample.SUPPLIER_REVALIDATION_MS = Number(validateTiming.supplier_ms);
    }
    if (validateTiming?.laravel_ms != null) {
      sample.LARAVEL_POST_REVALIDATION_MS = Number(validateTiming.laravel_ms);
    }
    if (
      sample.FARE_REVALIDATION_MS != null &&
      sample.SUPPLIER_REVALIDATION_MS == null
    ) {
      sample.SUPPLIER_REVALIDATION_MS = sample.FARE_REVALIDATION_MS;
      sample.supplier_ms_approx = true;
    }

    // Wait for Traveler READY
    const tReady0 = Date.now();
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
    sample.TRAVELER_DATA_READY_MS = readyAt - shellAt;
    sample.TRAVELER_STABLE_READY_MS = readyAt - T0;
    sample.BOOK_NOW_TO_TRAVELER_READY_TOTAL_MS = readyAt - T0;

    // Watch for full skeleton regression after ready
    await page.waitForTimeout(1200);
    const post = await page.evaluate(() => {
      const t = document.body?.innerText || "";
      const skeleton =
        (/Loading travelers|passenger-skeleton|LoadingÃ¢â‚¬Â¦|Loading passengers/i.test(t) &&
          !/Continue to review|First name|Traveler 1/i.test(t)) ||
        !!document.querySelector('[data-testid="passenger-skeleton"]');
      const hasForm =
        !!document.querySelector('[data-testid="save-and-continue"]') ||
        !!document.querySelector('input[name*="first" i], input');
      return { skeleton, hasForm, text_len: t.length };
    });
    if (post.skeleton && !post.hasForm) {
      skeletonAfterReady = 1;
      sample.TRAVELER_STATE_RESET_REASON = "full_skeleton_after_ready";
    } else if (post.skeleton && post.hasForm) {
      sample.TRAVELER_STATE_RESET_REASON = "partial_skeleton_with_form";
    } else {
      sample.TRAVELER_STATE_RESET_REASON = "none";
    }
    sample.TRAVELER_READY_TO_FULL_SKELETON_REGRESSION = skeletonAfterReady;
    sample.TRAVELER_SECONDARY_FETCH_COUNT = secondaryFetches.length;
    if (secondaryFetches.length >= 1) {
      const first = secondaryFetches[0];
      const last = secondaryFetches[secondaryFetches.length - 1];
      sample.PASSENGERS_AUTHORITATIVE_FETCH_MS = last.at - (validateEnd || first.at);
      sample.TRAVELER_SECONDARY_FETCH_MS = last.at - first.at;
    }
    // Capture X-JP-Passengers-Timing from last laravel passengers response if present on marks
    sample.rematch_count = rematchCount;
    sample.marks = marks.slice(0, 12);
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
        fare: s.FARE_REVALIDATION_MS,
        shell: s.TRAVELER_ROUTE_SHELL_MS,
        skeleton: s.TRAVELER_READY_TO_FULL_SKELETON_REGRESSION,
        passengers_url_used: s.SERVER_PASSENGERS_URL_USED,
        mutations: s.mutation_posts,
        err: s.error || null,
      }),
    );
  }
  await browser.close();
  const valid = samples.filter((s) => s.valid);
  const pick = (k) => valid.map((s) => s[k]).filter((n) => typeof n === "number");
  const urlUsed = valid.map((s) => s.SERVER_PASSENGERS_URL_USED);
  const out = {
    phase: "JP-DEEP-CLOSURE-01",
    kind: "return_fare_traveler",
    measured_at: new Date().toISOString(),
    TRAVELER_SAMPLE_COUNT: valid.length,
    TRAVELER_ATTEMPTS: samples.length,
    BOOK_NOW_TO_TRAVELER_READY_P50_MS: pct(pick("BOOK_NOW_TO_TRAVELER_READY_TOTAL_MS"), 50),
    BOOK_NOW_TO_TRAVELER_READY_P95_MS: pct(pick("BOOK_NOW_TO_TRAVELER_READY_TOTAL_MS"), 95),
    BOOK_NOW_ACK_P95_MS: pct(pick("BOOK_NOW_ACK_MS"), 95),
    FARE_REVALIDATION_P50_MS: pct(pick("FARE_REVALIDATION_MS"), 50),
    FARE_REVALIDATION_P95_MS: pct(pick("FARE_REVALIDATION_MS"), 95),
    SUPPLIER_REVALIDATION_P95_MS: pct(pick("SUPPLIER_REVALIDATION_MS"), 95),
    LARAVEL_POST_REVALIDATION_P95_MS: pct(pick("LARAVEL_POST_REVALIDATION_MS"), 95),
    RESPONSE_TO_NAVIGATION_P95_MS: pct(pick("RESPONSE_TO_NAVIGATION_MS"), 95),
    TRAVELER_ROUTE_SHELL_P50_MS: pct(pick("TRAVELER_ROUTE_SHELL_MS"), 50),
    TRAVELER_ROUTE_SHELL_P95_MS: pct(pick("TRAVELER_ROUTE_SHELL_MS"), 95),
    TRAVELER_DATA_READY_P50_MS: pct(pick("TRAVELER_DATA_READY_MS"), 50),
    TRAVELER_DATA_READY_P95_MS: pct(pick("TRAVELER_DATA_READY_MS"), 95),
    TRAVELER_SECONDARY_FETCH_P95_MS: pct(pick("TRAVELER_SECONDARY_FETCH_MS"), 95),
    PASSENGERS_AUTHORITATIVE_FETCH_P50_MS: pct(pick("PASSENGERS_AUTHORITATIVE_FETCH_MS"), 50),
    PASSENGERS_AUTHORITATIVE_FETCH_P95_MS: pct(pick("PASSENGERS_AUTHORITATIVE_FETCH_MS"), 95),
    PASSENGERS_DB_P50_MS: pct(pick("PASSENGERS_DB_MS"), 50),
    PASSENGERS_DB_P95_MS: pct(pick("PASSENGERS_DB_MS"), 95),
    PASSENGERS_HOLD_VALIDATE_P50_MS: pct(pick("PASSENGERS_HOLD_VALIDATE_MS"), 50),
    PASSENGERS_HOLD_VALIDATE_P95_MS: pct(pick("PASSENGERS_HOLD_VALIDATE_MS"), 95),
    PASSENGERS_APP_INTERNAL_P50_MS: pct(pick("PASSENGERS_APP_INTERNAL_MS"), 50),
    PASSENGERS_APP_INTERNAL_P95_MS: pct(pick("PASSENGERS_APP_INTERNAL_MS"), 95),
    TRAVELER_READY_TO_FULL_SKELETON_REGRESSION: valid.reduce(
      (a, s) => a + (s.TRAVELER_READY_TO_FULL_SKELETON_REGRESSION || 0),
      0,
    ),
    SERVER_PASSENGERS_URL_USED: urlUsed.every((v) => v === true || v === "YES")
      ? "YES"
      : urlUsed.some((v) => v === true || v === "YES")
        ? "PARTIAL"
        : String(urlUsed[0] ?? "UNKNOWN"),
    CLIENT_RECONSTRUCTS_TRAVELER_URL: valid.every((s) => s.CLIENT_RECONSTRUCTS_TRAVELER_URL === "NO")
      ? "NO"
      : valid.some((s) => s.CLIENT_RECONSTRUCTS_TRAVELER_URL === "YES")
        ? "YES"
        : "UNKNOWN",
    SEARCH_ID_PRESERVED: valid.every((s) => s.SEARCH_ID_PRESERVED === "YES")
      ? "YES"
      : valid.some((s) => s.SEARCH_ID_PRESERVED === "YES")
        ? "PARTIAL"
        : "NO",
    FARE_AUTHORITY_PRESERVED: valid.every((s) => s.FARE_AUTHORITY_PRESERVED === "YES")
      ? "YES"
      : "PARTIAL",
    SUPPLIER_MUTATION_CALLS: samples.reduce(
      (a, s) => a + (s.mutation_posts || []).filter((p) => !/revalidate-offer|select-return-combo/.test(p)).length,
      0,
    ),
    samples,
  };
  // Fix SERVER_PASSENGERS_URL_USED boolean mapping
  const usedYes = valid.filter((s) => s.SEARCH_ID_PRESERVED === "YES" && s.SERVER_PASSENGERS_URL).length;
  out.SERVER_PASSENGERS_URL_USED = usedYes === valid.length && valid.length ? "YES" : usedYes ? "PARTIAL" : "NO";
  out.CLIENT_RECONSTRUCTS_TRAVELER_URL = valid.every((s) => s.CLIENT_RECONSTRUCTS_TRAVELER_URL === "NO")
    ? "NO"
    : valid.some((s) => s.CLIENT_RECONSTRUCTS_TRAVELER_URL === "YES")
      ? "YES"
      : "UNKNOWN";
  fs.writeFileSync(path.join(OUT, "return-fare-traveler-n30.json"), JSON.stringify(out, null, 2));
  console.log(
    JSON.stringify(
      {
        valid: valid.length,
        total_p50: out.BOOK_NOW_TO_TRAVELER_READY_P50_MS,
        total_p95: out.BOOK_NOW_TO_TRAVELER_READY_P95_MS,
        fare_p95: out.FARE_REVALIDATION_P95_MS,
        shell_p95: out.TRAVELER_ROUTE_SHELL_P95_MS,
        passengers_url: out.SERVER_PASSENGERS_URL_USED,
        skeleton: out.TRAVELER_READY_TO_FULL_SKELETON_REGRESSION,
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
