import path from "node:path";
import { fileURLToPath } from "node:url";
import { createRequire } from "node:module";
import { existsSync, statSync } from "node:fs";
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire(path.join(__dirname, "../../../frontend/package.json"));
const { chromium } = require("playwright");

const out = path.join(__dirname, "screenshots", "w1024-flights-oneway.png");
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1024, height: 900 } });
await page.goto("https://jetpakistan.pk", { waitUntil: "domcontentloaded", timeout: 60000 });
await page.waitForSelector('[data-testid="homepage-search-shell"]', { timeout: 30000 });
await page.screenshot({ path: out });
const info = await page.evaluate(() => {
  const html = document.documentElement.outerHTML;
  const m = html.match(/jetpk-build-id|data-build-id|x-next-build-id|BUILD_ID/gi) || [];
  const scripts = Array.from(document.querySelectorAll("script[src]")).map((s) => s.src);
  const meta = Array.from(document.querySelectorAll("meta")).map((el) => ({
    name: el.getAttribute("name"),
    content: el.getAttribute("content"),
  })).filter((x) => x.name && /build|jetpk|sha/i.test(x.name));
  return { markers: m.slice(0, 10), meta, scriptCount: scripts.length };
});
console.log("exists", existsSync(out), existsSync(out) ? statSync(out).size : 0);
console.log("info", JSON.stringify(info, null, 2));
await browser.close();
