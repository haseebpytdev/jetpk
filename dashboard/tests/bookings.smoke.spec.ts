import { test, expect } from "@playwright/test";

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
  await page.locator("#bookings-search").fill("JP-BK-10001");
  await page.getByRole("button", { name: "Apply filters" }).click();
  await expect(table.getByText("JP-BK-10001")).toBeVisible();
  await expect(table.getByText("JP-BK-10002")).not.toBeVisible();
});

test("status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings", { waitUntil: "load" });
  const table = page.getByTestId("bookings-table");
  await page.locator("#filter-status").selectOption("cancelled");
  await page.getByRole("button", { name: "Apply filters" }).click();
  await expect(table.getByText("JP-BK-10005")).toBeVisible();
  await expect(table.getByText("JP-BK-10001")).not.toBeVisible();
});

test("clear filters restores results", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings?q=JP-BK-10001&pageSize=50", { waitUntil: "load" });
  const table = page.getByTestId("bookings-table");
  await expect(table.getByText("JP-BK-10002")).not.toBeVisible();
  await page.getByRole("button", { name: "Clear all" }).click();
  await expect(table.getByText("JP-BK-10002")).toBeVisible({ timeout: 15_000 });
});

test("sorting changes displayed ordering", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings?sort=amount&direction=asc&pageSize=50", { waitUntil: "load" });
  const firstRow = page.getByTestId("bookings-table").locator("tbody tr").first();
  await expect(firstRow).toContainText("JP-BK-10005");
});

test("pagination works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings?pageSize=10", { waitUntil: "load" });
  const table = page.getByTestId("bookings-table");
  await expect(page.getByText("1 /")).toBeVisible();
  await page.getByRole("button", { name: "Next page" }).click();
  await expect(page).toHaveURL(/page=2/);
  await expect(table.getByText("JP-BK-10011")).toBeVisible();
});

test("page size change works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings", { waitUntil: "load" });
  await page.getByLabel("Rows per page").selectOption("10");
  await expect(page).toHaveURL(/pageSize=10/);
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
  await page.getByTestId("bookings-table").getByRole("button", { name: "View" }).first().click();
  await expect(page.getByRole("dialog")).toBeVisible();
  await expect(page).toHaveURL(/id=JP-BK-/);
});

test("booking drawer displays selected booking", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings?id=JP-BK-10001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  await expect(page.getByTestId("booking-drawer-content")).toContainText("JP-BK-10001");
  await expect(page.getByTestId("booking-drawer-content")).toContainText("ABC123");
});

test("booking drawer closes through close control", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings?id=JP-BK-10001", { waitUntil: "load" });
  await page.getByRole("button", { name: "Close booking details" }).click();
  await expect(page.getByRole("dialog")).not.toBeVisible();
  await expect(page).not.toHaveURL(/id=JP-BK-10001/);
});

test("booking drawer closes using Escape", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings?id=JP-BK-10001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  await page.keyboard.press("Escape");
  await expect(page.getByRole("dialog")).not.toBeVisible();
  await expect(page).not.toHaveURL(/id=JP-BK-10001/);
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
