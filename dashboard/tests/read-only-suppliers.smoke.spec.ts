import { test, expect } from "@playwright/test";
import { getSuppliersPage } from "@/services/supplier-service";
import { containsSensitiveKeys } from "@/lib/read-only/sensitive-fields";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/admin/dashboard/suppliers", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("fixture suppliers page loads", async () => {
  const result = await getSuppliersPage({
    q: "",
    category: "all",
    operationalStatus: "all",
    integrationStatus: "all",
    credentialStatus: "all",
    settlementStatus: "all",
    operatingRegion: "",
    hasOutstandingSettlement: "all",
    activityFrom: "",
    activityTo: "",
    page: 1,
    pageSize: 20,
    sort: "supplierName",
    direction: "asc",
    selectedId: null,
    previewError: false,
    previewLoading: false,
  });
  expect(result.suppliers.length).toBeGreaterThan(0);
  expect(containsSensitiveKeys(result)).toBe(false);
});

test("fixture source notice on suppliers", async ({ page }) => {
  await page.goto("/admin/dashboard/suppliers?dataSourcePreview=fixture", { waitUntil: "load" });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("suppliers table at 1280px", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/suppliers", { waitUntil: "load" });
  await expect(page.getByTestId("suppliers-table")).toBeVisible({ timeout: 60_000 });
});

test("suppliers cards at 1024px", async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto("/admin/dashboard/suppliers", { waitUntil: "load" });
  await expect(page.getByTestId("suppliers-mobile-cards")).toBeVisible({ timeout: 60_000 });
});

test("suppliers channel filter URL sync", async ({ page }) => {
  await page.goto("/admin/dashboard/suppliers?integrationStatus=Connected&sort=bookingCount", { waitUntil: "load" });
  await expect(page).toHaveURL(/integrationStatus=Connected/);
});

test("suppliers pagination URL sync", async ({ page }) => {
  await page.goto("/admin/dashboard/suppliers?page=2", { waitUntil: "load" });
  await expect(page).toHaveURL(/page=2/);
});

test("suppliers drawer opens", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/suppliers?id=JP-SU-50001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 30_000 });
  await expect(page.getByTestId("supplier-drawer-content")).toBeVisible();
});

test("suppliers forbidden preview", async ({ page }) => {
  await page.goto("/admin/dashboard/suppliers?dataSourcePreview=forbidden", { waitUntil: "load" });
  await expect(page.getByText(/Access denied/i)).toBeVisible();
});

test("suppliers unauthorized preview", async ({ page }) => {
  await page.goto("/admin/dashboard/suppliers?dataSourcePreview=unauthorized", { waitUntil: "load" });
  await expect(page.getByText(/Sign in required/i)).toBeVisible();
});

test("suppliers stale preview", async ({ page }) => {
  await page.goto("/admin/dashboard/suppliers?dataSourcePreview=stale", { waitUntil: "load" });
  await expect(page.getByTestId("stale-data-notice")).toBeVisible();
});

test("suppliers unavailable preview", async ({ page }) => {
  await page.goto("/admin/dashboard/suppliers?dataSourcePreview=unavailable", { waitUntil: "load" });
  await expect(page.getByText(/Service unavailable/i)).toBeVisible();
});

test("suppliers live read-only notice", async ({ page }) => {
  await page.goto("/admin/dashboard/suppliers?dataSourcePreview=live", { waitUntil: "load" });
  await expect(page.getByTestId("live-readonly-notice")).toBeVisible();
});

test("suppliers no overflow at 390px", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/suppliers", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  expect(overflow).toBeFalsy();
});

test("suppliers no credential exposure in fixture service", async () => {
  const result = await getSuppliersPage({
    q: "",
    category: "all",
    operationalStatus: "all",
    integrationStatus: "all",
    credentialStatus: "all",
    settlementStatus: "all",
    operatingRegion: "",
    hasOutstandingSettlement: "all",
    activityFrom: "",
    activityTo: "",
    page: 1,
    pageSize: 5,
    sort: "supplierName",
    direction: "asc",
    selectedId: null,
    previewError: false,
    previewLoading: false,
  });
  const serialized = JSON.stringify(result).toLowerCase();
  expect(serialized).not.toContain("api_key");
  expect(serialized).not.toContain("lniata");
  expect(serialized).not.toContain("supplier_credentials");
});
