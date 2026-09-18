/**
 * CORRECTION-08 — search-driven Golden flight visual capture (QA-safe).
 * Creates live Laravel search context, then drives UI through results → review.
 * Never submits payment / creates PNR / tickets.
 *
 * Env: RELEASE_SHA, PUBLIC_BUILD_ID, DASHBOARD_BUILD_ID (optional)
 */
import { createRequire } from "module";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import { spawnSync } from "child_process";
import {
  stabilizeFullPage,
  waitForStableText,
  assertNoRejectState,
  assertPositiveRoute,
  measureOverflow,
  measureFabOverlap,
  verdictFromParts,
} from "./lib/stable-capture.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("../../../frontend/node_modules/playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outRoot = path.join(__dirname, "live");
const releaseSha = process.env.RELEASE_SHA || "";
const publicBuildId = process.env.PUBLIC_BUILD_ID || "unknown";
const baseURL = "https://jetpakistan.pk";
const sshKey = process.env.JP_SSH_KEY || `${process.env.USERPROFILE}\\.ssh\\jetpk_contabo_2026_v2`;
const widths = [390, 1440];

if (!releaseSha) {
  console.error("Missing RELEASE_SHA");
  process.exit(2);
}

function ssh(cmd) {
  const r = spawnSync("ssh", ["-i", sshKey, "-o", "BatchMode=yes", "root@185.215.166.176", cmd], {
    encoding: "utf8",
    maxBuffer: 8_000_000,
  });
  if (r.status !== 0) throw new Error(r.stderr || r.stdout || "ssh failed");
  return r.stdout;
}

function initSearch({ trip, depart, ret }) {
  const retArg = ret || "''";
  const out = ssh(
    `bash /tmp/jp-c08-fs-init.sh LHE DXB ${depart} ${retArg} ${trip} /tmp/jp-c08-fs-${trip}.json`,
  );
  const searchId = (out.match(/SEARCH_ID=(.+)/) || [])[1]?.trim();
  const resultsUrl = (out.match(/RESULTS_URL=(.+)/) || [])[1]?.trim();
  if (!searchId || !resultsUrl) throw new Error(`search init failed:\n${out}`);
  return { searchId, resultsUrl, raw: out };
}

async function shot(page, file, dir) {
  const abs = path.join(outRoot, dir, file);
  fs.mkdirSync(path.dirname(abs), { recursive: true });
  await page.screenshot({ path: abs, fullPage: true });
  return `${dir}/${file}`;
}

async function waitResultsLoaded(page) {
  await page
    .waitForFunction(
      () => {
        const t = document.body?.innerText || "";
        if (/Unable to load results|Missing search details|Please start a new search/i.test(t)) return true;
        return (
          document.querySelectorAll(
            "[data-testid='pair-return-card'], [data-testid*='flight-card'], [data-testid*='offer'], button, a",
          ).length > 5 && !/Loading results|Searching/i.test(t)
        );
      },
      { timeout: 90000 },
    )
    .catch(() => {});
  await page.waitForTimeout(1500);
}

async function captureState(page, opts) {
  const { key, state, positiveKey, width, extraMetrics } = opts;
  await page.setViewportSize({ width, height: width < 768 ? 844 : 900 });
  await stabilizeFullPage(page);
  const stable = await waitForStableText(page, { timeout: 25000 });
  const reject = await assertNoRejectState(page);
  const positive = await assertPositiveRoute(page, positiveKey);
  const ox = await measureOverflow(page);
  const fab = await measureFabOverlap(page);
  const body = await page.evaluate(() => (document.body?.innerText || "").slice(0, 800));
  const metrics = await page.evaluate(() => {
    const text = document.body?.innerText || "";
    const pairCards = document.querySelectorAll("[data-testid='pair-return-card']").length;
    const bookBtns = Array.from(document.querySelectorAll("button, a")).filter((el) =>
      /Book Now|Select fare|Continue|Select return/i.test(el.textContent || ""),
    ).length;
    const hasOutbound = /Outbound/i.test(text);
    const hasReturn = /\bReturn\b/i.test(text);
    const hasTotal = /Total|PKR/i.test(text);
    return {
      RESULT_COUNT: pairCards || bookBtns,
      PAIR_OUTBOUND_VISIBLE: hasOutbound,
      PAIR_RETURN_VISIBLE: hasReturn,
      PAIR_TOTAL_VISIBLE: hasTotal,
      PAIR_ACTION_VISIBLE: bookBtns > 0,
      emptyError: /Unable to load results|Missing search details|No flight selected|Please start a new search/i.test(
        text,
      ),
    };
  });
  const parts = [
    { ok: stable.ok && reject.ok, reason: stable.reason || reject.reason },
    { ok: positive.ok, reason: positive.ok ? null : positive.fails.join(",") },
    { ok: !metrics.emptyError, reason: metrics.emptyError ? "empty_or_error_shell" : null },
    { ok: ox.overflowX === 0, reason: ox.overflowX ? `ox=${ox.overflowX}` : null },
  ];
  const v = verdictFromParts(parts);
  const file = await shot(page, `${key}-w${width}.png`, "flights");
  return {
    FILE: file,
    URL_ROUTE: page.url().replace(baseURL, ""),
    VIEWPORT: width,
    ROLE: "anonymous",
    STATE: state,
    PUBLIC_BUILD_ID: publicBuildId,
    RELEASE_SHA: releaseSha,
    SOURCE: "live production",
    NOTES: v.NOTES,
    SANITIZED: "YES",
    SELF_REVIEW: v.SELF_REVIEW,
    METRICS: { ...metrics, ...(extraMetrics || {}), fab, ox, positive, bodyPreview: body.slice(0, 200) },
  };
}

async function clickFirst(page, patterns) {
  for (const re of patterns) {
    const loc = page.getByRole("button", { name: re }).first();
    if (await loc.count()) {
      await loc.click({ timeout: 10000 }).catch(() => {});
      return true;
    }
    const link = page.getByRole("link", { name: re }).first();
    if (await link.count()) {
      await link.click({ timeout: 10000 }).catch(() => {});
      return true;
    }
    const text = page.getByText(re).first();
    if (await text.count()) {
      await text.click({ timeout: 10000 }).catch(() => {});
      return true;
    }
  }
  return false;
}

const rows = [];
const summary = {
  ONE_WAY_RESULT_COUNT: 0,
  PAIR_RESULT_COUNT: 0,
  SEGMENTED_OUTBOUND_RESULTS: 0,
  SEGMENTED_RETURN_RESULTS: 0,
  PAYMENT_EXECUTED: "NO",
  PNR_CREATED: "NO",
  ORDER_CREATED: "NO",
  TICKET_ISSUED: "NO",
};

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
  viewport: { width: 1440, height: 900 },
  userAgent:
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 JetPakistanVisualUAT",
});
const page = await context.newPage();

// --- ONE WAY ---
const ow = initSearch({ trip: "one_way", depart: "2026-10-20" });
console.log("OW", ow.searchId, ow.resultsUrl);
await page.goto(`${baseURL}${ow.resultsUrl}&search_id=${ow.searchId}`, {
  waitUntil: "domcontentloaded",
  timeout: 90000,
});
await waitResultsLoaded(page);
for (const w of widths) {
  const row = await captureState(page, {
    key: "one-way-results",
    state: "ONE_WAY_RESULTS",
    positiveKey: "one-way-results",
    width: w,
  });
  rows.push(row);
  summary.ONE_WAY_RESULT_COUNT = Math.max(summary.ONE_WAY_RESULT_COUNT, row.METRICS.RESULT_COUNT || 0);
  console.log("one-way", w, row.SELF_REVIEW, "cards", row.METRICS.RESULT_COUNT);
}

// Open details / baggage / fare from first card (390 viewport)
await page.setViewportSize({ width: 390, height: 844 });
await page.goto(`${baseURL}${ow.resultsUrl}&search_id=${ow.searchId}`, { waitUntil: "domcontentloaded", timeout: 90000 });
await waitResultsLoaded(page);

const panelStates = [
  { key: "details", state: "DETAILS", positiveKey: "flight-details", patterns: [/^Details$/i, /Flight details/i] },
  { key: "baggage", state: "BAGGAGE_POLICY", positiveKey: "baggage", patterns: [/^Baggage$/i, /Baggage policy/i] },
  { key: "fare-policy", state: "FARE_POLICY", positiveKey: "fare-policy", patterns: [/Fare Policy/i, /Fare rules/i] },
  { key: "fare-details", state: "FARE_DETAILS", positiveKey: "fare-details", patterns: [/Fare Details/i, /Price breakdown/i] },
];

for (const panel of panelStates) {
  const opened = await clickFirst(page, panel.patterns);
  await page.waitForTimeout(800);
  for (const w of widths) {
    if (w !== 390) await page.setViewportSize({ width: w, height: 900 });
    const row = await captureState(page, {
      key: panel.key,
      state: panel.state,
      positiveKey: panel.positiveKey,
      width: w,
      extraMetrics: { panelOpened: opened },
    });
    rows.push(row);
    console.log(panel.key, w, row.SELF_REVIEW);
  }
  await page.keyboard.press("Escape").catch(() => {});
  await clickFirst(page, [/^Close$/i, /Dismiss/i]).catch(() => {});
  await page.waitForTimeout(400);
}

// Branded fare if available
const brandedOpened = await clickFirst(page, [/Select fare/i, /Branded/i, /Choose fare/i, /View fares/i]);
await page.waitForTimeout(1000);
for (const w of widths) {
  await page.setViewportSize({ width: w, height: w < 768 ? 844 : 900 });
  const row = await captureState(page, {
    key: "branded-fare",
    state: "BRANDED_FARE",
    positiveKey: "branded-fare",
    width: w,
    extraMetrics: { brandedOpened },
  });
  rows.push(row);
  console.log("branded", w, row.SELF_REVIEW);
}
await page.keyboard.press("Escape").catch(() => {});

// Traveler via Book Now (stop before payment)
await page.goto(`${baseURL}${ow.resultsUrl}&search_id=${ow.searchId}`, { waitUntil: "domcontentloaded", timeout: 90000 });
await waitResultsLoaded(page);
const booked = await clickFirst(page, [/Book Now/i]);
await page.waitForTimeout(2500);
if (booked) {
  // may land on branded first — pick first selectable fare then continue
  await clickFirst(page, [/Select$/i, /Continue/i, /Choose/i]);
  await page.waitForTimeout(2000);
}
for (const w of widths) {
  const onTraveler = /passengers|traveler/i.test(page.url());
  if (!onTraveler) {
    // force fail capture on current page
  }
  const row = await captureState(page, {
    key: "traveler",
    state: "TRAVELER",
    positiveKey: "traveler",
    width: w,
  });
  rows.push(row);
  console.log("traveler", w, row.SELF_REVIEW, page.url());
}

// Fill synthetic QA passenger + go to review (no payment)
async function fillTravelerAndReview() {
  const first = page.locator("input[name*='first' i], input[id*='first' i]").first();
  if (await first.count()) await first.fill("QA");
  const last = page.locator("input[name*='last' i], input[id*='last' i]").first();
  if (await last.count()) await last.fill("Traveler");
  const email = page.locator("input[type='email'], input[name*='email' i]").first();
  if (await email.count()) await email.fill("qa.flight.visual@jetpakistan.pk");
  const phone = page.locator("input[type='tel'], input[name*='phone' i]").first();
  if (await phone.count()) await phone.fill("+923001112233");
  await clickFirst(page, [/Continue to review/i, /Continue/i, /Review/i, /Next/i]);
  await page.waitForTimeout(2500);
}

if (/passengers|traveler/i.test(page.url())) {
  await fillTravelerAndReview();
}
for (const w of widths) {
  const row = await captureState(page, {
    key: "review",
    state: "REVIEW",
    positiveKey: "review",
    width: w,
  });
  rows.push(row);
  console.log("review", w, row.SELF_REVIEW, page.url());
}

// --- RETURN PAIR ---
const rt = initSearch({ trip: "round_trip", depart: "2026-10-20", ret: "2026-10-27" });
console.log("RT", rt.searchId, rt.resultsUrl);
const pairUrl = `${baseURL}${rt.resultsUrl}&search_id=${rt.searchId}&view=pair`;
await page.goto(pairUrl, { waitUntil: "domcontentloaded", timeout: 90000 });
await waitResultsLoaded(page);
// Prefer pair toggle if present
await clickFirst(page, [/Pair/i, /Combined/i]);
await page.waitForTimeout(1500);
for (const w of widths) {
  const row = await captureState(page, {
    key: "return-pair",
    state: "RETURN_PAIR",
    positiveKey: "return-pair",
    width: w,
  });
  rows.push(row);
  summary.PAIR_RESULT_COUNT = Math.max(summary.PAIR_RESULT_COUNT, row.METRICS.RESULT_COUNT || 0);
  console.log("pair", w, row.SELF_REVIEW, row.METRICS);
}

// --- SEGMENTED ---
const segUrl = `${baseURL}${rt.resultsUrl}&search_id=${rt.searchId}&view=segmented`;
await page.goto(segUrl, { waitUntil: "domcontentloaded", timeout: 90000 });
await waitResultsLoaded(page);
await clickFirst(page, [/Segmented/i, /Select separately/i]);
await page.waitForTimeout(1500);
for (const w of widths) {
  const row = await captureState(page, {
    key: "return-segmented-outbound",
    state: "RETURN_SEGMENTED_OUTBOUND",
    positiveKey: "return-segmented",
    width: w,
  });
  rows.push(row);
  summary.SEGMENTED_OUTBOUND_RESULTS = Math.max(
    summary.SEGMENTED_OUTBOUND_RESULTS,
    row.METRICS.RESULT_COUNT || 0,
  );
  console.log("seg-out", w, row.SELF_REVIEW);
}

// Select first outbound then capture return-options
await page.setViewportSize({ width: 390, height: 844 });
await page.goto(segUrl, { waitUntil: "domcontentloaded", timeout: 90000 });
await waitResultsLoaded(page);
await clickFirst(page, [/Select$/i, /Select flight/i, /Continue/i]);
await page.waitForTimeout(3000);
for (const w of widths) {
  const row = await captureState(page, {
    key: "return-segmented-return",
    state: "RETURN_SEGMENTED_RETURN",
    positiveKey: "return-segmented",
    width: w,
  });
  rows.push(row);
  summary.SEGMENTED_RETURN_RESULTS = Math.max(summary.SEGMENTED_RETURN_RESULTS, row.METRICS.RESULT_COUNT || 0);
  console.log("seg-ret", w, row.SELF_REVIEW, page.url());
}

await browser.close();

const fails = rows.filter((r) => String(r.SELF_REVIEW).startsWith("FAIL")).length;
const manifest = {
  releaseSha,
  publicBuildId,
  harness: "correction-08-golden-flights",
  ...summary,
  rows,
  fails,
};
fs.writeFileSync(path.join(__dirname, "manifest-golden-flights-live.json"), JSON.stringify(manifest, null, 2));
console.log(JSON.stringify({ total: rows.length, fails, ...summary }, null, 2));
process.exit(fails ? 1 : 0);
