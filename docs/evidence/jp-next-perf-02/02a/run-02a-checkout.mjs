/**
 * JP-NEXT-PERF-02A — Fare→Traveler + Review + Payment shell only.
 * Read-only commercially: stop before payment execution / final booking.
 */
import { chromium } from "../../../../frontend/node_modules/playwright/index.mjs";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = __dirname;
const CARD =
  '[data-testid="flight-result-card"], [data-testid="pair-return-card"], [data-testid="outbound-option-card"]';

function pct(arr, p) {
  if (!arr.length) return null;
  const a = [...arr].sort((x, y) => x - y);
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

async function waitCards(page, timeout = 100000) {
  await page.waitForSelector(CARD, { timeout });
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    userAgent:
      "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 JP-NEXT-PERF-02A",
  });
  const page = await context.newPage();

  const fareSamples = [];
  for (let i = 0; i < 10; i++) {
    const sample = { i, valid: false };
    try {
      await page.goto(
        "https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=2026-09-20&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&_=" +
          Date.now(),
        { waitUntil: "domcontentloaded", timeout: 120000 },
      );
      await waitCards(page, 100000);
      await page.waitForTimeout(400);

      const tBook = Date.now();
      await page.locator('[data-testid="book-now-trigger"]').first().click({ timeout: 15000 });
      sample.book_click_ms = Date.now() - tBook;

      // Fare/details: wait for continue-to-passengers or branded fare CTA
      const tFare = Date.now();
      const cont = page.locator('[data-testid="continue-to-passengers"]');
      try {
        await cont.first().waitFor({ state: "visible", timeout: 25000 });
        await cont.first().click({ timeout: 10000 });
      } catch {
        // branded fare cards may use Select / Book now buttons inside details
        const alt = page
          .locator(
            'button:has-text("Continue to passengers"), button:has-text("Select fare"), button:has-text("Confirm"), button:has-text("Book now")',
          )
          .filter({ hasNot: page.locator('[href]') });
        await alt.first().click({ timeout: 15000 });
      }
      sample.fare_action_ms = Date.now() - tFare;

      // May hit account-required gate — try guest continue if present
      try {
        const guest = page.locator('[data-testid="existing-account-continue-guest"]');
        if (await guest.count()) {
          await guest.first().click({ timeout: 5000 });
        }
      } catch {}

      const tNav = Date.now();
      await page.waitForURL(/\/booking\/(passengers|account-required|login)/, { timeout: 120000 });
      sample.book_now_to_route_shell_ms = Date.now() - tNav;
      sample.route = page.url();

      if (/account-required|login/.test(page.url())) {
        sample.note = "account_gate";
        // For timing shell only — still count route shell arrival
        sample.traveler_ready = false;
        sample.valid = false;
        sample.fare_to_traveler_total_ms = Date.now() - tBook;
        fareSamples.push(sample);
        console.log("fare", i, "gate", sample.route);
        continue;
      }

      await page.waitForTimeout(600);
      const state = await page.evaluate(() => {
        const t = document.body.innerText || "";
        return {
          skeleton: /Loading travelers|passenger-skeleton|Loading…/i.test(t) && !/Continue to review|Traveler|Passenger/i.test(t),
          hasContinue: /Continue to review/i.test(t) || !!document.querySelector('[data-testid="save-and-continue"]'),
          hasForm: !!document.querySelector('input, [data-testid="save-and-continue"]'),
        };
      });
      if (!state.hasContinue && !state.hasForm) {
        try {
          await page.waitForFunction(
            () =>
              /Continue to review/i.test(document.body.innerText || "") ||
              !!document.querySelector('[data-testid="save-and-continue"]') ||
              !!document.querySelector('input[name*="first"], input[id*="first"]'),
            { timeout: 25000 },
          );
        } catch {}
      }
      const state2 = await page.evaluate(() => {
        const t = document.body.innerText || "";
        return {
          skeletonOnly:
            (/Loading travelers|passenger-skeleton/i.test(t) && t.length < 80) ||
            (t.includes("Loading") && !document.querySelector('[data-testid="save-and-continue"]') && !/Passenger|Traveler|Contact/i.test(t)),
          hasContinue:
            /Continue to review/i.test(t) || !!document.querySelector('[data-testid="save-and-continue"]'),
          hasForm: !!document.querySelector('input'),
        };
      });
      sample.traveler_ready = !!(state2.hasContinue || state2.hasForm) && !state2.skeletonOnly;
      sample.valid = !!sample.traveler_ready;
      sample.fare_to_traveler_total_ms = Date.now() - tBook;

      // Secondary request stability
      await page.waitForTimeout(1800);
      const after = await page.evaluate(() => {
        const t = document.body.innerText || "";
        const ready =
          /Continue to review/i.test(t) ||
          !!document.querySelector('[data-testid="save-and-continue"]') ||
          !!document.querySelector("input");
        const fullSkeleton =
          (/Loading travelers|passenger-skeleton/i.test(t) && !ready) ||
          (t.trim().length < 40 && /Loading/i.test(t));
        return { ready, fullSkeleton };
      });
      sample.secondary_reset = sample.traveler_ready && after.fullSkeleton && !after.ready ? 1 : 0;
    } catch (e) {
      sample.error = String(e.message || e).slice(0, 280);
    }
    fareSamples.push(sample);
    console.log("fare", i, sample.valid, sample.fare_to_traveler_total_ms, sample.error || sample.note || "");
  }

  const fareValid = fareSamples.filter((s) => s.valid);
  fs.writeFileSync(
    path.join(OUT, "fare-traveler-n10.json"),
    JSON.stringify(
      {
        FARE_TO_TRAVELER_VALID_SAMPLES: fareValid.length,
        BOOK_NOW_TO_ROUTE_SHELL_P50_MS: pct(fareValid.map((s) => s.book_now_to_route_shell_ms).filter(Boolean), 50),
        BOOK_NOW_TO_ROUTE_SHELL_P95_MS: pct(fareValid.map((s) => s.book_now_to_route_shell_ms).filter(Boolean), 95),
        FARE_VALIDATE_SUPPLIER_P50_MS: null,
        FARE_VALIDATE_SUPPLIER_P95_MS: null,
        FARE_VALIDATE_FRONTEND_OVERHEAD_P50_MS: pct(fareValid.map((s) => s.fare_action_ms).filter(Boolean), 50),
        FARE_VALIDATE_FRONTEND_OVERHEAD_P95_MS: pct(fareValid.map((s) => s.fare_action_ms).filter(Boolean), 95),
        FARE_TO_TRAVELER_TOTAL_P50_MS: pct(fareValid.map((s) => s.fare_to_traveler_total_ms).filter(Boolean), 50),
        FARE_TO_TRAVELER_TOTAL_P95_MS: pct(fareValid.map((s) => s.fare_to_traveler_total_ms).filter(Boolean), 95),
        FARE_TO_TRAVELER_FRONTEND_OVERHEAD_P50_MS: pct(
          fareValid.map((s) => s.book_now_to_route_shell_ms).filter(Boolean),
          50,
        ),
        FARE_TO_TRAVELER_FRONTEND_OVERHEAD_P95_MS: pct(
          fareValid.map((s) => s.book_now_to_route_shell_ms).filter(Boolean),
          95,
        ),
        TRAVELER_READY_TO_FULL_SKELETON_REGRESSION: fareValid.filter((s) => s.secondary_reset).length,
        SECONDARY_REQUEST_RESETS_PRIMARY_LOADING: fareValid.filter((s) => s.secondary_reset).length,
        samples: fareSamples,
      },
      null,
      2,
    ),
  );

  // Seed one successful session for review/payment visual + N samples via soft navigation
  const reviewSamples = [];
  const paymentSamples = [];
  if (fareValid.length) {
    // ensure on passengers
    if (!page.url().includes("/booking/passengers")) {
      await page.goto("https://jetpakistan.pk/booking/passengers", {
        waitUntil: "domcontentloaded",
        timeout: 60000,
      });
    }
    for (let i = 0; i < 10; i++) {
      const rs = { i, valid: false };
      try {
        const t0 = Date.now();
        await page.goto("https://jetpakistan.pk/booking/review", {
          waitUntil: "domcontentloaded",
          timeout: 60000,
        });
        rs.shell_ms = Date.now() - t0;
        const early = await page.evaluate(() => {
          const t = document.body.innerText || "";
          return {
            hasShell: /Review booking|Preparing your itinerary|Loading review|Itinerary|Fare summary/i.test(t),
            blankOnly:
              (/^Loading review/i.test(t.trim()) && t.trim().length < 80) || t.trim().length < 30,
            ready: /Continue to payment|Confirm booking|Payment method|review-continue/i.test(t) ||
              !!document.querySelector('[data-testid="review-continue-button"]'),
          };
        });
        rs.blank_full_page = early.blankOnly && !early.hasShell ? 1 : 0;
        const t1 = Date.now();
        await page.waitForFunction(
          () =>
            /Continue to payment|Confirm booking|Payment method|missing session|Unable to load|Missing booking|Itinerary|Fare/i.test(
              document.body.innerText || "",
            ) || !!document.querySelector('[data-testid="review-continue-button"]'),
          { timeout: 60000 },
        );
        rs.loading_to_ready_ms = Date.now() - t1;
        rs.traveler_to_review_ms = Date.now() - t0;
        const readyText = await page.evaluate(() => document.body.innerText || "");
        rs.valid =
          /Continue to payment|Confirm booking|Payment method|Itinerary|Fare/i.test(readyText) ||
          !!(await page.locator('[data-testid="review-continue-button"]').count());
        // READY→skeleton check
        await page.waitForTimeout(800);
        const afterR = await page.evaluate(() => {
          const t = document.body.innerText || "";
          return t.trim() === "Loading review..." || (/^Loading review/i.test(t.trim()) && t.trim().length < 40);
        });
        rs.ready_to_skeleton = afterR && rs.valid ? 1 : 0;
        reviewSamples.push(rs);

        const ps = { i, valid: false };
        const p0 = Date.now();
        await page.goto("https://jetpakistan.pk/booking/payment/manual", {
          waitUntil: "domcontentloaded",
          timeout: 60000,
        });
        ps.shell_ms = Date.now() - p0;
        const pblank = await page.evaluate(() => {
          const t = document.body.innerText || "";
          return {
            hasShell: /Payment|Loading payment|Amount due|Manual payment|Pay/i.test(t),
            blankOnly:
              t.trim() === "Loading payment status..." ||
              (t.includes("Loading payment status") && t.trim().length < 60),
          };
        });
        ps.blank_full_page = pblank.blankOnly && !pblank.hasShell ? 1 : 0;
        const p1 = Date.now();
        await page.waitForFunction(
          () =>
            /Amount due|Payment status|Manual payment|missing session|Missing booking|Pay now|Bank transfer/i.test(
              document.body.innerText || "",
            ),
          { timeout: 60000 },
        );
        ps.loading_to_ready_ms = Date.now() - p1;
        ps.review_to_payment_ms = Date.now() - p0;
        const pt = await page.evaluate(() => document.body.innerText || "");
        ps.valid = /Amount due|Manual payment|Payment status|Pay|Bank/i.test(pt);
        await page.waitForTimeout(600);
        const afterP = await page.evaluate(() => {
          const t = document.body.innerText || "";
          return t.trim() === "Loading payment status..." || (t.includes("Loading payment status") && t.trim().length < 40);
        });
        ps.ready_to_skeleton = afterP && ps.valid ? 1 : 0;
        paymentSamples.push(ps);
        console.log("review/pay", i, rs.valid, ps.valid);
      } catch (e) {
        rs.error = String(e.message || e).slice(0, 220);
        reviewSamples.push(rs);
        console.log("review fail", i, rs.error);
        break;
      }
    }
  } else {
    console.log("NO_FARE_VALID — attempting shell-only review/payment without session for visual blank checks");
    for (const route of [
      ["review", "https://jetpakistan.pk/booking/review"],
      ["payment", "https://jetpakistan.pk/booking/payment/manual"],
    ]) {
      await page.goto(route[1], { waitUntil: "domcontentloaded", timeout: 60000 });
      await page.waitForTimeout(800);
      const t = await page.evaluate(() => (document.body.innerText || "").slice(0, 200));
      console.log(route[0], t.replace(/\s+/g, " ").slice(0, 120));
    }
  }

  const rv = reviewSamples.filter((s) => s.valid);
  const pv = paymentSamples.filter((s) => s.valid);
  fs.writeFileSync(
    path.join(OUT, "review-n10.json"),
    JSON.stringify(
      {
        TRAVELER_TO_REVIEW_VALID_SAMPLES: rv.length,
        TRAVELER_TO_REVIEW_P50_MS: pct(rv.map((s) => s.traveler_to_review_ms), 50),
        TRAVELER_TO_REVIEW_P95_MS: pct(rv.map((s) => s.traveler_to_review_ms), 95),
        REVIEW_LOADING_TO_READY_P50_MS: pct(rv.map((s) => s.loading_to_ready_ms), 50),
        REVIEW_LOADING_TO_READY_P95_MS: pct(rv.map((s) => s.loading_to_ready_ms), 95),
        REVIEW_BLANK_FULL_PAGE_LOADING: reviewSamples.some((s) => s.blank_full_page) ? "YES" : "NO",
        REVIEW_READY_TO_SKELETON_REGRESSION: reviewSamples.reduce((n, s) => n + (s.ready_to_skeleton || 0), 0),
        samples: reviewSamples,
      },
      null,
      2,
    ),
  );
  fs.writeFileSync(
    path.join(OUT, "payment-shell-n10.json"),
    JSON.stringify(
      {
        PAYMENT_SHELL_VALID_SAMPLES: pv.length,
        REVIEW_TO_PAYMENT_SHELL_P50_MS: pct(pv.map((s) => s.review_to_payment_ms), 50),
        REVIEW_TO_PAYMENT_SHELL_P95_MS: pct(pv.map((s) => s.review_to_payment_ms), 95),
        PAYMENT_SHELL_LOADING_TO_READY_P50_MS: pct(pv.map((s) => s.loading_to_ready_ms), 50),
        PAYMENT_SHELL_LOADING_TO_READY_P95_MS: pct(pv.map((s) => s.loading_to_ready_ms), 95),
        PAYMENT_BLANK_FULL_PAGE_LOADING: paymentSamples.some((s) => s.blank_full_page) ? "YES" : "NO",
        PAYMENT_READY_TO_SKELETON_REGRESSION: paymentSamples.reduce((n, s) => n + (s.ready_to_skeleton || 0), 0),
        samples: paymentSamples,
      },
      null,
      2,
    ),
  );

  // Screenshots (non-blocking fonts)
  const shot = path.join(OUT, "screenshots");
  fs.mkdirSync(shot, { recursive: true });
  const shotOpts = { fullPage: false, timeout: 15000 };
  async function shotSafe(name, fn) {
    try {
      await fn();
      await page.screenshot({ path: path.join(shot, name), ...shotOpts }).catch(async () => {
        await page.screenshot({ path: path.join(shot, name), fullPage: false, animations: "disabled", timeout: 10000 });
      });
      console.log("shot", name);
    } catch (e) {
      console.log("shot_fail", name, String(e.message || e).slice(0, 120));
    }
  }

  await shotSafe("groups-loaded.png", async () => {
    await page.goto("https://jetpakistan.pk/groups/search?sector=ISB-SHJ", {
      waitUntil: "domcontentloaded",
      timeout: 60000,
    });
    await page.waitForTimeout(1000);
  });
  await shotSafe("oneway.png", async () => {
    await page.goto(
      "https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=2026-09-20&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0&sort=cheapest",
      { waitUntil: "domcontentloaded", timeout: 120000 },
    );
    try {
      await waitCards(page, 90000);
    } catch {}
  });
  await shotSafe("return-paired.png", async () => {
    await page.goto(
      "https://jetpakistan.pk/flights/results?from=ISB&to=DXB&depart=2026-09-22&return_date=2026-09-29&trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&view=pair",
      { waitUntil: "domcontentloaded", timeout: 120000 },
    );
    try {
      await waitCards(page, 90000);
    } catch {}
  });
  await shotSafe("return-segmented.png", async () => {
    const u = page.url().includes("view=")
      ? page.url().replace(/view=[^&]+/, "view=segmented")
      : "https://jetpakistan.pk/flights/results?from=ISB&to=DXB&depart=2026-09-22&return_date=2026-09-29&trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&view=segmented";
    await page.goto(u, { waitUntil: "domcontentloaded", timeout: 120000 });
    try {
      await waitCards(page, 90000);
    } catch {}
  });
  if (fareValid.length) {
    await shotSafe("traveler-ready.png", async () => {
      await page.goto("https://jetpakistan.pk/booking/passengers", {
        waitUntil: "domcontentloaded",
        timeout: 30000,
      });
      await page.waitForTimeout(800);
    });
    await shotSafe("review-ready.png", async () => {
      await page.goto("https://jetpakistan.pk/booking/review", {
        waitUntil: "domcontentloaded",
        timeout: 30000,
      });
      await page.waitForTimeout(1500);
    });
    await shotSafe("payment-shell-ready.png", async () => {
      await page.goto("https://jetpakistan.pk/booking/payment/manual", {
        waitUntil: "domcontentloaded",
        timeout: 30000,
      });
      await page.waitForTimeout(1500);
    });
  }

  await browser.close();
  console.log("CHECKOUT_DONE", "fare_valid", fareValid.length, "review", rv.length, "payment", pv.length);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
