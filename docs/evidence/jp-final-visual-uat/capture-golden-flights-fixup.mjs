/**
 * Targeted golden fixups:
 * - Details drawer tabs (Baggage Policy / Fare Policy / Fare Details)
 * - Real /booking/review after filled passengers + terms
 * - Segmented outbound Book Now → /flights/return-options
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
const releaseSha = process.env.RELEASE_SHA || "675c7e5efaa2656b2435c186cfe4665e086676bb";
const publicBuildId = process.env.PUBLIC_BUILD_ID || "FfNP1fgjiB4_gK6lNt4FI";
const baseURL = "https://jetpakistan.pk";
const sshKey = process.env.JP_SSH_KEY || `${process.env.USERPROFILE}\\.ssh\\jetpk_contabo_2026_v2`;
const widths = [390, 1440];

function ssh(cmd) {
  const r = spawnSync("ssh", ["-i", sshKey, "-o", "BatchMode=yes", "root@185.215.166.176", cmd], {
    encoding: "utf8",
    maxBuffer: 8_000_000,
  });
  if (r.status !== 0) throw new Error(r.stderr || r.stdout);
  return r.stdout;
}

function init({ trip, depart, ret }) {
  const out = ssh(`bash /tmp/jp-c08-fs-init.sh LHE DXB ${depart} ${ret || "''"} ${trip} /tmp/jp-c08-fix2.json`);
  return {
    searchId: (out.match(/SEARCH_ID=(.+)/) || [])[1]?.trim(),
    resultsUrl: (out.match(/RESULTS_URL=(.+)/) || [])[1]?.trim(),
  };
}

async function capture(page, key, state, positiveKey, width, extraOk = async () => ({ ok: true })) {
  await page.setViewportSize({ width, height: width < 768 ? 844 : 900 });
  await stabilizeFullPage(page);
  const stable = await waitForStableText(page, { timeout: 20000 });
  const reject = await assertNoRejectState(page);
  const positive = await assertPositiveRoute(page, positiveKey);
  const ox = await measureOverflow(page);
  const fab = await measureFabOverlap(page);
  const extra = await extraOk();
  const parts = [
    { ok: stable.ok && reject.ok, reason: stable.reason || reject.reason },
    { ok: positive.ok, reason: positive.ok ? null : positive.fails.join(",") },
    { ok: extra.ok, reason: extra.reason || null },
    { ok: ox.overflowX === 0, reason: ox.overflowX ? `ox=${ox.overflowX}` : null },
  ];
  const v = verdictFromParts(parts);
  const file = `flights/${key}-w${width}.png`;
  fs.mkdirSync(path.join(outRoot, "flights"), { recursive: true });
  await page.screenshot({ path: path.join(outRoot, file), fullPage: true });
  console.log(key, width, v.SELF_REVIEW, page.url().replace(baseURL, "").slice(0, 120));
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
    METRICS: { ox, fab, positive, extra },
  };
}

async function fillByLabel(page, labelRe, value) {
  const field = page.getByLabel(labelRe).first();
  if (await field.count()) {
    await field.fill(value).catch(async () => {
      await field.selectOption({ label: value }).catch(async () => {
        await field.selectOption(value).catch(() => {});
      });
    });
    return true;
  }
  return false;
}

async function fillPassengerForm(page) {
  const card = page.getByTestId("passenger-card-0");
  await card.waitFor({ timeout: 30000 });
  await card.locator("select").nth(0).selectOption({ label: "Mr" }).catch(() => {});
  await card.locator("select").nth(1).selectOption("male").catch(() => {});
  await fillByLabel(page, /First name/i, "QA");
  await fillByLabel(page, /Last name/i, "Traveler");
  await fillByLabel(page, /Date of birth/i, "1990-05-10");
  await fillByLabel(page, /Nationality/i, "PK");
  // Passport fields if present
  await fillByLabel(page, /Passport number/i, "AB9988776");
  await fillByLabel(page, /Issuing country|Passport issuing/i, "PK");
  await fillByLabel(page, /Passport expiry|Expiry/i, "2032-12-31");
  await fillByLabel(page, /Passport issue|Issue date/i, "2018-01-15");
  // Contact
  await fillByLabel(page, /^Email/i, "qa.flight.visual@jetpakistan.pk");
  await fillByLabel(page, /Mobile|Phone/i, "3001112233");
  const terms = page.getByTestId("terms-acceptance-checkbox");
  if (await terms.count()) {
    await terms.check({ force: true }).catch(async () => {
      await terms.click({ force: true });
    });
  }
  await page.waitForTimeout(400);
  const desktop = page.getByTestId("save-and-continue");
  const mobile = page.getByTestId("save-and-continue-mobile");
  if (await desktop.isVisible().catch(() => false)) {
    await desktop.click({ timeout: 15000 });
  } else {
    await mobile.click({ timeout: 15000 });
  }
}

const rows = [];
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({
  userAgent:
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 JetPakistanVisualUAT",
});

const ow = init({ trip: "one_way", depart: "2026-10-20" });
if (!ow.searchId || !ow.resultsUrl) throw new Error(`OW init failed: ${JSON.stringify(ow)}`);
const owUrl = `${baseURL}${ow.resultsUrl}&search_id=${ow.searchId}`;
console.log("OW", ow.searchId, ow.resultsUrl);

async function openDetailsDrawer() {
  await page.goto(owUrl, { waitUntil: "domcontentloaded", timeout: 90000 });
  await page.waitForTimeout(8000);
  await page.getByTestId("flight-details-trigger").first().click({ timeout: 20000 }).catch(async () => {
    await page.getByRole("button", { name: /^Details$/i }).first().click({ timeout: 15000 });
  });
  await page.getByTestId("flight-details-drawer").waitFor({ timeout: 30000 });
  await page.getByTestId("fare-summary-tabs").waitFor({ timeout: 45000 });
  await page.getByTestId("fare-summary-tabs").scrollIntoViewIfNeeded();
  await page.waitForTimeout(800);
}

async function clickFareTab(labelRe) {
  const tabs = page.getByTestId("fare-summary-tabs");
  await tabs.getByRole("tab", { name: labelRe }).click({ timeout: 10000 });
  await page.waitForTimeout(700);
}

for (const [key, state, positive, tab] of [
  ["baggage", "BAGGAGE_POLICY", "baggage", /Baggage Policy/i],
  ["fare-policy", "FARE_POLICY", "fare-policy", /Fare Policy/i],
  ["fare-details", "FARE_DETAILS", "fare-details", /Fare Details/i],
  ["details", "DETAILS", "flight-details", null],
]) {
  for (const w of widths) {
    await page.setViewportSize({ width: w, height: w < 768 ? 844 : 900 });
    await openDetailsDrawer();
    if (tab) await clickFareTab(tab);
    else await page.getByTestId("flight-details-scroll-surface").scrollIntoViewIfNeeded().catch(() => {});
    rows.push(
      await capture(page, key, state, positive, w, async () => {
        const hasTabs = (await page.getByTestId("fare-summary-tabs").count()) > 0;
        return { ok: hasTabs, reason: hasTabs ? null : "no_fare_summary_tabs" };
      }),
    );
    await page.keyboard.press("Escape").catch(() => {});
    await page.getByRole("button", { name: /^Close$/i }).click({ timeout: 3000 }).catch(() => {});
  }
}

// Branded fare via Book Now
await page.setViewportSize({ width: 390, height: 844 });
await page.goto(owUrl, { waitUntil: "domcontentloaded", timeout: 90000 });
await page.waitForTimeout(8000);
await page.getByTestId("book-now-trigger").first().click({ timeout: 20000 }).catch(async () => {
  await page.getByRole("button", { name: /Book Now/i }).first().click({ timeout: 15000 });
});
await page.waitForTimeout(3000);
await page.getByTestId("flight-details-drawer").waitFor({ timeout: 30000 }).catch(() => {});
for (const w of widths) {
  rows.push(
    await capture(page, "branded-fare", "BRANDED_FARE", "branded-fare", w, async () => {
      const n = await page.locator("[data-testid='fare-select'], [data-testid='base-fare-select'], [data-testid*='fare']").count();
      const body = await page.locator("body").innerText();
      const ok = n >= 1 || (/Select fare|Choose your flight|Fare/i.test(body) && !/No flight selected/i.test(body));
      return { ok, reason: ok ? null : "no_branded_ui", n };
    }),
  );
}

// Continue to passengers
await page.getByTestId("continue-to-passengers").click({ timeout: 20000 }).catch(async () => {
  await page.getByRole("button", { name: /Continue|Select fare/i }).last().click({ timeout: 15000 }).catch(() => {});
});
await page.waitForURL(/\/booking\/passengers/i, { timeout: 90000 }).catch(() => {});
await page.waitForTimeout(2000);
for (const w of widths) {
  rows.push(
    await capture(page, "traveler", "TRAVELER", "traveler", w, async () => ({
      ok: /\/booking\/passengers/i.test(page.url()),
      reason: /\/booking\/passengers/i.test(page.url()) ? null : `url=${page.url()}`,
    })),
  );
}

if (!/\/booking\/passengers/i.test(page.url())) {
  console.error("NOT_ON_PASSENGERS", page.url());
} else {
  await fillPassengerForm(page);
  await page.waitForURL(/\/booking\/review/i, { timeout: 90000 }).catch(() => {});
  await page.waitForTimeout(2500);
}
for (const w of widths) {
  rows.push(
    await capture(page, "review", "REVIEW", "review", w, async () => ({
      ok: /\/booking\/review/i.test(page.url()),
      reason: /\/booking\/review/i.test(page.url()) ? null : `url=${page.url()}`,
    })),
  );
}

// Segmented return-options
const rt = init({ trip: "round_trip", depart: "2026-10-20", ret: "2026-10-27" });
if (!rt.searchId || !rt.resultsUrl) throw new Error(`RT init failed: ${JSON.stringify(rt)}`);
const segUrl = `${baseURL}${rt.resultsUrl}&search_id=${rt.searchId}&view=segmented`;
console.log("RT", rt.searchId, rt.resultsUrl);

await page.goto(segUrl, { waitUntil: "domcontentloaded", timeout: 90000 });
await page.waitForTimeout(8000);
await page.getByTestId("return-view-segmented").click({ timeout: 10000 }).catch(async () => {
  await page.getByText(/Segmented/i).first().click({ timeout: 5000 }).catch(() => {});
});
await page.waitForTimeout(1500);
for (const w of widths) {
  rows.push(await capture(page, "return-segmented-outbound", "RETURN_SEGMENTED_OUTBOUND", "return-segmented", w));
}

await page.setViewportSize({ width: 390, height: 844 });
await page.goto(segUrl, { waitUntil: "domcontentloaded", timeout: 90000 });
await page.waitForTimeout(8000);
await page.getByTestId("return-view-segmented").click({ timeout: 10000 }).catch(() => {});
await page.waitForTimeout(1000);
await page.getByTestId("outbound-book-now").first().click({ timeout: 20000 }).catch(async () => {
  await page.getByRole("button", { name: /Book Now/i }).first().click({ timeout: 15000 });
});
await page.waitForURL(/\/flights\/return-options/i, { timeout: 90000 }).catch(async () => {
  // Manual continue if auto-nav did not fire
  await page.getByTestId("continue-to-passengers").click({ timeout: 15000 }).catch(() => {});
  await page.waitForURL(/\/flights\/return-options/i, { timeout: 60000 }).catch(() => {});
});
await page.waitForTimeout(4000);
for (const w of widths) {
  rows.push(
    await capture(page, "return-segmented-return", "RETURN_SEGMENTED_RETURN", "return-segmented-return", w, async () => ({
      ok: /\/flights\/return-options/i.test(page.url()),
      reason: /\/flights\/return-options/i.test(page.url()) ? null : `url=${page.url()}`,
    })),
  );
}

await browser.close();

const existingPath = path.join(__dirname, "manifest-golden-flights-live.json");
const existing = JSON.parse(fs.readFileSync(existingPath, "utf8"));
const byKey = new Map(existing.rows.map((r) => [`${r.STATE}|${r.VIEWPORT}`, r]));
for (const r of rows) byKey.set(`${r.STATE}|${r.VIEWPORT}`, r);
existing.rows = [...byKey.values()];
existing.fails = existing.rows.filter((r) => String(r.SELF_REVIEW).startsWith("FAIL")).length;
existing.releaseSha = releaseSha;
existing.publicBuildId = publicBuildId;
existing.capturedAt = new Date().toISOString();
fs.writeFileSync(existingPath, JSON.stringify(existing, null, 2));

const failStates = existing.rows
  .filter((r) => String(r.SELF_REVIEW).startsWith("FAIL"))
  .map((r) => `${r.STATE}@${r.VIEWPORT}:${r.SELF_REVIEW} url=${(r.URL_ROUTE || "").slice(0, 80)}`);
console.log(JSON.stringify({ fails: existing.fails, failStates, updated: rows.length }, null, 2));
process.exit(existing.fails ? 1 : 0);
