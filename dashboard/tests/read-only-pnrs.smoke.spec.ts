import { test, expect } from "@playwright/test";
import { closeDrawerWithEscape } from "./helpers";
import { getPnrsPage } from "@/services/pnr-service";
import { containsSensitiveKeys } from "@/lib/read-only/sensitive-fields";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/admin/dashboard/pnrs", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("fixture pnrs page loads", async () => {
  const result = await getPnrsPage({
    q: "",
    referenceType: "all",
    channel: "all",
    supplier: "",
    airline: "",
    lifecycleStatus: "all",
    fulfilmentStatus: "all",
    ticketingStatus: "all",
    paymentStatus: "all",
    tripType: "all",
    hasAgent: "all",
    reviewRequired: "all",
    deadlineFrom: "",
    deadlineTo: "",
    departureFrom: "",
    departureTo: "",
    page: 1,
    pageSize: 20,
    sort: "newest",
    direction: "desc",
    selectedId: null,
    previewError: false,
    previewLoading: false,
  });
  expect(result.pnrs.length).toBeGreaterThan(0);
  expect(containsSensitiveKeys(result)).toBe(false);
});

test("fixture source notice on pnrs", async ({ page }) => {
  await page.goto("/admin/dashboard/pnrs?dataSourcePreview=fixture", { waitUntil: "load" });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("pnrs table at 1280px", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/pnrs", { waitUntil: "load" });
  await expect(page.getByTestId("pnrs-table")).toBeVisible({ timeout: 60_000 });
});

test("pnrs cards at 1024px", async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto("/admin/dashboard/pnrs", { waitUntil: "load" });
  await expect(page.getByTestId("pnrs-mobile-cards")).toBeVisible({ timeout: 60_000 });
});

test("pnr type filter URL sync", async ({ page }) => {
  await page.goto("/admin/dashboard/pnrs?referenceType=GDS+PNR&channel=Sabre+GDS", { waitUntil: "load" });
  await expect(page).toHaveURL(/referenceType=/);
});

test("pnr drawer opens", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/pnrs?id=JP-PN-70001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 30_000 });
  await expect(page.getByTestId("pnr-drawer-content")).toBeVisible();
});

test("pnr drawer closes with Escape", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/pnrs?id=JP-PN-70001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 30_000 });
  await closeDrawerWithEscape(page, /id=JP-PN-70001/);
});

test("pnrs forbidden preview", async ({ page }) => {
  await page.goto("/admin/dashboard/pnrs?dataSourcePreview=forbidden", { waitUntil: "load" });
  await expect(page.getByText(/Access denied/i)).toBeVisible();
});

test("pnrs live read-only notice", async ({ page }) => {
  await page.goto("/admin/dashboard/pnrs?dataSourcePreview=live", { waitUntil: "load" });
  await expect(page.getByTestId("live-readonly-notice")).toBeVisible();
});

test("pnrs GDS channel distinction in fixtures", async () => {
  const result = await getPnrsPage({
    q: "",
    referenceType: "GDS PNR",
    channel: "all",
    supplier: "",
    airline: "",
    lifecycleStatus: "all",
    fulfilmentStatus: "all",
    ticketingStatus: "all",
    paymentStatus: "all",
    tripType: "all",
    hasAgent: "all",
    reviewRequired: "all",
    deadlineFrom: "",
    deadlineTo: "",
    departureFrom: "",
    departureTo: "",
    page: 1,
    pageSize: 50,
    sort: "newest",
    direction: "desc",
    selectedId: null,
    previewError: false,
    previewLoading: false,
  });
  expect(result.pnrs.every((p) => p.referenceType === "GDS PNR" || p.channel === "Sabre GDS")).toBeTruthy();
});

test("pnrs no overflow at 390px", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/pnrs", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  expect(overflow).toBeFalsy();
});
