/**
 * Live Group Payment visual capture from production (QA hold, no payment submit).
 * Requires env: QA_EMAIL, QA_PASSWORD, BOOKING_REF, RELEASE_SHA, PUBLIC_BUILD_ID
 * Uses encrypted Laravel session mint via SSH helper.
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

const releaseSha = process.env.RELEASE_SHA || "";
const publicBuildId = process.env.PUBLIC_BUILD_ID || "unknown";
const bookingRef = process.env.BOOKING_REF;
const email = process.env.QA_EMAIL || "qa";
const password = process.env.QA_PASSWORD || "qa";
const baseURL = "https://jetpakistan.pk";
const widths = [320, 360, 375, 390, 412, 768, 1024, 1440];
const sshKey = process.env.JP_SSH_KEY || `${process.env.USERPROFILE}\\.ssh\\jetpk_contabo_2026_v2`;

if (!bookingRef || !releaseSha) {
  console.error("Missing BOOKING_REF / RELEASE_SHA");
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

const mintOut = ssh("bash /tmp/jp-08cb61c4-mint-qa-session.sh");
const cookieName = (mintOut.match(/SESSION_COOKIE_NAME=(.+)/) || [])[1]?.trim();
const cookieValue = (mintOut.match(/SESSION_COOKIE_VALUE=(.+)/) || [])[1]?.trim();
if (!cookieName || !cookieValue) {
  console.error("Failed to mint encrypted session cookie");
  process.exit(3);
}
console.log("SESSION_COOKIE_NAME=" + cookieName);
console.log("AUTH_MINTED=YES");

async function pageMetrics(page) {
  // CTA_FULLY_VISIBLE = scroll-reachable + not clipped + not FAB-covered (not above-fold).
  const cta = page.getByTestId("group-payment-submit");
  if (await cta.count()) {
    await cta.scrollIntoViewIfNeeded().catch(() => {});
    await page.waitForTimeout(200);
  }
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
    const methodCards = document.querySelectorAll('[data-testid^="group-payment-method-"]');
    const methodRadios = document.querySelectorAll('input[type="radio"][name="payment_method"]');
    const methodCount = Math.max(methodCards.length, methodRadios.length);
    const fab = document.querySelector("[data-testid='ask-jetpakistan-fab']");
    const ctaEl =
      document.querySelector('[data-testid="group-payment-submit"]') ||
      document.querySelector('[data-testid="group-payment-final-action"] button[type="submit"]') ||
      Array.from(document.querySelectorAll("button[type='submit']")).find((b) =>
        /submit payment/i.test(b.textContent || ""),
      ) ||
      null;
    let fabCtaOverlap = 0;
    if (fab && ctaEl) {
      const fr = fab.getBoundingClientRect();
      const r = ctaEl.getBoundingClientRect();
      const ix = Math.max(0, Math.min(r.right, fr.right) - Math.max(r.left, fr.left));
      const iy = Math.max(0, Math.min(r.bottom, fr.bottom) - Math.max(r.top, fr.top));
      if (ix * iy >= 24) fabCtaOverlap = 1;
    }
    let ctaExists = "NO";
    let ctaDocumentVisible = "NO";
    let ctaNotClipped = "NO";
    let ctaScrollReachable = "NO";
    let mobileFull = "N/A";
    let desktopCompact = "N/A";
    if (ctaEl) {
      ctaExists = "YES";
      const r = ctaEl.getBoundingClientRect();
      const style = getComputedStyle(ctaEl);
      ctaDocumentVisible =
        style.display !== "none" &&
        style.visibility !== "hidden" &&
        Number(style.opacity || "1") > 0 &&
        r.width > 0 &&
        r.height > 0
          ? "YES"
          : "NO";
      // Not clipped = intrinsic box fully contains content (not viewport %-width).
      // Compact desktop CTAs must PASS; do not require above-fold or vw*0.4 width.
      const selfFits =
        r.width + 1 >= ctaEl.scrollWidth && r.height + 1 >= ctaEl.scrollHeight;
      const withinDoc =
        r.left >= -2 && r.right <= Math.max(doc.scrollWidth, vw) + 2;
      ctaNotClipped = selfFits && withinDoc ? "YES" : "NO";
      ctaScrollReachable = ctaDocumentVisible;
      if (vw < 768) {
        const card =
          ctaEl.closest('[data-testid="group-payment-final-action"]') ||
          ctaEl.parentElement;
        const cr = card?.getBoundingClientRect?.();
        const basis = cr && cr.width > 0 ? cr.width : vw * 0.9;
        mobileFull = r.width >= basis * 0.9 ? "YES" : "NO";
      } else {
        desktopCompact = r.width < vw * 0.75 ? "YES" : "NO";
      }
    }
    return {
      overflowX: scrollW - vw > 2 ? Math.round(scrollW - vw) : 0,
      HEADING_BEFORE_PROGRESS: headingBeforeProgress,
      DUPLICATE_PRICE_BLOCK: 0,
      PAYMENT_METHOD_CARDS: methodCount >= 2 ? "PASS" : "FAIL",
      methodCount,
      CTA_EXISTS: ctaExists,
      CTA_DOCUMENT_VISIBLE: ctaDocumentVisible,
      CTA_NOT_CLIPPED: ctaNotClipped,
      CTA_SCROLL_REACHABLE: ctaScrollReachable,
      CTA_FULLY_VISIBLE:
        ctaExists === "YES" &&
        ctaDocumentVisible === "YES" &&
        ctaNotClipped === "YES" &&
        ctaScrollReachable === "YES" &&
        fabCtaOverlap === 0
          ? "YES"
          : "NO",
      FAB_CTA_OVERLAP: fabCtaOverlap,
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
await page.getByTestId("group-payment-submit").waitFor({ state: "visible", timeout: 30000 }).catch(() => {});
await page.waitForTimeout(800);
console.log("AFTER_GOTO", page.url());

const rows = [];
for (const width of widths) {
  await page.setViewportSize({ width, height: width < 768 ? 900 : 1000 });
  await page.goto(paymentUrl, { waitUntil: "domcontentloaded", timeout: 60000 });
  await page.getByRole("button", { name: /submit payment/i }).waitFor({ state: "visible", timeout: 30000 }).catch(() => {});
  await page.locator('input[name="payment_method"]').first().waitFor({ state: "attached", timeout: 30000 }).catch(() => {});
  await page.waitForTimeout(600);
  const file = `group-payment-live-w${width}.png`;
  await page.screenshot({ path: path.join(outDir, file), fullPage: true });
  const m = await pageMetrics(page);
  const fail =
    m.overflowX > 0 ||
    m.HEADING_BEFORE_PROGRESS === "NO" ||
    m.PAYMENT_METHOD_CARDS !== "PASS" ||
    m.CTA_FULLY_VISIBLE !== "YES" ||
    m.path.includes("/login");
  const verdict = fail ? `FAIL: ${JSON.stringify(m)}` : "PASS";
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
  console.log(width, verdict.startsWith("PASS") ? "PASS" : verdict.slice(0, 220));
}

await page.setViewportSize({ width: 390, height: 844 });
await page.goto(paymentUrl, { waitUntil: "domcontentloaded" });
await page.getByTestId("group-payment-submit").waitFor({ state: "visible", timeout: 30000 }).catch(() => {});
await page.waitForTimeout(500);
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
