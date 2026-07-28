import { test, expect } from "@playwright/test";
import { getPaymentsPage } from "@/services/payment-service";
import { containsSensitiveKeys } from "@/lib/read-only/sensitive-fields";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/admin/dashboard/payments", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("fixture payments page loads", async () => {
  const result = await getPaymentsPage({
    q: "",
    paymentStatus: "all",
    transactionStatus: "all",
    type: "all",
    method: "all",
    channel: "all",
    reconciliation: "all",
    currency: "",
    dateFrom: "",
    dateTo: "",
    minAmount: "",
    maxAmount: "",
    page: 1,
    pageSize: 25,
    sort: "transactionDate",
    direction: "desc",
    selectedTransactionId: null,
    previewError: false,
  });
  expect(result.transactions.length).toBeGreaterThan(0);
  expect(containsSensitiveKeys(result)).toBe(false);
});

test("payments fixture notice", async ({ page }) => {
  await page.goto("/admin/dashboard/payments?dataSourcePreview=fixture", { waitUntil: "load" });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("payments table at 1280px", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/payments", { waitUntil: "load" });
  await expect(page.getByTestId("payments-table")).toBeVisible({ timeout: 60_000 });
});

test("payments cards at 1024px", async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto("/admin/dashboard/payments", { waitUntil: "load" });
  await expect(page.getByTestId("payments-mobile-cards")).toBeVisible({ timeout: 60_000 });
});

test("payments filter URL sync reconciliation", async ({ page }) => {
  await page.goto("/admin/dashboard/payments?reconciliation=unreconciled", { waitUntil: "load" });
  await expect(page).toHaveURL(/reconciliation=unreconciled/);
});

test("payments pagination URL sync", async ({ page }) => {
  await page.goto("/admin/dashboard/payments?page=2", { waitUntil: "load" });
  await expect(page).toHaveURL(/page=2/);
});

test("payments unavailable preview state", async ({ page }) => {
  await page.goto("/admin/dashboard/payments?dataSourcePreview=unavailable", { waitUntil: "load" });
  await expect(page.getByText(/Service unavailable/i)).toBeVisible();
});

test("payments no overflow at 390px", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/payments", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  expect(overflow).toBeFalsy();
});

test("payments live read-only notice", async ({ page }) => {
  await page.goto("/admin/dashboard/payments?dataSourcePreview=live", { waitUntil: "load" });
  await expect(page.getByTestId("live-readonly-notice")).toBeVisible();
});

test("payments drawer with selected transaction", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/payments?transactionId=JP-TX-20001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 30_000 });
});
