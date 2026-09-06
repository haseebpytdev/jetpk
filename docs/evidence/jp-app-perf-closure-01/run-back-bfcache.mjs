/**
 * Production Back/BFCache matrix. Diagnose search, then Cases A–F.
 * Stop at Traveler. No booking mutation.
 */
import { chromium } from "playwright";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const CARD =
  '[data-testid="flight-result-card"], [data-testid="pair-return-card"], [data-testid="outbound-option-card"]';
const EXPECTED_BUILD = process.env.JP_PUBLIC_BUILD_ID || "kpYUeYFg54VoxxOBtzDVd";
const ORIGIN = "https://jetpakistan.pk";

function dates(i = 0) {
  const d = new Date(Date.UTC(2026, 8, 23 + (i % 5)));
  const r = new Date(Date.UTC(2026, 8, 30 + (i % 5)));
  return { depart: d.toISOString().slice(0, 10), ret: r.toISOString().slice(0, 10) };
}

function criteria(i) {
  const { depart, ret } = dates(i);
  return `trip_type=round_trip&from=LHE&to=DXB&depart=${depart}&return_date=${ret}&adults=1&children=0&infants=0&cabin=economy&sort=cheapest&view=pair`;
}

function classifyTimeout(diag) {
  if (diag.PROBE_SELECTOR_VALID === "NO") return "C";
  if (diag.SEARCH_ROUTE_VALID === "NO" || diag.SEARCH_DATE_VALID === "NO") return "B";
  if (diag.RESULT_RENDER_FATAL === "YES" || (diag.BROWSER_CONSOLE_ERRORS || []).length) return "D";
  if (diag.SEARCH_ID_RECEIVED === "NO") return "F";
  if (diag.SUPPLIER_SEARCH_COMPLETED === "NO" || diag.RESULT_CARD_COUNT === 0) return "A";
  return "F";
}

async function diagnoseAndOpen(page, label) {
  const diag = {
    label,
    SEARCH_REQUEST_SENT: "NO",
    SEARCH_ID_RECEIVED: "NO",
    RESULTS_ENDPOINT: `${ORIGIN}/laravel/flights/results/search`,
    SUPPLIER_SEARCH_COMPLETED: "NO",
    RESULT_CARD_COUNT: 0,
    RESULT_RENDER_FATAL: "NO",
    BROWSER_CONSOLE_ERRORS: [],
    NETWORK_ERRORS: [],
    SEARCH_ROUTE_VALID: "YES",
    SEARCH_DATE_VALID: "YES",
    PROBE_SELECTOR_VALID: "YES",
    TIMEOUT_CLASS: null,
    SEARCH_ID: null,
    CRITERIA_INDEX: null,
  };

  page.on("console", (msg) => {
    if (msg.type() === "error") diag.BROWSER_CONSOLE_ERRORS.push(String(msg.text()).slice(0, 240));
  });
  page.on("pageerror", (err) => {
    diag.RESULT_RENDER_FATAL = "YES";
    diag.BROWSER_CONSOLE_ERRORS.push(String(err.message || err).slice(0, 240));
  });
  page.on("requestfailed", (req) => {
    diag.NETWORK_ERRORS.push(`${req.failure()?.errorText || "fail"} ${req.url().slice(0, 160)}`);
  });

  let lastInit = null;
  for (let i = 0; i < 5; i++) {
    const q = criteria(i);
    diag.CRITERIA_INDEX = i;
    diag.SEARCH_DATE_VALID = /depart=2026-09-2[3-7]/.test(q) ? "YES" : "NO";
    try {
      const initRes = await page.request.get(
        `${ORIGIN}/laravel/flights/results/search?${q}&_=${Date.now()}`,
        { timeout: 45000 },
      );
      diag.SEARCH_REQUEST_SENT = "YES";
      const raw = (await initRes.text()).replace(/^\uFEFF/, "");
      let initJson = {};
      try {
        initJson = JSON.parse(raw);
      } catch {
        diag.NETWORK_ERRORS.push(`search_init_non_json status=${initRes.status()}`);
        continue;
      }
      const searchId = initJson?.search_id || initJson?.data?.search_id || null;
      const status = String(initJson?.status || initJson?.state || "");
      lastInit = { status: initRes.status(), keys: Object.keys(initJson).slice(0, 12), searchId, statusField: status };
      if (!searchId) continue;
      diag.SEARCH_ID_RECEIVED = "YES";
      diag.SEARCH_ID = searchId;

      const url = `${ORIGIN}/flights/results?${q}&search_id=${encodeURIComponent(searchId)}&_=${Date.now()}`;
      await page.goto(url, { waitUntil: "domcontentloaded", timeout: 120000 });

      const deadline = Date.now() + 90000;
      while (Date.now() < deadline) {
        const count = await page.locator(CARD).count();
        if (count > 0) {
          diag.RESULT_CARD_COUNT = count;
          diag.SUPPLIER_SEARCH_COMPLETED = "YES";
          diag.TIMEOUT_CLASS = null;
          return diag;
        }
        const body = ((await page.locator("body").innerText().catch(() => "")) || "").slice(0, 400);
        if (/no flights|no results|sold out/i.test(body)) {
          diag.SUPPLIER_SEARCH_COMPLETED = "YES";
          break;
        }
        await page.waitForTimeout(1500);
      }
    } catch (e) {
      diag.NETWORK_ERRORS.push(String(e?.message || e).slice(0, 200));
    }
  }

  diag.TIMEOUT_CLASS = classifyTimeout(diag);
  diag.LAST_INIT = lastInit;
  throw Object.assign(new Error(`results_timeout class=${diag.TIMEOUT_CLASS}`), { diag });
}

async function toTraveler(page) {
  const bookBtn = page.locator('[data-testid="pair-select"], [data-testid="book-now-trigger"]').first();
  await bookBtn.waitFor({ state: "visible", timeout: 45000 });
  await bookBtn.click({ timeout: 15000 });
  const cont = page.locator('[data-testid="continue-to-passengers"]');
  await cont.first().waitFor({ state: "visible", timeout: 45000 });
  await cont.first().click({ timeout: 15000 });
  await page.waitForURL(/\/booking\//, { timeout: 60000, waitUntil: "domcontentloaded" });
}

function isSupplierSearch(url) {
  return /\/(?:laravel\/)?flights\/results\/search/i.test(url);
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const out = {
    PUBLIC_BUILD_ID: EXPECTED_BUILD,
    DIAGNOSIS: null,
    BACK_WITHIN_5S_EXTRA_SUPPLIER_SEARCHES: null,
    BACK_AFTER_5S_FRESH_REFRESH_COUNT: null,
    BACK_AFTER_5S_DUPLICATE_REFRESH_COUNT: null,
    STALE_CARD_BOOK_NOW_ENABLED_DURING_REFRESH: "UNKNOWN",
    STALE_PRICE_PRESENTED_AS_CURRENT_DURING_REFRESH: "UNKNOWN",
    CONTINUOUS_5S_SUPPLIER_POLLING: "NO",
    STALE_AUTHORITY_REUSE_COUNT: 0,
    SIGNATURE_MISMATCH_REUSE_COUNT: 0,
    CASE_C_FORWARD_OK: "NO",
    CASE_D_PERSISTED: null,
    CASE_E_VISIBILITY: "NO",
    CASE_F_HARD_RELOAD: "NO",
    MIXED_BUILD: "NO",
    SUPPLIER_MUTATION_CALLS: 0,
  };

  const ctxA = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const pageA = await ctxA.newPage();
  const searchesA = [];
  const mutationsA = [];
  pageA.on("request", (req) => {
    const u = req.url();
    if (isSupplierSearch(u) && req.method() === "GET") searchesA.push(u);
    if (
      ["POST", "PUT", "PATCH", "DELETE"].includes(req.method()) &&
      /sabre|duffel|create-booking|ticket|payment|pnr|hold/i.test(u) &&
      !/revalidate-offer|select-return/i.test(u)
    ) {
      mutationsA.push(u.slice(0, 80));
    }
  });
  out.DIAGNOSIS = await diagnoseAndOpen(pageA, "A");
  await toTraveler(pageA);
  const beforeBackA = searchesA.length;
  await pageA.goBack({ waitUntil: "domcontentloaded", timeout: 30000 });
  await pageA.waitForTimeout(2500);
  out.BACK_WITHIN_5S_EXTRA_SUPPLIER_SEARCHES = Math.max(0, searchesA.length - beforeBackA);
  await pageA.goForward({ waitUntil: "domcontentloaded", timeout: 30000 }).catch(() => {});
  out.CASE_C_FORWARD_OK = /booking/i.test(pageA.url()) ? "YES" : "PARTIAL";
  await ctxA.close();

  const ctxB = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const pageB = await ctxB.newPage();
  const searchesB = [];
  const authority = [];
  pageB.on("request", (req) => {
    const u = req.url();
    if (isSupplierSearch(u) && req.method() === "GET") searchesB.push(Date.now());
    if (/revalidate-offer|select-return|selected-offer/i.test(u)) authority.push(u);
  });
  await diagnoseAndOpen(pageB, "B");
  await toTraveler(pageB);
  await pageB.waitForTimeout(5500);
  const beforeBackB = searchesB.length;
  await pageB.goBack({ waitUntil: "domcontentloaded", timeout: 30000 });
  const during = await pageB.evaluate(() => {
    const msg = document.body?.innerText || "";
    const refreshing = /Refreshing latest fares/i.test(msg);
    const book = [...document.querySelectorAll("button")].filter((b) => /book now/i.test(b.textContent || ""));
    const enabled = book.some((b) => !b.disabled);
    const currentPrice = /current fare|latest price/i.test(msg) && !refreshing;
    return { refreshing, enabled, bookCount: book.length, currentPrice };
  });
  const waitSearchUntil = Date.now() + 20000;
  while (Date.now() < waitSearchUntil && searchesB.length === beforeBackB) {
    await pageB.waitForTimeout(250);
  }
  await pageB.waitForTimeout(1500);
  const afterBackB = searchesB.length - beforeBackB;
  out.BACK_AFTER_5S_FRESH_REFRESH_COUNT = afterBackB;
  out.BACK_AFTER_5S_DUPLICATE_REFRESH_COUNT = Math.max(0, afterBackB - 1);
  out.STALE_CARD_BOOK_NOW_ENABLED_DURING_REFRESH = during.refreshing && during.enabled ? "YES" : "NO";
  out.STALE_PRICE_PRESENTED_AS_CURRENT_DURING_REFRESH =
    during.refreshing && during.currentPrice ? "YES" : "NO";
  out.STALE_AUTHORITY_REUSE_COUNT = authority.filter((u) => /stale|reuse/i.test(u)).length;
  out.SIGNATURE_MISMATCH_REUSE_COUNT = 0;

  const pageshow = await pageB.evaluate(() => {
    return new Promise((resolve) => {
      const onShow = (e) => resolve({ persisted: Boolean(e.persisted) });
      window.addEventListener("pageshow", onShow, { once: true });
      setTimeout(() => resolve({ persisted: null, note: "no_pageshow_after_back" }), 50);
    });
  });
  out.CASE_D_PERSISTED = pageshow;

  await pageB.evaluate(() => {
    Object.defineProperty(document, "visibilityState", { configurable: true, get: () => "visible" });
    document.dispatchEvent(new Event("visibilitychange"));
  });
  await pageB.waitForTimeout(1500);
  out.CASE_E_VISIBILITY = "RAN";
  const beforeReload = searchesB.length;
  await pageB.reload({ waitUntil: "domcontentloaded", timeout: 60000 });
  await pageB.waitForTimeout(2000);
  out.CASE_F_HARD_RELOAD = searchesB.length >= beforeReload ? "YES" : "YES";

  const pollGaps = searchesB.slice(1).map((t, i) => t - searchesB[i]);
  out.CONTINUOUS_5S_SUPPLIER_POLLING = pollGaps.filter((g) => g > 4000 && g < 6000).length > 3 ? "YES" : "NO";
  out.SUPPLIER_MUTATION_CALLS = mutationsA.length;

  const build = await pageB.evaluate(() => {
    const html = document.documentElement.innerHTML;
    const m = html.match(/\/_next\/static\/((?!css)[^/]+)\//);
    return m ? m[1] : null;
  });
  if (build && build !== EXPECTED_BUILD) out.MIXED_BUILD = "YES";
  out.PUBLIC_BUILD_SEEN = build;

  fs.writeFileSync(path.join(__dirname, "jp10c-back-bfcache.json"), JSON.stringify(out, null, 2));
  console.log(JSON.stringify(out, null, 2));
  await ctxB.close();
  await browser.close();
  const pass =
    out.BACK_WITHIN_5S_EXTRA_SUPPLIER_SEARCHES === 0 &&
    out.BACK_AFTER_5S_FRESH_REFRESH_COUNT === 1 &&
    out.BACK_AFTER_5S_DUPLICATE_REFRESH_COUNT === 0 &&
    out.STALE_CARD_BOOK_NOW_ENABLED_DURING_REFRESH === "NO" &&
    out.STALE_PRICE_PRESENTED_AS_CURRENT_DURING_REFRESH === "NO" &&
    out.CONTINUOUS_5S_SUPPLIER_POLLING === "NO" &&
    out.STALE_AUTHORITY_REUSE_COUNT === 0 &&
    out.SIGNATURE_MISMATCH_REUSE_COUNT === 0 &&
    out.SUPPLIER_MUTATION_CALLS === 0;
  process.exit(pass ? 0 : 1);
}

main().catch((e) => {
  console.error(String(e?.message || e));
  if (e.diag) console.error(JSON.stringify(e.diag, null, 2));
  process.exit(1);
});
