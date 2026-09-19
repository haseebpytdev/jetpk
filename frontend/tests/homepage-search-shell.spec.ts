import { test, expect } from "@playwright/test";

const VIEWPORTS = [
  { width: 320, height: 720 },
  { width: 390, height: 844 },
  { width: 768, height: 1024 },
  { width: 1024, height: 768 },
  { width: 1440, height: 900 },
] as const;

const MODES = [
  { service: "flights" as const, trip: "one_way" as const, label: "one_way" },
  { service: "flights" as const, trip: "return" as const, label: "return" },
  { service: "flights" as const, trip: "multi_city" as const, label: "multi_city" },
  { service: "group" as const, trip: null, label: "group" },
];

async function selectMode(
  page: import("@playwright/test").Page,
  mode: (typeof MODES)[number],
) {
  if (mode.service === "group") {
    await page.getByTestId("search-service-group").click();
    await expect(page.getByTestId("search-module")).toHaveAttribute("data-search-mode", "group");
    return;
  }
  await page.getByTestId("search-service-flights").click();
  await page.getByTestId(`search-trip-tab-${mode.trip}`).click();
  await expect(page.getByTestId("search-module")).toHaveAttribute("data-search-mode", mode.trip!);
}

test.describe("homepage search shell + hero backdrop geometry", () => {
  for (const viewport of VIEWPORTS) {
    test(`backdrop stable across modes @${viewport.width}`, async ({ page }) => {
      await page.setViewportSize(viewport);
      await page.goto("/", { waitUntil: "load" });

      await expect(page.getByTestId("homepage-hero-backdrop")).toBeVisible();
      await expect(page.getByTestId("homepage-search-shell")).toBeVisible();
      await expect(page.getByTestId("homepage-service-switcher")).toBeVisible();
      await expect(page.getByTestId("search-trip-tabs")).toBeVisible();
      await expect(page.getByTestId("search-trip-tabs").getByRole("tab", { name: "Group Ticketing" })).toHaveCount(0);

      const baseline = await page.getByTestId("homepage-hero-backdrop").boundingBox();
      expect(baseline).toBeTruthy();

      const searchTops: number[] = [];

      for (const mode of MODES) {
        await selectMode(page, mode);
        const box = await page.getByTestId("homepage-hero-backdrop").boundingBox();
        expect(box).toBeTruthy();
        expect(Math.abs((box!.top ?? 0) - (baseline!.top ?? 0))).toBeLessThanOrEqual(1);
        expect(Math.abs((box!.width ?? 0) - (baseline!.width ?? 0))).toBeLessThanOrEqual(1);
        expect(Math.abs((box!.height ?? 0) - (baseline!.height ?? 0))).toBeLessThanOrEqual(1);

        const shell = await page.getByTestId("homepage-search-shell").boundingBox();
        expect(shell).toBeTruthy();
        searchTops.push(shell!.y);

        const overflowX = await page.evaluate(() => {
          return document.documentElement.scrollWidth > document.documentElement.clientWidth + 1;
        });
        expect(overflowX).toBeFalsy();
      }

      // Search upper anchor stays stable (only lower edge may grow).
      const topDelta = Math.max(...searchTops) - Math.min(...searchTops);
      expect(topDelta).toBeLessThanOrEqual(8);

      // Multi-city / group must not clip — shell bottom below backdrop.
      await selectMode(page, MODES[2]!);
      const multiShell = await page.getByTestId("homepage-search-shell").boundingBox();
      const backdrop = await page.getByTestId("homepage-hero-backdrop").boundingBox();
      expect(multiShell!.y + multiShell!.height).toBeGreaterThan(backdrop!.y + backdrop!.height - 4);

      await selectMode(page, MODES[3]!);
      await expect(page.getByRole("button", { name: "Search Group Fares" })).toBeVisible();
    });
  }

  test("service switch preserves flight trip type and field values", async ({ page }) => {
    await page.setViewportSize({ width: 1024, height: 768 });
    await page.goto("/", { waitUntil: "load" });

    await page.getByTestId("search-trip-tab-return").click();
    const departure = page.getByRole("textbox", { name: "Departure" });
    await departure.fill("2026-10-15");
    await expect(page.getByRole("textbox", { name: "Return" })).toBeVisible();

    await page.getByTestId("search-service-group").click();
    await expect(page.getByTestId("search-module")).toHaveAttribute("data-search-mode", "group");
    await expect(page.getByTestId("search-trip-tabs")).toHaveCount(0);

    await page.getByTestId("search-service-flights").click();
    await expect(page.getByTestId("search-module")).toHaveAttribute("data-search-mode", "return");
    await expect(page.getByRole("textbox", { name: "Return" })).toBeVisible();
    await expect(departure).toHaveValue("2026-10-15");
  });
});
