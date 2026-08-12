/**
 * Verifier: account menu open + portal links via accessible name (not testids as nav map).
 */
import { chromium } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../..");
const baseUrl = process.env.JP_ACCEPTANCE_BASE_URL ?? "https://jetpakistan.pk";
const role = process.argv[2] || "customer";
const storage = path.join(repoRoot, `tmp/jp-dash-03-${role}-storage-state.json`);

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ storageState: storage, viewport: { width: 1440, height: 900 } });
const page = await context.newPage();
await page.goto(`${baseUrl}/`, { waitUntil: "domcontentloaded", timeout: 60000 });

const trigger = page.getByRole("button", { name: /JP-DASH-03 QA|Customer|Agent|Staff|Admin/i }).first();
console.log(`TRIGGER_COUNT=${await trigger.count()}`);
if ((await trigger.count()) > 0) {
  await trigger.click();
  await page.waitForTimeout(500);
  const menu = page.getByRole("menu");
  console.log(`MENU_COUNT=${await menu.count()}`);
  const items = await page.getByRole("menuitem").allTextContents();
  console.log(`MENU_ITEMS=${JSON.stringify(items.map((t) => t.trim()))}`);
  const dash = page.getByRole("menuitem", { name: /dashboard/i }).first();
  if ((await dash.count()) > 0) {
    await dash.click();
    await page.waitForTimeout(2000);
  }
}
console.log(`FINAL_PATH=${new URL(page.url()).pathname}`);
const body = (await page.locator("body").innerText()).replace(/\s+/g, " ").slice(0, 300);
console.log(`SNIPPET=${body}`);
await browser.close();
