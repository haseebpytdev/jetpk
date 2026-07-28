import { test, expect } from "@playwright/test";
import { getCustomersPage } from "@/services/customer-service";
import { containsSensitiveKeys } from "@/lib/read-only/sensitive-fields";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/admin/dashboard/customers", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("fixture customers page loads", async () => {
  const result = await getCustomersPage({
    q: "",
    accountStatus: "all",
    verificationStatus: "all",
    customerType: "all",
    city: "",
    country: "",
    hasOutstandingBalance: "all",
    hasBookings: "all",
    activityFrom: "",
    activityTo: "",
    page: 1,
    pageSize: 25,
    sort: "name",
    direction: "asc",
    selectedId: null,
    previewError: false,
    previewLoading: false,
  });
  expect(result.customers.length).toBeGreaterThan(0);
  expect(containsSensitiveKeys(result)).toBe(false);
});

test("customers fixture notice", async ({ page }) => {
  await page.goto("/admin/dashboard/customers?dataSourcePreview=fixture", { waitUntil: "load" });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("customers table at 1280px", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/customers", { waitUntil: "load" });
  await expect(page.getByTestId("customers-table")).toBeVisible({ timeout: 60_000 });
});

test("customers table at 1024px", async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto("/admin/dashboard/customers", { waitUntil: "load" });
  await expect(page.getByTestId("customers-table")).toBeVisible({ timeout: 60_000 });
});

test("customers cards below 768px", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/customers", { waitUntil: "load" });
  await expect(page.getByTestId("customers-mobile-cards")).toBeVisible({ timeout: 60_000 });
});

test("customers filter URL sync account status", async ({ page }) => {
  await page.goto("/admin/dashboard/customers?accountStatus=Active", { waitUntil: "load" });
  await expect(page).toHaveURL(/accountStatus=Active/);
});

test("customers pagination URL sync", async ({ page }) => {
  await page.goto("/admin/dashboard/customers?page=2", { waitUntil: "load" });
  await expect(page).toHaveURL(/page=2/);
});

test("customers drawer with selected id", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/customers?id=JP-CU-40026", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 30_000 });
});

test("customers forbidden preview state", async ({ page }) => {
  await page.goto("/admin/dashboard/customers?dataSourcePreview=forbidden", { waitUntil: "load" });
  await expect(page.getByText(/Access denied/i)).toBeVisible();
});

test("customers no overflow at 360px", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/admin/dashboard/customers", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  expect(overflow).toBeFalsy();
});

test("customers live read-only notice", async ({ page }) => {
  await page.goto("/admin/dashboard/customers?dataSourcePreview=live", { waitUntil: "load" });
  await expect(page.getByTestId("live-readonly-notice")).toBeVisible();
});
