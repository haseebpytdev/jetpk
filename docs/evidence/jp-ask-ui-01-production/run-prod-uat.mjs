/**
 * JP-ASK-UI-01 production UAT — read-only browser checks on https://jetpakistan.pk
 */
import { chromium } from "playwright";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = "https://jetpakistan.pk";
const outDir = path.resolve(__dirname, "jp-ask-ui-01-production");
fs.mkdirSync(outDir, { recursive: true });

const report = {
  base: BASE,
  captured_at: new Date().toISOString(),
  gates: {},
  chat_probe: null,
  errors: [],
};

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();

async function shot(name) {
  await page.screenshot({ path: path.join(outDir, `${name}.png`), fullPage: false });
}

async function overflow() {
  return page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
}

for (const vp of [
  { name: "desktop-1280", width: 1280, height: 800 },
  { name: "mobile-390", width: 390, height: 844 },
  { name: "mobile-320", width: 320, height: 568 },
]) {
  await page.setViewportSize({ width: vp.width, height: vp.height });
  await page.goto(`${BASE}/`, { waitUntil: "domcontentloaded", timeout: 120000 });
  await page.waitForTimeout(2500);

  const counts = await page.evaluate(() => ({
    humanSupport: document.querySelectorAll('[data-testid="human-support-fab"]').length,
    askFab: document.querySelectorAll('[data-testid="ask-jetpakistan-fab"]').length,
    publicDock: document.querySelectorAll('[data-testid="public-fab-dock"]').length,
  }));

  report.gates[`${vp.name}_human_support_fab`] = counts.humanSupport;
  report.gates[`${vp.name}_ask_fab`] = counts.askFab;
  report.gates[`${vp.name}_public_dock`] = counts.publicDock;
  report.gates[`${vp.name}_overflow`] = await overflow();

  await shot(`home-${vp.name}`);

  if (counts.askFab > 0) {
    await page.getByTestId("ask-jetpakistan-fab").click({ timeout: 15000 });
    await page.waitForTimeout(800);
    await shot(`assistant-open-${vp.name}`);
    if (vp.width >= 1280) {
      await page.keyboard.press("Escape");
      await page.waitForTimeout(400);
    } else {
      await page.getByRole("button", { name: "Close Ask JetPakistan" }).click({ timeout: 5000 }).catch(() => {});
    }
  }

  if (vp.width < 1280 && counts.publicDock > 0) {
    const trigger = page.getByTestId("public-fab-trigger");
    if (await trigger.isVisible().catch(() => false)) {
      await trigger.click({ timeout: 5000 }).catch(() => {});
      await page.waitForTimeout(500);
      const supportTile = page.getByRole("link", { name: "Support", exact: true });
      report.gates[`${vp.name}_dock_support_tile`] = await supportTile.isVisible().catch(() => false);
      await shot(`dock-open-${vp.name}`);
      await page.keyboard.press("Escape").catch(() => {});
    }
  }
}

await page.setViewportSize({ width: 1280, height: 800 });
await page.goto(`${BASE}/support`, { waitUntil: "domcontentloaded", timeout: 120000 });
await page.waitForTimeout(2000);
report.gates.support_faq_preview = await page.getByTestId("support-faq-preview").isVisible().catch(() => false);
report.gates.support_contact = await page.getByRole("heading", { name: "Contact Us" }).isVisible().catch(() => false);
report.gates.support_form_heading = await page.getByRole("heading", { name: "Submit a support request" }).isVisible().catch(() => false);
const faqBtn = page.getByTestId("support-faq-preview").getByRole("button").first();
if (await faqBtn.isVisible().catch(() => false)) {
  await faqBtn.click();
  report.gates.support_faq_expanded = (await faqBtn.getAttribute("aria-expanded")) === "true";
}
report.gates.support_overflow = await overflow();
await page.screenshot({ path: path.join(outDir, "support-desktop-1280.png"), fullPage: true });

// Safe chat probe: synthetic message, no PII
await page.goto(`${BASE}/`, { waitUntil: "domcontentloaded", timeout: 120000 });
await page.waitForTimeout(2000);
if (await page.getByTestId("ask-jetpakistan-fab").isVisible().catch(() => false)) {
  await page.getByTestId("ask-jetpakistan-fab").click();
  await page.waitForTimeout(500);
  const msg = `JP-ASK-UI-01 probe ${Date.now()}`;
  const chatResult = await page.evaluate(async (text) => {
    try {
      const csrfRes = await fetch("/laravel/api/public/content/csrf-token", { credentials: "include" });
      const csrfJson = await csrfRes.json().catch(() => ({}));
      const token = csrfJson.csrf_token || "";
      const res = await fetch("/laravel/api/public/ai/chat", {
        method: "POST",
        credentials: "include",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
          ...(token ? { "X-XSRF-TOKEN": token } : {}),
        },
        body: JSON.stringify({ message: text }),
      });
      const json = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, hasMessage: typeof json.message === "string" && json.message.length > 0 };
    } catch (e) {
      return { ok: false, status: 0, hasMessage: false, error: String(e) };
    }
  }, msg);
  report.chat_probe = chatResult;
}

fs.writeFileSync(path.join(outDir, "uat-report.json"), JSON.stringify(report, null, 2));
await browser.close();
console.log(JSON.stringify(report, null, 2));
