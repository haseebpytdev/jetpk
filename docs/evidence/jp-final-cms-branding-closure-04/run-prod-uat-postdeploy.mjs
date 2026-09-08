import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = __dirname;

const report = {
  ts: new Date().toISOString(),
  base: "https://jetpakistan.pk",
  authorized_sha: "20e921661da55e121a9b2353cba535b350613493",
};

const browser = await chromium.launch({ headless: true });
const page = await (await browser.newContext({ viewport: { width: 390, height: 844 } })).newPage();

for (const [k, url] of [["home", "/"], ["support", "/support"]]) {
  const r = await page.goto(report.base + url, { waitUntil: "domcontentloaded", timeout: 120000 });
  report[`${k}_status`] = r?.status() ?? 0;
}

report.ai_health = await page.evaluate(async () => {
  const csrf = await fetch("/laravel/api/public/content/csrf-token", { credentials: "include" })
    .then((r) => r.json())
    .catch(() => ({}));
  const h = await fetch("/laravel/api/public/ai/health", {
    credentials: "include",
    headers: { "X-XSRF-TOKEN": csrf.csrf_token || "" },
  });
  return { status: h.status, body: await h.json().catch(() => ({})) };
});

report.public_config = await page.evaluate(async () => {
  const r = await fetch("/laravel/api/public/content/config", { credentials: "include" });
  const j = await r.json().catch(() => ({}));
  return {
    status: r.status,
    ai_enabled: j.ai_assistant_enabled,
    logo_url: j.logo_url,
    brand_name: j.brand_name,
  };
});

await page.goto(`${report.base}/`, { waitUntil: "domcontentloaded", timeout: 120000 });
await page.waitForTimeout(6000);

const ask = page.getByTestId("ask-jetpakistan-fab");
const dock = page.getByTestId("public-fab-trigger");
report.ask_fab_visible = await ask.isVisible().catch(() => false);
report.dock_visible = await dock.isVisible().catch(() => false);

if (report.ask_fab_visible && report.dock_visible) {
  const askBox = await ask.boundingBox();
  const dockBox = await dock.boundingBox();
  report.fab_overlap =
    Boolean(askBox && dockBox) &&
    !(
      askBox.x + askBox.width <= dockBox.x ||
      dockBox.x + dockBox.width <= askBox.x ||
      askBox.y + askBox.height <= dockBox.y ||
      dockBox.y + dockBox.height <= askBox.y
    );
  report.ask_fab_bottom_css = await page.evaluate(() =>
    Number.parseInt(getComputedStyle(document.documentElement).getPropertyValue("--jp-ask-fab-bottom"), 10),
  );
}

report.header_logo_src = await page.locator("[data-testid=site-logo-link] img").first().getAttribute("src").catch(() => null);
report.header_logo_uses_storage = Boolean(report.header_logo_src?.includes("/storage/"));

try {
  await page.screenshot({ path: path.join(outDir, "prod-uat-home-mobile-postdeploy.png"), animations: "disabled", timeout: 5000 });
} catch (e) {
  report.screenshot_home_error = String(e.message || e);
}

if (report.ask_fab_visible) {
  await ask.click();
  await page.waitForTimeout(800);
  report.ask_panel_visible = await page.getByTestId("ask-jetpakistan-panel").isVisible().catch(() => false);
  try {
    await page.screenshot({ path: path.join(outDir, "prod-uat-ask-open-mobile.png"), animations: "disabled", timeout: 5000 });
  } catch (e) {
    report.screenshot_ask_error = String(e.message || e);
  }
}

report.trending_cta_href_sample = await page
  .locator('[data-testid="trending-route-card"] a, region[name="Trending route cards"] a')
  .first()
  .getAttribute("href")
  .catch(() => null);

// bounded AI chat probe (read-only, no PII)
if (report.public_config?.ai_enabled) {
  const chat = await page.evaluate(async () => {
    const csrf = await fetch("/laravel/api/public/content/csrf-token", { credentials: "include" }).then((r) => r.json());
    const r = await fetch("/laravel/api/public/ai/chat", {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        "X-XSRF-TOKEN": csrf.csrf_token || "",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({ message: "What is your baggage policy?" }),
    });
    const j = await r.json().catch(() => ({}));
    return { status: r.status, has_message: Boolean(j.message), message_id: j.message_id ?? null };
  });
  report.ai_chat_probe = chat;
}

fs.writeFileSync(path.join(outDir, "prod-uat-postdeploy.json"), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
await browser.close();
