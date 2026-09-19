import path from "node:path";
import { fileURLToPath } from "node:url";
import { createRequire } from "node:module";
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire(path.join(__dirname, "../../../frontend/package.json"));
const { chromium } = require("playwright");

const out = path.join(__dirname, "screenshots", "test-shot.png");
const browser = await chromium.launch();
const page = await browser.newPage();
await page.goto("https://jetpakistan.pk", { waitUntil: "networkidle" });
await page.screenshot({ path: out });
const buildId = await page.evaluate(() => {
  const scripts = Array.from(document.querySelectorAll("script[src]"));
  const ids = [];
  for (const s of scripts) {
    const m = s.src.match(/_next\/static\/([A-Za-z0-9_-]+)\//);
    if (m) ids.push(m[1]);
  }
  const data = [...ids].filter((x) => !["chunks", "css", "media"].includes(x));
  return { ids: [...new Set(ids)], filtered: [...new Set(data)] };
});
console.log("buildId probe", buildId);
console.log("wrote", out);
await browser.close();
