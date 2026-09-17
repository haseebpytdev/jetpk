import { createRequire } from "module";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const require = createRequire(import.meta.url);
const { chromium } = require("C:/Users/khadi/ota/node_modules/playwright");
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.join(__dirname, "screenshots");
const base = "http://127.0.0.1:8765/artifacts";
const file = "booking-long-data-stress.html";
const widths = [1280, 320];
const results = [];

console.log("launching...");
const browser = await chromium.launch({ headless: true, timeout: 120000 });
const context = await browser.newContext();
for (const width of widths) {
  const page = await context.newPage();
  await page.setViewportSize({ width, height: 900 });
  await page.goto(`${base}/${file}`, { waitUntil: "load", timeout: 30000 });
  const metrics = await page.evaluate(() => {
    const doc = document.documentElement;
    const body = document.body;
    const vw = window.innerWidth;
    const overflowX = Math.max(doc.scrollWidth, body.scrollWidth) - vw;
    let cut = 0;
    for (const el of body.querySelectorAll("*")) {
      const r = el.getBoundingClientRect();
      if (r.width > 0 && (r.right > vw + 1 || r.left < -1)) cut++;
    }
    return {
      overflowX: overflowX > 2 ? Math.round(overflowX) : 0,
      cutOff: cut,
      textSample: (body.innerText || "").slice(0, 500),
    };
  });
  const shot = `booking-long-data-stress-w${width}.png`;
  await page.screenshot({ path: path.join(outDir, shot), fullPage: true });
  await page.close();
  results.push({ width, shot, ...metrics });
}
await browser.close();
const summary = {
  LONG_DATA_OVERFLOW: results.reduce((n, r) => n + (r.overflowX > 0 ? 1 : 0), 0),
  LONG_DATA_CUT_OFF: results.reduce((n, r) => n + r.cutOff, 0),
  LONG_DATA_LAYOUT: results.every((r) => r.overflowX === 0 && r.cutOff === 0) ? "PASS" : "FAIL",
  results,
};
fs.writeFileSync(path.join(__dirname, "visual-uat-gate2-long-data.json"), JSON.stringify(summary, null, 2));
console.log(JSON.stringify(summary, null, 2));
