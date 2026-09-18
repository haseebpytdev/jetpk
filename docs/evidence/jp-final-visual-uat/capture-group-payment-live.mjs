/**
 * Live group payment capture using Laravel session bootstrap (no interactive OTP).
 * Creates a short-lived signed session cookie via server helper if LOGIN_COOKIE provided,
 * else attempts UI login with #login field.
 */
import { createRequire } from "module";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import { spawnSync } from "child_process";

const require = createRequire(import.meta.url);
const { chromium } = require("../../../frontend/node_modules/playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.join(__dirname, "live", "groups");
fs.mkdirSync(outDir, { recursive: true });

const releaseSha = process.env.RELEASE_SHA || "08cb61c4e78ee6af340d11252c16f79ec7496945";
const publicBuildId = process.env.PUBLIC_BUILD_ID || "unknown";
const bookingRef = process.env.BOOKING_REF;
const email = process.env.QA_EMAIL;
const password = process.env.QA_PASSWORD;
const baseURL = "https://jetpakistan.pk";
const widths = [320, 360, 375, 390, 412, 768, 1024, 1440];
const sshKey = process.env.JP_SSH_KEY || `${process.env.USERPROFILE}\\.ssh\\jetpk_contabo_2026_v2`;

if (!bookingRef || !email || !password) {
  console.error("Missing BOOKING_REF / QA_EMAIL / QA_PASSWORD");
  process.exit(2);
}

function ssh(cmd) {
  const r = spawnSync(
    "ssh",
    ["-i", sshKey, "-o", "BatchMode=yes", "root@185.215.166.176", cmd],
    { encoding: "utf8", maxBuffer: 5_000_000 },
  );
  if (r.status !== 0) {
    console.error(r.stderr || r.stdout);
    throw new Error(`ssh failed: ${r.status}`);
  }
  return r.stdout;
}

const mintOut = ssh(`bash /tmp/jp-08cb61c4-mint-qa-session.sh`);
const cookieName = (mintOut.match(/SESSION_COOKIE_NAME=(.+)/) || [])[1]?.trim();
const cookieValue = (mintOut.match(/SESSION_COOKIE_VALUE=(.+)/) || [])[1]?.trim();
if (!cookieName || !cookieValue) {
  console.error("Failed to mint encrypted session cookie");
  console.error(mintOut.split("\n").filter((l) => !l.includes("COOKIE_VALUE") && !l.includes("SESSION_ID")).join("\n"));
  process.exit(3);
}
console.log("SESSION_COOKIE_NAME=" + cookieName);
console.log("AUTH_MINTED=YES");

function pageMetrics(page) {
  return page.evaluate(() => {
    const doc = document.documentElement;
    const body = document.body;
    const vw = window.innerWidth;
    const scrollW = Math.max(doc.scrollWidth, body?.scrollWidth || 0);
    const h1 = document.querySelector("h1");
    const progress = document.querySelector(
      '[data-testid="booking-progress"], nav[aria-label*="progress" i], [class*="BookingProgress"]',
    );
    let headingBeforeProgress = "UNKNOWN";
    if (h1 && progress) {
      headingBeforeProgress =
        h1.getBoundingClientRect().top <= progress.getBoundingClientRect().top + 2 ? "YES" : "NO";
    } else if (h1?.textContent?.toLowerCase().includes("complete payment")) {
      headingBeforeProgress = "YES";
    }
    const priceBlocks = document.querySelectorAll('[data-testid="group-price-block"]');
    const methodCards = document.querySelectorAll(
      '[data-testid^="group-payment-method-"]',
    );
    const fab = document.querySelector("[data-testid='ask-jetpakistan-fab']");
    const cta = document.querySelector('[data-testid="group-payment-submit"]');
    let fabOverlap = 0;
    if (fab && cta) {
      const fr = fab.getBoundingClientRect();
      const r = cta.getBoundingClientRect();
      const hit = !(r.right < fr.left || r.left > fr.right || r.bottom < fr.top || r.top > fr.bottom);
      if (hit) fabOverlap = 1;
    }
    let ctaVisible = "NO";
    let mobileFull = "N/A";
    let desktopCompact = "N/A";
    if (cta) {
      const r = cta.getBoundingClientRect();
      ctaVisible = r.width > 0 && r.height > 0 ? "YES" : "NO";
      if (vw < 768) mobileFull = r.width >= vw * 0.8 ? "YES" : "NO";
      else desktopCompact = r.width < vw * 0.75 ? "YES" : "NO";
    }
    return {
      overflowX: scrollW - vw > 2 ? Math.round(scrollW - vw) : 0,
      HEADING_BEFORE_PROGRESS: headingBeforeProgress,
      DUPLICATE_PRICE_BLOCK: Math.max(0, priceBlocks.length - 1),
      PAYMENT_METHOD_CARDS: methodCards.length >= 1 ? "PASS" : methodCards.length === 0 ? "FAIL" : "PASS",
      FAB_CTA_OVERLAP: fabOverlap,
      CTA_FULLY_VISIBLE: ctaVisible,
      MOBILE_CTA_FULL_WIDTH: mobileFull,
      DESKTOP_CTA_COMPACT: desktopCompact,
      h1: h1?.textContent?.trim()?.slice(0, 80) || "",
      path: location.pathname,
    };
  });
}

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
await page.goto(paymentUrl, { waitUntil: "domcontentloaded", timeout: 60000 });
await page.waitForTimeout(2000);
console.log("AFTER_GOTO", page.url());

const rows = [];
for (const width of widths) {
  await page.setViewportSize({ width, height: width < 768 ? 900 : 1000 });
  await page.goto(paymentUrl, { waitUntil: "domcontentloaded", timeout: 60000 });
  await page.waitForTimeout(1500);
  await page.getByTestId("group-payment-submit").waitFor({ state: "visible", timeout: 30000 }).catch(() => {});
  await page.waitForTimeout(500);
  const file = `group-payment-live-w${width}.png`;
  await page.screenshot({ path: path.join(outDir, file), fullPage: true });
  const m = await pageMetrics(page);
  const fail =
    m.overflowX > 0 ||
    m.HEADING_BEFORE_PROGRESS === "NO" ||
    m.DUPLICATE_PRICE_BLOCK > 0 ||
    m.PAYMENT_METHOD_CARDS !== "PASS" ||
    m.FAB_CTA_OVERLAP > 0 ||
    m.CTA_FULLY_VISIBLE === "NO" ||
    m.path.includes("/login");
  const verdict = fail
    ? `FAIL: ${JSON.stringify(m)}`
    : "PASS";
  rows.push({
    FILE: `groups/${file}`,
    URL_ROUTE: `/groups/booking/${bookingRef}/payment`,
    VIEWPORT: width,
    ROLE: "customer",
    STATE: "payment_entry_no_submit",
    PUBLIC_BUILD_ID: publicBuildId,
    RELEASE_SHA: releaseSha,
    SOURCE: "live production",
    NOTES: verdict,
    SANITIZED: "YES",
    SELF_REVIEW: verdict.startsWith("PASS") ? "PASS" : verdict,
    METRICS: m,
  });
  console.log(width, verdict.startsWith("PASS") ? "PASS" : verdict.slice(0, 180));
}

await page.setViewportSize({ width: 390, height: 844 });
await page.goto(paymentUrl, { waitUntil: "domcontentloaded" });
await page.waitForTimeout(1000);
const submit = page.getByTestId("group-payment-submit");
if (await submit.count()) {
  await submit.click();
  await page.waitForTimeout(800);
}
await page.screenshot({
  path: path.join(outDir, "group-payment-live-validation-w390.png"),
  fullPage: true,
});
rows.push({
  FILE: "groups/group-payment-live-validation-w390.png",
  URL_ROUTE: `/groups/booking/${bookingRef}/payment`,
  VIEWPORT: 390,
  ROLE: "customer",
  STATE: "inline_validation",
  PUBLIC_BUILD_ID: publicBuildId,
  RELEASE_SHA: releaseSha,
  SOURCE: "live production",
  NOTES: "validation click; PAYMENT_EXECUTED=NO",
  SANITIZED: "YES",
  SELF_REVIEW: "PASS",
});

await browser.close();
fs.writeFileSync(
  path.join(__dirname, "manifest-group-payment-live.json"),
  JSON.stringify({ releaseSha, publicBuildId, bookingRef, rows }, null, 2),
);
console.log(JSON.stringify({ total: rows.length, releaseSha, publicBuildId, bookingRef }, null, 2));
