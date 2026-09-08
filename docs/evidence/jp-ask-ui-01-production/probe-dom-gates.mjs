/**
 * Supplemental read-only DOM gate probe for JP-ASK-UI-01 production closure.
 * Run from frontend/: node ../docs/evidence/jp-ask-ui-01-production/probe-dom-gates.mjs
 */
import { createRequire } from "node:module";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire(path.join(__dirname, "../../../frontend/package.json"));
const { chromium } = require("playwright");

const BASE = "https://jetpakistan.pk";
const out = { captured_at: new Date().toISOString(), base: BASE, viewports: {} };

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
await page.route(/\.(woff2?|ttf|otf|eot)(\?.*)?$/i, (r) => r.abort());
await page.route("**/fonts.googleapis.com/**", (r) => r.abort());
await page.route("**/fonts.gstatic.com/**", (r) => r.abort());

for (const vp of [
  { key: "desktop-1280", width: 1280, height: 800 },
  { key: "mobile-390", width: 390, height: 844 },
  { key: "mobile-320", width: 320, height: 568 },
]) {
  await page.setViewportSize({ width: vp.width, height: vp.height });
  await page.goto(`${BASE}/`, { waitUntil: "networkidle", timeout: 120000 }).catch(() =>
    page.goto(`${BASE}/`, { waitUntil: "domcontentloaded", timeout: 120000 }),
  );
  await page.waitForTimeout(3000);

  const home = await page.evaluate(() => ({
    humanSupportFab: document.querySelectorAll('[data-testid="human-support-fab"]').length,
    askFab: document.querySelectorAll('[data-testid="ask-jetpakistan-fab"]').length,
    overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    bodyOverflow: document.body.scrollWidth - document.body.clientWidth,
  }));

  await page.getByTestId("ask-jetpakistan-fab").click({ timeout: 30000 });
  await page.getByTestId("ask-jetpakistan-panel").waitFor({ state: "visible", timeout: 15000 });
  const assistant = await page.evaluate(() => ({
    talkToSupport: !!Array.from(document.querySelectorAll("button")).find((b) =>
      b.textContent?.includes("Talk to Support"),
    ),
    chatOptions: !!document.querySelector('[aria-label="Chat options"]'),
    quickCards: document.querySelectorAll("button.quickCard, [class*='quickCard']").length,
    panelOverflow:
      document.documentElement.scrollWidth - document.documentElement.clientWidth,
  }));

  if (vp.width >= 1280) {
    await page.keyboard.press("Escape");
    await page.waitForTimeout(500);
    const escapeClosed = !(await page.getByTestId("ask-jetpakistan-panel").isVisible().catch(() => false));
    assistant.escapeClose = escapeClosed;
  } else {
    await page.getByRole("button", { name: "Close Ask JetPakistan" }).click();
  }

  out.viewports[vp.key] = { home, assistant };
}

await page.setViewportSize({ width: 1280, height: 800 });
await page.goto(`${BASE}/support`, { waitUntil: "domcontentloaded", timeout: 120000 });
await page.waitForTimeout(2000);
const faqBtn = page.getByTestId("support-faq-preview").getByRole("button").first();
await faqBtn.click();
await page.waitForTimeout(300);
out.support = {
  faqVisible: await page.getByTestId("support-faq-preview").isVisible(),
  faqExpanded: (await faqBtn.getAttribute("aria-expanded")) === "true",
  contactCard: await page.getByRole("heading", { name: "Contact Us" }).isVisible(),
  supportForm: await page.getByRole("heading", { name: "Submit a support request" }).isVisible(),
  duplicateFaqLinks: await page.locator('a[href="/faq"]').count(),
  overflow: await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth),
};

fs.writeFileSync(path.join(__dirname, "dom-gates-probe.json"), JSON.stringify(out, null, 2));
await browser.close();
console.log(JSON.stringify(out, null, 2));
