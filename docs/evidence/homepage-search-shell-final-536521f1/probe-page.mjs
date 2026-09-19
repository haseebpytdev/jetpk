import path from "node:path";
import { fileURLToPath } from "node:url";
import { createRequire } from "node:module";
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire(path.join(__dirname, "../../../frontend/package.json"));
const { chromium } = require("playwright");

const browser = await chromium.launch();
const page = await browser.newPage();
await page.goto("https://jetpakistan.pk", { waitUntil: "domcontentloaded", timeout: 60000 });
const data = await page.evaluate(() => {
  const html = document.documentElement.innerHTML;
  const hits = [];
  for (const re of [
    /buildId/gi,
    /BUILD_ID/gi,
    /jetpk-runtime/gi,
    /source-sha/gi,
    /536521f/gi,
    /[A-Za-z0-9]{20,}/g,
  ]) {
    const m = html.match(re);
    if (m) hits.push({ re: re.toString(), count: m.length, sample: m.slice(0, 3) });
  }
  const comments = [...html.matchAll(/<!--([\s\S]*?)-->/g)].map((m) => m[1]).filter((c) => /build|sha|jetpk/i.test(c));
  const allMeta = Array.from(document.querySelectorAll("meta")).map((el) => ({
    name: el.getAttribute("name"),
    property: el.getAttribute("property"),
    content: el.getAttribute("content"),
  }));
  const bodyAttrs = Array.from(document.body?.attributes || []).map((a) => [a.name, a.value]);
  const htmlAttrs = Array.from(document.documentElement.attributes).map((a) => [a.name, a.value]);
  return { hits, comments, allMeta: allMeta.slice(0, 20), bodyAttrs, htmlAttrs };
});
console.log(JSON.stringify(data, null, 2));
await browser.close();
