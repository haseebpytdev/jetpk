/**
 * Focused home + auth capture with step logging (avoids silent hang).
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
  verdictFromParts,
} from "./lib/stable-capture.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("../../../frontend/node_modules/playwright");
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outRoot = path.join(__dirname, "live");
const releaseSha = process.env.RELEASE_SHA || "";
const publicBuildId = process.env.PUBLIC_BUILD_ID || "unknown";
const baseURL = "https://jetpakistan.pk";
if (!releaseSha) process.exit(2);

const highRisk = [320, 360, 375, 390, 412, 768, 1024, 1440];
const standard = [390, 1440];
const routes = [
  { key: "home", path: "/", dir: "public", widths: highRisk, positive: "home", homeSections: true },
  { key: "login", path: "/login", dir: "auth", widths: highRisk, positive: "login" },
  { key: "register", path: "/register", dir: "auth", widths: standard, positive: "register" },
  { key: "forgot-password", path: "/forgot-password", dir: "auth", widths: standard, positive: "forgot-password" },
];

const rows = [];
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
  viewport: { width: 1440, height: 900 },
  userAgent:
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 JetPakistanVisualUAT",
});
context.setDefaultTimeout(45000);
const page = await context.newPage();

for (const route of routes) {
  for (const width of route.widths) {
    console.log("START", route.key, width);
    await page.setViewportSize({ width, height: width < 768 ? 844 : 900 });
    console.log("goto");
    await page.goto(`${baseURL}${route.path}`, { waitUntil: "domcontentloaded", timeout: 60000 });
    console.log("stabilize");
    await stabilizeFullPage(page);
    console.log("stableText");
    const stable = await waitForStableText(page, { timeout: 20000 });
    console.log("reject");
    const reject = await assertNoRejectState(page);
    console.log("overflow");
    const overflow = await measureOverflow(page);
    console.log("fab");
    const fab = await measureFabOverlap(page);
    let sections = null;
    let media = null;
    let positive = null;
    if (route.homeSections) {
      console.log("sections");
      sections = await assertHomepageSections(page);
      console.log("media");
      media = await assertRouteMedia(page);
    }
    if (route.positive) {
      console.log("positive");
      positive = await assertPositiveRoute(page, route.positive);
    }
    console.log("shot");
    const dir = path.join(outRoot, route.dir);
    fs.mkdirSync(dir, { recursive: true });
    const fileRel = `${route.dir}/${route.key}-w${width}.png`;
    await page.screenshot({ path: path.join(outRoot, fileRel), fullPage: true });
    const parts = [
      { ok: stable.ok && reject.ok, reason: stable.reason || reject.reason },
      { ok: overflow.overflowX === 0, reason: overflow.overflowX ? `ox=${overflow.overflowX}` : null },
      {
        ok: (fab.FAB_MEANINGFUL_CONTENT_OVERLAP || 0) === 0 && (fab.FAB_CTA_OVERLAP || 0) === 0,
        reason: `fabM=${fab.FAB_MEANINGFUL_CONTENT_OVERLAP} fabCta=${fab.FAB_CTA_OVERLAP}`,
      },
    ];
    if (positive) parts.push({ ok: positive.ok, reason: positive.ok ? null : positive.fails?.join(",") });
    if (sections) {
      parts.push({
        ok: sections.MISSING_APPROVED_HOMEPAGE_SECTIONS === 0,
        reason: sections.missing?.join(",") || null,
      });
    }
    if (media) {
      parts.push({
        ok:
          (media.TRENDING_ROUTE_MEDIA_BLANK || 0) === 0 &&
          (media.DESTINATION_MEDIA_BLANK || 0) === 0 &&
          (media.FEATURED_DEAL_MEDIA_BLANK || 0) === 0,
        reason: `media blank T=${media.TRENDING_ROUTE_MEDIA_BLANK} D=${media.DESTINATION_MEDIA_BLANK} F=${media.FEATURED_DEAL_MEDIA_BLANK}`,
      });
    }
    const verdict = verdictFromParts(parts);
    const pass = !String(verdict.SELF_REVIEW || "").startsWith("FAIL");
    rows.push({
      FILE: fileRel,
      URL_ROUTE: route.path,
      VIEWPORT: width,
      ROLE: "anonymous",
      STATE: "settled",
      PUBLIC_BUILD_ID: publicBuildId,
      RELEASE_SHA: releaseSha,
      SOURCE: "live production",
      NOTES: verdict.NOTES || verdict.SELF_REVIEW,
      SANITIZED: "YES",
      SELF_REVIEW: verdict.SELF_REVIEW,
      METRICS: {
        overflowX: overflow.overflowX,
        fab,
        sections,
        media,
        positive,
      },
    });
    console.log(pass ? "PASS" : "FAIL", route.key, width, verdict.SELF_REVIEW);
  }
}

await browser.close();
const fails = rows.filter((r) => r.SELF_REVIEW !== "PASS").length;
fs.writeFileSync(path.join(__dirname, "manifest-live.json"), JSON.stringify({ rows, fails, releaseSha, publicBuildId }, null, 2));
fs.writeFileSync(
  path.join(__dirname, "manifest-live.md"),
  [
    `# Live visual UAT — ${releaseSha.slice(0, 8)}`,
    "",
    `| File | Route | W | Review |`,
    `|---|---|---|---|`,
    ...rows.map((r) => `| ${r.FILE} | ${r.URL_ROUTE} | ${r.VIEWPORT} | ${r.SELF_REVIEW} |`),
    "",
    `FAILS=${fails}`,
  ].join("\n"),
);
console.log(JSON.stringify({ total: rows.length, fails, releaseSha, publicBuildId }, null, 2));
process.exit(fails ? 1 : 0);
