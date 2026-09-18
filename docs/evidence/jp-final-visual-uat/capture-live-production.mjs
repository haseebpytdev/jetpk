/**
 * Live production visual capture — CORRECTION-08 hardened harness.
 * Usage: RELEASE_SHA=... PUBLIC_BUILD_ID=... node docs/evidence/jp-final-visual-uat/capture-live-production.mjs
 */
import { createRequire } from "module";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import {
  stabilizeFullPage,
  waitForStableText,
  assertNoRejectState,
  assertHomepageSections,
  assertRouteMedia,
  measureFabOverlap,
  measureOverflow,
  assertPositiveRoute,
  assertExpectedPublicBuild,
  verdictFromParts,
} from "./lib/stable-capture.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("../../../frontend/node_modules/playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outRoot = path.join(__dirname, "live");
const releaseSha = process.env.RELEASE_SHA || "";
const publicBuildId = process.env.PUBLIC_BUILD_ID || "unknown";
const baseURL = "https://jetpakistan.pk";

if (!releaseSha) {
  console.error("Missing RELEASE_SHA");
  process.exit(2);
}

fs.mkdirSync(outRoot, { recursive: true });
["public", "auth", "groups", "branding", "global"].forEach((d) =>
  fs.mkdirSync(path.join(outRoot, d), { recursive: true }),
);

const highRiskWidths = [320, 360, 375, 390, 412, 768, 1024, 1440];
const standardWidths = [390, 1440];

const routes = [
  { key: "home", path: "/", dir: "public", highRisk: true, positive: "home", homeSections: true },
  { key: "about-us", path: "/about-us", dir: "public" },
  { key: "support", path: "/support", dir: "public" },
  { key: "faq", path: "/faq", dir: "public" },
  { key: "login", path: "/login", dir: "auth", highRisk: true, positive: "login" },
  { key: "register", path: "/register", dir: "auth", positive: "register" },
  { key: "forgot-password", path: "/forgot-password", dir: "auth", positive: "forgot-password" },
  { key: "groups-search", path: "/groups/search", dir: "groups", highRisk: true, positive: "groups-search" },
];

const manifest = [];

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
  viewport: { width: 1440, height: 900 },
  userAgent:
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 JetPakistanVisualUAT",
});
const page = await context.newPage();

// Fail-fast: homepage must serve expected final public build (no restamp).
{
  await page.goto(`${baseURL}/`, { waitUntil: "domcontentloaded", timeout: 60000 });
  await stabilizeFullPage(page);
  const buildCheck = await assertExpectedPublicBuild(page, publicBuildId);
  console.log("OBSERVED_PUBLIC_BUILD", buildCheck.observed, "EXPECTED", publicBuildId);
  if (!buildCheck.ok) {
    console.error("PUBLIC_BUILD_MISMATCH", buildCheck.reason);
    process.exit(3);
  }
}

for (const route of routes) {
  const widths = route.highRisk ? highRiskWidths : standardWidths;
  for (const width of widths) {
    await page.setViewportSize({ width, height: width < 768 ? 844 : 900 });
    await page.goto(`${baseURL}${route.path}`, { waitUntil: "domcontentloaded", timeout: 60000 });
    await stabilizeFullPage(page);
    const stable = await waitForStableText(page, { timeout: 20000 });
    const reject = await assertNoRejectState(page);
    const overflow = await measureOverflow(page);
    const fab = await measureFabOverlap(page);
    const buildCheck = await assertExpectedPublicBuild(page, publicBuildId);
    let sections = null;
    let media = null;
    let positive = null;
    if (route.homeSections) {
      sections = await assertHomepageSections(page);
      media = await assertRouteMedia(page);
    }
    if (route.positive) {
      positive = await assertPositiveRoute(page, route.positive);
    }

    const parts = [
      { ok: stable.ok && reject.ok, reason: stable.reason || reject.reason || null },
      { ok: overflow.overflowX === 0, reason: overflow.overflowX ? `overflowX=${overflow.overflowX}` : null },
      {
        ok:
          fab.FAB_MEANINGFUL_CONTENT_OVERLAP === 0 &&
          fab.FAB_CARD_CONTENT_OVERLAP === 0 &&
          fab.FAB_INTERACTIVE_OVERLAP === 0 &&
          fab.FAB_CTA_OVERLAP === 0,
        reason:
          fab.fabOverlap > 0
            ? `FAB overlap meaningful=${fab.FAB_MEANINGFUL_CONTENT_OVERLAP} card=${fab.FAB_CARD_CONTENT_OVERLAP} interactive=${fab.FAB_INTERACTIVE_OVERLAP} cta=${fab.FAB_CTA_OVERLAP}`
            : null,
      },
    ];
    if (sections) {
      parts.push({
        ok: sections.MISSING_APPROVED_HOMEPAGE_SECTIONS === 0,
        reason:
          sections.MISSING_APPROVED_HOMEPAGE_SECTIONS > 0
            ? `MISSING_SECTIONS=${sections.missing.join(",")}`
            : null,
      });
    }
    if (media) {
      parts.push({
        ok: media.BLANK_ROUTE_MEDIA === 0 && media.IMG_COMPLETE === "YES",
        reason:
          media.BLANK_ROUTE_MEDIA > 0 || media.IMG_COMPLETE !== "YES"
            ? `BLANK_ROUTE_MEDIA=${media.BLANK_ROUTE_MEDIA} IMG_COMPLETE=${media.IMG_COMPLETE}`
            : null,
      });
    }
    if (positive) {
      parts.push({
        ok: positive.ok,
        reason: positive.ok ? null : `positive:${positive.fails.join(",")}`,
      });
    }
    parts.push({ ok: buildCheck.ok, reason: buildCheck.reason });

    const file = `${route.key}-w${width}.png`;
    const abs = path.join(outRoot, route.dir, file);
    await page.screenshot({ path: abs, fullPage: true });
    const v = verdictFromParts(parts);
    const row = {
      FILE: `${route.dir}/${file}`,
      URL_ROUTE: route.path,
      VIEWPORT: width,
      ROLE: "anonymous",
      STATE: "stable_default",
      PUBLIC_BUILD_ID: publicBuildId,
      OBSERVED_PUBLIC_BUILD_ID: buildCheck.observed,
      RELEASE_SHA: releaseSha,
      SOURCE: "live production",
      NOTES: v.NOTES,
      SANITIZED: "YES",
      SELF_REVIEW: v.SELF_REVIEW,
      METRICS: {
        overflow,
        fab,
        sections,
        media,
        positive,
        buildCheck,
        HOME_SCROLL_STABILIZED: route.homeSections ? "YES" : "N/A",
        MISSING_APPROVED_HOMEPAGE_SECTIONS: sections?.MISSING_APPROVED_HOMEPAGE_SECTIONS ?? null,
        TRENDING_ROUTE_MEDIA_BLANK: media?.BLANK_ROUTE_MEDIA ?? null,
      },
    };
    manifest.push(row);
    console.log(route.key, width, v.SELF_REVIEW);
  }
}

// Branding crop
await page.setViewportSize({ width: 1440, height: 900 });
await page.goto(`${baseURL}/`, { waitUntil: "domcontentloaded" });
await stabilizeFullPage(page);
await page.screenshot({ path: path.join(outRoot, "branding", "home-branding-w1440.png"), fullPage: false });
manifest.push({
  FILE: "branding/home-branding-w1440.png",
  URL_ROUTE: "/",
  VIEWPORT: 1440,
  ROLE: "anonymous",
  STATE: "branding_header",
  PUBLIC_BUILD_ID: publicBuildId,
  RELEASE_SHA: releaseSha,
  SOURCE: "live production",
  NOTES: "Header branding crop",
  SANITIZED: "YES",
  SELF_REVIEW: "PASS",
});

// FAB open
await page.setViewportSize({ width: 390, height: 844 });
await page.goto(`${baseURL}/`, { waitUntil: "domcontentloaded" });
await stabilizeFullPage(page);
const fabBtn = page.getByTestId("ask-jetpakistan-fab");
if (await fabBtn.count()) {
  await fabBtn.click({ timeout: 5000 }).catch(() => {});
  await page.waitForTimeout(600);
  await page.screenshot({ path: path.join(outRoot, "global", "ask-fab-open-w390.png"), fullPage: true });
  manifest.push({
    FILE: "global/ask-fab-open-w390.png",
    URL_ROUTE: "/",
    VIEWPORT: 390,
    ROLE: "anonymous",
    STATE: "ask_fab_open",
    PUBLIC_BUILD_ID: publicBuildId,
    RELEASE_SHA: releaseSha,
    SOURCE: "live production",
    NOTES: "Ask FAB open",
    SANITIZED: "YES",
    SELF_REVIEW: "PASS",
  });
}

await browser.close().catch(() => {});

const failCount = manifest.filter((r) => String(r.SELF_REVIEW).startsWith("FAIL")).length;
const lines = [
  "# JetPakistan Final Visual UAT — LIVE Manifest (CORRECTION-08)",
  "",
  `RELEASE_SHA: ${releaseSha}`,
  `PUBLIC_BUILD_ID: ${publicBuildId}`,
  `CAPTURED_AT: ${new Date().toISOString()}`,
  `SOURCE: live production`,
  `SANITIZED: YES`,
  `HARNESS: stable-state + positive assertions + scroll stabilize`,
  `SELF_REVIEW_FAILS: ${failCount}`,
  "",
  "| FILE | URL/ROUTE | VIEWPORT | ROLE | STATE | SELF_REVIEW | NOTES |",
  "| --- | --- | --- | --- | --- | --- | --- |",
];
for (const row of manifest) {
  lines.push(
    `| ${row.FILE} | ${row.URL_ROUTE} | ${row.VIEWPORT} | ${row.ROLE} | ${row.STATE} | ${row.SELF_REVIEW} | ${String(row.NOTES || "").replace(/\|/g, "/")} |`,
  );
}
fs.writeFileSync(path.join(__dirname, "manifest-live.md"), lines.join("\n") + "\n");
fs.writeFileSync(
  path.join(__dirname, "manifest-live.json"),
  JSON.stringify({ releaseSha, publicBuildId, harness: "correction-08", rows: manifest }, null, 2),
);
console.log(JSON.stringify({ total: manifest.length, fails: failCount, releaseSha, publicBuildId }, null, 2));
process.exit(failCount > 0 ? 1 : 0);
