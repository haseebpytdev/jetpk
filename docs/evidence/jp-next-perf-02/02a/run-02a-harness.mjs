/**
 * JP-NEXT-PERF-02A production verification harness (read-only).
 * Usage: node docs/evidence/jp-next-perf-02/02a/run-02a-harness.mjs
 */
import { chromium } from "../../../../frontend/node_modules/playwright/index.mjs";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = __dirname;

function pct(arr, p) {
  if (!arr.length) return null;
  const a = [...arr].sort((x, y) => x - y);
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

async function waitCards(page, timeout = 100000) {
  await page.waitForSelector(
    '[data-testid="flight-result-card"], [data-testid="pair-return-card"], [data-testid="outbound-option-card"]',
    { timeout },
  );
}

async function measureFlightBatch(page, { url, n, label }) {
  const samples = [];
  for (let i = 0; i < n; i++) {
    const t0 = Date.now();
    const sep = url.includes("?") ? "&" : "?";
    await page.goto(url + sep + "_=" + Date.now(), { waitUntil: "domcontentloaded", timeout: 120000 });
    let firstCard = null;
    try {
      await waitCards(page, 100000);
      firstCard = Date.now() - t0;
    } catch {}
    await page.waitForTimeout(400);
    const marks = await page.evaluate(() => {
      const res = performance.getEntriesByType("resource");
      const apis = res.filter(
        (r) =>
          r.name.includes("/flights/results/data") ||
          r.name.includes("/flights/results/search") ||
          r.name.includes("/flights/return-options/data"),
      );
      const totalApi = apis.reduce((s, r) => s + r.duration, 0);
      const lastEnd = apis.length ? Math.max(...apis.map((r) => r.startTime + r.duration)) : null;
      return {
        backend_proxy_ms: Math.round(totalApi),
        api_last_end_ms: lastEnd != null ? Math.round(lastEnd) : null,
        cards: document.querySelectorAll(
          '[data-testid="flight-result-card"], [data-testid="pair-return-card"], [data-testid="outbound-option-card"]',
        ).length,
        skeleton: /Finding the best available flights|Searching live flights/i.test(document.body.innerText || ""),
        href: location.href,
      };
    });
    const post =
      firstCard != null && marks.api_last_end_ms != null ? Math.max(0, firstCard - marks.api_last_end_ms) : null;
    samples.push({
      i,
      label,
      total_ms: firstCard,
      backend_proxy_ms: marks.backend_proxy_ms,
      post_api_render_ms: post,
      next_overhead_ms: post,
      cards: marks.cards,
      skeleton: marks.skeleton,
      href: marks.href,
      valid: !!(firstCard && marks.cards > 0),
    });
    console.log(label, i, samples[samples.length - 1].total_ms, "valid", samples[samples.length - 1].valid);
  }
  const valid = samples.filter((s) => s.valid);
  return {
    label,
    valid_n: valid.length,
    TOTAL_P50: pct(valid.map((s) => s.total_ms), 50),
    TOTAL_P95: pct(valid.map((s) => s.total_ms), 95),
    BACKEND_P50: pct(valid.map((s) => s.backend_proxy_ms), 50),
    BACKEND_P95: pct(valid.map((s) => s.backend_proxy_ms), 95),
    POST_API_P50: pct(valid.map((s) => s.post_api_render_ms).filter((x) => x != null), 50),
    POST_API_P95: pct(valid.map((s) => s.post_api_render_ms).filter((x) => x != null), 95),
    NEXT_OH_P50: pct(valid.map((s) => s.next_overhead_ms).filter((x) => x != null), 50),
    NEXT_OH_P95: pct(valid.map((s) => s.next_overhead_ms).filter((x) => x != null), 95),
    samples,
  };
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    userAgent:
      "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 JP-NEXT-PERF-02A",
  });
  const page = await context.newPage();

  const oneway = await measureFlightBatch(page, {
    label: "oneway",
    n: 10,
    url: "https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=2026-09-20&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0&sort=cheapest",
  });
  fs.writeFileSync(path.join(OUT, "oneway-n10.json"), JSON.stringify(oneway, null, 2));

  const paired = await measureFlightBatch(page, {
    label: "return_paired",
    n: 10,
    url: "https://jetpakistan.pk/flights/results?from=ISB&to=DXB&depart=2026-09-22&return_date=2026-09-29&trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&view=pair",
  });
  fs.writeFileSync(path.join(OUT, "return-paired-n10.json"), JSON.stringify(paired, null, 2));

  let segUrl =
    "https://jetpakistan.pk/flights/results?from=ISB&to=DXB&depart=2026-09-22&return_date=2026-09-29&trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&view=segmented";
  const last = paired.samples.filter((s) => s.valid).slice(-1)[0];
  if (last?.href?.includes("search_id=")) {
    segUrl = last.href.includes("view=")
      ? last.href.replace(/view=[^&]+/, "view=segmented")
      : last.href + "&view=segmented";
  }
  const segmented = await measureFlightBatch(page, { label: "return_segmented", n: 10, url: segUrl });
  fs.writeFileSync(path.join(OUT, "return-segmented-n10.json"), JSON.stringify(segmented, null, 2));

  // switches
  await page.goto(
    "https://jetpakistan.pk/flights/results?from=ISB&to=DXB&depart=2026-09-22&return_date=2026-09-29&trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&view=pair",
    { waitUntil: "domcontentloaded", timeout: 120000 },
  );
  await waitCards(page, 100000);
  const pairToSeg = [];
  const segToPair = [];
  for (let i = 0; i < 20; i++) {
    let t0 = Date.now();
    const u1 = new URL(page.url());
    u1.searchParams.set("view", "segmented");
    await page.goto(u1.toString(), { waitUntil: "domcontentloaded", timeout: 120000 });
    await waitCards(page, 60000);
    pairToSeg.push(Date.now() - t0);
    t0 = Date.now();
    const u2 = new URL(page.url());
    u2.searchParams.set("view", "pair");
    await page.goto(u2.toString(), { waitUntil: "domcontentloaded", timeout: 120000 });
    await waitCards(page, 60000);
    segToPair.push(Date.now() - t0);
  }
  const switches = {
    PAIR_TO_SEGMENTED_P50_MS: pct(pairToSeg, 50),
    PAIR_TO_SEGMENTED_P95_MS: pct(pairToSeg, 95),
    SEGMENTED_TO_PAIR_P50_MS: pct(segToPair, 50),
    SEGMENTED_TO_PAIR_P95_MS: pct(segToPair, 95),
    samples: { pairToSeg, segToPair },
  };
  fs.writeFileSync(path.join(OUT, "view-switch-n20.json"), JSON.stringify(switches, null, 2));

  // sort/filter/nearby
  await page.goto(
    "https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=2026-09-20&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0&sort=cheapest",
    { waitUntil: "domcontentloaded", timeout: 120000 },
  );
  await waitCards(page, 100000);
  const sortTimes = [];
  let readyToSkeleton = 0;
  for (let i = 0; i < 10; i++) {
    const before = await page
      .locator(
        '[data-testid="flight-result-card"], [data-testid="pair-return-card"], [data-testid="outbound-option-card"]',
      )
      .count();
    const t0 = Date.now();
    const sort = page.locator('[data-testid="sort-control"]');
    if (await sort.count()) {
      try {
        await sort.selectOption({ index: (i % 3) + 1 });
      } catch {
        try {
          await sort.click();
        } catch {}
      }
    }
    await page.waitForTimeout(100);
    try {
      await waitCards(page, 30000);
    } catch {}
    sortTimes.push(Date.now() - t0);
    const mid = await page
      .locator(
        '[data-testid="flight-result-card"], [data-testid="pair-return-card"], [data-testid="outbound-option-card"]',
      )
      .count();
    const skel = await page.evaluate(() =>
      /Finding the best available flights/i.test(document.body.innerText || ""),
    );
    if (skel && mid === 0 && before > 0) readyToSkeleton += 1;
  }
  const filterTimes = [];
  for (let i = 0; i < 10; i++) {
    const t0 = Date.now();
    const cb = page.locator('[data-testid="results-filter-panel"] input[type="checkbox"]').first();
    if (await cb.count()) {
      try {
        await cb.click({ force: true });
      } catch {}
    }
    await page.waitForTimeout(80);
    try {
      await waitCards(page, 30000);
    } catch {}
    filterTimes.push(Date.now() - t0);
  }
  const nearby = [];
  for (let i = 0; i < 5; i++) {
    const t0 = Date.now();
    const next = page.locator('[data-testid="nearby-date-next"]');
    if (await next.count()) await next.click();
    try {
      await waitCards(page, 90000);
    } catch {}
    nearby.push(Date.now() - t0);
  }
  const local = {
    LOCAL_SORT_P95_MS: pct(sortTimes, 95),
    LOCAL_FILTER_P95_MS: pct(filterTimes, 95),
    FILTER_OR_SORT_READY_TO_SKELETON_REGRESSIONS: readyToSkeleton,
    NEARBY_DATE_TOTAL_P50_MS: pct(nearby, 50),
    NEARBY_DATE_TOTAL_P95_MS: pct(nearby, 95),
    sortTimes,
    filterTimes,
    nearby,
  };
  fs.writeFileSync(path.join(OUT, "filter-sort-nearby.json"), JSON.stringify(local, null, 2));

  // ACK measurements on groups
  const acks = [];
  for (let i = 0; i < 20; i++) {
    await page.goto("https://jetpakistan.pk/groups", { waitUntil: "domcontentloaded", timeout: 60000 });
    await page.waitForTimeout(200);
    const t0 = Date.now();
    await page.evaluate(() => {
      const btn = Array.from(document.querySelectorAll("button")).find((b) =>
        /Search Groups|Search/i.test(b.textContent || ""),
      );
      if (btn) btn.click();
    });
    try {
      await page.waitForFunction(
        () =>
          /Searching|Checking|Finding matching|group departures/i.test(document.body.innerText || "") ||
          location.pathname.includes("/groups/search"),
        { timeout: 3000 },
      );
    } catch {}
    acks.push(Date.now() - t0);
  }
  fs.writeFileSync(
    path.join(OUT, "user-action-ack.json"),
    JSON.stringify(
      {
        USER_ACTION_TO_ACK_P50_MS: pct(acks, 50),
        USER_ACTION_TO_ACK_P95_MS: pct(acks, 95),
        samples: acks,
      },
      null,
      2,
    ),
  );

  // Fare → Traveler (up to 10) — stop at traveler
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
      const tBook = Date.now();
      const book = page.locator('button:has-text("Book"), a:has-text("Book"), [data-testid*="book"]').first();
      await book.click({ timeout: 10000 });
      sample.book_click_ms = Date.now() - tBook;
      // fare modal / continue
      const tFare = Date.now();
      const continueBtn = page
        .locator('button:has-text("Continue"), button:has-text("Select"), button:has-text("Confirm fare"), button:has-text("Book now")')
        .first();
      await continueBtn.click({ timeout: 15000 }).catch(async () => {
        await page.locator('button:has-text("Book now")').first().click({ timeout: 5000 });
      });
      sample.fare_action_ms = Date.now() - tFare;
      const tNav = Date.now();
      await page.waitForURL(/\/booking\/passengers/, { timeout: 120000 });
      sample.book_now_to_route_shell_ms = Date.now() - tNav;
      await page.waitForSelector('[data-testid="passenger-form"], [data-testid="booking-page"], form, h1', {
        timeout: 60000,
      });
      const readyAt = Date.now();
      sample.fare_to_traveler_total_ms = readyAt - tBook;
      const state = await page.evaluate(() => {
        const t = document.body.innerText || "";
        return {
          skeleton: /passenger-skeleton|Loading travelers|Loading…/i.test(t) && !/Continue to review/i.test(t),
          hasContinue: /Continue to review/i.test(t),
          blank: t.trim().length < 40,
        };
      });
      sample.traveler_skeleton = state.skeleton;
      sample.traveler_ready = state.hasContinue || !state.skeleton;
      sample.valid = !!sample.traveler_ready;
      await page.waitForTimeout(1500);
      const after = await page.evaluate(() =>
        /passenger-skeleton|Loading travelers/i.test(document.body.innerText || "") &&
        !/Continue to review/i.test(document.body.innerText || ""),
      );
      sample.secondary_reset = after && sample.traveler_ready ? 1 : 0;
    } catch (e) {
      sample.error = String(e.message || e).slice(0, 200);
    }
    fareSamples.push(sample);
    console.log("fare", i, sample.valid, sample.fare_to_traveler_total_ms);
  }
  const fareValid = fareSamples.filter((s) => s.valid);
  fs.writeFileSync(
    path.join(OUT, "fare-traveler-n10.json"),
    JSON.stringify(
      {
        FARE_TO_TRAVELER_VALID_SAMPLES: fareValid.length,
        BOOK_NOW_TO_ROUTE_SHELL_P50_MS: pct(fareValid.map((s) => s.book_now_to_route_shell_ms).filter(Boolean), 50),
        BOOK_NOW_TO_ROUTE_SHELL_P95_MS: pct(fareValid.map((s) => s.book_now_to_route_shell_ms).filter(Boolean), 95),
        FARE_TO_TRAVELER_TOTAL_P50_MS: pct(fareValid.map((s) => s.fare_to_traveler_total_ms).filter(Boolean), 50),
        FARE_TO_TRAVELER_TOTAL_P95_MS: pct(fareValid.map((s) => s.fare_to_traveler_total_ms).filter(Boolean), 95),
        TRAVELER_READY_TO_FULL_SKELETON_REGRESSION: fareValid.filter((s) => s.secondary_reset).length,
        SECONDARY_REQUEST_RESETS_PRIMARY_LOADING: fareValid.filter((s) => s.secondary_reset).length,
        samples: fareSamples,
      },
      null,
      2,
    ),
  );

  // Review + payment shell from a traveler session if we have one
  const reviewSamples = [];
  const paymentSamples = [];
  if (fareValid.length) {
    for (let i = 0; i < Math.min(10, fareValid.length + 5); i++) {
      const rs = { i, valid: false };
      try {
        // ensure on traveler
        if (!page.url().includes("/booking/passengers")) {
          await page.goto("https://jetpakistan.pk/booking/passengers", {
            waitUntil: "domcontentloaded",
            timeout: 60000,
          });
        }
        const t0 = Date.now();
        await page.goto("https://jetpakistan.pk/booking/review", {
          waitUntil: "domcontentloaded",
          timeout: 60000,
        });
        rs.shell_ms = Date.now() - t0;
        const blank = await page.evaluate(() => {
          const t = document.body.innerText || "";
          return {
            hasShell: /Review booking|Preparing your itinerary|Loading review/i.test(t),
            blankOnly: /^[\s\S]{0,80}$/.test(t.trim()) || (t.includes("Loading review") && !t.includes("Review booking")),
            ready: /Continue to payment|Confirm booking|Payment method|Itinerary/i.test(t),
          };
        });
        rs.blank_full_page = blank.blankOnly && !blank.hasShell ? 1 : 0;
        const t1 = Date.now();
        await page.waitForFunction(
          () => /Continue to payment|Confirm booking|Payment method|missing session|Unable to load/i.test(document.body.innerText || ""),
          { timeout: 60000 },
        );
        rs.loading_to_ready_ms = Date.now() - t1;
        rs.traveler_to_review_ms = Date.now() - t0;
        const readyText = await page.evaluate(() => document.body.innerText || "");
        rs.valid = /Continue to payment|Confirm booking|Payment method/i.test(readyText);
        rs.missing = /missing session|Unable to load/i.test(readyText);
        if (rs.valid) {
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
              hasShell: /Payment|Loading payment|Amount due|Manual payment/i.test(t),
              blankOnly: t.includes("Loading payment status") && !t.includes("Manual payment") && !t.includes("Amount due"),
              ready: /Amount due|Payment status|Manual payment/i.test(t),
            };
          });
          ps.blank_full_page = pblank.blankOnly ? 1 : 0;
          const p1 = Date.now();
          await page.waitForFunction(
            () => /Amount due|Payment status|Manual payment|missing session/i.test(document.body.innerText || ""),
            { timeout: 60000 },
          );
          ps.loading_to_ready_ms = Date.now() - p1;
          ps.review_to_payment_ms = Date.now() - p0;
          const pt = await page.evaluate(() => document.body.innerText || "");
          ps.valid = /Amount due|Manual payment|Payment status/i.test(pt);
          paymentSamples.push(ps);
        } else {
          reviewSamples.push(rs);
          break;
        }
      } catch (e) {
        rs.error = String(e.message || e).slice(0, 200);
        reviewSamples.push(rs);
        break;
      }
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
        samples: paymentSamples,
      },
      null,
      2,
    ),
  );

  // screenshots desktop
  const shot = path.join(OUT, "screenshots");
  fs.mkdirSync(shot, { recursive: true });
  await page.goto(
    "https://jetpakistan.pk/groups/search?sector=ISB-SHJ",
    { waitUntil: "domcontentloaded", timeout: 60000 },
  );
  await page.waitForTimeout(1500);
  await page.screenshot({ path: path.join(shot, "groups-loaded.png"), fullPage: false });
  await page.goto(
    "https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=2026-09-20&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0&sort=cheapest",
    { waitUntil: "domcontentloaded", timeout: 120000 },
  );
  try {
    await waitCards(page, 90000);
  } catch {}
  await page.screenshot({ path: path.join(shot, "oneway.png"), fullPage: false });
  await page.goto(
    "https://jetpakistan.pk/flights/results?from=ISB&to=DXB&depart=2026-09-22&return_date=2026-09-29&trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&view=pair",
    { waitUntil: "domcontentloaded", timeout: 120000 },
  );
  try {
    await waitCards(page, 90000);
  } catch {}
  await page.screenshot({ path: path.join(shot, "return-paired.png"), fullPage: false });
  const u = new URL(page.url());
  u.searchParams.set("view", "segmented");
  await page.goto(u.toString(), { waitUntil: "domcontentloaded", timeout: 120000 });
  try {
    await waitCards(page, 90000);
  } catch {}
  await page.screenshot({ path: path.join(shot, "return-segmented.png"), fullPage: false });

  await browser.close();
  console.log("DONE");
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
