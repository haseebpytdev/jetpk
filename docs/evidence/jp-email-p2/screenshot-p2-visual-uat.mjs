import { createRequire } from "module";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const require = createRequire(import.meta.url);
const { chromium } = require("C:/Users/khadi/ota/node_modules/playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.join(__dirname, "screenshots");
fs.mkdirSync(outDir, { recursive: true });

const base = process.env.P2_HTML_BASE || "http://127.0.0.1:8765/artifacts";
const files = [
  "auth-admin-login.html",
  "welcome-customer.html",
  "booking-confirmed-customer.html",
  "daily-admin-report.html",
  "abandoned-search.html",
];
const widths = [1280, 430, 390, 360, 320];
const results = [];

function inspectMetrics(page) {
  return page.evaluate(() => {
    const doc = document.documentElement;
    const body = document.body;
    const vw = window.innerWidth;
    const scrollW = Math.max(doc.scrollWidth, body.scrollWidth);
    const overflowX = scrollW - vw;

    const overflowing = [];
    for (const el of body.querySelectorAll("*")) {
      const r = el.getBoundingClientRect();
      if (r.width <= 0 || r.height <= 0) continue;
      if (r.right > vw + 1 || r.left < -1) {
        overflowing.push({
          tag: el.tagName.toLowerCase(),
          className: String(el.className || "").slice(0, 80),
          left: Math.round(r.left),
          right: Math.round(r.right),
          text: (el.innerText || "").slice(0, 60).replace(/\s+/g, " "),
        });
        if (overflowing.length >= 12) break;
      }
    }

    const links = [...body.querySelectorAll("a")].map((a) => {
      const r = a.getBoundingClientRect();
      return {
        text: (a.innerText || a.getAttribute("aria-label") || "").slice(0, 80).replace(/\s+/g, " "),
        href: (a.getAttribute("href") || "").slice(0, 120),
        w: Math.round(r.width),
        h: Math.round(r.height),
        clipped: r.width > 0 && (r.right > vw + 1 || r.left < -1),
      };
    });

    const tables = [...body.querySelectorAll("table")].map((t) => {
      const r = t.getBoundingClientRect();
      return {
        w: Math.round(r.width),
        clipped: r.right > vw + 1 || r.left < -1,
      };
    });

    return {
      overflowX: overflowX > 2 ? Math.round(overflowX) : 0,
      scrollWidth: scrollW,
      viewport: vw,
      overflowingCount: overflowing.length,
      overflowingSample: overflowing.slice(0, 5),
      brokenButtons: links.filter((l) => l.clipped || (l.h > 0 && l.h < 8) || (l.w > 0 && l.w < 8)).length,
      brokenTables: tables.filter((t) => t.clipped).length,
      ctaSample: links.slice(0, 8),
      tableCount: tables.length,
    };
  });
}

console.log("launching chromium...");
const browser = await chromium.launch({ headless: true, timeout: 120000 });
console.log("launched");
const context = await browser.newContext();
for (const file of files) {
  for (const width of widths) {
    const page = await context.newPage();
    await page.setViewportSize({ width, height: width >= 1280 ? 900 : 800 });
    const url = `${base}/${file}`;
    console.log(`render ${file} @${width}`);
    await page.goto(url, { waitUntil: "load", timeout: 30000 });
    const metrics = await inspectMetrics(page);
    const shot = `${file.replace(".html", "")}-w${width}.png`;
    await page.screenshot({
      path: path.join(outDir, shot),
      fullPage: true,
      type: "png",
    });
    await page.close();
    results.push({ file, width, shot, ...metrics });
  }
}
await browser.close();

const overflowHits = results.filter((r) => (r.overflowX || 0) > 0 || (r.overflowingCount || 0) > 0);
const brokenButtons = results.reduce((n, r) => n + (r.brokenButtons || 0), 0);
const brokenTables = results.reduce((n, r) => n + (r.brokenTables || 0), 0);
const byW = (w) => results.filter((r) => r.width === w);
const passW = (w) => byW(w).every((r) => !r.overflowX && !r.overflowingCount && !(r.brokenButtons > 0) && !(r.brokenTables > 0));

const summary = {
  SCREENSHOT_EVIDENCE_CREATED: results.length === files.length * widths.length ? "YES" : "NO",
  DESKTOP_VISUAL_QA: passW(1280) ? "PASS" : "FAIL",
  MOBILE_430: passW(430) ? "PASS" : "FAIL",
  MOBILE_390: passW(390) ? "PASS" : "FAIL",
  MOBILE_360: passW(360) ? "PASS" : "FAIL",
  MOBILE_320: passW(320) ? "PASS" : "FAIL",
  HORIZONTAL_OVERFLOW: overflowHits.filter((r) => (r.overflowX || 0) > 0).length,
  CUT_OFF_COMPONENTS: results.reduce((n, r) => n + (r.overflowingCount || 0), 0),
  BROKEN_BUTTONS: brokenButtons,
  BROKEN_TABLES: brokenTables,
  results,
};

fs.writeFileSync(path.join(__dirname, "visual-uat-gate1.json"), JSON.stringify(summary, null, 2));
console.log(JSON.stringify(summary, null, 2));
