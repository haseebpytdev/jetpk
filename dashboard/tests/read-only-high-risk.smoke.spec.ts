import { test, expect } from "@playwright/test";
import { applySearchAndWaitForRow, closeDrawerWithEscape, expectTableReady, fillSearchInput } from "./helpers";
import { createReadOnlyService, ReadOnlyServiceError } from "@/lib/read-only/read-only-service";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";

test.describe.configure({ retries: 0 });

test("no silent fallback when laravel adapter missing", async () => {
  const service = createReadOnlyService({
    module: "bookings",
    fixtureAdapter: {
      mode: "fixture",
      fetch: async () => createReadOnlyEnvelope({ data: { ok: true } }),
    },
  });
  const original = process.env.NEXT_PUBLIC_USE_MOCK_DATA;
  process.env.NEXT_PUBLIC_USE_MOCK_DATA = "false";
  try {
    await expect(service.fetchReadOnly({})).rejects.toBeInstanceOf(ReadOnlyServiceError);
  } finally {
    process.env.NEXT_PUBLIC_USE_MOCK_DATA = original;
  }
});

test("session expiry unauthorized preview", async ({ page }) => {
  await page.goto("/testdash?dataSourcePreview=unauthorized", { waitUntil: "load" });
  await expect(page.getByText(/Sign in required/i)).toBeVisible();
});

test("booking filter URL synchronization", async ({ page }) => {
  await page.goto("/testdash/bookings?status=pending&sort=departureDate&direction=asc", { waitUntil: "load" });
  await expect(page).toHaveURL(/status=pending/);
  await expect(page).toHaveURL(/sort=departureDate/);
});

test("payment filter URL synchronization", async ({ page }) => {
  await page.goto("/testdash/payments?paymentStatus=paid&sort=grossAmount", { waitUntil: "load" });
  await expect(page).toHaveURL(/paymentStatus=paid/);
});

test("customer filter URL synchronization", async ({ page }) => {
  await page.goto("/testdash/customers?verificationStatus=Verified&sort=bookingCount", { waitUntil: "load" });
  await expect(page).toHaveURL(/verificationStatus=Verified/);
});

test("bookings 1024px table card transition", async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto("/testdash/bookings", { waitUntil: "load" });
  await expect(page.getByTestId("bookings-mobile-cards")).toBeVisible({ timeout: 60_000 });
});

test("payments 390px overflow guard", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/testdash/payments", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  expect(overflow).toBeFalsy();
});

test("stale data notice via preview gate", async ({ page }) => {
  await page.goto("/testdash/bookings?dataSourcePreview=stale", { waitUntil: "load" });
  await expect(page.getByTestId("stale-data-notice")).toBeVisible();
});

test("unavailable to error transition gates", async ({ page }) => {
  await page.goto("/testdash/customers?dataSourcePreview=unavailable", { waitUntil: "load" });
  await expect(page.getByText(/Service unavailable/i)).toBeVisible();
  await page.goto("/testdash/customers?dataSourcePreview=error", { waitUntil: "load" });
  await expect(page.getByText(/Unable to load data/i)).toBeVisible();
});

test("bookings drawer closes with Escape", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings?id=JP-BK-10001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 30_000 });
  await closeDrawerWithEscape(page, /id=JP-BK-10001/);
});

test("bookings drawer Escape focus returns to trigger", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings?q=JP-BK-10001&pageSize=50", { waitUntil: "load" });
  const table = page.getByTestId("bookings-table");
  await expectTableReady(table);
  const trigger = table.getByRole("button", { name: "JP-BK-10001" });
  await trigger.focus();
  await trigger.click();
  await expect(page).toHaveURL(/id=JP-BK-10001/, { timeout: 30_000 });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 15_000 });
  await closeDrawerWithEscape(page, /id=JP-BK-10001/);
  await expect(trigger).toBeFocused();
});

test("pagination on customers", async ({ page }) => {
  await page.goto("/testdash/customers?page=2&pageSize=10", { waitUntil: "load" });
  await expect(page).toHaveURL(/page=2/);
  await expect(page).toHaveURL(/pageSize=10/);
});

test("supplier channel filter URL sync", async ({ page }) => {
  await page.goto("/testdash/suppliers?integrationStatus=Connected", { waitUntil: "load" });
  await expect(page).toHaveURL(/integrationStatus=Connected/);
});

test("agent filter URL sync", async ({ page }) => {
  await page.goto("/testdash/agents?accountStatus=Active", { waitUntil: "load" });
  await expect(page).toHaveURL(/accountStatus=Active/);
});

test("pnr order type filter URL", async ({ page }) => {
  await page.goto("/testdash/pnrs?referenceType=NDC+Order", { waitUntil: "load" });
  await expect(page).toHaveURL(/referenceType=/);
});

test("ticket filter URL sync", async ({ page }) => {
  await page.goto("/testdash/tickets?issueStatus=Issued", { waitUntil: "load" });
  await expect(page).toHaveURL(/issueStatus=Issued/);
});

test("report date range URL", async ({ page }) => {
  await page.goto("/testdash/reports?datePreset=last_30_days", { waitUntil: "load" });
  await expect(page).toHaveURL(/datePreset=last_30_days/);
});

test("report currency filter", async ({ page }) => {
  await page.goto("/testdash/reports?currency=PKR", { waitUntil: "load" });
  await expect(page).toHaveURL(/currency=PKR/);
});

test("suppliers 1024px table card transition", async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto("/testdash/suppliers", { waitUntil: "load" });
  await expect(page.getByTestId("suppliers-mobile-cards")).toBeVisible({ timeout: 60_000 });
});

test("pnrs 390px overflow guard", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/testdash/pnrs", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  expect(overflow).toBeFalsy();
});

test("source mode switching suppliers", async ({ page }) => {
  await page.goto("/testdash/suppliers?dataSourcePreview=fixture", { waitUntil: "load" });
  await expect(page.getByTestId("fixture-data-notice")).toBeVisible();
  await page.goto("/testdash/suppliers?dataSourcePreview=live", { waitUntil: "load" });
  await expect(page.getByTestId("live-readonly-notice")).toBeVisible();
});

test("supplier credential exclusion in fixture service", async ({ page }) => {
  await page.goto("/testdash/suppliers?pageSize=50", { waitUntil: "load" });
  const payload = (await page.content()).toLowerCase();
  expect(payload).not.toContain("api_key");
  expect(payload).not.toContain("lniata");
  expect(payload).not.toContain("supplier_credentials");
});

test("GDS NDC distinction in fixture pnrs", async ({ page }) => {
  await page.goto("/testdash/pnrs?referenceType=GDS+PNR&pageSize=50", { waitUntil: "load" });
  await expect(page.getByText("GDS PNR").first()).toBeVisible({ timeout: 30_000 });
  await page.goto("/testdash/pnrs?referenceType=NDC+Order&pageSize=50", { waitUntil: "load" });
  await expect(page.getByText("NDC Order").first()).toBeVisible({ timeout: 30_000 });
});

test("pnr drawer closes with Escape", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?id=JP-PN-70001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 30_000 });
  await closeDrawerWithEscape(page, /id=JP-PN-70001/);
});

test("pnr drawer Escape focus returns to trigger", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?pageSize=50", { waitUntil: "load" });
  const table = page.getByTestId("pnrs-table");
  await expectTableReady(table);
  const search = page.locator("#pnrs-search");
  await fillSearchInput(search, "JP-PN-70001");
  await applySearchAndWaitForRow(page, search, table, /q=JP-PN-70001/, "JP-PN-70001");
  const trigger = table.getByRole("button", { name: "JP-PN-70001" });
  await trigger.focus();
  await trigger.click();
  await expect(page).toHaveURL(/id=JP-PN-70001/, { timeout: 30_000 });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 15_000 });
  await closeDrawerWithEscape(page, /id=JP-PN-70001/);
  await expect(trigger).toBeFocused();
});

test("ticket drawer closes with Escape", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?id=JP-TK-80001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 30_000 });
  await closeDrawerWithEscape(page, /id=JP-TK-80001/);
});

test("ticket drawer Escape focus returns to trigger", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?pageSize=50", { waitUntil: "load" });
  const table = page.getByTestId("tickets-table");
  await expectTableReady(table);
  const search = page.locator("#tickets-search");
  await fillSearchInput(search, "JP-TK-80001");
  await applySearchAndWaitForRow(page, search, table, /q=JP-TK-80001/, "JP-TK-80001");
  const trigger = table.getByRole("button", { name: "JP-TK-80001" });
  await trigger.focus();
  await trigger.click();
  await expect(page).toHaveURL(/id=JP-TK-80001/, { timeout: 30_000 });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 15_000 });
  await closeDrawerWithEscape(page, /id=JP-TK-80001/);
  await expect(trigger).toBeFocused();
});

test("report export preview manifest", async ({ page }) => {
  await page.goto("/testdash/reports?currency=PKR", { waitUntil: "load" });
  await page.getByTestId("reports-export-button").click();
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 15_000 });
  await expect(page.getByText(/Export preview report/i)).toBeVisible();
  await expect(page.getByText(/PKR/i).first()).toBeVisible();
});
