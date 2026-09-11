import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
page.on("request", (r) => {
  if (r.url().includes("csrf")) console.log("req", r.method(), r.url());
});
page.on("response", (r) => {
  if (r.url().includes("csrf")) console.log("res", r.status(), r.url());
});
await page.goto("http://127.0.0.1:3002/", { waitUntil: "networkidle", timeout: 120000 });
await page.waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", null, { timeout: 60000 });
await page.getByTestId("header-login-cta").click({ timeout: 8000 });
for (let i = 0; i < 35; i += 1) {
  const disabled = await page.locator('[data-testid="login-form"] input[type="password"]').isDisabled().catch(() => true);
  const cookie = await page.evaluate(() => document.cookie.includes("XSRF-TOKEN"));
  const banner = await page.locator('[data-testid="login-form"]').innerText().catch(() => "");
  console.log(`t=${i}s disabled=${disabled} cookie=${cookie}`);
  if (!disabled) break;
  await page.waitForTimeout(1000);
}
await browser.close();
