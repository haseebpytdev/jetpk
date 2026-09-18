/**
 * Recapture BRANDED_FARE from Details drawer (avoids Book Now auto-continue to passengers).
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

function ssh(cmd) {
  const r = spawnSync("ssh", ["-i", sshKey, "-o", "BatchMode=yes", "root@185.215.166.176", cmd], {
    encoding: "utf8",
    maxBuffer: 8_000_000,
  });
  if (r.status !== 0) throw new Error(r.stderr || r.stdout);
  return r.stdout;
}

const out = ssh("bash /tmp/jp-c08-fs-init.sh LHE DXB 2026-10-20 '' one_way /tmp/jp-c08-fix2.json");
const searchId = (out.match(/SEARCH_ID=(.+)/) || [])[1]?.trim();
const resultsUrl = (out.match(/RESULTS_URL=(.+)/) || [])[1]?.trim();
if (!searchId || !resultsUrl) throw new Error("init failed");
const owUrl = `${baseURL}${resultsUrl}&search_id=${searchId}`;

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({
  userAgent:
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 JetPakistanVisualUAT",
});

const rows = [];
for (const w of [390, 1440]) {
  await page.setViewportSize({ width: w, height: w < 768 ? 844 : 900 });
  await page.goto(owUrl, { waitUntil: "domcontentloaded", timeout: 90000 });
  await page.waitForTimeout(8000);

  let fareN = 0;
  let found = false;
  const detailCount = await page.getByTestId("flight-details-trigger").count();
  for (let i = 0; i < Math.min(detailCount, 10); i++) {
    await page.goto(owUrl, { waitUntil: "domcontentloaded", timeout: 90000 });
    await page.waitForTimeout(5000);
    await page.getByTestId("flight-details-trigger").nth(i).click({ timeout: 15000 });
    await page.getByTestId("flight-details-drawer").waitFor({ timeout: 30000 });
    await page.waitForTimeout(2000);
    fareN = await page.locator("[data-testid='fare-select'], [data-testid='base-fare-select']").count();
    console.log("details", i, "fareN", fareN);
    if (fareN >= 1) {
      found = true;
      break;
    }
    await page.getByRole("button", { name: /^Close$/i }).click({ timeout: 3000 }).catch(() => {});
    await page.keyboard.press("Escape").catch(() => {});
  }

  if (!found) {
    await page.goto(owUrl, { waitUntil: "domcontentloaded", timeout: 90000 });
    await page.waitForTimeout(6000);
    await page.getByTestId("flight-details-trigger").first().click({ timeout: 15000 });
    await page.getByTestId("flight-details-drawer").waitFor({ timeout: 30000 });
    await page.waitForTimeout(2000);
    fareN = await page.locator("[data-testid='fare-select'], [data-testid='base-fare-select']").count();
  }

  await page.locator("[data-testid='fare-select'], [data-testid='base-fare-select'], [data-testid='fare-summary-tabs']").first().scrollIntoViewIfNeeded().catch(() => {});
  await stabilizeFullPage(page);
  const stable = await waitForStableText(page, { timeout: 15000 });
  const reject = await assertNoRejectState(page);
  const positive = await assertPositiveRoute(page, "branded-fare");
  const ox = await measureOverflow(page);
  const fab = await measureFabOverlap(page);
  const onPassengers = /\/booking\/passengers/i.test(page.url());
  const extra = {
    ok: fareN >= 1 && !onPassengers,
    reason: onPassengers ? "on_passengers" : fareN >= 1 ? null : "no_selectable_fare",
    fareN,
  };
  const v = verdictFromParts([
    { ok: stable.ok && reject.ok, reason: stable.reason || reject.reason },
    { ok: positive.ok, reason: positive.ok ? null : positive.fails.join(",") },
    { ok: extra.ok, reason: extra.reason },
    { ok: ox.overflowX === 0, reason: ox.overflowX ? `ox=${ox.overflowX}` : null },
  ]);
  const file = `flights/branded-fare-w${w}.png`;
  fs.mkdirSync(path.join(outRoot, "flights"), { recursive: true });
  await page.screenshot({ path: path.join(outRoot, file), fullPage: true });
  rows.push({
    FILE: file,
    URL_ROUTE: page.url().replace(baseURL, ""),
    VIEWPORT: w,
    ROLE: "anonymous",
    STATE: "BRANDED_FARE",
    PUBLIC_BUILD_ID: publicBuildId,
    RELEASE_SHA: releaseSha,
    SOURCE: "live production",
    NOTES: v.NOTES,
    SANITIZED: "YES",
    SELF_REVIEW: v.SELF_REVIEW,
    METRICS: { ox, fab, positive, extra, BRANDED_FARE_OPTIONS: fareN },
  });
  console.log("branded", w, v.SELF_REVIEW, "fareN", fareN, page.url().slice(0, 100));
}

await browser.close();

const existingPath = path.join(__dirname, "manifest-golden-flights-live.json");
const existing = JSON.parse(fs.readFileSync(existingPath, "utf8"));
const byKey = new Map(existing.rows.map((r) => [`${r.STATE}|${r.VIEWPORT}`, r]));
for (const r of rows) byKey.set(`${r.STATE}|${r.VIEWPORT}`, r);
existing.rows = [...byKey.values()];
existing.fails = existing.rows.filter((r) => String(r.SELF_REVIEW).startsWith("FAIL")).length;
existing.BRANDED_FARE_OPTIONS = Math.max(...rows.map((r) => r.METRICS?.BRANDED_FARE_OPTIONS || 0), 0);
fs.writeFileSync(existingPath, JSON.stringify(existing, null, 2));
console.log(JSON.stringify({ fails: existing.fails, BRANDED_FARE_OPTIONS: existing.BRANDED_FARE_OPTIONS }, null, 2));
process.exit(existing.fails ? 1 : 0);
