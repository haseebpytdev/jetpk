import { test, expect, type Page } from "@playwright/test";
import path from "node:path";
import fs from "node:fs";

const OUTPUT_DIR = path.resolve(process.cwd(), "..", "tmp", "search-ui-polish-final-owner-review");

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

test.describe("Final owner review — light grey glass search panel", () => {
  test("1. desktop one-way", async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "One Way" }).click();

    await assertDesktopMainRow(page);
    await assertNoHorizontalOverflow(page);

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "01-desktop-one-way.png"),
      fullPage: false,
    });
  });

  test("2. desktop travellers/cabin open", async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("travelers-cabin-trigger").first().click();
    await page.waitForTimeout(200);

    await assertPanelInViewport(page, "travelers-cabin-panel");
    await assertNoHorizontalOverflow(page);
    await expect(page.getByText("Cabin Class")).toBeVisible();
    await expect(page.getByRole("radio", { name: "First" })).toBeVisible();

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "02-desktop-travellers-cabin-open.png"),
      fullPage: false,
    });
  });

  test("3. desktop Return", async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "Return" }).click();

    await expect(page.getByTestId("date-range-trigger")).toContainText("Select dates");
    await assertDesktopMainRow(page);
    await assertNoHorizontalOverflow(page);

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "03-desktop-return.png"),
      fullPage: false,
    });
  });

  test("4. desktop Return calendar open", async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "Return" }).click();
    await page.getByTestId("date-range-trigger").click();
    await page.waitForTimeout(200);

    await assertPanelInViewport(page, "date-range-panel");
    await assertNoHorizontalOverflow(page);

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "04-desktop-return-calendar-open.png"),
      fullPage: false,
    });
  });

  test("5. desktop Groups success state", async ({ page }) => {
    await mockGroupFacets(page);
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("product-tab-group").click();

    await expect(page.getByTestId("trip-type-trigger")).toHaveCount(0);
    await expect(page.getByRole("button", { name: "Search Groups" })).toBeVisible();
    await expect(page.getByLabel("Sector")).toBeEnabled();
    await expect(page.getByRole("radio", { name: "KSA" })).toBeVisible();
    await expect(page.getByText("Request failed")).toHaveCount(0);
    await assertNoHorizontalOverflow(page);

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "05-desktop-group-ticketing-success.png"),
      fullPage: false,
    });
  });

  test("6. laptop one-way", async ({ page }) => {
    await page.setViewportSize({ width: 1366, height: 768 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "One Way" }).click();

    await assertDesktopMainRow(page);
    await assertNoHorizontalOverflow(page);

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "06-laptop-one-way.png"),
      fullPage: false,
    });
  });

  test("7. tablet one-way", async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "One Way" }).click();

    await assertNoHorizontalOverflow(page);

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "07-tablet-one-way.png"),
      fullPage: false,
    });
  });

  test("8. mobile Return", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "Return" }).click();

    await expect(page.getByTestId("date-range-trigger")).toBeVisible();
    await assertNoHorizontalOverflow(page);

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "08-mobile-return.png"),
      fullPage: false,
    });
  });

  test("9. mobile Return calendar open", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "Return" }).click();
    await page.getByTestId("date-range-trigger").click();
    await page.waitForTimeout(200);

    await assertPanelInViewport(page, "date-range-panel");
    await assertNoHorizontalOverflow(page);

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "09-mobile-return-calendar-open.png"),
      fullPage: false,
    });
  });

  test("10. mobile header/logo", async ({ page }) => {
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
      path: path.join(OUTPUT_DIR, "10-mobile-header-logo.png"),
      fullPage: false,
    });
  });
});
