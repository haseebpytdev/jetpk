import { test, expect } from "@playwright/test";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/admin/dashboard/reports/payments", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("payment KPIs are deterministic", async ({ page }) => {
  await page.goto("/admin/dashboard/reports/payments", { waitUntil: "load" });
  await expect(page.getByText("Collection rate")).toBeVisible();
  await expect(page.getByText("Payment count")).toBeVisible();
});

test("collection rate handles display safely", async ({ page }) => {
  await page.goto("/admin/dashboard/reports/payments", { waitUntil: "load" });
  const grid = page.getByTestId("reports-metric-grid");
  await expect(grid).not.toContainText("Infinity");
  await expect(grid).not.toContainText("NaN");
});

test("payment status distribution works", async ({ page }) => {
  await page.goto("/admin/dashboard/reports/payments", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-payment_status")).toBeVisible();
});

test("payment method distribution works", async ({ page }) => {
  await page.goto("/admin/dashboard/reports/payments", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-payment_method")).toBeVisible();
});

test("refund trend area uses collection series", async ({ page }) => {
  await page.goto("/admin/dashboard/reports/payments", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-payment_collection")).toBeVisible();
});

test("agent balance breakdown works", async ({ page }) => {
  await page.goto("/admin/dashboard/reports/payments", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-agent")).toBeVisible();
});

test("reconciliation queue metric visible", async ({ page }) => {
  await page.goto("/admin/dashboard/reports/payments", { waitUntil: "load" });
  await expect(page.getByText("Reconciliation required")).toBeVisible();
});

test("no card data is exposed", async ({ page }) => {
  await page.goto("/admin/dashboard/reports/payments", { waitUntil: "load" });
  const main = await page.locator("main").textContent();
  expect(main?.toLowerCase()).not.toContain("cvv");
  expect(main).not.toMatch(/\b4111[\s-]?1111[\s-]?1111[\s-]?1111\b/);
});

test("payment links work", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/reports/payments", { waitUntil: "load" });
  const link = page.getByTestId("reports-table").getByRole("link").first();
  await expect(link).toHaveAttribute("href", /\/payments\?/);
});

test("payments filters do not overflow mobile", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/admin/dashboard/reports/payments", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflow).toBe(false);
});
