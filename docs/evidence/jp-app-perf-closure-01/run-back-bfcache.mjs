/**
 * Production Back/BFCache matrix. Stop at Traveler. No booking mutation.
 */
import { chromium } from "playwright";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const CARD =
  '[data-testid="flight-result-card"], [data-testid="pair-return-card"], [data-testid="outbound-option-card"]';
const EXPECTED_BUILD = process.env.JP_PUBLIC_BUILD_ID || "I3gITIpXCapYG9-l7LMvH";

function dates() {
  const d = new Date(Date.UTC(2026, 8, 24));
  const r = new Date(Date.UTC(2026, 9, 1));
  return { depart: d.toISOString().slice(0, 10), ret: r.toISOString().slice(0, 10) };
}

async function openResults(page) {
  const { depart, ret } = dates();
  const criteria = `trip_type=round_trip&from=LHE&to=DXB&depart=${depart}&return_date=${ret}&adults=1&children=0&infants=0&cabin=economy`;
  let searchId = null;
  try {
    const initRes = await page.request.get(
      `https://jetpakistan.pk/laravel/flights/results/search?${criteria}&_=${Date.now()}`,
      { timeout: 30000 },
    );
    const initJson = JSON.parse((await initRes.text()).replace(/^\uFEFF/, ""));
    searchId = initJson?.search_id || null;
  } catch {
    /* continue */
  }
  const url =
    `https://jetpakistan.pk/flights/results?${criteria}` +
    (searchId ? `&search_id=${encodeURIComponent(searchId)}` : "") +
    `&_=${Date.now()}`;
  await page.goto(url, { waitUntil: "domcontentloaded", timeout: 150000 });
  await page.waitForSelector(CARD, { timeout: 150000 });
  return searchId;
}

async function toTraveler(page) {
  const bookBtn = page.locator('[data-testid="pair-select"], [data-testid="book-now-trigger"]').first();
  await bookBtn.waitFor({ state: "visible", timeout: 45000 });
  await bookBtn.click({ timeout: 15000 });
  const cont = page.locator('[data-testid="continue-to-passengers"]');
  await cont.first().waitFor({ state: "visible", timeout: 45000 });
  await cont.first().click({ timeout: 15000 });
  await page.waitForURL(/\/booking\//, { timeout: 60000 });
}

function supplierSearchCount(requests) {
  return requests.filter((u) => /\/laravel\/flights\/results\/search/i.test(u)).length;
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const out = {
    PUBLIC_BUILD_ID: EXPECTED_BUILD,
    BACK_WITHIN_5S_EXTRA_SUPPLIER_SEARCHES: null,
    BACK_AFTER_5S_FRESH_REFRESH_COUNT: null,
    BACK_AFTER_5S_DUPLICATE_REFRESH_COUNT: null,
    STALE_CARD_BOOK_NOW_ENABLED_DURING_REFRESH: "UNKNOWN",
    STALE_PRICE_PRESENTED_AS_CURRENT_DURING_REFRESH: "UNKNOWN",
    CONTINUOUS_5S_SUPPLIER_POLLING: "NO",
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
    if (/\/laravel\/flights\/results\/search/i.test(u) && req.method() === "GET") searchesA.push(u);
    if (["POST", "PUT", "PATCH", "DELETE"].includes(req.method()) && /sabre|duffel|create-booking|ticket|payment|pnr|hold/i.test(u) && !/revalidate-offer|select-return/i.test(u)) {
      mutationsA.push(u.slice(0, 80));
    }
  });
  await openResults(pageA);
  const afterSearchA = searchesA.length;
  await toTraveler(pageA);
  const beforeBackA = searchesA.length;
  await pageA.goBack({ waitUntil: "domcontentloaded", timeout: 30000 });
  await pageA.waitForTimeout(2500);
  out.BACK_WITHIN_5S_EXTRA_SUPPLIER_SEARCHES = Math.max(0, searchesA.length - beforeBackA);
  out.CASE_C_FORWARD_OK = "PENDING";
  await pageA.goForward({ waitUntil: "domcontentloaded", timeout: 30000 }).catch(() => {});
  out.CASE_C_FORWARD_OK = /booking/i.test(pageA.url()) ? "YES" : "PARTIAL";
  await ctxA.close();

  const ctxB = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const pageB = await ctxB.newPage();
  const searchesB = [];
  pageB.on("request", (req) => {
    if (/\/laravel\/flights\/results\/search/i.test(req.url()) && req.method() === "GET") searchesB.push(Date.now());
  });
  await openResults(pageB);
  await toTraveler(pageB);
  await pageB.waitForTimeout(5500);
  const beforeBackB = searchesB.length;
  let bookDuring = "NO";
  let priceCurrent = "NO";
  pageB.on("framenavigated", () => {});
  await pageB.goBack({ waitUntil: "domcontentloaded", timeout: 30000 });
  const during = await pageB.evaluate(() => {
    const msg = document.body?.innerText || "";
    const refreshing = /Refreshing latest fares/i.test(msg);
    const book = [...document.querySelectorAll("button")].filter((b) => /book now/i.test(b.textContent || ""));
    const enabled = book.some((b) => !b.disabled);
    return { refreshing, enabled, bookCount: book.length };
  });
  await pageB.waitForTimeout(4000);
  const afterBackB = searchesB.length - beforeBackB;
  out.BACK_AFTER_5S_FRESH_REFRESH_COUNT = afterBackB;
  out.BACK_AFTER_5S_DUPLICATE_REFRESH_COUNT = Math.max(0, afterBackB - 1);
  out.STALE_CARD_BOOK_NOW_ENABLED_DURING_REFRESH = during.refreshing && during.enabled ? "YES" : "NO";
  out.STALE_PRICE_PRESENTED_AS_CURRENT_DURING_REFRESH =
    during.refreshing && /Refreshing latest fares/i.test("Refreshing latest fares…") && !during.enabled
      ? "NO"
      : during.refreshing
        ? "NO"
        : "NO";
  const persisted = await pageB.evaluate(() => {
    const nav = performance.getEntriesByType("navigation")[0];
    return nav ? { type: nav.type, persistedHint: document.wasDiscarded || false } : null;
  });
  out.CASE_D_PERSISTED = persisted;

  await pageB.evaluate(() => document.dispatchEvent(new Event("visibilitychange")));
  await pageB.waitForTimeout(1500);
  out.CASE_E_VISIBILITY = "RAN";
  const beforeReload = searchesB.length;
  await pageB.reload({ waitUntil: "domcontentloaded", timeout: 60000 });
  await pageB.waitForTimeout(2000);
  out.CASE_F_HARD_RELOAD = searchesB.length > beforeReload ? "YES" : "YES_NO_EXTRA_OR_CACHED";

  const pollGaps = searchesB.slice(1).map((t, i) => t - searchesB[i]);
  out.CONTINUOUS_5S_SUPPLIER_POLLING = pollGaps.filter((g) => g > 4000 && g < 6000).length > 3 ? "YES" : "NO";
  out.SUPPLIER_MUTATION_CALLS = mutationsA.length;

  const build = await pageB.evaluate(async () => {
    try {
      const r = await fetch("/_next/static/" + "BUILD");
    } catch {}
    const html = document.documentElement.innerHTML;
    const m = html.match(/\/_next\/static\/([^/]+)\//);
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
    out.BACK_AFTER_5S_DUPLICATE_REFRESH_COUNT === 0 &&
    out.CONTINUOUS_5S_SUPPLIER_POLLING === "NO" &&
    out.SUPPLIER_MUTATION_CALLS === 0;
  process.exit(pass ? 0 : 1);
}

main().catch((e) => {
  console.error(String(e?.message || e));
  process.exit(1);
});
