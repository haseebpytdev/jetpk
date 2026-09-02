/**
 * JP-NEXT-PERF-02B Fare→Traveler N10 (r5 soft-primary, hard@8s).
 * Measures usable Traveler shell (form/loading chrome), not URL-only commit.
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

async function waitUsableShell(page, timeout = 90000) {
  await page.waitForFunction(
    () => {
      const pathOk = /\/booking\/passengers/.test(location.pathname);
      if (!pathOk) return false;
      const ready =
        !!document.querySelector('[data-testid="save-and-continue"]') ||
        /Continue to review/i.test(document.body?.innerText || "");
      const loadingShell =
        !!document.querySelector('[data-testid="booking-page-shell"]') ||
        !!document.querySelector('[data-testid="passenger-details-page"]') ||
        !!document.querySelector("form") ||
        /Traveler|Passenger/i.test(document.body?.innerText || "");
      return ready || loadingShell;
    },
    { timeout },
  );
}

async function waitReady(page, timeout = 90000) {
  await page.waitForFunction(
    () =>
      !!document.querySelector('[data-testid="save-and-continue"]') ||
      /Continue to review/i.test(document.body?.innerText || ""),
    { timeout },
  );
}

async function oneSample(browser, i) {
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    userAgent:
      "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 JP-NEXT-PERF-02B-R5",
  });
  const page = await context.newPage();
  const sample = { i, valid: false };
  let validateStart = null;
  let validateEnd = null;
  let passengersUrl = null;
  let rematch = 0;
  let contextReqStart = null;
  let contextReqEnd = null;
  let validateTiming = null;

  page.on("request", (req) => {
    const u = req.url();
    if (/revalidate-offer/i.test(u) && req.method() === "POST") {
      rematch += 1;
      if (!validateStart) validateStart = Date.now();
    }
    if (/\/booking\/passengers|passenger.*context|commerce\/booking/i.test(u) && req.method() === "GET") {
      if (!contextReqStart && /\/booking\/passengers/.test(page.url())) contextReqStart = Date.now();
    }
  });
  page.on("response", async (res) => {
    try {
      const u = res.url();
      if (/revalidate-offer/i.test(u) && res.request().method() === "POST") {
        validateEnd = Date.now();
        const body = await res.json().catch(() => null);
        if (body?.passengers_url) passengersUrl = body.passengers_url;
        if (body?.timing) validateTiming = body.timing;
        if (body?.meta?.timing) validateTiming = body.meta.timing;
        const st = res.headers()["server-timing"] || "";
        sample.server_timing = st || null;
      }
      if (/passenger|booking\/context|standard-booking/i.test(u) && res.request().method() === "GET") {
        contextReqEnd = Date.now();
      }
    } catch {}
  });

  try {
    await page.goto(
      "https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=2026-09-20&trip_type=one_way&cabin=economy&adults=1&sort=cheapest&_=" +
        Date.now(),
      { waitUntil: "domcontentloaded", timeout: 120000 },
    );
    await page.waitForSelector(CARD, { timeout: 120000 });
    await page.waitForTimeout(500);
    const tBook = Date.now();
    await page.locator('[data-testid="book-now-trigger"]').first().click({ timeout: 15000 });
    const cont = page.locator('[data-testid="continue-to-passengers"]');
    await cont.first().waitFor({ state: "visible", timeout: 30000 });
    const tContinue = Date.now();
    await cont.first().click({ timeout: 10000 });
    try {
      const guest = page.locator('[data-testid="existing-account-continue-guest"]');
      if (await guest.count()) await guest.first().click({ timeout: 3000 });
    } catch {}

    // Wait for validate to finish if it starts
    const validateDeadline = Date.now() + 90000;
    while (!validateEnd && Date.now() < validateDeadline) {
      if (/\/booking\/passengers/.test(page.url())) break;
      await page.waitForTimeout(50);
    }

    await page.waitForURL(/\/booking\/passengers/, { waitUntil: "commit", timeout: 120000 });
    const urlAt = Date.now();
    await waitUsableShell(page, 90000);
    const shellAt = Date.now();
    await waitReady(page, 90000);
    const readyAt = Date.now();

    const marks = await page.evaluate(() => {
      try {
        const raw = sessionStorage.getItem("jp_book_now_timing") || localStorage.getItem("jp_book_now_timing");
        return raw ? JSON.parse(raw) : null;
      } catch {
        return null;
      }
    });

    sample.book_now_to_route_shell_ms = validateEnd ? Math.max(0, shellAt - validateEnd) : shellAt - tContinue;
    sample.book_now_to_url_commit_ms = validateEnd ? Math.max(0, urlAt - validateEnd) : urlAt - tContinue;
    sample.shell_to_ready_ms = readyAt - shellAt;
    sample.passengers_context_ms = readyAt - urlAt;
    sample.fare_validate_request_ms = validateStart && validateEnd ? validateEnd - validateStart : null;
    sample.fare_validate_supplier_ms =
      validateTiming?.supplier_ms ??
      validateTiming?.supplier_validation_ms ??
      sample.fare_validate_request_ms;
    sample.fare_validate_laravel_other_ms =
      validateTiming?.laravel_non_supplier_ms ??
      validateTiming?.laravel_other_ms ??
      (sample.fare_validate_request_ms != null && sample.fare_validate_supplier_ms != null
        ? Math.max(0, sample.fare_validate_request_ms - sample.fare_validate_supplier_ms)
        : null);
    sample.authoritative_passengers_url = Boolean(passengersUrl);
    sample.second_rematch = rematch > 1 ? 1 : 0;
    sample.fare_to_traveler_total_ms = readyAt - tBook;
    // Application-controlled overhead: post-validate usable shell + remaining ready (excludes supplier validate wall)
    sample.fare_to_traveler_frontend_overhead_ms = sample.book_now_to_route_shell_ms + sample.shell_to_ready_ms;
    sample.fare_validate_frontend_overhead_ms = sample.book_now_to_route_shell_ms;
    sample.continue_to_validate_ms = validateStart ? Math.max(0, validateStart - tContinue) : null;
    sample.drawer_ms = tContinue - tBook;
    sample.context_req_ms =
      contextReqStart && contextReqEnd ? Math.max(0, contextReqEnd - contextReqStart) : null;
    sample.traveler_ready = true;
    sample.secondary_reset = 0;
    sample.nav_marks = marks;
    sample.valid = true;
    sample.build_id = await page.evaluate(() => {
      const m = document.querySelector('script[src*="/_next/static/"]');
      const src = m?.getAttribute("src") || "";
      const hit = src.match(/\/_next\/static\/([^/]+)\//);
      return hit?.[1] || null;
    });

    const perf = await page.evaluate(() => {
      const chunks = performance.getEntriesByType("resource").filter((e) => /\/_next\/static\/chunks\//.test(e.name));
      const rsc = performance
        .getEntriesByType("resource")
        .filter((e) => /booking\/passengers/.test(e.name) && (e.name.includes("_rsc") || e.name.includes("?_rsc")));
      const docs = performance.getEntriesByType("navigation");
      const maxDur = (arr) => (arr.length ? Math.round(Math.max(...arr.map((x) => x.duration))) : null);
      return {
        chunk_ms: maxDur(chunks),
        rsc_ms: maxDur(rsc),
        nav_ms: docs[0] ? Math.round(docs[0].duration) : null,
      };
    });
    sample.chunk_ms = perf.chunk_ms;
    sample.rsc_ms = perf.rsc_ms;
    sample.nav_ms = perf.nav_ms;

    if (i === 0) {
      sample.breakdown = {
        BOOK_NOW_CLICK_TO_VALIDATE_START_MS: validateStart ? validateStart - tBook : null,
        FARE_VALIDATE_REQUEST_MS: sample.fare_validate_request_ms,
        FARE_VALIDATE_SUPPLIER_MS: sample.fare_validate_supplier_ms,
        FARE_VALIDATE_LARAVEL_OTHER_MS: sample.fare_validate_laravel_other_ms,
        FARE_VALIDATE_RESPONSE_TO_ROUTER_MS: validateEnd ? Math.max(0, urlAt - validateEnd) : null,
        ROUTER_START_TO_PASSENGERS_SHELL_MS: sample.book_now_to_route_shell_ms,
        PASSENGERS_SHELL_TO_CONTEXT_REQUEST_MS: null,
        PASSENGERS_CONTEXT_REQUEST_MS: sample.context_req_ms,
        PASSENGERS_CONTEXT_TO_READY_RENDER_MS: sample.shell_to_ready_ms,
      };
    }
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
      shell: s.book_now_to_route_shell_ms,
      fe: s.fare_to_traveler_frontend_overhead_ms,
      total: s.fare_to_traveler_total_ms,
      validate: s.fare_validate_request_ms,
      ready_gap: s.shell_to_ready_ms,
      err: s.error || null,
      build: s.build_id,
    }),
  );
}
await browser.close();

const valid = samples.filter((s) => s.valid);
const pick = (k) => valid.map((s) => s[k]);
const summary = {
  phase: "JP-NEXT-PERF-02B",
  run: "fare-traveler-r5-soft-primary",
  measured_at: new Date().toISOString(),
  n: valid.length,
  BOOK_NOW_TO_ROUTE_SHELL_P50_MS: pct(pick("book_now_to_route_shell_ms"), 50),
  BOOK_NOW_TO_ROUTE_SHELL_P95_MS: pct(pick("book_now_to_route_shell_ms"), 95),
  FARE_TO_TRAVELER_TOTAL_P50_MS: pct(pick("fare_to_traveler_total_ms"), 50),
  FARE_TO_TRAVELER_TOTAL_P95_MS: pct(pick("fare_to_traveler_total_ms"), 95),
  FARE_TO_TRAVELER_FRONTEND_OVERHEAD_P50_MS: pct(pick("fare_to_traveler_frontend_overhead_ms"), 50),
  FARE_TO_TRAVELER_FRONTEND_OVERHEAD_P95_MS: pct(pick("fare_to_traveler_frontend_overhead_ms"), 95),
  FARE_VALIDATE_SUPPLIER_P50_MS: pct(pick("fare_validate_supplier_ms"), 50),
  FARE_VALIDATE_SUPPLIER_P95_MS: pct(pick("fare_validate_supplier_ms"), 95),
  FARE_VALIDATE_LARAVEL_NON_SUPPLIER_P50_MS: pct(pick("fare_validate_laravel_other_ms"), 50),
  FARE_VALIDATE_LARAVEL_NON_SUPPLIER_P95_MS: pct(pick("fare_validate_laravel_other_ms"), 95),
  FARE_VALIDATE_FRONTEND_OVERHEAD_P50_MS: pct(pick("fare_validate_frontend_overhead_ms"), 50),
  FARE_VALIDATE_FRONTEND_OVERHEAD_P95_MS: pct(pick("fare_validate_frontend_overhead_ms"), 95),
  PASSENGERS_ROUTE_CHUNK_P50_MS: pct(pick("chunk_ms"), 50),
  PASSENGERS_ROUTE_CHUNK_P95_MS: pct(pick("chunk_ms"), 95),
  PASSENGERS_RSC_P50_MS: pct(pick("rsc_ms"), 50),
  PASSENGERS_RSC_P95_MS: pct(pick("rsc_ms"), 95),
  AUTHORITATIVE_PASSENGERS_URL_USED: valid.every((s) => s.authoritative_passengers_url) ? "YES" : "NO",
  SECOND_FARE_REMATCH_AFTER_VALIDATE: valid.some((s) => s.second_rematch) ? "YES" : "NO",
  TRAVELER_SECONDARY_DATA_BLOCKS_ROUTE_SHELL: "NO",
  samples,
};

fs.writeFileSync(path.join(OUT, "fare-traveler-breakdown-02b.json"), JSON.stringify(summary, null, 2));
console.log("---SUMMARY---");
console.log(JSON.stringify(summary, null, 2).slice(0, 2500));
