import { createRequire } from "node:module";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const out = path.join(path.dirname(fileURLToPath(import.meta.url)), "../../../tmp/login-debug");
fs.mkdirSync(out, { recursive: true });

async function main() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto("https://jetpakistan.pk/login", { waitUntil: "networkidle", timeout: 60000 });
  const inputs = await page.evaluate(() =>
    Array.from(document.querySelectorAll("input")).map((el) => ({
      id: el.id,
      name: el.name,
      type: el.type,
      label: el.labels?.[0]?.textContent?.trim() || null,
    })),
  );
  fs.writeFileSync(path.join(out, "inputs.json"), JSON.stringify(inputs, null, 2));
  fs.writeFileSync(path.join(out, "title.txt"), await page.title());
  console.log("INPUT_COUNT=" + inputs.length);
  await browser.close();
}

main();
