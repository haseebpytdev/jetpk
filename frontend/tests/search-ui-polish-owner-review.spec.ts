import { test, expect, type Page } from "@playwright/test";
import path from "node:path";
import fs from "node:fs";

const OUTPUT_DIR = path.resolve(process.cwd(), "..", "tmp", "search-ui-polish-visual");

const GROUP_FACETS_FIXTURE = {
  sectors: [
    { value: "SKT-SHJ", label: "Sialkot — Sharjah" },
    { value: "JED", label: "KSA — Jeddah" },
  ],
  categories: [
    { value: "ksa", label: "KSA" },
    { value: "uae", label: "UAE" },
    { value: "muscat", label: "Muscat" },
  ],
  date_bounds: { minimum: "2026-01-01", maximum: "2027-12-31" },
};

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

async function assertDesktopMainRow(page: Page) {
  const fromField = page.getByRole("combobox", { name: "From" });
  const searchButton = page.getByRole("button", { name: "Search Flights" });
  const fromBox = await fromField.boundingBox();
  const searchBox = await searchButton.boundingBox();
  expect(fromBox).not.toBeNull();
  expect(searchBox).not.toBeNull();
  if (!fromBox || !searchBox) return;
  expect(Math.abs(fromBox.y - searchBox.y)).toBeLessThan(36);
}

async function mockGroupFacets(page: Page) {
  await page.route("**/laravel/groups/search/facets**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(GROUP_FACETS_FIXTURE),
    });
  });
}

test.beforeAll(() => {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });
});

test.describe("Owner review final search UI visuals", () => {
  test("desktop one-way main row", async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "One Way" }).click();

    await assertDesktopMainRow(page);
    await assertNoHorizontalOverflow(page);

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "desktop-one-way.png"),
      fullPage: false,
    });
  });

  test("desktop return mode with compact date placeholder", async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "Return" }).click();

    await expect(page.getByTestId("date-range-trigger")).toContainText("Select dates");
    await expect(page.getByLabel("Departure", { exact: true })).toHaveCount(0);
    await assertDesktopMainRow(page);
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

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "desktop-return-calendar-open.png"),
      fullPage: false,
    });
  });

  test("desktop group ticketing success state", async ({ page }) => {
    await mockGroupFacets(page);
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("product-tab-group").click();

    await expect(page.getByTestId("trip-type-trigger")).toHaveCount(0);
    await expect(page.getByRole("button", { name: "Search Group Fares" })).toBeVisible();
    await expect(page.getByLabel("Sector")).toBeEnabled();
    await expect(page.getByRole("radio", { name: "KSA" })).toBeVisible();
    await expect(page.getByText("Request failed")).toHaveCount(0);
    await assertNoHorizontalOverflow(page);

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "desktop-group-ticketing-success.png"),
      fullPage: false,
    });
  });

  test("tablet one-way layout", async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "One Way" }).click();

    await assertNoHorizontalOverflow(page);

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "tablet-one-way.png"),
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

  test("mobile header logo", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/", { waitUntil: "load" });

    const headerLogo = page.getByRole("banner").getByTestId("jetpakistan-header-logo");
    await expect(headerLogo).toBeVisible();
    await expect(headerLogo).toHaveAttribute("alt", /JetPakistan/i);
    const logoBox = await headerLogo.boundingBox();
    expect(logoBox).not.toBeNull();
    if (logoBox) {
      expect(logoBox.height).toBeGreaterThanOrEqual(32);
    }

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "mobile-header-logo.png"),
      fullPage: false,
    });
  });
});
