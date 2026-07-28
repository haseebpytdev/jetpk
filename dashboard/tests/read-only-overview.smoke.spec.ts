import { test, expect } from "@playwright/test";
import { getOverviewData } from "@/services/overview-service";

const widths = [1280, 1024, 390, 360];

test.beforeAll(async ({ request }) => {
  expect((await request.get("/testdash", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("fixture overview data loads", async () => {
  const data = await getOverviewData();
  expect(data.summaryStats.length).toBeGreaterThan(0);
  expect(data.operationalActionCards.length).toBeGreaterThan(0);
});

for (const width of widths) {
  test(`overview fixture notice at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: width <= 400 ? 844 : 720 });
    await page.goto("/testdash?dataSourcePreview=fixture", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Dashboard", level: 1 })).toBeVisible({ timeout: 60_000 });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
    await expect(page.getByText("Pending Deposits")).toBeVisible();
  });
}

test("overview live notice via preview gate", async ({ page }) => {
  await page.goto("/testdash?dataSourcePreview=live", { waitUntil: "load" });
  await expect(page.getByTestId("live-readonly-notice")).toBeVisible();
});

test("overview error state via preview gate", async ({ page }) => {
  await page.goto("/testdash?dataSourcePreview=error", { waitUntil: "load" });
  await expect(page.getByText(/Unable to load data/i)).toBeVisible();
});

test("overview typography uses font-display", async ({ page }) => {
  await page.goto("/testdash", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Dashboard", level: 1 })).toHaveClass(/font-display/);
});

test("overview has single h1", async ({ page }) => {
  await page.goto("/testdash", { waitUntil: "load" });
  await expect(page.getByRole("heading", { level: 1 })).toHaveCount(1);
});

test("overview no page overflow at 390px", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/testdash", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  expect(overflow).toBeFalsy();
});
