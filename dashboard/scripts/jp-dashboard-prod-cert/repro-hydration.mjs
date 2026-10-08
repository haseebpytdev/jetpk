import { createRequire } from "node:module";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const out = path.join(repoRoot, "docs/evidence/jp-dashboard-production-cert-20261008/hydration");
fs.mkdirSync(out, { recursive: true });

const routes = [
  "/admin/dashboard",
  "/admin/dashboard/bookings",
  "/admin/dashboard/api-connections",
  "/admin/dashboard/settings",
  "/admin/dashboard/cms/pages",
];

async function main() {
  const state = path.join(repoRoot, "tmp/jp-dash-03-admin-storage-state.json");
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ storageState: state });
  const page = await context.newPage();
  const report = {};

  for (const route of routes) {
    const hydration = [];
    const pageErrors = [];
    page.on("console", (msg) => {
      if (msg.type() === "error") hydration.push(msg.text());
    });
    page.on("pageerror", (err) => pageErrors.push(err.message));

    await page.goto(`https://jetpakistan.pk${route}`, { waitUntil: "networkidle", timeout: 90000 }).catch(() => {});
    await page.waitForTimeout(1500);
    report[route] = {
      hydrationErrors: hydration.filter((t) => /418|hydration/i.test(t)),
      pageErrors,
      allConsoleErrors: hydration.slice(0, 10),
    };
    await page.screenshot({ path: path.join(out, route.replace(/\//g, "_") + ".png"), fullPage: true });
  }

  fs.writeFileSync(path.join(out, "hydration-report.json"), JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  await browser.close();
}

main();
