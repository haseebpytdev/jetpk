import { chromium } from "../../../../frontend/node_modules/playwright/index.mjs";

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
const samples = [];
for (let i = 0; i < 5; i++) {
  await page.goto("https://jetpakistan.pk/", { waitUntil: "domcontentloaded", timeout: 60000 });
  await page.waitForTimeout(600);
  const link = page.locator('footer a[href="/privacy"]').first();
  await link.hover({ timeout: 2000 }).catch(() => null);
  const t0 = Date.now();
  await Promise.all([
    page.waitForURL((url) => new URL(url).pathname.replace(/\/$/, "") === "/privacy", { timeout: 15000 }),
    link.click({ timeout: 10000 }),
  ]);
  samples.push(Date.now() - t0);
}
console.log(JSON.stringify({ samples }));
await browser.close();
