import { test } from "@playwright/test";
import path from "node:path";
import fs from "node:fs";

const OUTPUT_DIR = path.resolve(process.cwd(), "..", "tmp", "search-ui-polish-visual");

const VIEWPORTS = [
  { name: "desktop", width: 1920, height: 1080 },
  { name: "tablet", width: 768, height: 1024 },
  { name: "mobile", width: 390, height: 844 },
];

test.beforeAll(() => {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });
});

for (const viewport of VIEWPORTS) {
  test(`capture search panel at ${viewport.name}`, async ({ page }) => {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("search-module").scrollIntoViewIfNeeded();
    await page.screenshot({
      path: path.join(OUTPUT_DIR, `${viewport.name}-search-panel.png`),
      fullPage: false,
    });
  });

  test(`capture travellers panel at ${viewport.name}`, async ({ page }) => {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("travelers-cabin-trigger").first().click();
    await page.waitForTimeout(200);
    await page.screenshot({
      path: path.join(OUTPUT_DIR, `${viewport.name}-travelers-panel.png`),
      fullPage: false,
    });
  });
}
