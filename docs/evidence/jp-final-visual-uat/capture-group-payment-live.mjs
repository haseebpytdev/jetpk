/**
 * Live Group Payment visual capture — CORRECTION-08 stable + visual assertions.
 * Env: BOOKING_REF, RELEASE_SHA, PUBLIC_BUILD_ID
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
  assertGroupPaymentVisual,
  measureFabOverlap,
  measureOverflow,
  verdictFromParts,
} from "./lib/stable-capture.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("../../../frontend/node_modules/playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.join(__dirname, "live", "groups");
fs.mkdirSync(outDir, { recursive: true });

const releaseSha = process.env.RELEASE_SHA || "";
const publicBuildId = process.env.PUBLIC_BUILD_ID || "unknown";
const bookingRef = process.env.BOOKING_REF;
const baseURL = "https://jetpakistan.pk";
const widths = [320, 360, 375, 390, 412, 768, 1024, 1440];
const sshKey = process.env.JP_SSH_KEY || `${process.env.USERPROFILE}\\.ssh\\jetpk_contabo_2026_v2`;

if (!bookingRef || !releaseSha) {
  console.error("Missing BOOKING_REF / RELEASE_SHA");
  process.exit(2);
}

function ssh(cmd) {
  const r = spawnSync("ssh", ["-i", sshKey, "-o", "BatchMode=yes", "root@185.215.166.176", cmd], {
    encoding: "utf8",
    maxBuffer: 5_000_000,
  });
  if (r.status !== 0) throw new Error(r.stderr || r.stdout);
  return r.stdout;
}

const mintOut = ssh("bash /tmp/jp-08cb61c4-mint-qa-session.sh");
const cookieName = (mintOut.match(/SESSION_COOKIE_NAME=(.+)/) || [])[1]?.trim();
const cookieValue = (mintOut.match(/SESSION_COOKIE_VALUE=(.+)/) || [])[1]?.trim();
if (!cookieName || !cookieValue) {
  console.error("Failed to mint encrypted session cookie");
  process.exit(3);
}
console.log("AUTH_MINTED=YES");

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
  viewport: { width: 1440, height: 900 },
  userAgent:
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 JetPakistanVisualUAT",
});
await context.addCookies([
  {
    name: cookieName,
    value: cookieValue,
    domain: "jetpakistan.pk",
    path: "/",
    httpOnly: true,
    secure: true,
    sameSite: "Lax",
  },
]);

const page = await context.newPage();
const paymentUrl = `${baseURL}/groups/booking/${bookingRef}/payment`;
const rows = [];

for (const width of widths) {
  await page.setViewportSize({ width, height: width < 768 ? 900 : 1000 });
  await page.goto(paymentUrl, { waitUntil: "domcontentloaded", timeout: 60000 });
  await page.getByTestId("group-payment-submit").waitFor({ state: "visible", timeout: 45000 }).catch(() => {});
  await stabilizeFullPage(page);
  const stable = await waitForStableText(page, { timeout: 25000 });
  const reject = await assertNoRejectState(page);
  const positive = await assertPositiveRoute(page, "group-payment");
  const visual = await assertGroupPaymentVisual(page);
  const fab = await measureFabOverlap(page);
  const overflow = await measureOverflow(page);

  const parts = [
    { ok: stable.ok && reject.ok, reason: stable.reason || reject.reason },
    { ok: positive.ok, reason: positive.ok ? null : positive.fails.join(",") },
    { ok: overflow.overflowX === 0, reason: overflow.overflowX ? `ox=${overflow.overflowX}` : null },
    {
      ok: visual.PAYMENT_METHOD_CARDS === "PASS" && visual.PAYMENT_CARD_VISUAL_SEPARATION === "PASS",
      reason: `cards=${visual.PAYMENT_METHOD_CARDS} sep=${visual.PAYMENT_CARD_VISUAL_SEPARATION}`,
    },
    {
      ok: visual.CTA_BUTTON_VISUAL === "PASS" && visual.CTA_NOT_DETACHED === "YES",
      reason: `cta=${visual.CTA_BUTTON_VISUAL} detached=${visual.CTA_NOT_DETACHED}`,
    },
    {
      ok: width < 768 ? visual.MOBILE_CTA_FULL_WIDTH === "YES" : visual.DESKTOP_CTA_COMPACT === "YES",
      reason:
        width < 768
          ? `MOBILE_CTA_FULL_WIDTH=${visual.MOBILE_CTA_FULL_WIDTH}`
          : `DESKTOP_CTA_COMPACT=${visual.DESKTOP_CTA_COMPACT}`,
    },
    {
      ok: visual.BOOKING_SUMMARY_ORDER === "PASS" && visual.HEADER_COLLISION === 0,
      reason: `order=${visual.BOOKING_SUMMARY_ORDER} headerCollision=${visual.HEADER_COLLISION}`,
    },
    {
      ok: visual.LIVE_GROUP_PAYMENT_SOURCE_SIGNATURE === "CURRENT",
      reason: `signature=${visual.LIVE_GROUP_PAYMENT_SOURCE_SIGNATURE}`,
    },
    {
      ok: fab.FAB_CTA_OVERLAP === 0 && fab.FAB_MEANINGFUL_CONTENT_OVERLAP === 0,
      reason: `fabCta=${fab.FAB_CTA_OVERLAP} fabMeaningful=${fab.FAB_MEANINGFUL_CONTENT_OVERLAP}`,
    },
  ];

  const file = `group-payment-live-w${width}.png`;
  await page.screenshot({ path: path.join(outDir, file), fullPage: true });
  const v = verdictFromParts(parts);
  rows.push({
    FILE: `groups/${file}`,
    URL_ROUTE: `/groups/booking/${bookingRef}/payment`,
    VIEWPORT: width,
    ROLE: "customer",
    STATE: "payment_entry_no_submit",
    PUBLIC_BUILD_ID: publicBuildId,
    RELEASE_SHA: releaseSha,
    SOURCE: "live production",
    NOTES: v.NOTES,
    SANITIZED: "YES",
    SELF_REVIEW: v.SELF_REVIEW,
    METRICS: { ...visual, overflow, fab, positive },
  });
  console.log("group-payment", width, v.SELF_REVIEW, visual.LIVE_GROUP_PAYMENT_SOURCE_SIGNATURE);
}

await browser.close();

const fails = rows.filter((r) => String(r.SELF_REVIEW).startsWith("FAIL")).length;
fs.writeFileSync(
  path.join(__dirname, "manifest-group-payment-live.json"),
  JSON.stringify(
    {
      releaseSha,
      publicBuildId,
      bookingRef,
      LIVE_GROUP_PAYMENT_SOURCE_SIGNATURE: rows[0]?.METRICS?.LIVE_GROUP_PAYMENT_SOURCE_SIGNATURE,
      harness: "correction-08",
      rows,
    },
    null,
    2,
  ),
);
console.log(JSON.stringify({ total: rows.length, fails, releaseSha }, null, 2));
process.exit(fails > 0 ? 1 : 0);
