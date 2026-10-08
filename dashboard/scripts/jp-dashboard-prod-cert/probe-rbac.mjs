import { createRequire } from "node:module";
import path from "node:path";
import { fileURLToPath } from "node:url";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");

async function probe(role, url) {
  const state = path.join(repoRoot, `tmp/jp-dash-03-${role.replace("_", "-")}-storage-state.json`);
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ storageState: state });
  const page = await context.newPage();
  const resp = await page.goto(url, { waitUntil: "domcontentloaded", timeout: 60000 });
  await page.waitForTimeout(2000);
  const body = await page.locator("body").innerText();
  const agentShell = (await page.getByTestId("agent-dashboard-shell").count()) > 0;
  const agentOverview = (await page.getByTestId("agent-dashboard-overview").count()) > 0;
  const accessDenied = (await page.getByTestId("dashboard-access-denied").count()) > 0;
  const result = {
    role,
    url,
    finalUrl: page.url().split("?")[0],
    status: resp?.status() ?? 0,
    agentShell,
    agentOverview,
    accessDenied,
    bodySnippet: body.slice(0, 200),
  };
  await browser.close();
  return result;
}

const urls = [
  "https://jetpakistan.pk/agent/dashboard",
  "https://jetpakistan.pk/agent/bookings",
  "https://jetpakistan.pk/agent/wallet",
];

async function main() {
  for (const url of urls) {
    const r = await probe("customer", url);
    console.log(JSON.stringify(r));
  }
}

main();
