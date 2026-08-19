import { test, expect, type Page } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

const OUTPUT_DIR = path.resolve(process.cwd(), "..", "tmp", "search-input-flow-20260819");

function daysFromNowIso(days: number): string {
  const date = new Date();
  date.setDate(date.getDate() + days);
  return date.toISOString().slice(0, 10);
}

async function mockAirports(page: Page) {
  await page.route("**/laravel/airports/search**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify([
        { iata: "ISB", name: "Islamabad International Airport", city: "Islamabad", country: "Pakistan" },
        { iata: "LHE", name: "Allama Iqbal International Airport", city: "Lahore", country: "Pakistan" },
        { iata: "DXB", name: "Dubai International Airport", city: "Dubai", country: "UAE" },
        { iata: "KHI", name: "Jinnah International Airport", city: "Karachi", country: "Pakistan" },
      ]),
    });
  });
  await page.route("**/laravel/airports/popular**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify([
        { iata: "ISB", name: "Islamabad International Airport", city: "Islamabad", country: "Pakistan" },
        { iata: "LHE", name: "Allama Iqbal International Airport", city: "Lahore", country: "Pakistan" },
        { iata: "DXB", name: "Dubai International Airport", city: "Dubai", country: "UAE" },
      ]),
    });
  });
}

async function selectAirportOption(page: Page, fieldName: "From" | "To", query: string, optionName: RegExp) {
  const field = page.getByRole("combobox", { name: fieldName }).first();
  await field.click();
  await field.fill(query);
  const option = page.getByRole("option", { name: optionName }).first();
  await expect(option).toBeVisible();
  await option.click();
  return field;
}

async function assertNoHorizontalOverflow(page: Page) {
  const overflow = await page.evaluate(() =>
    Math.max(0, document.documentElement.scrollWidth - window.innerWidth),
  );
  expect(overflow).toBeLessThanOrEqual(1);
}

test.beforeAll(async ({ request }) => {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

test.describe("Airport dropdown portal layering", () => {
  test.beforeEach(async ({ page }) => {
    await mockAirports(page);
  });

  test("dropdown opens, stays in viewport, and paints above trust tiles", async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto("/", { waitUntil: "load" });

    const from = page.getByRole("combobox", { name: "From" }).first();
    await from.click();
    const list = page.getByTestId("airport-suggestions");
    await expect(list).toBeVisible();
    await expect(list.getByRole("option").first()).toBeVisible();

    const host = await list.evaluate((el) => ({
      parent: el.parentElement?.tagName ?? null,
      zIndex: Number.parseInt(getComputedStyle(el).zIndex || "0", 10),
      position: getComputedStyle(el).position,
    }));
    expect(host.parent).toBe("BODY");
    expect(host.position).toBe("fixed");
    expect(host.zIndex).toBeGreaterThanOrEqual(60);

    const listBox = await list.boundingBox();
    const trust = page.getByTestId("benefit-strip");
    const trustBox = await trust.boundingBox();
    const viewport = page.viewportSize();
    expect(listBox).not.toBeNull();
    expect(trustBox).not.toBeNull();
    expect(viewport).not.toBeNull();
    if (!listBox || !trustBox || !viewport) return;

    expect(listBox.x).toBeGreaterThanOrEqual(-1);
    expect(listBox.x + listBox.width).toBeLessThanOrEqual(viewport.width + 1);
    expect(listBox.y).toBeGreaterThanOrEqual(-1);

    const overlapTop = Math.max(listBox.y, trustBox.y);
    const overlapBottom = Math.min(listBox.y + listBox.height, trustBox.y + trustBox.height);
    expect(overlapBottom - overlapTop).toBeGreaterThan(4);

    const sampleX = Math.round(listBox.x + Math.min(listBox.width / 2, trustBox.width / 2));
    const sampleY = Math.round((overlapTop + overlapBottom) / 2);
    const topTestId = await page.evaluate(
      ({ x, y }) => {
        const el = document.elementFromPoint(x, y) as HTMLElement | null;
        if (!el) return null;
        if (el.closest("[data-testid='airport-suggestions']")) return "airport-suggestions";
        if (el.closest("[data-testid='benefit-strip']")) return "benefit-strip";
        return el.getAttribute("data-testid");
      },
      { x: sampleX, y: sampleY },
    );
    expect(topTestId).toBe("airport-suggestions");

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "desktop-airport-dropdown-over-trust-strip.png"),
      fullPage: false,
    });
  });

  test("portal selection, outside click, escape restore, and keyboard work", async ({ page }) => {
    await page.goto("/", { waitUntil: "load" });
    const from = page.getByRole("combobox", { name: "From" }).first();

    await from.click();
    await from.fill("Lah");
    await expect(page.getByRole("option", { name: /LHE.*Lahore/i })).toBeVisible();
    await page.getByRole("option", { name: /LHE.*Lahore/i }).click();
    await expect(from).toHaveValue("Lahore (LHE)");

    await from.click();
    await expect(page.getByTestId("airport-suggestions")).toBeVisible();
    await page.getByRole("heading", { level: 1 }).click();
    await expect(page.getByTestId("airport-suggestions")).toHaveCount(0);
    await expect(from).toHaveValue("Lahore (LHE)");

    await from.click();
    await expect(from).toHaveValue("");
    await page.keyboard.press("Escape");
    await expect(from).toHaveValue("Lahore (LHE)");
    await expect(page.getByTestId("airport-suggestions")).toHaveCount(0);

    await from.click();
    await from.fill("Kar");
    await expect(page.getByRole("option", { name: /KHI.*Karachi/i })).toBeVisible();
    await page.keyboard.press("ArrowDown");
    await page.keyboard.press("Enter");
    await expect(from).toHaveValue("Karachi (KHI)");
  });

  for (const viewport of [
    { name: "tablet", width: 768, height: 1024, file: "tablet-airport-dropdown.png" },
    { name: "mobile", width: 390, height: 844, file: "mobile-airport-dropdown.png" },
  ] as const) {
    test(`airport dropdown stays usable on ${viewport.name}`, async ({ page }) => {
      await page.setViewportSize({ width: viewport.width, height: viewport.height });
      await page.goto("/", { waitUntil: "load" });
      await page.getByRole("combobox", { name: "From" }).first().click();
      const list = page.getByTestId("airport-suggestions");
      await expect(list).toBeVisible();
      const box = await list.boundingBox();
      expect(box).not.toBeNull();
      if (box) {
        expect(box.x).toBeGreaterThanOrEqual(-1);
        expect(box.x + box.width).toBeLessThanOrEqual(viewport.width + 1);
      }
      await assertNoHorizontalOverflow(page);
      await page.screenshot({ path: path.join(OUTPUT_DIR, viewport.file), fullPage: false });
    });
  }
});

test.describe("Cabin option typography", () => {
  test("only cabin option labels are reduced; passenger and trigger sizes stay", async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto("/", { waitUntil: "load" });

    const trigger = page.getByTestId("travelers-cabin-trigger").first();
    const triggerFont = await trigger.evaluate((el) => getComputedStyle(el).fontSize);
    await trigger.click();
    const panel = page.getByTestId("travelers-cabin-panel");
    await expect(panel).toBeVisible();

    const cabinLabel = panel.getByTestId("cabin-option-label").first();
    const adultsLabel = panel.getByText("Adults", { exact: true });
    const cabinFont = await cabinLabel.evaluate((el) => getComputedStyle(el).fontSize);
    const adultsFont = await adultsLabel.evaluate((el) => getComputedStyle(el).fontSize);
    const cabinPx = Number.parseFloat(cabinFont);
    const adultsPx = Number.parseFloat(adultsFont);
    const triggerPx = Number.parseFloat(triggerFont);

    expect(cabinPx).toBeLessThan(adultsPx);
    expect(adultsPx).toBeGreaterThanOrEqual(triggerPx - 0.5);
    expect(await panel.getByRole("button", { name: "Increase adults" }).boundingBox()).toMatchObject({
      height: expect.any(Number),
    });
    const plusBox = await panel.getByRole("button", { name: "Increase adults" }).boundingBox();
    expect(plusBox?.height ?? 0).toBeGreaterThanOrEqual(36);

    await page.screenshot({
      path: path.join(OUTPUT_DIR, "desktop-cabin-panel-small-labels.png"),
      fullPage: false,
    });
  });
});

test.describe("Auto-advance focus flow", () => {
  test.beforeEach(async ({ page }) => {
    await mockAirports(page);
  });

  test("one way: From → To → Departure, then Travelers stays closed", async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "One Way" }).click();

    await selectAirportOption(page, "From", "Lah", /LHE.*Lahore/i);
    const to = page.getByRole("combobox", { name: "To" }).first();
    await expect(to).toBeFocused();
    await expect(to).toHaveValue("");
    await expect(page.getByTestId("airport-suggestions")).toBeVisible();
    await page.screenshot({
      path: path.join(OUTPUT_DIR, "desktop-from-selected-to-focused.png"),
      fullPage: false,
    });

    await to.fill("Kar");
    await page.getByRole("option", { name: /KHI.*Karachi/i }).click();
    const departure = page.getByLabel("Departure", { exact: true });
    await expect(departure).toBeFocused();
    await page.screenshot({
      path: path.join(OUTPUT_DIR, "desktop-to-selected-date-focused.png"),
      fullPage: false,
    });

    await departure.fill(daysFromNowIso(10));
    await expect(page.getByTestId("travelers-cabin-panel")).toHaveCount(0);
    await expect(page.getByTestId("travelers-cabin-trigger").first()).not.toBeFocused();
  });

  test("return: From → To → DateRange, range completion does not open Travelers", async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "Return" }).click();

    await selectAirportOption(page, "From", "Lah", /LHE.*Lahore/i);
    const to = page.getByRole("combobox", { name: "To" }).first();
    await expect(to).toBeFocused();

    await to.fill("DXB");
    await page.getByRole("option", { name: /DXB.*Dubai/i }).click();
    const rangePanel = page.getByTestId("date-range-panel");
    await expect(rangePanel).toBeVisible();
    await expect
      .poll(async () => rangePanel.evaluate((element) => element.contains(document.activeElement)))
      .toBe(true);
    await page.screenshot({
      path: path.join(OUTPUT_DIR, "return-to-date-range-focused.png"),
      fullPage: false,
    });

    const outbound = daysFromNowIso(5);
    const inbound = daysFromNowIso(8);
    await page.locator(`[data-testid="date-range-panel"] [data-date="${outbound}"]`).click();
    const inboundCell = page.locator(`[data-testid="date-range-panel"] [data-date="${inbound}"]`);
    if ((await inboundCell.count()) === 0) {
      await page.getByRole("button", { name: "Next month" }).click();
    }
    await page.locator(`[data-testid="date-range-panel"] [data-date="${inbound}"]`).click();
    await expect(page.getByTestId("date-range-panel")).toHaveCount(0);
    await expect(page.getByTestId("travelers-cabin-panel")).toHaveCount(0);
    await expect(page.getByTestId("date-range-trigger")).toHaveCount(1);
  });

  test("multi-city sequential focus advances across existing segments only", async ({ page }) => {
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "Multi-City" }).click();

    const segmentCountBefore = await page.getByText(/Flight \d+/).count();
    expect(segmentCountBefore).toBeGreaterThanOrEqual(2);

    const from1 = page.getByRole("combobox", { name: "From" }).nth(0);
    const to1 = page.getByRole("combobox", { name: "To" }).nth(0);
    const date1 = page.getByLabel("Departure").nth(0);
    const from2 = page.getByRole("combobox", { name: "From" }).nth(1);

    await from1.click();
    await from1.fill("ISB");
    await page.getByRole("option", { name: /ISB.*Islamabad/i }).first().click();
    await expect(to1).toBeFocused();

    await to1.fill("DXB");
    await page.getByRole("option", { name: /DXB.*Dubai/i }).first().click();
    await expect(date1).toBeFocused();

    await date1.fill(daysFromNowIso(14));
    await expect(from2).toBeFocused();
    await expect(from2).toHaveValue("");

    await from2.click();
    await from2.fill("DXB");
    await page.getByRole("option", { name: /DXB.*Dubai/i }).first().click();
    const to2 = page.getByRole("combobox", { name: "To" }).nth(1);
    await expect(to2).toBeFocused();
    await to2.fill("LHE");
    await page.getByRole("option", { name: /LHE.*Lahore/i }).first().click();
    const date2 = page.getByLabel("Departure").nth(1);
    await expect(date2).toBeFocused();
    await date2.fill(daysFromNowIso(21));

    await expect(page.getByTestId("travelers-cabin-panel")).toHaveCount(0);
    const segmentCountAfter = await page.getByText(/Flight \d+/).count();
    expect(segmentCountAfter).toBe(segmentCountBefore);
  });
});

test.describe("Search input-flow regressions", () => {
  test("return range, click-to-replace, First cabin, and no overflow", async ({ page }) => {
    await mockAirports(page);
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto("/", { waitUntil: "load" });

    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "Return" }).click();
    await expect(page.getByTestId("date-range-trigger")).toHaveCount(1);

    const from = page.getByRole("combobox", { name: "From" }).first();
    await from.click();
    await expect(from).toHaveValue("");
    await page.keyboard.press("Escape");
    await expect(from).toHaveValue("Islamabad (ISB)");

    await page.getByTestId("travelers-cabin-trigger").first().click();
    await expect(page.getByRole("radio", { name: "First" })).toBeVisible();
    await page.keyboard.press("Escape");

    await assertNoHorizontalOverflow(page);
    for (const size of [
      { width: 1366, height: 768 },
      { width: 768, height: 1024 },
      { width: 390, height: 844 },
    ]) {
      await page.setViewportSize(size);
      await assertNoHorizontalOverflow(page);
    }
  });
});
