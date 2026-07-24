import { test, expect } from "@playwright/test";
import {
  applySearchAndWaitForRow,
  closeDrawerWithButton,
  closeDrawerWithEscape,
  expectTableReady,
  fillSearchInput,
  selectAndApplyFilter,
} from "./helpers";

const viewports = [
  { width: 360, height: 740 },
  { width: 390, height: 844 },
  { width: 1280, height: 720 },
];

test.beforeAll(async ({ request }) => {
  const response = await request.get("/testdash", { timeout: 120_000 });
  expect(response.ok()).toBeTruthy();
});

test("overview page from DASH-01 still works", async ({ page }) => {
  await page.goto("/testdash", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Dashboard", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expect(page.getByText(/Preview mode/i)).toBeVisible();
});

test("planned module route from DASH-01 still works", async ({ page }) => {
  await page.goto("/testdash/planned/bookings", { waitUntil: "load" });
  await expect(page.getByText(/Planned module/i)).toBeVisible({ timeout: 30_000 });
  await expect(page.getByText("admin.bookings")).toBeVisible();
});

for (const viewport of viewports.filter((v) => v.width >= 1280)) {
  test(`bookings route renders at desktop ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto("/testdash/bookings", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Bookings", level: 1 })).toBeVisible({
      timeout: 60_000,
    });
    await expect(page.getByTestId("bookings-table")).toBeVisible();
    await expect(page.getByText(/Preview data/i).first()).toBeVisible();
  });
}

for (const viewport of viewports.filter((v) => v.width < 768)) {
  test(`bookings route renders at mobile ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto("/testdash/bookings", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Bookings", level: 1 })).toBeVisible({
      timeout: 60_000,
    });
    await expect(page.getByTestId("bookings-mobile-cards")).toBeVisible();
  });
}

test("search filters visible bookings", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings", { waitUntil: "load" });
  const table = page.getByTestId("bookings-table");
  await expectTableReady(table);
  const search = page.locator("#bookings-search");
  await fillSearchInput(search, "JP-BK-10001");
  await applySearchAndWaitForRow(page, search, table, /q=JP-BK-10001/, "JP-BK-10001");
  await expect(table.getByText("JP-BK-10002")).not.toBeVisible();
});

test("status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings", { waitUntil: "load" });
  const table = page.getByTestId("bookings-table");
  await expectTableReady(table);
  const status = page.locator("#filter-status");
  await selectAndApplyFilter(page, table, status, "cancelled", /status=cancelled/, "JP-BK-10005");
  await expect(table.getByText("JP-BK-10001")).not.toBeVisible();
});

test("clear filters restores results", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings?q=JP-BK-10001&pageSize=50", { waitUntil: "load" });
  const table = page.getByTestId("bookings-table");
  await expect(table.getByText("JP-BK-10002")).not.toBeVisible();
  await page.getByRole("button", { name: "Clear all" }).click();
  await page.waitForURL((url) => !url.search.includes("q=JP-BK-10001"), { timeout: 15_000 });
  await expectTableReady(table);
  await expect(table.getByText("JP-BK-10002")).toBeVisible();
});

test("sorting changes displayed ordering", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings?sort=amount&direction=asc&pageSize=50", { waitUntil: "load" });
  const table = page.getByTestId("bookings-table");
  await expectTableReady(table);
  const firstRow = table.locator("tbody tr").first();
  await expect(firstRow).toContainText("JP-BK-10005");
});

test("pagination works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings?pageSize=10", { waitUntil: "load" });
  const table = page.getByTestId("bookings-table");
  await expectTableReady(table);
  await expect(page.getByText("1 /")).toBeVisible();
  await page.getByRole("button", { name: "Next page" }).click();
  await page.waitForURL(/page=2/, { timeout: 15_000 });
  await expectTableReady(table);
  await expect(table.getByText("JP-BK-10011")).toBeVisible();
});

test("page size change works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings", { waitUntil: "load" });
  const table = page.getByTestId("bookings-table");
  await expectTableReady(table);
  await page.getByLabel("Rows per page").selectOption("10");
  await page.waitForURL(/pageSize=10/, { timeout: 15_000 });
  await expect(table.locator("tbody tr")).toHaveCount(10);
});

test("URL query state is preserved on reload", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings?q=Emirates&status=confirmed", { waitUntil: "load" });
  await page.reload({ waitUntil: "load" });
  await expect(page).toHaveURL(/q=Emirates/);
  await expect(page).toHaveURL(/status=confirmed/);
  await expect(page.locator("#bookings-search")).toHaveValue("Emirates");
});

test("booking drawer opens", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings", { waitUntil: "load" });
  const table = page.getByTestId("bookings-table");
  await expectTableReady(table);
  await table.getByRole("button", { name: "View" }).first().click();
  await page.waitForURL(/id=JP-BK-/, { timeout: 15_000 });
  await expect(page.getByRole("dialog")).toBeVisible();
});

test("booking drawer displays selected booking", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings?id=JP-BK-10001", { waitUntil: "load" });
  const dialog = page.getByRole("dialog");
  await expect(dialog).toBeVisible();
  const content = page.getByTestId("booking-drawer-content");
  await expect(content).toContainText("JP-BK-10001");
  await expect(content).toContainText("ABC123");
});

test("booking drawer closes through close control", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings?id=JP-BK-10001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  await closeDrawerWithButton(page, "Close booking details", /id=JP-BK-10001/);
});

test("booking drawer closes using Escape", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings?id=JP-BK-10001", { waitUntil: "load" });
  await closeDrawerWithEscape(page, /id=JP-BK-10001/);
});

test("mobile booking cards render without horizontal viewport overflow", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/testdash/bookings", { waitUntil: "load" });
  const overflow = await page.evaluate(() => {
    return document.documentElement.scrollWidth > document.documentElement.clientWidth;
  });
  expect(overflow).toBe(false);
});

test("empty filter state renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings?q=zzznomatchzzz", { waitUntil: "load" });
  await expect(page.getByText("No bookings match your filters")).toBeVisible();
});
