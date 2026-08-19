import { test, expect, type Page } from "@playwright/test";

async function assertNoHorizontalOverflow(page: Page) {
  const overflow = await page.evaluate(() =>
    Math.max(0, document.documentElement.scrollWidth - window.innerWidth),
  );
  expect(overflow).toBeLessThanOrEqual(1);
}

async function assertElementInViewport(page: Page, selector: string) {
  const box = await page.locator(selector).boundingBox();
  const viewport = page.viewportSize();
  expect(box).not.toBeNull();
  expect(viewport).not.toBeNull();
  if (!box || !viewport) return;

  expect(box.x).toBeGreaterThanOrEqual(-1);
  expect(box.y).toBeGreaterThanOrEqual(-1);
  expect(box.x + box.width).toBeLessThanOrEqual(viewport.width + 1);
  expect(box.y + box.height).toBeLessThanOrEqual(viewport.height + 1);
}

function boxesOverlap(
  a: { x: number; y: number; width: number; height: number },
  b: { x: number; y: number; width: number; height: number },
): boolean {
  return a.x < b.x + b.width && a.x + a.width > b.x && a.y < b.y + b.height && a.y + a.height > b.y;
}

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

test.describe("Search UI portal keyboard and focus", () => {
  test("travelers panel opens via keyboard and returns focus on escape", async ({ page }) => {
    await page.goto("/", { waitUntil: "load" });
    const trigger = page.getByTestId("travelers-cabin-trigger").first();
    await trigger.focus();
    await page.keyboard.press("ArrowDown");
    const panel = page.getByTestId("travelers-cabin-panel");
    await expect(panel).toBeVisible();
    await expect
      .poll(async () => panel.evaluate((element) => element.contains(document.activeElement)))
      .toBe(true);
    await page.keyboard.press("Escape");
    await expect(panel).toHaveCount(0);
    await expect(trigger).toBeFocused();
  });

  test("date range panel opens via keyboard and closes on click-away", async ({ page }) => {
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "Return" }).click();

    const trigger = page.getByTestId("date-range-trigger");
    await trigger.focus();
    await page.keyboard.press("Enter");
    const panel = page.getByTestId("date-range-panel");
    await expect(panel).toBeVisible();
    await expect(panel.getByRole("button", { name: "Previous month" })).toBeFocused();

    await page.getByRole("heading", { level: 1 }).click();
    await expect(panel).toHaveCount(0);
  });

  test("trip type dropdown supports keyboard open, menu navigation, and escape", async ({ page }) => {
    await page.goto("/", { waitUntil: "load" });
    const trigger = page.getByTestId("trip-type-trigger");
    await trigger.focus();
    await page.keyboard.press("ArrowDown");
    const panel = page.getByTestId("trip-type-panel");
    await expect(panel).toBeVisible();
    await expect(panel.getByRole("menuitem", { name: "One Way" })).toBeFocused();

    await page.keyboard.press("ArrowDown");
    await expect(panel.getByRole("menuitem", { name: "Return" })).toBeFocused();
    await page.keyboard.press("Enter");
    await expect(panel).toHaveCount(0);
    await expect(trigger).toBeFocused();
    await expect(page.getByTestId("date-range-trigger")).toBeVisible();
  });

  test("product tabs support left and right arrow navigation", async ({ page }) => {
    await page.goto("/", { waitUntil: "load" });
    const flightsTab = page.getByTestId("product-tab-flights");
    await flightsTab.focus();
    await page.keyboard.press("ArrowRight");
    const groupTab = page.getByTestId("product-tab-group");
    await expect(groupTab).toBeFocused();
    await expect(groupTab).toHaveAttribute("aria-selected", "true");
    await expect(page.getByRole("button", { name: "Search Group Fares" })).toBeVisible();

    await page.keyboard.press("ArrowLeft");
    await expect(flightsTab).toBeFocused();
    await expect(flightsTab).toHaveAttribute("aria-selected", "true");
  });
});

test.describe("Search UI geometry assertions", () => {
  const viewports = [
    { name: "desktop", width: 1920, height: 1080 },
    { name: "laptop", width: 1366, height: 768 },
    { name: "tablet", width: 768, height: 1024 },
    { name: "mobile", width: 390, height: 844 },
  ] as const;

  for (const viewport of viewports) {
    test(`layout stays within viewport at ${viewport.name}`, async ({ page }) => {
      await page.setViewportSize({ width: viewport.width, height: viewport.height });
      await page.goto("/", { waitUntil: "load" });

      await assertNoHorizontalOverflow(page);

      const searchModule = page.getByTestId("search-module");
      const searchBox = await searchModule.boundingBox();
      expect(searchBox).not.toBeNull();
      if (searchBox) {
        expect(searchBox.x).toBeGreaterThanOrEqual(-1);
        expect(searchBox.x + searchBox.width).toBeLessThanOrEqual(viewport.width + 1);
      }

      await expect(page.getByTestId("homepage-hero-image")).toBeVisible();
      await expect(page.getByTestId("homepage-hero-image").locator("img")).toHaveAttribute("src", /hero-pakistan/);
      await expect(page.getByTestId("product-tab-group")).toContainText("Group Ticketing");

      await page.getByTestId("trip-type-trigger").click();
      await page.getByRole("menuitem", { name: "Return" }).click();
      await expect(page.getByTestId("date-range-trigger")).toHaveCount(1);

      if (viewport.name === "desktop" || viewport.name === "laptop") {
        const fromField = page.getByRole("combobox", { name: "From" });
        const searchButton = page.getByRole("button", { name: "Search Flights" });
        const fromBox = await fromField.boundingBox();
        const searchBox = await searchButton.boundingBox();
        expect(fromBox).not.toBeNull();
        expect(searchBox).not.toBeNull();
        if (fromBox && searchBox) {
          expect(Math.abs(fromBox.y - searchBox.y)).toBeLessThan(36);
        }
      }

      await page.getByTestId("travelers-cabin-trigger").first().click();
      await assertElementInViewport(page, '[data-testid="travelers-cabin-panel"]');
      await expect(page.getByRole("radio", { name: "First" })).toBeVisible();

      const searchButton = page.getByRole("button", { name: "Search Flights" });
      const travelersTrigger = page.getByTestId("travelers-cabin-trigger").first();
      const searchButtonBox = await searchButton.boundingBox();
      const travelersBox = await travelersTrigger.boundingBox();
      expect(searchButtonBox).not.toBeNull();
      expect(travelersBox).not.toBeNull();
      if (searchButtonBox && travelersBox && boxesOverlap(searchButtonBox, travelersBox)) {
        expect(false, "Search Flights button overlaps travelers field").toBe(true);
      }
    });
  }
});
