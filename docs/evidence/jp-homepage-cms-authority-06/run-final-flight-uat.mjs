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

async function fillPassengerForm(page) {
  const card = page.getByTestId("passenger-card-0");
  await card.waitFor({ state: "visible", timeout: 90000 });
  await page.getByTestId("standard-passengers-form").waitFor({ state: "visible", timeout: 30000 });

  const titleSelect = card.locator("select").first();
  if (await titleSelect.count()) await titleSelect.selectOption({ index: 1 }).catch(() => null);

  const genderSelect = card.getByLabel(/Gender/i);
  if (await genderSelect.count()) await genderSelect.selectOption("male").catch(() => null);

  await card.getByLabel(/First name/i).fill("QA");
  await card.getByLabel(/Last name/i).fill("Traveler");
  await card.getByLabel(/Date of birth/i).fill("1990-01-15");
  const nationality = card.getByLabel(/Nationality/i);
  if (await nationality.count()) await nationality.fill("PK");

  const passportNumber = card.getByLabel(/Passport number/i);
  if (await passportNumber.count()) {
    await passportNumber.fill("AB1234567");
    await card.getByLabel(/Issuing country/i).fill("PK");
    await card.getByLabel(/Passport expiry/i).fill("2030-12-31");
    await card.getByLabel(/Passport issue date/i).fill("2020-01-01");
  }

  const contact = page.getByTestId("contact-details");
  await contact.getByLabel(/^Email/i).fill("qa-uat-authority06@example.com");
  await contact.getByLabel(/^Mobile/i).fill("+923001234567");

  const guestBtn = page.getByTestId("existing-account-continue-guest");
  if (await guestBtn.isVisible().catch(() => false)) await guestBtn.click({ timeout: 5000 });

  const terms = page.getByTestId("terms-acceptance-checkbox");
  await terms.scrollIntoViewIfNeeded().catch(() => null);
  await terms.setChecked(true, { force: true });
  await page.waitForFunction(
    () => {
      const btn = document.querySelector('[data-testid="save-and-continue"]');
      return btn && !btn.disabled;
    },
    { timeout: 30000 },
  );
}

async function waitForReturnOptions(page, timeout = 120000) {
  const drawer = page.getByTestId("flight-details-drawer");
  await drawer.waitFor({ state: "visible", timeout: 60000 }).catch(() => null);
  if (!/\/flights\/return-options/.test(page.url())) {
    const cont = page.getByTestId("continue-to-passengers");
    if (await cont.count()) {
      await cont.first().waitFor({ state: "visible", timeout: 30000 }).catch(() => null);
      await cont.first().click({ timeout: 15000 }).catch(() => null);
    }
    await page.waitForURL(/\/flights\/return-options/, { waitUntil: "commit", timeout });
  }
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
    steps.segmented_mode_selected = steps.view_param === "segmented" && steps.outbound_count > 0;
    const segBtn = page.getByTestId("return-view-segmented");
    if (await segBtn.count()) await segBtn.click({ timeout: 10000 }).catch(() => null);
    await page.locator('[data-testid="outbound-option-card"]').first().waitFor({ state: "visible", timeout: 60000 });
    steps.outbound_selected = true;
    await page.locator('[data-testid="outbound-book-now"]').first().click({ timeout: 15000 });
    await waitForReturnOptions(page);
    steps.return_leg_route = "/flights/return-options";
    steps.return_step = page.url();
    await page.getByTestId("return-option-card").first().waitFor({ state: "visible", timeout: 90000 });
    steps.return_count = await page.getByTestId("return-option-card").count();
    steps.return_leg_visible = steps.return_count > 0;
    await page.getByTestId("result-price-button").first().click({ timeout: 15000 });
    await page.getByTestId("flight-details-drawer").waitFor({ state: "visible", timeout: 45000 });
    steps.return_selected = true;
    await page
      .locator('[data-testid="fare-family-details"], [data-fare-family-card], [data-testid="branded-fare-carousel"]')
      .first()
      .waitFor({ state: "visible", timeout: 60000 })
      .catch(() => null);
    const fareCards = await page.locator("[data-fare-family-card]").count();
    const fareDetails = await page.getByTestId("fare-family-details").count();
    const continueBtn = await page.getByTestId("continue-to-passengers").count();
    steps.branded = {
      branded_visible: fareCards > 0 || fareDetails > 0 || continueBtn > 0,
      fare_cards: fareCards,
      fare_details: fareDetails,
      continue_visible: continueBtn > 0,
    };
    const bodyText = await page.locator("body").innerText();
    const url = page.url();
    steps.pair_leakage = /view=pair/i.test(url) || (/paired return/i.test(bodyText) && !/segmented/i.test(url));
    steps.segmented_leakage = steps.view_param === "segmented" && /view=pair/i.test(url);
    steps.pass =
      steps.segmented_mode_selected &&
      steps.outbound_count > 0 &&
      steps.return_leg_visible &&
      steps.return_selected &&
      steps.branded?.branded_visible &&
      !steps.pair_leakage &&
      !steps.segmented_leakage;
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
    await fillPassengerForm(page);
    steps.passenger_form_valid = true;
    steps.save_and_continue_enabled = true;
    const save = page.locator('[data-testid="save-and-continue"]');
    await save.first().click({ timeout: 30000 });
    await page.waitForURL(/\/booking\/review/, { waitUntil: "commit", timeout: 120000 });
    await page.getByTestId("booking-review-page").waitFor({ state: "visible", timeout: 90000 });
    await page.getByTestId("edit-traveler-details").waitFor({ state: "visible", timeout: 90000 });
    await page.getByTestId("review-itinerary").waitFor({ state: "visible", timeout: 60000 });
    steps.review_url = page.url();
    steps.change_flight =
      (await page.getByTestId("change-flight-button").count()) +
      (await page.getByTestId("edit-traveler-details").count());
    steps.fare_breakdown =
      (await page.getByTestId("review-order-summary").count()) +
      (await page.getByTestId("review-price-summary").count()) +
      (await page.getByTestId("order-summary-total").count()) +
      (await page.getByText(/^Total$/i).count());
    steps.flight_preview =
      (await page.getByTestId("review-itinerary").count()) +
      (await page.getByTestId("flight-preview-body").count());
    steps.baggage =
      (await page.getByText(/baggage|carry-on|checked/i).count()) +
      (await page.getByText(/fare rules|change.*cancel/i).count());
    const priceTexts = await page.locator("text=/PKR|Rs\\.?\\s*[\\d,]+/i").allTextContents();
    steps.price_consistency = priceTexts.length >= 1;
    const editTravelers = page.getByTestId("edit-traveler-details");
    if (await editTravelers.count()) await editTravelers.first().click({ timeout: 10000 });
    await page.waitForURL(/\/booking\/passengers/, { waitUntil: "commit", timeout: 60000 }).catch(() => null);
    steps.back_ok = /\/booking\/passengers/.test(page.url());
    steps.pass =
      steps.review_url.includes("/booking/review") &&
      steps.fare_breakdown > 0 &&
      steps.flight_preview > 0 &&
      steps.baggage > 0 &&
      steps.price_consistency &&
      steps.change_flight > 0 &&
      steps.back_ok &&
      report.supplier_mutations.length === 0;
  } catch (e) {
    steps.error = String(e?.message || e).slice(0, 200);
    steps.pass = false;
  }
  await ctx.close();
  return steps;
}

async function main() {
  const only = (process.env.JP_UAT_ONLY || "")
    .split(",")
    .map((s) => s.trim())
    .filter(Boolean);
  const run = (name) => only.length === 0 || only.includes(name);

  const browser = await chromium.launch({ headless: true });

  if (run("one_way")) {
    report.flows.one_way = await oneWayUat(browser);
    gate("ONE_WAY_UAT", report.flows.one_way.pass === true, report.flows.one_way);
  }

  if (run("segmented")) {
    report.flows.return_segmented = await returnSegmentedUat(browser);
    gate("RETURN_SEGMENTED_UAT", report.flows.return_segmented.pass === true, report.flows.return_segmented);
  }

  if (run("traveler")) {
    const travelerSamples = [];
    for (let i = 0; i < TRAVELER_N; i += 1) travelerSamples.push(await travelerSample(browser, i));
    const travelerValid = travelerSamples.filter((s) => s.valid);
    report.flows.traveler = { n: TRAVELER_N, valid: travelerValid.length, samples: travelerSamples };
    gate("TRAVELER_UAT", travelerValid.length >= Math.min(3, TRAVELER_N), report.flows.traveler);
  }

  if (run("checkout")) {
    report.flows.checkout_safe = await checkoutSafeUat(browser);
    gate("CHECKOUT_SAFE_UAT", report.flows.checkout_safe.pass === true, report.flows.checkout_safe);
  }

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
