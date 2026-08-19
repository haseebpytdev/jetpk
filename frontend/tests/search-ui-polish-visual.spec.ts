import { test, expect, type Page } from "@playwright/test";
import path from "node:path";
import fs from "node:fs";

const OUTPUT_DIR = path.resolve(process.cwd(), "..", "tmp", "search-ui-polish-visual");

const VIEWPORTS = [
  { name: "desktop", width: 1920, height: 1080 },
  { name: "laptop", width: 1366, height: 768 },
  { name: "tablet", width: 768, height: 1024 },
  { name: "mobile", width: 390, height: 844 },
] as const;

async function assertNoHorizontalOverflow(page: Page) {
  const overflow = await page.evaluate(() =>
    Math.max(0, document.documentElement.scrollWidth - window.innerWidth),
  );
  expect(overflow).toBeLessThanOrEqual(1);
}

async function assertPanelInViewport(page: Page, testId: string) {
  const box = await page.getByTestId(testId).boundingBox();
  const viewport = page.viewportSize();
  expect(box).not.toBeNull();
  expect(viewport).not.toBeNull();
  if (!box || !viewport) return;

  expect(box.x).toBeGreaterThanOrEqual(-1);
  expect(box.y).toBeGreaterThanOrEqual(-1);
  expect(box.x + box.width).toBeLessThanOrEqual(viewport.width + 1);
  expect(box.y + box.height).toBeLessThanOrEqual(viewport.height + 1);
}

test.beforeAll(() => {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });
});

for (const viewport of VIEWPORTS) {
  test(`capture search panel at ${viewport.name}`, async ({ page }) => {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("search-module").scrollIntoViewIfNeeded();

    const searchModule = page.getByTestId("search-module");
    const searchBox = await searchModule.boundingBox();
    expect(searchBox).not.toBeNull();
    if (searchBox) {
      expect(searchBox.x + searchBox.width).toBeLessThanOrEqual(viewport.width + 1);
    }
    await assertNoHorizontalOverflow(page);
    await expect(page.getByTestId("homepage-hero-image")).toBeVisible();

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

    await assertPanelInViewport(page, "travelers-cabin-panel");
    await assertNoHorizontalOverflow(page);
    await expect(page.getByText("Cabin Class")).toBeVisible();
    await expect(page.getByRole("radio", { name: "First" })).toBeVisible();

    await page.screenshot({
      path: path.join(OUTPUT_DIR, `${viewport.name}-travelers-panel.png`),
      fullPage: false,
    });
  });
}
