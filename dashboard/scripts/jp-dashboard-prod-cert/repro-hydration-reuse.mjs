import { createRequire } from "node:module";
import path from "node:path";
import { fileURLToPath } from "node:url";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../../../");
const state = path.join(repoRoot, "tmp/jp-dash-03-admin-storage-state.json");
const routes = ["/admin/dashboard", "/admin/dashboard/bookings", "/admin/dashboard/payments"];

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ storageState: state });
  const page = await context.newPage();
  const all = [];

  for (const route of routes) {
    const hydration = [];
    page.on("pageerror", (e) => hydration.push(e.message));
    page.on("console", (msg) => {
      if (msg.type() === "error") hydration.push(msg.text());
    });
    await page.goto(`https://jetpakistan.pk${route}`, { waitUntil: "domcontentloaded" });
    await page.waitForTimeout(2000);
    all.push({ route, errors: hydration.filter((t) => /418|hydration/i.test(t)) });
  }

  console.log(JSON.stringify(all, null, 2));
  await browser.close();
}

main();
