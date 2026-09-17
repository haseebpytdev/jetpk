import { createRequire } from "module";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import { createServer } from "http";

const require = createRequire(import.meta.url);
let chromium;
try {
  ({ chromium } = require("../../../node_modules/playwright"));
} catch {
  ({ chromium } = require("C:/Users/khadi/ota/node_modules/playwright"));
}

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const fixturesDir = path.join(__dirname, "fixtures");
const outDir = path.join(__dirname, "screenshots-continue04");
fs.mkdirSync(outDir, { recursive: true });

const files = [
  "login-otp-current.html",
  "auth-admin-login.html",
  "welcome-customer.html",
  "booking-confirmed-customer.html",
  "daily-admin-report.html",
  "abandoned-search.html",
  "booking-long-data-stress.html",
].filter((f) => fs.existsSync(path.join(fixturesDir, f)));

const widths = [1280, 430, 390, 360, 320];

function inspectMetrics(page) {
  return page.evaluate(() => {
    const doc = document.documentElement;
    const body = document.body;
    const vw = window.innerWidth;
    const scrollW = Math.max(doc.scrollWidth, body.scrollWidth);
    const overflowX = scrollW - vw > 2 ? Math.round(scrollW - vw) : 0;

    let narrowValueColumn = 0;
    let rightEdgeSqueeze = 0;
    const text = (body.innerText || "").replace(/\s+/g, " ");

    for (const el of body.querySelectorAll("td, div")) {
      const t = (el.innerText || "").trim();
      if (!t) continue;
      const r = el.getBoundingClientRect();
      if (r.width > 0 && r.width < 48 && t.length >= 6 && /sign|web|context|http|booking/i.test(t)) {
        narrowValueColumn += 1;
      }
      if (r.right > vw - 8 && r.width < 72 && t.includes("\n") && t.length < 40) {
        rightEdgeSqueeze += 1;
      }
    }

    const overflowing = [];
    for (const el of body.querySelectorAll("*")) {
      const r = el.getBoundingClientRect();
      if (r.width <= 0 || r.height <= 0) continue;
      if (r.right > vw + 1 || r.left < -1) {
        overflowing.push(el.tagName.toLowerCase());
        if (overflowing.length >= 12) break;
      }
    }

    const links = [...body.querySelectorAll("a")].map((a) => {
      const r = a.getBoundingClientRect();
      return {
        clipped: r.width > 0 && (r.right > vw + 1 || r.left < -1),
        tiny: (r.h = Math.round(r.height)) < 8 || Math.round(r.width) < 8,
      };
    });
    const tables = [...body.querySelectorAll("table")].map((t) => {
      const r = t.getBoundingClientRect();
      return { clipped: r.right > vw + 1 || r.left < -1 };
    });

    const hasRequestContext =
      /Request context/i.test(text) || /Web sign-in/i.test(text);

    return {
      overflowX,
      overflowingCount: overflowing.length,
      brokenButtons: links.filter((l) => l.clipped || l.tiny).length,
      brokenTables: tables.filter((t) => t.clipped).length,
      narrowValueColumn,
      rightEdgeSqueeze,
      hasRequestContext,
      hasNowrap: !!body.innerHTML.includes("white-space:nowrap"),
      hasTwoTdRightAlignPattern: /white-space:nowrap[\s\S]{0,200}align=\"right\"/i.test(body.innerHTML),
    };
  });
}

const server = createServer((req, res) => {
  const rel = decodeURIComponent((req.url || "/").replace(/^\//, ""));
  const file = path.normalize(path.join(fixturesDir, rel.replace(/^fixtures\//, "")));
  if (!file.startsWith(fixturesDir) || !fs.existsSync(file)) {
    res.writeHead(404);
    res.end("missing");
    return;
  }
  res.writeHead(200, { "Content-Type": "text/html; charset=utf-8" });
  res.end(fs.readFileSync(file));
});

await new Promise((resolve) => server.listen(0, "127.0.0.1", resolve));
const { port } = server.address();
const base = `http://127.0.0.1:${port}`;

const browser = await chromium.launch({ headless: true, timeout: 120000 });
const context = await browser.newContext();
const results = [];

for (const file of files) {
  for (const width of widths) {
    const page = await context.newPage();
    await page.setViewportSize({ width, height: width >= 1280 ? 900 : 900 });
    await page.goto(`${base}/${file}`, { waitUntil: "load", timeout: 30000 });
    const metrics = await inspectMetrics(page);
    const shot = `${file.replace(".html", "")}-w${width}.png`;
    await page.screenshot({ path: path.join(outDir, shot), fullPage: true, type: "png" });
    await page.close();
    results.push({ file, width, shot, ...metrics });
    console.log(`${file}@${width}`, JSON.stringify(metrics));
  }
}

await browser.close();
server.close();

const passW = (w) =>
  results
    .filter((r) => r.width === w)
    .every(
      (r) =>
        !r.overflowX &&
        !r.overflowingCount &&
        !(r.brokenButtons > 0) &&
        !(r.brokenTables > 0) &&
        !(r.narrowValueColumn > 0) &&
        !(r.rightEdgeSqueeze > 0) &&
        !r.hasTwoTdRightAlignPattern
    );

const otpRows = results.filter((r) => r.file === "login-otp-current.html");
const summary = {
  SCREENSHOT_EVIDENCE_CREATED: results.length === files.length * widths.length ? "YES" : "NO",
  DESKTOP_VISUAL_QA: passW(1280) ? "PASS" : "FAIL",
  MOBILE_430: passW(430) ? "PASS" : "FAIL",
  MOBILE_390: passW(390) ? "PASS" : "FAIL",
  MOBILE_360: passW(360) ? "PASS" : "FAIL",
  MOBILE_320: passW(320) ? "PASS" : "FAIL",
  HORIZONTAL_OVERFLOW: results.filter((r) => r.overflowX > 0).length,
  CUT_OFF_COMPONENTS: results.reduce((n, r) => n + (r.overflowingCount || 0), 0),
  BROKEN_BUTTONS: results.reduce((n, r) => n + (r.brokenButtons || 0), 0),
  BROKEN_TABLES: results.reduce((n, r) => n + (r.brokenTables || 0), 0),
  NARROW_VALUE_COLUMN: results.reduce((n, r) => n + (r.narrowValueColumn || 0), 0),
  RIGHT_EDGE_SQUEEZE: results.reduce((n, r) => n + (r.rightEdgeSqueeze || 0), 0),
  OTP_NO_REQUEST_CONTEXT: otpRows.every((r) => !r.hasRequestContext) ? "PASS" : "FAIL",
  TWO_TD_NOWRAP_REGRESSION: results.some((r) => r.hasTwoTdRightAlignPattern) ? "FAIL" : "PASS",
  LONG_DATA_LAYOUT: results.filter((r) => r.file.includes("long-data")).every((r) => !r.overflowX && !r.narrowValueColumn)
    ? "PASS"
    : "FAIL",
  results,
};

fs.writeFileSync(path.join(__dirname, "continue04-visual-uat.json"), JSON.stringify(summary, null, 2));
console.log(JSON.stringify(summary, null, 2));

const hardFail =
  summary.HORIZONTAL_OVERFLOW > 0 ||
  summary.NARROW_VALUE_COLUMN > 0 ||
  summary.RIGHT_EDGE_SQUEEZE > 0 ||
  summary.TWO_TD_NOWRAP_REGRESSION === "FAIL" ||
  summary.MOBILE_320 === "FAIL" ||
  summary.DESKTOP_VISUAL_QA === "FAIL";
process.exit(hardFail ? 1 : 0);
