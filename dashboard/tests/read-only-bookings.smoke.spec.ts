import { test, expect } from "@playwright/test";
import { getBookingsPage } from "@/services/booking-service";
import { containsSensitiveKeys } from "@/lib/read-only/sensitive-fields";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/testdash/bookings", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("fixture bookings page loads", async () => {
  const result = await getBookingsPage({
    q: "",
    status: "all",
    payment: "all",
    ticketing: "all",
    supplier: "",
    airline: "",
    tripType: "all",
    bookingDateFrom: "",
    bookingDateTo: "",
    departureDateFrom: "",
    departureDateTo: "",
    page: 1,
    pageSize: 25,
    sort: "bookingDate",
    direction: "desc",
    selectedId: null,
    previewError: false,
  });
  expect(result.bookings.length).toBeGreaterThan(0);
  expect(containsSensitiveKeys(result)).toBe(false);
});

test("fixture source notice on bookings", async ({ page }) => {
  await page.goto("/testdash/bookings?dataSourcePreview=fixture", { waitUntil: "load" });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("bookings table at 1280px", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings", { waitUntil: "load" });
  await expect(page.getByTestId("bookings-table")).toBeVisible({ timeout: 60_000 });
});

test("bookings cards at 1024px", async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto("/testdash/bookings", { waitUntil: "load" });
  await expect(page.getByTestId("bookings-mobile-cards")).toBeVisible({ timeout: 60_000 });
});

test("bookings filter URL sync status", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings?status=confirmed", { waitUntil: "load" });
  await expect(page).toHaveURL(/status=confirmed/);
});

test("bookings pagination URL sync", async ({ page }) => {
  await page.goto("/testdash/bookings?page=2", { waitUntil: "load" });
  await expect(page).toHaveURL(/page=2/);
});

test("bookings drawer opens and shows content", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings?id=JP-BK-10001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 30_000 });
  await expect(page.getByTestId("booking-drawer-content")).toContainText("JP-BK-10001");
});

test("bookings forbidden preview state", async ({ page }) => {
  await page.goto("/testdash/bookings?dataSourcePreview=forbidden", { waitUntil: "load" });
  await expect(page.getByText(/Access denied/i)).toBeVisible();
});

test("bookings no overflow at 390px", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/testdash/bookings", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  expect(overflow).toBeFalsy();
});

test("bookings live read-only notice", async ({ page }) => {
  await page.goto("/testdash/bookings?dataSourcePreview=live", { waitUntil: "load" });
  await expect(page.getByTestId("live-readonly-notice")).toBeVisible();
});
