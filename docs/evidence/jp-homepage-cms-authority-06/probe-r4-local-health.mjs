import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
const consoleMsgs = [];
page.on("console", (m) => consoleMsgs.push(`${m.type()}: ${m.text()}`));
page.on("pageerror", (e) => consoleMsgs.push(`pageerror: ${e.message}`));
try {
  await page.goto("http://127.0.0.1:3002/", { waitUntil: "networkidle", timeout: 120000 });
  await page.waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", null, { timeout: 60000 }).catch(() => null);
  const hydrated = await page.evaluate(() => document.documentElement.dataset.jpHydrated);
  const loginVisible = await page.getByTestId("header-login-cta").isVisible().catch(() => false);
  const loginInDom = await page.locator('[data-testid="header-login-cta"]').count();
  const title = await page.title();
  const snap = await page.evaluate(() => ({
    path: location.pathname,
    bodyText: document.body?.innerText?.slice(0, 400) ?? "",
    navHtml: document.querySelector("nav")?.outerHTML?.slice(0, 500) ?? "no-nav",
    testIds: [...document.querySelectorAll("[data-testid]")].map((e) => e.getAttribute("data-testid")).slice(0, 20),
  }));
  console.log("title", title, "hydrated", hydrated, "loginVisible", loginVisible, "loginInDom", loginInDom);
  console.log("snap", JSON.stringify(snap, null, 2));
  if (consoleMsgs.length) console.log("console", consoleMsgs.slice(0, 15));
  if (loginVisible) {
    await page.getByTestId("header-login-cta").click({ timeout: 8000 });
    await page.waitForTimeout(5000);
    const path = await page.evaluate(() => location.pathname);
    const pwd = page.locator('[data-testid="login-form"] input[type="password"]');
    const count = await pwd.count();
    const disabled = count ? await pwd.first().isDisabled() : null;
    console.log("path", path, "passwordCount", count, "disabled", disabled);
  }
  const groups = page.locator('nav[aria-label="Primary"] a[href="/groups"]').first();
  console.log("groupsLink", await groups.count());
} catch (e) {
  console.error("ERR", e.message);
}
await browser.close();
