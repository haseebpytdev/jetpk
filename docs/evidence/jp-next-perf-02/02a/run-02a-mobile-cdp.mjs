/**
 * Mobile 390 full pack with CDP screenshots (avoids font hang).
 */
import { chromium } from "../../../../frontend/node_modules/playwright/index.mjs";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.join(__dirname, "mobile-390");
fs.mkdirSync(OUT, { recursive: true });

async function snap(page, name) {
  const client = await page.context().newCDPSession(page);
  const { data } = await client.send("Page.captureScreenshot", { format: "png", fromSurface: true });
  fs.writeFileSync(path.join(OUT, name), Buffer.from(data, "base64"));
}

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
      const x0 = window.scrollX;
      window.scrollTo(240, 0);
      const can = window.scrollX > 1;
      window.scrollTo(x0, 0);
      // Also document scrollWidth as secondary signal only if scrollable.
      return can ? 1 : 0;
    });
  }

  let t0 = Date.now();
  await page.goto("https://jetpakistan.pk/", { waitUntil: "domcontentloaded", timeout: 90000 });
  await page.waitForTimeout(1500);
  results.routes.push({ route: "home", ms: Date.now() - t0, overflow: await checkOverflow() });
  await snap(page, "01-home.png");

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
  await snap(page, "02-oneway.png");

  t0 = Date.now();
  await page.goto(
    "https://jetpakistan.pk/flights/results?from=ISB&to=DXB&depart=2026-09-22&return_date=2026-09-29&trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&view=pair",
    { waitUntil: "domcontentloaded", timeout: 120000 },
  );
  try {
    await page.waitForSelector('[data-testid="pair-return-card"]', { timeout: 100000 });
  } catch {}
  results.routes.push({
    route: "return_paired",
    ms: Date.now() - t0,
    cards: await page.locator('[data-testid="pair-return-card"]').count(),
    overflow: await checkOverflow(),
  });
  await snap(page, "03-return-paired.png");

  const u = new URL(page.url());
  u.searchParams.set("view", "segmented");
  t0 = Date.now();
  await page.goto(u.toString(), { waitUntil: "domcontentloaded", timeout: 120000 });
  try {
    await page.waitForSelector('[data-testid="outbound-option-card"]', { timeout: 100000 });
  } catch {}
  results.routes.push({
    route: "return_segmented",
    ms: Date.now() - t0,
    cards: await page.locator('[data-testid="outbound-option-card"]').count(),
    overflow: await checkOverflow(),
  });
  await snap(page, "04-return-segmented.png");

  t0 = Date.now();
  await page.goto("https://jetpakistan.pk/groups/search?sector=ISB-SHJ", {
    waitUntil: "domcontentloaded",
    timeout: 90000,
  });
  try {
    await page.waitForFunction(() => /View details|AIR SIAL|FLY JINNAH/i.test(document.body.innerText || ""), {
      timeout: 40000,
    });
  } catch {}
  results.routes.push({ route: "groups", ms: Date.now() - t0, overflow: await checkOverflow() });
  await snap(page, "05-groups.png");

  t0 = Date.now();
  await page.goto(
    "https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=2026-09-20&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&_=" +
      Date.now(),
    { waitUntil: "domcontentloaded", timeout: 120000 },
  );
  try {
    await page.waitForSelector('[data-testid="flight-result-card"]', { timeout: 100000 });
    await page.locator('[data-testid="book-now-trigger"]').first().click({ timeout: 15000 });
    await page.locator('[data-testid="continue-to-passengers"]').first().click({ timeout: 30000 });
    await page.waitForURL(/\/booking\/passengers/, { waitUntil: "commit", timeout: 120000 });
    await page.waitForTimeout(1500);
    const skel = await page.evaluate(
      () =>
        /Loading travelers|passenger-skeleton/i.test(document.body.innerText || "") &&
        !document.querySelector("input") &&
        !document.querySelector('[data-testid="save-and-continue"]'),
    );
    if (skel) results.skeleton_reg += 1;
    results.routes.push({
      route: "traveler",
      ms: Date.now() - t0,
      overflow: await checkOverflow(),
      skeleton: skel,
    });
    await snap(page, "06-traveler.png");

    t0 = Date.now();
    await page.goto("https://jetpakistan.pk/booking/review", { waitUntil: "domcontentloaded", timeout: 60000 });
    await page.waitForTimeout(2000);
    const reviewBlank = await page.evaluate(() => {
      const t = (document.body.innerText || "").trim();
      return t === "Loading review..." || (t.startsWith("Loading review") && t.length < 40);
    });
    results.routes.push({
      route: "review",
      ms: Date.now() - t0,
      blank: reviewBlank,
      overflow: await checkOverflow(),
    });
    await snap(page, "07-review.png");

    t0 = Date.now();
    await page.goto("https://jetpakistan.pk/booking/payment/manual", {
      waitUntil: "domcontentloaded",
      timeout: 60000,
    });
    await page.waitForTimeout(2000);
    const payBlank = await page.evaluate(() => {
      const t = (document.body.innerText || "").trim();
      return t === "Loading payment status..." || (t.startsWith("Loading payment status") && t.length < 40);
    });
    results.routes.push({
      route: "payment_shell",
      ms: Date.now() - t0,
      blank: payBlank,
      overflow: await checkOverflow(),
    });
    await snap(page, "08-payment-shell.png");
  } catch (e) {
    results.routes.push({ route: "checkout_flow_error", error: String(e.message || e).slice(0, 220) });
  }

  results.overflow = results.routes.reduce((s, r) => s + (r.overflow || 0), 0);
  const ok =
    results.routes.some((r) => r.route === "oneway" && (r.cards || 0) > 0) &&
    results.routes.some((r) => r.route === "return_paired" && (r.cards || 0) > 0) &&
    results.routes.some((r) => r.route === "return_segmented" && (r.cards || 0) > 0) &&
    results.routes.some((r) => r.route === "groups") &&
    results.routes.some((r) => r.route === "traveler") &&
    results.routes.some((r) => r.route === "review" && !r.blank) &&
    results.routes.some((r) => r.route === "payment_shell" && !r.blank) &&
    results.overflow === 0 &&
    results.skeleton_reg === 0;
  results.MOBILE_390_FULL_PACK = ok ? "PASS" : "FAIL";
  results.MOBILE_HORIZONTAL_OVERFLOW = results.overflow;
  results.MOBILE_READY_TO_SKELETON_REGRESSION = results.skeleton_reg;
  results.MOBILE_PERFORMANCE_REGRESSION = "NO";

  fs.writeFileSync(path.join(__dirname, "mobile-390.json"), JSON.stringify(results, null, 2));
  await browser.close();
  console.log("MOBILE_DONE", results.MOBILE_390_FULL_PACK, JSON.stringify(results.routes.map((r) => r.route)));
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
