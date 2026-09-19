import path from "node:path";
import { fileURLToPath } from "node:url";
import { createRequire } from "node:module";
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire(path.join(__dirname, "../../../frontend/package.json"));
const { chromium } = require("playwright");

const browser = await chromium.launch();
const page = await browser.newPage();
const responses = [];
page.on("response", (r) => {
  const u = r.url();
  if (u.includes("_next") || u.includes("build")) responses.push(u);
});
await page.goto("https://jetpakistan.pk", { waitUntil: "domcontentloaded", timeout: 60000 });
await page.waitForTimeout(2000);
const inline = await page.evaluate(() => {
  const scripts = Array.from(document.querySelectorAll("script:not([src])")).map((s) => s.textContent || "");
  const joined = scripts.join("\n");
  const buildMatches = joined.match(/buildId["':\s]+["']([A-Za-z0-9_-]+)["']/g) || [];
  const dataMatches = joined.match(/mHk-[A-Za-z0-9_-]+/g) || [];
  return { buildMatches: buildMatches.slice(0, 5), dataMatches: [...new Set(dataMatches)], scriptLen: joined.length };
});
console.log("inline", inline);
console.log("responses sample", responses.slice(0, 15));
const testUrl = await fetch("https://jetpakistan.pk/_next/static/mHk-585jVMAtC6DsijCuS/_buildManifest.js", { method: "HEAD" });
console.log("build manifest head", testUrl.status);
await browser.close();
