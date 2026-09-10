/**
 * Authority-06 mandatory flight UAT — read-only / safe paths only.
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PROD = "https://jetpakistan.pk";
const OUT = path.join(__dirname, "final-flight-uat.json");
const TRAVELER_N = Number(process.env.JP_TRAVELER_N || 5);
const CARD =
  '[data-testid="flight-result-card"], [data-testid="pair-return-card"], [data-testid="outbound-option-card"]';

const report = { captured_at: new Date().toISOString(), gates: {}, flows: {}, supplier_mutations: [] };

function gate(name, pass, detail = {}) {
  report.gates[name] = { pass, ...detail };
}

function dates() {
  const d = new Date(Date.UTC(2026, 9, 20));
  const r = new Date(Date.UTC(2026, 9, 27));
  return { depart: d.toISOString().slice(0, 10), ret: r.toISOString().slice(0, 10) };
}

async function initSearch(page, criteria) {
  const res = await page.request.get(`${PROD}/laravel/flights/results/search?${criteria}&_=${Date.now()}`, {
    timeout: 60000,
  });
  const json = await res.json();
  return json?.search_id ?? null;
}

function trackMutations(page) {
  page.on("request", (req) => {
    const u = req.url();
    const m = req.method();
    if (m !== "POST") return;
    if (/(createBooking|createPnr|ticket|cancel|refund|payment\/confirm|checkout\/confirm)/i.test(u)) {
      report.supplier_mutations.push({ url: u.slice(0, 160), method: m });
    }
  });
}

async function waitResults(page, timeout = 120000) {
  await page.waitForSelector(CARD, { timeout });
  return page.locator(CARD).count();
}

async function openBrandedFare(page) {
  const book = page.locator('[data-testid="book-now-trigger"], [data-testid="pair-select"]').first();
  await book.waitFor({ state: "visible", timeout: 45000 });
  await book.click({ timeout: 15000 });
  await page.locator('[data-testid="flight-details-drawer"], [data-fare-family-card]').first().waitFor({ timeout: 45000 }).catch(() => null);
  const carousel = await page.locator('[data-testid="branded-fare-carousel"]').count();
  const fareCards = await page.locator("[data-fare-family-card]").count();
  const fareBreakdown =
    (await page.locator('[data-testid="fare-breakdown"]').count()) +
    (await page.locator('[data-testid="fare-summary"]').count());
  const baggage = await page.getByText(/baggage|carry-on|checked/i).count();
  const loading = await page.locator('[data-testid="fare-processing-transition"]').count();
  const price = await page.locator('[data-testid="fare-total"], [data-testid="total-price"]').first().textContent().catch(() => "");
  const continueBtn = await page.locator('[data-testid="continue-to-passengers"]').count();
  return {
    branded_visible: carousel > 0 || fareCards > 0 || continueBtn > 0,
    fare_cards: fareCards,
    fare_breakdown: fareBreakdown > 0 || fareCards > 0,
    baggage_policy: baggage > 0,
    terminal_loading_seen: loading > 0,
    price_text: (price || "").trim().slice(0, 40),
  };
}

async function oneWayUat(browser) {
  const { depart } = dates();
  const criteria = `from=ISB&to=DXB&depart=${depart}&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0&sort=cheapest`;
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  trackMutations(page);
  const steps = {};
  try {
    await page.goto(PROD, { waitUntil: "domcontentloaded", timeout: 120000 });
    const searchId = await initSearch(page, criteria);
    await page.goto(`${PROD}/flights/results?${criteria}${searchId ? `&search_id=${searchId}` : ""}`, {
      waitUntil: "domcontentloaded",
      timeout: 120000,
    });
    steps.results_count = await waitResults(page);
    steps.branded = await openBrandedFare(page);
    steps.url = page.url();
    steps.pass = steps.results_count > 0 && steps.branded.branded_visible && steps.branded.fare_breakdown;
  } catch (e) {
    steps.error = String(e?.message || e).slice(0, 200);
    steps.pass = false;
  }
  await ctx.close();
  return steps;
}

async function returnSegmentedUat(browser) {
  const { depart, ret } = dates();
  const criteria =
    `from=ISB&to=DXB&depart=${depart}&return_date=${ret}` +
    `&trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&view=segmented`;
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  trackMutations(page);
  const steps = {};
  try {
    await page.goto(PROD, { waitUntil: "domcontentloaded", timeout: 120000 });
    const searchId = await initSearch(page, criteria);
    await page.goto(`${PROD}/flights/results?${criteria}${searchId ? `&search_id=${searchId}` : ""}`, {
      waitUntil: "domcontentloaded",
      timeout: 120000,
    });
    steps.view_param = page.url().includes("view=segmented") ? "segmented" : "other";
    steps.outbound_count = await waitResults(page);
    const segBtn = page.getByTestId("return-view-segmented");
    if (await segBtn.count()) await segBtn.click({ timeout: 10000 }).catch(() => null);
    const outboundCard = page.locator('[data-testid="outbound-option-card"]').first();
    await outboundCard.waitFor({ state: "visible", timeout: 60000 });
    await page.locator('[data-testid="outbound-book-now"]').first().click({ timeout: 15000 });
    await page.waitForSelector('[data-testid="segmented-progress"], [data-testid="return-option-card"]', { timeout: 60000 });
    await page.waitForTimeout(2000);
    steps.return_step = page.url();
    const returnCards = await page.locator('[data-testid="return-option-card"], [data-testid="flight-result-card"]').count();
    steps.return_count = returnCards;
    if (returnCards > 0) {
      const retBtn = page.locator('[data-testid="return-select"], [data-testid="book-now-trigger"]').first();
      if (await retBtn.count()) await retBtn.click({ timeout: 15000 });
    }
    steps.branded = await openBrandedFare(page).catch(() => ({ branded_visible: false }));
    const bodyText = await page.locator("body").innerText();
    steps.pair_leakage = /paired return|view=pair/i.test(bodyText) && steps.view_param === "segmented";
    steps.pass =
      steps.outbound_count > 0 &&
      !steps.pair_leakage &&
      (steps.branded?.branded_visible || steps.return_count > 0);
  } catch (e) {
    steps.error = String(e?.message || e).slice(0, 200);
    steps.pass = false;
  }
  await ctx.close();
  return steps;
}

async function travelerSample(browser, attempt) {
  const { depart, ret } = dates();
  const criteria =
    `from=ISB&to=DXB&depart=${depart}&return_date=${ret}` +
    `&trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&view=pair`;
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  const sample = { attempt, valid: false, rematch: 0 };
  page.on("request", (req) => {
    if (/revalidate-offer/i.test(req.url()) && req.method() === "POST") sample.rematch += 1;
    if (req.method() === "POST" && /(createBooking|ticket|payment)/i.test(req.url())) {
      report.supplier_mutations.push({ url: req.url().slice(0, 120) });
    }
  });
  try {
    await page.goto(PROD, { waitUntil: "domcontentloaded", timeout: 120000 });
    const searchId = await initSearch(page, criteria);
    await page.goto(`${PROD}/flights/results?${criteria}${searchId ? `&search_id=${searchId}` : ""}`, {
      waitUntil: "domcontentloaded",
      timeout: 120000,
    });
    await page.waitForSelector(CARD, { timeout: 120000 });
    await page.locator('[data-testid="pair-select"], [data-testid="book-now-trigger"]').first().click({ timeout: 15000 });
    const cont = page.locator('[data-testid="continue-to-passengers"]');
    await cont.first().waitFor({ state: "visible", timeout: 45000 });
    await cont.first().waitFor({ state: "visible", timeout: 60000 });
    await page.waitForFunction(
      () => {
        const btn = document.querySelector('[data-testid="continue-to-passengers"]');
        return btn && !btn.disabled && btn.getAttribute("aria-busy") !== "true";
      },
      { timeout: 60000 },
    );
    await cont.first().click({ timeout: 15000 });
    await page.waitForURL(/\/booking\/(passengers|account-required)/, { waitUntil: "commit", timeout: 120000 });
    if (/account-required/.test(page.url())) {
      sample.note = "account_gate";
      await ctx.close();
      return sample;
    }
    await page.waitForFunction(
      () =>
        !!document.querySelector('[data-testid="save-and-continue"]') ||
        !!document.querySelector('input[name*="first" i]'),
      { timeout: 90000 },
    );
    sample.preview =
      (await page.locator('[data-testid="flight-preview"]').count()) +
      (await page.locator('[data-testid="itinerary-summary"]').count());
    sample.fare =
      (await page.locator('[data-testid="fare-summary"]').count()) +
      (await page.getByText(/PKR|total/i).count());
    sample.save_continue = await page.locator('[data-testid="save-and-continue"]').count();
    sample.duplicate_revalidation = sample.rematch > 2;
    sample.valid = sample.preview > 0 || sample.save_continue > 0;
    sample.url = page.url();
  } catch (e) {
    sample.error = String(e?.message || e).slice(0, 200);
  }
  await ctx.close();
  return sample;
}

async function checkoutSafeUat(browser) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  trackMutations(page);
  const steps = {};
  try {
    const { depart, ret } = dates();
    const criteria =
      `from=ISB&to=DXB&depart=${depart}&return_date=${ret}` +
      `&trip_type=round_trip&cabin=economy&adults=1&sort=cheapest&view=pair`;
    await page.goto(PROD, { waitUntil: "domcontentloaded", timeout: 120000 });
    const searchId = await initSearch(page, criteria);
    await page.goto(`${PROD}/flights/results?${criteria}${searchId ? `&search_id=${searchId}` : ""}`, {
      waitUntil: "domcontentloaded",
      timeout: 120000,
    });
    await page.waitForSelector(CARD, { timeout: 120000 });
    await page.locator('[data-testid="pair-select"]').first().click({ timeout: 15000 });
    const cont = page.locator('[data-testid="continue-to-passengers"]');
    await cont.first().waitFor({ state: "visible", timeout: 60000 });
    await page.waitForFunction(
      () => {
        const btn = document.querySelector('[data-testid="continue-to-passengers"]');
        return btn && !btn.disabled && btn.getAttribute("aria-busy") !== "true";
      },
      { timeout: 60000 },
    );
    await cont.first().click({ timeout: 15000 });
    await page.waitForURL(/\/booking\/passengers/, { waitUntil: "commit", timeout: 120000 });
    await page.locator('input[name*="first" i], input[autocomplete="given-name"]').first().fill("QA");
    await page.locator('input[name*="last" i], input[autocomplete="family-name"]').first().fill("Traveler");
    const gender = page.locator('select[name*="gender" i]').first();
    if (await gender.count()) await gender.selectOption({ index: 1 }).catch(() => null);
    const dob = page.locator('input[name*="birth" i], input[type="date"]').first();
    if (await dob.count()) await dob.fill("1990-01-15").catch(() => null);
    await page.locator('[data-testid="terms-acceptance-checkbox"]').check({ timeout: 10000 }).catch(() => null);
    const save = page.locator('[data-testid="save-and-continue"]');
    await page.waitForFunction(
      () => {
        const btn = document.querySelector('[data-testid="save-and-continue"]');
        return btn && !btn.disabled;
      },
      { timeout: 60000 },
    );
    await save.first().click({ timeout: 30000 });
    await page.waitForURL(/\/booking\/review/, { waitUntil: "commit", timeout: 120000 });
    steps.review_url = page.url();
    steps.change_flight = await page.locator('text=/Change Flight|change flight/i').count();
    steps.fare_breakdown = await page.locator('[data-testid="fare-breakdown"], [data-testid="booking-review-page"]').count();
    steps.flight_preview = await page.locator('[data-testid="flight-preview"], [data-testid="itinerary-summary"]').count();
    steps.baggage = await page.locator('text=/baggage|fare rules/i').count();
    const back = page.locator('text=/Back|Change Flight/i').first();
    if (await back.count()) await back.click({ timeout: 10000 }).catch(() => null);
    steps.back_ok = /passengers|results/.test(page.url());
    steps.pass =
      steps.review_url.includes("/booking/review") &&
      steps.fare_breakdown > 0 &&
      steps.flight_preview > 0 &&
      report.supplier_mutations.length === 0;
  } catch (e) {
    steps.error = String(e?.message || e).slice(0, 200);
    steps.pass = false;
  }
  await ctx.close();
  return steps;
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  report.flows.one_way = await oneWayUat(browser);
  gate("ONE_WAY_UAT", report.flows.one_way.pass === true, report.flows.one_way);

  report.flows.return_segmented = await returnSegmentedUat(browser);
  gate("RETURN_SEGMENTED_UAT", report.flows.return_segmented.pass === true, report.flows.return_segmented);

  const travelerSamples = [];
  for (let i = 0; i < TRAVELER_N; i += 1) travelerSamples.push(await travelerSample(browser, i));
  const travelerValid = travelerSamples.filter((s) => s.valid);
  report.flows.traveler = { n: TRAVELER_N, valid: travelerValid.length, samples: travelerSamples };
  gate("TRAVELER_UAT", travelerValid.length >= Math.min(3, TRAVELER_N), report.flows.traveler);

  report.flows.checkout_safe = await checkoutSafeUat(browser);
  gate("CHECKOUT_SAFE_UAT", report.flows.checkout_safe.pass === true, report.flows.checkout_safe);

  gate("SUPPLIER_MUTATION_CALLS_ZERO", report.supplier_mutations.length === 0, {
    count: report.supplier_mutations.length,
    items: report.supplier_mutations,
  });

  report.result = Object.values(report.gates).every((g) => g.pass) ? "PASS" : "PARTIAL";
  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ result: report.result, gates: report.gates }, null, 2));
  await browser.close();
  process.exit(report.result === "PASS" ? 0 : 1);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
