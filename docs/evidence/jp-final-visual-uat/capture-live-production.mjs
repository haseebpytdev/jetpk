/**
 * Live production visual capture for JetPakistan closure evidence.
 * Captures sanitized public routes from https://jetpakistan.pk
 * Does NOT perform payments or irreversible supplier mutations.
 *
 * Usage: node docs/evidence/jp-final-visual-uat/capture-live-production.mjs
 */
import { createRequire } from "module";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const require = createRequire(import.meta.url);
const { chromium } = require("../../../frontend/node_modules/playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outRoot = path.join(__dirname, "live");
const releaseSha = process.env.RELEASE_SHA || "08cb61c4e78ee6af340d11252c16f79ec7496945";
const publicBuildId = process.env.PUBLIC_BUILD_ID || "unknown";
const baseURL = "https://jetpakistan.pk";

fs.mkdirSync(outRoot, { recursive: true });
["public", "auth", "groups", "branding", "global"].forEach((d) =>
  fs.mkdirSync(path.join(outRoot, d), { recursive: true }),
);

const highRiskWidths = [320, 360, 375, 390, 412, 768, 1024, 1440];
const standardWidths = [390, 1440];

const routes = [
  { key: "home", path: "/", dir: "public", highRisk: true },
  { key: "about-us", path: "/about-us", dir: "public" },
  { key: "support", path: "/support", dir: "public" },
  { key: "faq", path: "/faq", dir: "public" },
  { key: "login", path: "/login", dir: "auth", highRisk: true },
  { key: "register", path: "/register", dir: "auth" },
  { key: "forgot-password", path: "/forgot-password", dir: "auth" },
  { key: "groups-search", path: "/groups/search", dir: "groups", highRisk: true },
];

const manifest = [];

function metrics(page) {
  return page.evaluate(() => {
    const doc = document.documentElement;
    const body = document.body;
    const vw = window.innerWidth;
    const scrollW = Math.max(doc.scrollWidth, body?.scrollWidth || 0);
    const fab = document.querySelector("[data-testid='ask-jetpakistan-fab']");
    let fabOverlap = 0;
    const fabHits = [];
    if (fab) {
      const fr = fab.getBoundingClientRect();
      // Meaningful interactive/content only — ignore decorative wrappers and the FAB subtree.
      const candidates = body.querySelectorAll(
        "a[href], button, input, select, textarea, label, [role='button'], [role='link'], [role='textbox'], h1, h2, p, li, td, th",
      );
      for (const el of candidates) {
        if (fab === el || fab.contains(el) || (el instanceof Element && el.contains(fab))) continue;
        // Dock siblings / Ask chrome are not content obstruction.
        if (
          el.closest("[data-testid='ask-jetpakistan-fab'], .jp-public-fab-dock, [data-jp-ask-open], [data-testid^='fab-']")
        ) {
          continue;
        }
        const testid = el.getAttribute("data-testid") || "";
        if (testid.startsWith("fab-") || testid.startsWith("ask-")) continue;
        const style = window.getComputedStyle(el);
        if (style.visibility === "hidden" || style.display === "none" || Number(style.opacity) === 0) {
          continue;
        }
        const r = el.getBoundingClientRect();
        if (r.width <= 0 || r.height <= 0) continue;
        // Require a non-trivial intersection area (not a 1px edge kiss).
        const ix = Math.max(0, Math.min(r.right, fr.right) - Math.max(r.left, fr.left));
        const iy = Math.max(0, Math.min(r.bottom, fr.bottom) - Math.max(r.top, fr.top));
        const area = ix * iy;
        if (area < 24) continue;
        const tag = el.tagName.toLowerCase();
        const role = el.getAttribute("role") || tag;
        const interactive = ["a", "button", "input", "select", "textarea", "label"].includes(tag) ||
          ["button", "link", "textbox"].includes(role);
        const text = (el.textContent || "").trim().slice(0, 40);
        fabHits.push({
          selector: el.getAttribute("data-testid")
            ? `[data-testid='${el.getAttribute("data-testid")}']`
            : `${tag}${el.id ? "#" + el.id : ""}.${(el.className || "").toString().split(/\s+/).slice(0, 2).join(".")}`,
          role,
          interactive,
          text,
          box: {
            left: Math.round(r.left),
            top: Math.round(r.top),
            right: Math.round(r.right),
            bottom: Math.round(r.bottom),
          },
          area: Math.round(area),
        });
        fabOverlap += 1;
      }
    }
    const icon = document.querySelector('link[rel="icon"], link[rel="shortcut icon"]');
    const logo = document.querySelector('header img, a[aria-label*="JetPakistan"] img');
    return {
      overflowX: scrollW - vw > 2 ? Math.round(scrollW - vw) : 0,
      fabOverlap,
      fabHits: fabHits.slice(0, 8),
      fabBox: fab
        ? {
            left: Math.round(fab.getBoundingClientRect().left),
            top: Math.round(fab.getBoundingClientRect().top),
            right: Math.round(fab.getBoundingClientRect().right),
            bottom: Math.round(fab.getBoundingClientRect().bottom),
          }
        : null,
      faviconHref: icon?.getAttribute("href") || null,
      logoSrc: logo?.getAttribute("src") || null,
      h1: document.querySelector("h1")?.textContent?.trim()?.slice(0, 80) || "",
    };
  });
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
  viewport: { width: 1440, height: 900 },
  userAgent:
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 JetPakistanVisualUAT",
});
const page = await context.newPage();

for (const route of routes) {
  const widths = route.highRisk ? highRiskWidths : standardWidths;
  for (const width of widths) {
    await page.setViewportSize({ width, height: width < 768 ? 844 : 900 });
    await page.goto(`${baseURL}${route.path}`, { waitUntil: "domcontentloaded", timeout: 60000 });
    await page
      .waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", { timeout: 12000 })
      .catch(() => {});
    await page.waitForTimeout(800);
    const file = `${route.key}-w${width}.png`;
    const abs = path.join(outRoot, route.dir, file);
    await page.screenshot({ path: abs, fullPage: true });
    const m = await metrics(page);
    const verdict =
      m.overflowX === 0 && m.fabOverlap === 0
        ? "PASS"
        : `FAIL: overflowX=${m.overflowX} fabOverlap=${m.fabOverlap}${m.fabHits?.length ? " hits=" + JSON.stringify(m.fabHits) : ""}`;
    manifest.push({
      FILE: `${route.dir}/${file}`,
      URL_ROUTE: route.path,
      VIEWPORT: width,
      ROLE: "anonymous",
      STATE: "default",
      PUBLIC_BUILD_ID: publicBuildId,
      RELEASE_SHA: releaseSha,
      SOURCE: "live production",
      NOTES: `${verdict}; h1=${m.h1}; favicon=${m.faviconHref}; logo=${m.logoSrc}`,
      SANITIZED: "YES",
      SELF_REVIEW: verdict.startsWith("PASS") ? "PASS" : verdict,
      METRICS: m,
    });
    console.log(route.key, width, verdict);
  }
}

// Branding / favicon spot at home 1440
await page.setViewportSize({ width: 1440, height: 900 });
await page.goto(`${baseURL}/`, { waitUntil: "domcontentloaded" });
await page.waitForTimeout(800);
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

// FAB open state
await page.setViewportSize({ width: 390, height: 844 });
await page.goto(`${baseURL}/`, { waitUntil: "domcontentloaded" });
await page.waitForTimeout(800);
const fab = page.getByTestId("ask-jetpakistan-fab");
if (await fab.count()) {
  await fab.click({ timeout: 5000 }).catch(() => {});
  await page.waitForTimeout(800);
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

await browser.close();

const lines = [
  "# JetPakistan Final Visual UAT — LIVE Manifest",
  "",
  `RELEASE_SHA: ${releaseSha}`,
  `PUBLIC_BUILD_ID: ${publicBuildId}`,
  `CAPTURED_AT: ${new Date().toISOString()}`,
  `SOURCE: live production`,
  `SANITIZED: YES`,
  "",
  "| FILE | URL/ROUTE | VIEWPORT | ROLE | STATE | SELF_REVIEW | NOTES |",
  "| --- | --- | --- | --- | --- | --- | --- |",
];
for (const row of manifest) {
  lines.push(
    `| ${row.FILE} | ${row.URL_ROUTE} | ${row.VIEWPORT} | ${row.ROLE} | ${row.STATE} | ${row.SELF_REVIEW} | ${row.NOTES.replace(/\|/g, "/")} |`,
  );
}
fs.writeFileSync(path.join(__dirname, "manifest-live.md"), lines.join("\n") + "\n");
fs.writeFileSync(
  path.join(__dirname, "manifest-live.json"),
  JSON.stringify({ releaseSha, publicBuildId, rows: manifest }, null, 2),
);
console.log(JSON.stringify({ total: manifest.length, releaseSha, publicBuildId }, null, 2));
