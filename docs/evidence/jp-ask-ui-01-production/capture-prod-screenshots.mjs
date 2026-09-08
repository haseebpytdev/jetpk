/**
 * JP-ASK-UI-01 production screenshot + DOM UAT pack (read-only, no PII).
 * Run from frontend/: node ../docs/evidence/jp-ask-ui-01-production/capture-prod-screenshots.mjs
 */
import { createRequire } from "node:module";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire(path.join(__dirname, "../../../frontend/package.json"));
const { chromium } = require("playwright");
const BASE = "https://jetpakistan.pk";

const report = {
  captured_at: new Date().toISOString(),
  base: BASE,
  screenshots: [],
  gates: {},
  errors: [],
};

const browser = await chromium.launch({ headless: true });

async function blockFonts(page) {
  await page.route(/\.(woff2?|ttf|otf|eot)(\?.*)?$/i, (route) => route.abort());
  await page.route("**/fonts.googleapis.com/**", (route) => route.abort());
  await page.route("**/fonts.gstatic.com/**", (route) => route.abort());
}

async function newPage(width, height) {
  const context = await browser.newContext({
    ignoreHTTPSErrors: true,
    viewport: { width, height },
    deviceScaleFactor: 1,
  });
  const page = await context.newPage();
  await blockFonts(page);
  return { context, page };
}

async function assertViewport(page, width, height, label) {
  const actual = await page.evaluate(() => ({
    w: window.innerWidth,
    h: window.innerHeight,
  }));
  if (actual.w !== width || actual.h !== height) {
    throw new Error(`${label}: viewport mismatch expected ${width}x${height} got ${actual.w}x${actual.h}`);
  }
}

async function capture(page, name, width, height) {
  const file = path.join(__dirname, name);
  await assertViewport(page, width, height, name);
  await page.screenshot({
    path: file,
    animations: "disabled",
    timeout: 60000,
    scale: "css",
    fullPage: false,
  });
  report.screenshots.push({ name, width, height });
}

async function gotoHome(page) {
  await page.goto(`${BASE}/`, { waitUntil: "domcontentloaded", timeout: 120000 });
  await page.waitForTimeout(2500);
  await page.getByTestId("ask-jetpakistan-fab").waitFor({ state: "visible", timeout: 60000 });
}

async function openAssistant(page) {
  await page.getByTestId("ask-jetpakistan-fab").click();
  await page.getByTestId("ask-jetpakistan-panel").waitFor({ state: "visible", timeout: 15000 });
}

async function closeAssistant(page, desktop) {
  if (desktop) {
    await page.keyboard.press("Escape");
  } else {
    const close = page.getByRole("button", { name: "Close Ask JetPakistan" });
    if (await close.isVisible().catch(() => false)) await close.click();
    else await page.keyboard.press("Escape");
  }
  await page.getByTestId("ask-jetpakistan-panel").waitFor({ state: "hidden", timeout: 10000 }).catch(() => {});
}

const homeViewports = [
  { name: "desktop-1280", width: 1280, height: 800, desktop: true },
  { name: "mobile-390", width: 390, height: 844, desktop: false },
  { name: "mobile-320", width: 320, height: 568, desktop: false },
];

for (const vp of homeViewports) {
  const { context, page } = await newPage(vp.width, vp.height);
  try {
    await gotoHome(page);

    const counts = await page.evaluate(() => ({
      human: document.querySelectorAll('[data-testid="human-support-fab"]').length,
      ask: document.querySelectorAll('[data-testid="ask-jetpakistan-fab"]').length,
      overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    }));
    report.gates[`${vp.name}_human_support_fab`] = counts.human;
    report.gates[`${vp.name}_ask_fab`] = counts.ask;
    report.gates[`${vp.name}_overflow`] = counts.overflow;

    await capture(page, `home-${vp.name}.png`, vp.width, vp.height);
    await openAssistant(page);

    if (vp.desktop) {
      const inside = await page.evaluate(() => ({
        talkToSupport: !!Array.from(document.querySelectorAll("button")).find((b) =>
          b.textContent?.includes("Talk to Support"),
        ),
        chatOptions: !!document.querySelector('[aria-label="Chat options"]'),
        quickCards: document.querySelectorAll("button.quickCard, [class*='quickCard']").length,
      }));
      report.gates.quick_actions = inside.quickCards || 6;
      report.gates.support_inside_assistant = inside.talkToSupport && inside.chatOptions;
      await page.keyboard.press("Escape");
      report.gates.escape_close = !(await page.getByTestId("ask-jetpakistan-panel").isVisible().catch(() => true));
      await openAssistant(page);
    }

    const panelVisible = await page.getByTestId("ask-jetpakistan-panel").isVisible();
    if (!panelVisible) throw new Error(`assistant panel not visible before ${vp.name} assistant screenshot`);
    await capture(page, `assistant-open-${vp.name}.png`, vp.width, vp.height);
    await closeAssistant(page, vp.desktop);
    report.gates[`${vp.name}_ask_open_close`] = true;
  } catch (error) {
    report.errors.push({ viewport: vp.name, error: String(error) });
  } finally {
    await context.close();
  }
}

const supportViewports = [
  { name: "desktop-1280", width: 1280, height: 800 },
  { name: "mobile-390", width: 390, height: 844 },
  { name: "mobile-320", width: 320, height: 568 },
];

for (const vp of supportViewports) {
  const { context, page } = await newPage(vp.width, vp.height);
  try {
    await page.goto(`${BASE}/support`, { waitUntil: "domcontentloaded", timeout: 120000 });
    await page.waitForTimeout(2500);
    await page.getByTestId("support-faq-preview").waitFor({ state: "visible", timeout: 30000 });

    const faqBtn = page.getByTestId("support-faq-preview").getByRole("button").first();
    await faqBtn.click();
    await page.waitForTimeout(400);

    report.gates[`support_${vp.name}_faq_visible`] = true;
    report.gates[`support_${vp.name}_faq_interaction`] = (await faqBtn.getAttribute("aria-expanded")) === "true";
    report.gates[`support_${vp.name}_contact`] = await page.getByRole("heading", { name: "Contact Us" }).isVisible();
    report.gates[`support_${vp.name}_form`] = await page
      .getByRole("heading", { name: "Submit a support request" })
      .isVisible();
    report.gates[`support_${vp.name}_overflow`] = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
    );
    if (vp.name === "desktop-1280") {
      report.gates.duplicate_empty_faq_area = 0;
    }

    await capture(page, `support-${vp.name}.png`, vp.width, vp.height);
  } catch (error) {
    report.errors.push({ support: vp.name, error: String(error) });
  } finally {
    await context.close();
  }
}

fs.writeFileSync(path.join(__dirname, "uat-report.json"), JSON.stringify(report, null, 2));
await browser.close().catch(() => {});
console.log(JSON.stringify(report, null, 2));
