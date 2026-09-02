/**
 * Clean Fare→Traveler N10 with commit-level navigation timing + API marks.
 */
import { chromium } from "../../../../frontend/node_modules/playwright/index.mjs";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
function pct(arr, p) {
  if (!arr.length) return null;
  const a = [...arr].sort((x, y) => x - y);
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  const samples = [];

  for (let i = 0; i < 10; i++) {
    const s = { i, valid: false };
    try {
      await page.goto(
        "https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=2026-09-20&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&_=" +
          Date.now(),
        { waitUntil: "domcontentloaded", timeout: 120000 },
      );
      await page.waitForSelector('[data-testid="flight-result-card"]', { timeout: 100000 });
      await page.waitForTimeout(250);

      const tBook = Date.now();
      await page.locator('[data-testid="book-now-trigger"]').first().click({ timeout: 15000 });
      const cont = page.locator('[data-testid="continue-to-passengers"]');
      await cont.first().waitFor({ state: "visible", timeout: 35000 });
      const tCont = Date.now();
      await cont.first().click({ timeout: 10000 });
      s.fare_action_ms = Date.now() - tCont;

      const tNav = Date.now();
      await page.waitForURL(/\/booking\/passengers/, { waitUntil: "commit", timeout: 120000 });
      s.book_now_to_route_shell_ms = Date.now() - tNav;
      s.fare_to_traveler_total_ms = Date.now() - tBook;

      await page.waitForTimeout(900);
      const marks = await page.evaluate(() => {
        const res = performance.getEntriesByType("resource");
        const apis = res.filter((r) =>
          /fare|validate|price|offer|booking\/|passengers|hold/i.test(r.name),
        );
        const supplierish = apis.filter((r) =>
          /validate|fare|price|offer|laravel/i.test(r.name),
        );
        return {
          api_sum: Math.round(apis.reduce((a, r) => a + r.duration, 0)),
          supplier_sum: Math.round(supplierish.reduce((a, r) => a + r.duration, 0)),
          names: supplierish.slice(0, 8).map((r) => ({
            n: r.name.replace(/https?:\/\/[^/]+/, ""),
            d: Math.round(r.duration),
          })),
          ready:
            !!document.querySelector('[data-testid="save-and-continue"]') ||
            !!document.querySelector("input") ||
            /Passenger|Traveler|Contact/i.test(document.body.innerText || ""),
          full_skel:
            /Loading travelers|passenger-skeleton/i.test(document.body.innerText || "") &&
            !document.querySelector("input"),
        };
      });
      s.fare_validate_supplier_ms = marks.supplier_sum || null;
      s.frontend_overhead_ms =
        s.fare_to_traveler_total_ms != null && marks.supplier_sum
          ? Math.max(0, s.fare_to_traveler_total_ms - marks.supplier_sum)
          : s.book_now_to_route_shell_ms;
      s.traveler_ready = !!marks.ready && !marks.full_skel;
      s.valid = !!s.traveler_ready;
      s.secondary_reset = 0;
      s.api_names = marks.names;
      await page.waitForTimeout(1200);
      const after = await page.evaluate(
        () =>
          /Loading travelers|passenger-skeleton/i.test(document.body.innerText || "") &&
          !document.querySelector("input") &&
          !document.querySelector('[data-testid="save-and-continue"]'),
      );
      s.secondary_reset = s.valid && after ? 1 : 0;
    } catch (e) {
      s.error = String(e.message || e).slice(0, 220);
    }
    samples.push(s);
    console.log("fare", i, s.valid, s.fare_to_traveler_total_ms, s.book_now_to_route_shell_ms, s.error || "");
  }

  const v = samples.filter((x) => x.valid);
  const out = {
    FARE_TO_TRAVELER_VALID_SAMPLES: v.length,
    BOOK_NOW_TO_ROUTE_SHELL_P50_MS: pct(v.map((x) => x.book_now_to_route_shell_ms).filter(Boolean), 50),
    BOOK_NOW_TO_ROUTE_SHELL_P95_MS: pct(v.map((x) => x.book_now_to_route_shell_ms).filter(Boolean), 95),
    FARE_VALIDATE_SUPPLIER_P50_MS: pct(v.map((x) => x.fare_validate_supplier_ms).filter((n) => n != null), 50),
    FARE_VALIDATE_SUPPLIER_P95_MS: pct(v.map((x) => x.fare_validate_supplier_ms).filter((n) => n != null), 95),
    FARE_VALIDATE_FRONTEND_OVERHEAD_P50_MS: pct(v.map((x) => x.fare_action_ms).filter(Boolean), 50),
    FARE_VALIDATE_FRONTEND_OVERHEAD_P95_MS: pct(v.map((x) => x.fare_action_ms).filter(Boolean), 95),
    FARE_TO_TRAVELER_TOTAL_P50_MS: pct(v.map((x) => x.fare_to_traveler_total_ms).filter(Boolean), 50),
    FARE_TO_TRAVELER_TOTAL_P95_MS: pct(v.map((x) => x.fare_to_traveler_total_ms).filter(Boolean), 95),
    FARE_TO_TRAVELER_FRONTEND_OVERHEAD_P50_MS: pct(v.map((x) => x.frontend_overhead_ms).filter(Boolean), 50),
    FARE_TO_TRAVELER_FRONTEND_OVERHEAD_P95_MS: pct(v.map((x) => x.frontend_overhead_ms).filter(Boolean), 95),
    TRAVELER_READY_TO_FULL_SKELETON_REGRESSION: v.filter((x) => x.secondary_reset).length,
    SECONDARY_REQUEST_RESETS_PRIMARY_LOADING: v.filter((x) => x.secondary_reset).length,
    method: "waitUntil=commit; supplier_ms=sum browser resource durations matching fare/validate/price/offer",
    samples,
  };
  fs.writeFileSync(path.join(__dirname, "fare-traveler-n10.json"), JSON.stringify(out, null, 2));
  await browser.close();
  console.log("FARE_CLEAN_DONE", v.length, out.FARE_TO_TRAVELER_TOTAL_P50_MS, out.FARE_TO_TRAVELER_TOTAL_P95_MS);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
