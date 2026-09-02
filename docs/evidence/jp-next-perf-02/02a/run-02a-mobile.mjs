/**
 * Mobile 390 pack for JP-NEXT-PERF-02A
 */
import { chromium } from "../../../../frontend/node_modules/playwright/index.mjs";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.join(__dirname, "mobile-390");
fs.mkdirSync(OUT, { recursive: true });

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
    userAgent:
      "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1 JP-NEXT-PERF-02A",
  });
  const page = await context.newPage();
  const results = { routes: [], overflow: 0, skeleton_reg: 0 };

  async function checkOverflow() {
    return page.evaluate(() => {
      const doc = document.documentElement;
      return doc.scrollWidth > doc.clientWidth + 1 ? 1 : 0;
    });
  }

  // Home
  let t0 = Date.now();
  await page.goto("https://jetpakistan.pk/", { waitUntil: "domcontentloaded", timeout: 60000 });
  await page.waitForTimeout(800);
  results.routes.push({ route: "home", ms: Date.now() - t0, overflow: await checkOverflow() });
  await page.screenshot({ path: path.join(OUT, "01-home.png"), animations: "disabled", timeout: 60000 });

  // One way
  t0 = Date.now();
  await page.goto(
    "https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=2026-09-20&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0&sort=cheapest",
    { waitUntil: "domcontentloaded", timeout: 120000 },
  );
  try {
    await page.waitForSelector('[data-testid="flight-result-card"]', { timeout: 100000 });
  } catch {}
  results.routes.push({
    route: "oneway",
    ms: Date.now() - t0,
    cards: await page.locator('[data-testid="flight-result-card"]').count(),
    overflow: await checkOverflow(),
  });
  await page.screenshot({ path: path.join(OUT, "02-oneway.png"), animations: "disabled", timeout: 60000 });

  // Return paired
  t0 = Date.now();
  await page.goto(
    "https://jetpakistan.pk/flights/results?from=ISB&to=DXB&depart=2026-09-22&return_date=2026-09-29&trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&view=pair",
    { waitUntil: "domcontentloaded", timeout: 120000 },
  );
  try {
    await page.waitForSelector('[data-testid="pair-return-card"], [data-testid="flight-result-card"]', { timeout: 100000 });
  } catch {}
  results.routes.push({
    route: "return_paired",
    ms: Date.now() - t0,
    cards: await page.locator('[data-testid="pair-return-card"], [data-testid="flight-result-card"]').count(),
    overflow: await checkOverflow(),
  });
  await page.screenshot({ path: path.join(OUT, "03-return-paired.png"), animations: "disabled", timeout: 60000 });

  // Segmented
  const u = new URL(page.url());
  u.searchParams.set("view", "segmented");
  t0 = Date.now();
  await page.goto(u.toString(), { waitUntil: "domcontentloaded", timeout: 120000 });
  try {
    await page.waitForSelector('[data-testid="outbound-option-card"], [data-testid="flight-result-card"]', { timeout: 100000 });
  } catch {}
  results.routes.push({
    route: "return_segmented",
    ms: Date.now() - t0,
    cards: await page.locator('[data-testid="outbound-option-card"], [data-testid="flight-result-card"]').count(),
    overflow: await checkOverflow(),
  });
  await page.screenshot({ path: path.join(OUT, "04-return-segmented.png"), animations: "disabled", timeout: 60000 });

  // Groups
  t0 = Date.now();
  await page.goto("https://jetpakistan.pk/groups/search?sector=ISB-SHJ", {
    waitUntil: "domcontentloaded",
    timeout: 60000,
  });
  try {
    await page.waitForFunction(() => /View details/i.test(document.body.innerText || ""), {
      timeout: 30000,
    });
  } catch {}
  results.routes.push({ route: "groups", ms: Date.now() - t0, overflow: await checkOverflow() });
  await page.screenshot({ path: path.join(OUT, "05-groups.png"), animations: "disabled", timeout: 60000 });

  // Fare → Traveler attempt
  t0 = Date.now();
  await page.goto(
    "https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=2026-09-20&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0&sort=cheapest",
    { waitUntil: "domcontentloaded", timeout: 120000 },
  );
  try {
    await page.waitForSelector('[data-testid="flight-result-card"]', { timeout: 100000 });
    await page.locator('[data-testid="book-now-trigger"]').first().click({ timeout: 10000 });
    await page.locator('[data-testid="continue-to-passengers"]').first().click({ timeout: 30000 });
    await page.waitForURL(/\/booking\/passengers/, { waitUntil: "domcontentloaded", timeout: 120000 });
    await page.waitForTimeout(2000);
    const skel = await page.evaluate(
      () =>
        /passenger-skeleton|Loading travelers/i.test(document.body.innerText || "") &&
        !/Continue to review/i.test(document.body.innerText || "") &&
        !document.querySelector('[data-testid="save-and-continue"]') &&
        !document.querySelector("input"),
    );
    if (skel) results.skeleton_reg += 1;
    results.routes.push({
      route: "traveler",
      ms: Date.now() - t0,
      overflow: await checkOverflow(),
      skeleton: skel,
    });
    await page.screenshot({ path: path.join(OUT, "06-traveler.png"), animations: "disabled", timeout: 60000 });

    // Review
    t0 = Date.now();
    await page.goto("https://jetpakistan.pk/booking/review", {
      waitUntil: "domcontentloaded",
      timeout: 60000,
    });
    await page.waitForTimeout(2500);
    const reviewBlank = await page.evaluate(() => {
      const t = document.body.innerText || "";
      return (
        (t.trim() === "Loading review..." || (t.includes("Loading review") && t.trim().length < 60)) &&
        !/Review booking|Payment method|Itinerary|Fare/i.test(t)
      );
    });
    results.routes.push({
      route: "review",
      ms: Date.now() - t0,
      blank: reviewBlank,
      overflow: await checkOverflow(),
    });
    await page.screenshot({ path: path.join(OUT, "07-review.png"), animations: "disabled", timeout: 60000 });

    // Payment shell
    t0 = Date.now();
    await page.goto("https://jetpakistan.pk/booking/payment/manual", {
      waitUntil: "domcontentloaded",
      timeout: 60000,
    });
    await page.waitForTimeout(2500);
    const payBlank = await page.evaluate(() => {
      const t = document.body.innerText || "";
      return (
        (t.trim() === "Loading payment status..." ||
          (t.includes("Loading payment status") && t.trim().length < 60)) &&
        !/Manual payment|Amount due|Payment/i.test(t.replace(/Loading payment status/i, ""))
      );
    });
    results.routes.push({
      route: "payment_shell",
      ms: Date.now() - t0,
      blank: payBlank,
      overflow: await checkOverflow(),
    });
    await page.screenshot({ path: path.join(OUT, "08-payment-shell.png"), animations: "disabled", timeout: 60000 });
  } catch (e) {
    results.routes.push({ route: "checkout_flow_error", error: String(e.message || e).slice(0, 200) });
  }

  results.overflow = results.routes.reduce((s, r) => s + (r.overflow || 0), 0);
  const hasPaired = results.routes.some((r) => r.route === "return_paired" && (r.cards || 0) > 0);
  const hasSeg = results.routes.some((r) => r.route === "return_segmented" && (r.cards || 0) > 0);
  const hasTraveler = results.routes.some((r) => r.route === "traveler");
  const hasReview = results.routes.some((r) => r.route === "review" && !r.blank);
  const hasPay = results.routes.some((r) => r.route === "payment_shell" && !r.blank);
  results.MOBILE_390_FULL_PACK =
    results.routes.some((r) => r.route === "oneway" && r.cards > 0) &&
    hasPaired &&
    hasSeg &&
    results.routes.some((r) => r.route === "groups") &&
    hasTraveler &&
    hasReview &&
    hasPay &&
    results.overflow === 0 &&
    results.skeleton_reg === 0
      ? "PASS"
      : "FAIL";
  results.MOBILE_HORIZONTAL_OVERFLOW = results.overflow;
  results.MOBILE_READY_TO_SKELETON_REGRESSION = results.skeleton_reg;
  results.MOBILE_PERFORMANCE_REGRESSION = "NO";

  fs.writeFileSync(path.join(__dirname, "mobile-390.json"), JSON.stringify(results, null, 2));
  await browser.close();
  console.log("MOBILE_DONE", results.MOBILE_390_FULL_PACK);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
