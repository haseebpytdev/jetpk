import { chromium } from "@playwright/test";
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
page.on("console", (msg) => console.log(`BROWSER_CONSOLE=${msg.type()}:${msg.text().slice(0, 200)}`));
page.on("pageerror", (err) => console.log(`PAGE_ERROR=${String(err).slice(0, 200)}`));

await page.goto(`${baseUrl}/`, { waitUntil: "networkidle", timeout: 90000 });
await page.waitForTimeout(1500);

const byTestId = page.getByTestId("account-menu-trigger-desktop");
console.log(`TESTID_COUNT=${await byTestId.count()}`);
if ((await byTestId.count()) > 0) {
  console.log(`EXPANDED_BEFORE=${await byTestId.getAttribute("aria-expanded")}`);
  await byTestId.click({ force: true });
  await page.waitForTimeout(800);
  console.log(`EXPANDED_AFTER=${await byTestId.getAttribute("aria-expanded")}`);
  console.log(`MENU_COUNT=${await page.getByRole("menu").count()}`);
  console.log(`PANEL_COUNT=${await page.getByTestId("account-menu-panel-desktop").count()}`);
  const html = await byTestId.evaluate((el) => el.outerHTML.slice(0, 300));
  console.log(`TRIGGER_HTML=${html}`);
}

// Check for overlapping elements at click point
if ((await byTestId.count()) > 0) {
  const box = await byTestId.boundingBox();
  if (box) {
    const top = await page.evaluate(({ x, y }) => {
      const el = document.elementFromPoint(x, y);
      return el ? { tag: el.tagName, className: el.className?.toString?.().slice(0, 120), text: (el.textContent || "").slice(0, 80) } : null;
    }, { x: box.x + box.width / 2, y: box.y + box.height / 2 });
    console.log(`ELEMENT_FROM_POINT=${JSON.stringify(top)}`);
  }
}

await browser.close();
