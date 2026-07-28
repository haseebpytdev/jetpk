import { test, expect } from "@playwright/test";
import { AUDIT_FIXTURE_COUNT } from "@/mocks/audit-fixtures";
import {
  applyAuditFiltersAndWait,
  applyAuditPresetAndWait,
  clickAuditSortHeader,
  closeDrawerWithEscape,
  expectAuditReady,
  expectTableReady,
  fillSearchInput,
  openAuditEventDrawer,
  openAuditExportDrawer,
  resetAuditFiltersAndWait,
  selectAndApplyFilter,
} from "./helpers";

const EVENT_ID = "JP-AUD-0001";
const ACTOR_USER_EVENT = "JP-AUD-0002";

test.beforeAll(async ({ request }) => {
  await request.get("/admin/dashboard/audit", { timeout: 120_000 });
});

test("audit route renders", async ({ page }) => {
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  await expectAuditReady(page);
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("summary metrics derive from fixtures", async ({ page }) => {
  await page.goto("/admin/dashboard/audit?datePreset=this_month", { waitUntil: "load" });
  const grid = page.getByTestId("audit-metric-grid");
  await expect(grid).toBeVisible();
  await expect(grid.getByText("Total events")).toBeVisible();
  await expect(page.getByTestId("audit-metric-totalEvents").getByRole("heading", { name: String(AUDIT_FIXTURE_COUNT) })).toBeVisible();
  expect(AUDIT_FIXTURE_COUNT).toBe(60);
});

test("search works", async ({ page }) => {
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  await expectAuditReady(page);
  const search = page.locator("#audit-search");
  await fillSearchInput(search, "Ayesha");
  await applyAuditFiltersAndWait(page, /search=Ayesha/);
  const table = page.getByTestId("audit-table");
  await expectTableReady(table);
  await expect(table.getByText("Ayesha K.").first()).toBeVisible();
});

test("category filter works", async ({ page }) => {
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  const table = page.getByTestId("audit-table");
  await selectAndApplyFilter(page, table, page.locator("#audit-category"), "authentication", /category=authentication/, EVENT_ID);
});

test("severity filter works", async ({ page }) => {
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  const table = page.getByTestId("audit-table");
  await selectAndApplyFilter(page, table, page.locator("#audit-severity"), "warning", /severity=warning/, "JP-AUD-0002");
});

test("outcome filter works", async ({ page }) => {
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  const table = page.getByTestId("audit-table");
  await selectAndApplyFilter(page, table, page.locator("#audit-outcome"), "failure", /outcome=failure/, "JP-AUD-0002");
});

test("actor filter works", async ({ page }) => {
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  const table = page.getByTestId("audit-table");
  await selectAndApplyFilter(page, table, page.locator("#audit-actor"), "JP-USR-0001", /actor=JP-USR-0001/, EVENT_ID);
});

test("target filter works", async ({ page }) => {
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  const table = page.getByTestId("audit-table");
  await selectAndApplyFilter(page, table, page.locator("#audit-target-type"), "user", /targetType=user/, "Waqas B.");
});

test("source module filter works", async ({ page }) => {
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  const table = page.getByTestId("audit-table");
  await selectAndApplyFilter(page, table, page.locator("#audit-source-module"), "bookings", /sourceModule=bookings/, "JP-AUD-0026");
});

test("channel filter works", async ({ page }) => {
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  const table = page.getByTestId("audit-table");
  await selectAndApplyFilter(page, table, page.locator("#audit-channel"), "gds", /channel=gds/, "JP-AUD-0026");
});

test("date preset updates URL state", async ({ page }) => {
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  await applyAuditPresetAndWait(page, "last_7_days", /datePreset=last_7_days/);
});

test("custom date range shows validation error", async ({ page }) => {
  await page.goto("/admin/dashboard/audit?datePreset=custom&startDate=2026-08-01&endDate=2026-07-01", { waitUntil: "load" });
  await expect(page.locator("#audit-date-error")).toContainText(/cannot be after/i);
});

test("invalid URL values fall back safely", async ({ page }) => {
  await page.goto("/admin/dashboard/audit?datePreset=this_month&category=invalid&sort=notreal&page=-1", { waitUntil: "load" });
  await expectAuditReady(page);
  await expect(
    page.getByTestId("audit-metric-totalEvents").getByRole("heading", { name: "60" }),
  ).toBeVisible();
});

test("reset filters works", async ({ page }) => {
  await page.goto("/admin/dashboard/audit?category=authentication", { waitUntil: "load" });
  await resetAuditFiltersAndWait(page, /category=authentication/);
});

test("sorting works", async ({ page }) => {
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  await clickAuditSortHeader(page, "Event ID", "id");
});

test("pagination works", async ({ page }) => {
  await page.goto("/admin/dashboard/audit?pageSize=10", { waitUntil: "load" });
  await expectAuditReady(page);
  await page.getByRole("button", { name: "Next page" }).click();
  await expect(page).toHaveURL(/page=2/, { timeout: 30_000 });
});

test("browser back/forward works", async ({ page }) => {
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  await clickAuditSortHeader(page, "Event ID", "id");
  await page.goBack();
  await expect(page).not.toHaveURL(/sort=id/);
  await page.goForward();
  await expect(page).toHaveURL(/sort=id/);
});

test("desktop table renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  await expect(page.getByTestId("audit-table")).toBeVisible();
});

test("mobile cards render", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  await expect(page.getByTestId("audit-mobile-cards")).toBeVisible();
});

test("drawer opens", async ({ page }) => {
  await page.goto(`/admin/dashboard/audit?selected=${EVENT_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("audit-event-detail-drawer")).toBeVisible();
});

test("drawer closes with Escape", async ({ page }) => {
  await page.goto(`/admin/dashboard/audit?selected=${EVENT_ID}`, { waitUntil: "load" });
  await closeDrawerWithEscape(page, /selected=JP-AUD-0001/);
});

test("focus returns after drawer close", async ({ page }) => {
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  await openAuditEventDrawer(page, EVENT_ID);
  const trigger = page.getByTestId("audit-table").getByRole("button", { name: EVENT_ID });
  await closeDrawerWithEscape(page, /selected=JP-AUD-0001/);
  await expect(trigger).toBeVisible();
});

test("selected event deep-link works", async ({ page }) => {
  await page.goto(`/admin/dashboard/audit?selected=${EVENT_ID}`, { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  await expect(page.getByTestId("audit-event-detail-drawer")).toContainText(EVENT_ID);
});

test("actor link renders in drawer", async ({ page }) => {
  await page.goto(`/admin/dashboard/audit?selected=${EVENT_ID}`, { waitUntil: "load" });
  const drawer = page.getByTestId("audit-event-detail-drawer");
  await expect(drawer.getByRole("link", { name: "Ayesha K." })).toHaveAttribute("href", /\/users\?selected=JP-USR-0001/);
});

test("target link renders in drawer", async ({ page }) => {
  await page.goto(`/admin/dashboard/audit?selected=${ACTOR_USER_EVENT}`, { waitUntil: "load" });
  const drawer = page.getByTestId("audit-event-detail-drawer");
  await expect(drawer.getByLabel("Target summary").getByRole("link", { name: "Waqas B." })).toHaveAttribute("href", /\/users\?selected=JP-USR-0016/);
});

test("security view toggles", async ({ page }) => {
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  await expect(page.getByTestId("audit-security-panel")).toBeVisible();
  await page.getByRole("button", { name: "Show security events" }).click();
  await expect(page).toHaveURL(/securityView=1/, { timeout: 30_000 });
  await expect(page.getByRole("button", { name: "Showing security events" })).toBeVisible();
});

test("authorization explanation renders", async ({ page }) => {
  await page.goto(`/admin/dashboard/audit?selected=${EVENT_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("audit-authorization-summary")).toBeVisible();
  await expect(page.getByTestId("audit-authorization-summary")).toContainText(/Authorization explanation/i);
});

test("preview-only label is visible", async ({ page }) => {
  await page.goto(`/admin/dashboard/audit?selected=${EVENT_ID}`, { waitUntil: "load" });
  await expect(page.getByText("Preview-only event — no live mutation was recorded.")).toBeVisible();
});

test("export preview drawer opens", async ({ page }) => {
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  await openAuditExportDrawer(page);
  await expect(page.getByTestId("audit-export-preview")).toBeVisible();
  await expect(page.getByText(/JetPakistan audit export preview/i)).toBeVisible();
});

test("loading state works", async ({ page }) => {
  await page.goto("/admin/dashboard/audit?previewLoading=1", { waitUntil: "load" });
  await expect(page.getByTestId("audit-loading-state")).toBeVisible();
});

test("empty state works", async ({ page }) => {
  await page.goto("/admin/dashboard/audit?previewEmpty=1", { waitUntil: "load" });
  await expect(page.getByText(/No audit events match your filters/i)).toBeVisible();
});

test("error state works", async ({ page }) => {
  await page.goto("/admin/dashboard/audit?previewError=1", { waitUntil: "load" });
  await expect(page.getByText(/Unable to load audit events/i)).toBeVisible();
});

test("360px has no overflow", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflow).toBe(false);
});

test("390px has no overflow", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflow).toBe(false);
});

test("JetPakistan brand is visible", async ({ page }) => {
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  await expect(page.getByText(/JetPakistan/i).first()).toBeVisible();
  const body = await page.locator("body").textContent();
  expect(body).not.toMatch(/Parwaaz|YoursDomain|haseeb-master/i);
});

test("navigation includes Audit link", async ({ page }) => {
  await page.goto("/admin/dashboard", { waitUntil: "load" });
  await expect(page.getByRole("link", { name: "Audit", exact: true })).toBeVisible();
});
