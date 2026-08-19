import { test, expect, type Page } from "@playwright/test";
import path from "node:path";
import fs from "node:fs";

const OUTPUT_DIR = path.resolve(process.cwd(), "..", "tmp", "search-ui-polish-visual-owner-review");

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

test.describe("Owner review return mode visuals", () => {
  test("desktop return mode with combined date-range field", async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "Return" }).click();

    await expect(page.getByTestId("date-range-trigger")).toBeVisible();
    await expect(page.getByLabel("Departure", { exact: true })).toHaveCount(0);
    await expect(page.getByLabel("Return", { exact: true })).toHaveCount(0);
    await assertNoHorizontalOverflow(page);

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "desktop-return-mode.png"),
      fullPage: false,
    });
  });

  test("desktop return calendar open", async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "Return" }).click();
    await page.getByTestId("date-range-trigger").click();
    await page.waitForTimeout(200);

    await assertPanelInViewport(page, "date-range-panel");
    await assertNoHorizontalOverflow(page);
    await expect(page.getByTestId("travelers-cabin-trigger").first()).toBeVisible();

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "desktop-return-calendar-open.png"),
      fullPage: false,
    });
  });

  test("mobile return mode", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "Return" }).click();

    await expect(page.getByTestId("date-range-trigger")).toBeVisible();
    await assertNoHorizontalOverflow(page);

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "mobile-return-mode.png"),
      fullPage: false,
    });
  });

  test("mobile return calendar open", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "Return" }).click();
    await page.getByTestId("date-range-trigger").click();
    await page.waitForTimeout(200);

    await assertPanelInViewport(page, "date-range-panel");
    await assertNoHorizontalOverflow(page);

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "mobile-return-calendar-open.png"),
      fullPage: false,
    });
  });

  test("desktop group ticketing tab state", async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("product-tab-group").click();

    await expect(page.getByTestId("search-module")).toBeVisible();
    await expect(page.getByTestId("trip-type-trigger")).toHaveCount(0);
    await assertNoHorizontalOverflow(page);

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "desktop-group-ticketing-tab.png"),
      fullPage: false,
    });
  });
});
