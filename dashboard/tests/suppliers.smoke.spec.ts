import { test, expect } from "@playwright/test";
import { SUPPLIER_FIXTURE_COUNT } from "../mocks/supplier-fixtures";
import {
  applySearchAndWaitForRow,
  closeDrawerWithButton,
  closeDrawerWithEscape,
  expectTableReady,
  fillSearchInput,
  selectAndApplyFilter,
  waitForUrlChange,
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

test("suppliers route loads", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Suppliers", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expect(page.getByTestId("suppliers-table")).toBeVisible();
});

test("navigation link works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash", { waitUntil: "load" });
  await page.getByRole("link", { name: "Suppliers", exact: true }).click();
  await page.waitForURL(/\/testdash\/suppliers/, { timeout: 15_000 });
  await expect(page.getByRole("heading", { name: "Suppliers", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
});

for (const viewport of viewports.filter((v) => v.width >= 1280)) {
  test(`suppliers route renders at desktop ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto("/testdash/suppliers", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Suppliers", level: 1 })).toBeVisible({
      timeout: 60_000,
    });
    await expect(page.getByTestId("suppliers-table")).toBeVisible();
    await expect(page.getByText(/Preview data/i).first()).toBeVisible();
  });
}

for (const viewport of viewports.filter((v) => v.width < 768)) {
  test(`suppliers route renders at mobile ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto("/testdash/suppliers", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Suppliers", level: 1 })).toBeVisible({
      timeout: 60_000,
    });
    await expect(page.getByTestId("suppliers-mobile-cards")).toBeVisible();
  });
}

test("heading and summaries render", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers", { waitUntil: "load" });
  const summary = page.getByLabel("Supplier summary metrics");
  await expect(summary).toBeVisible();
  await expect(summary.getByText("Total suppliers", { exact: true })).toBeVisible();
  await expect(summary.getByText("Connected", { exact: true })).toBeVisible();
  await expect(summary.getByText("Outstanding settlements", { exact: true })).toBeVisible();
});

test("deterministic fixture count", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers?pageSize=50", { waitUntil: "load" });
  const table = page.getByTestId("suppliers-table");
  await expectTableReady(table);
  await expect(table.locator("tbody tr")).toHaveCount(SUPPLIER_FIXTURE_COUNT);
});

test("search filters suppliers", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers", { waitUntil: "load" });
  const table = page.getByTestId("suppliers-table");
  await expectTableReady(table);
  const search = page.locator("#suppliers-search");
  await fillSearchInput(search, "JP-SU-50001");
  await applySearchAndWaitForRow(page, search, table, /q=JP-SU-50001/, "Sabre");
  await expect(table.getByText("Duffel")).not.toBeVisible();
});

test("category filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers", { waitUntil: "load" });
  const table = page.getByTestId("suppliers-table");
  await expectTableReady(table);
  const category = page.locator("#filter-category");
  await selectAndApplyFilter(page, table, category, "GDS", /category=GDS/, "Sabre");
  await expect(table.getByText("Emirates")).not.toBeVisible();
});

test("operational-status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers", { waitUntil: "load" });
  const table = page.getByTestId("suppliers-table");
  await expectTableReady(table);
  const operational = page.locator("#filter-operational-status");
  await selectAndApplyFilter(
    page,
    table,
    operational,
    "Maintenance",
    /operationalStatus=Maintenance/,
    "Malaysia Airlines",
  );
  await expect(table.getByText("Sabre")).not.toBeVisible();
});

test("integration-status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers", { waitUntil: "load" });
  const table = page.getByTestId("suppliers-table");
  await expectTableReady(table);
  const integration = page.locator("#filter-integration-status");
  await selectAndApplyFilter(page, table, integration, "Disabled", /integrationStatus=Disabled/, "Malaysia Airlines");
  await expect(table.getByText("Sabre")).not.toBeVisible();
});

test("credential-status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers", { waitUntil: "load" });
  const table = page.getByTestId("suppliers-table");
  await expectTableReady(table);
  const credential = page.locator("#filter-credential-status");
  await selectAndApplyFilter(page, table, credential, "Missing", /credentialStatus=Missing/, "Amadeus");
  await expect(table.getByText("Sabre")).not.toBeVisible();
});

test("settlement-status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers", { waitUntil: "load" });
  const table = page.getByTestId("suppliers-table");
  await expectTableReady(table);
  const settlement = page.locator("#filter-settlement-status");
  await selectAndApplyFilter(
    page,
    table,
    settlement,
    "Overdue",
    /settlementStatus=Overdue/,
    "EasyFly Consolidator",
  );
  await expect(table.getByText("Sabre")).not.toBeVisible();
});

test("outstanding-settlement filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers", { waitUntil: "load" });
  const table = page.getByTestId("suppliers-table");
  await expectTableReady(table);
  const outstanding = page.locator("#filter-outstanding-settlement");
  await selectAndApplyFilter(
    page,
    table,
    outstanding,
    "yes",
    /hasOutstandingSettlement=yes/,
    "Sabre",
  );
  await expect(table.getByText("Amadeus")).not.toBeVisible();
});

test("sorting changes ordering", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers?sort=bookingCount&direction=desc&pageSize=50", {
    waitUntil: "load",
  });
  const table = page.getByTestId("suppliers-table");
  await expectTableReady(table);
  const firstRow = table.locator("tbody tr").first();
  await expect(firstRow).toContainText("Sabre");
});

test("pagination works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers?pageSize=10", { waitUntil: "load" });
  const table = page.getByTestId("suppliers-table");
  await expectTableReady(table);
  await expect(page.getByText("1 /")).toBeVisible();
  const firstPageId = await table.locator("tbody tr").first().textContent();
  await page.getByRole("button", { name: "Next page" }).click();
  await waitForUrlChange(page, /page=2/);
  await expectTableReady(table);
  await expect(table.locator("tbody tr").first()).not.toHaveText(firstPageId ?? "");
});

test("reset filters restores results", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers?q=Sabre&pageSize=50", { waitUntil: "load" });
  const table = page.getByTestId("suppliers-table");
  await expect(table.getByText("Duffel")).not.toBeVisible();
  await page.getByRole("button", { name: "Clear all" }).click();
  await page.waitForURL((url) => !url.search.includes("q=Sabre"), { timeout: 15_000 });
  await expectTableReady(table);
  await expect(table.getByText("Duffel")).toBeVisible();
});

test("URL state survives reload", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers?q=Sabre&category=GDS", { waitUntil: "load" });
  await page.reload({ waitUntil: "load" });
  await expect(page).toHaveURL(/q=Sabre/);
  await expect(page).toHaveURL(/category=GDS/);
  await expect(page.locator("#suppliers-search")).toHaveValue("Sabre");
});

test("browser back and forward preserves state", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers", { waitUntil: "load" });
  const table = page.getByTestId("suppliers-table");
  await expectTableReady(table);
  const search = page.locator("#suppliers-search");
  await fillSearchInput(search, "Duffel");
  await applySearchAndWaitForRow(page, search, table, /q=Duffel/, "Duffel");
  await page.goBack();
  await page.waitForURL((url) => !url.search.includes("q=Duffel"), { timeout: 15_000 });
  await expectTableReady(table);
  await page.goForward();
  await page.waitForURL(/q=Duffel/, { timeout: 15_000 });
  await expect(table.getByText("Duffel")).toBeVisible();
});

test("desktop table renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers", { waitUntil: "load" });
  await expect(page.getByTestId("suppliers-table")).toBeVisible();
  await expect(page.getByTestId("suppliers-mobile-cards")).toBeHidden();
});

test("mobile cards render", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/testdash/suppliers?pageSize=50", { waitUntil: "load" });
  const cards = page.getByTestId("suppliers-mobile-cards");
  await expect(cards).toBeVisible();
  await expect(cards.getByText("Serene Air")).toBeVisible();
});

test("drawer opens", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers", { waitUntil: "load" });
  const table = page.getByTestId("suppliers-table");
  await expectTableReady(table);
  await table.getByRole("button", { name: "View" }).first().click();
  await page.waitForURL(/id=JP-SU-/, { timeout: 15_000 });
  await expect(page.getByRole("dialog")).toBeVisible();
});

test("drawer content shows supplier details", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers?id=JP-SU-50001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  const content = page.getByTestId("supplier-drawer-content");
  await expect(content).toContainText("JP-SU-50001");
  await expect(content).toContainText("Sabre");
  await expect(content).toContainText("SBR");
});

test("safe credential-status display", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers?id=JP-SU-50001", { waitUntil: "load" });
  const content = page.getByTestId("supplier-drawer-content");
  await expect(content).toContainText("Configured");
  await expect(content).toContainText("no credentials, API keys, or secrets");
  await expect(content).not.toContainText("password");
  await expect(content).not.toContainText("api_key");
});

test("drawer shows linked bookings", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers?id=JP-SU-50001", { waitUntil: "load" });
  const content = page.getByTestId("supplier-drawer-content");
  await expect(content).toContainText("JP-BK-10001");
});

test("drawer shows linked payments", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers?id=JP-SU-50001", { waitUntil: "load" });
  const content = page.getByTestId("supplier-drawer-content");
  await expect(content).toContainText("JP-TX-20025");
});

test("drawer closes through close control", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers?id=JP-SU-50001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  await closeDrawerWithButton(page, "Close supplier details", /id=JP-SU-50001/);
});

test("drawer closes using Escape", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers?id=JP-SU-50001", { waitUntil: "load" });
  await closeDrawerWithEscape(page, /id=JP-SU-50001/);
});

test("invalid supplier ID does not crash", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers?id=JP-SU-INVALID", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Suppliers", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expect(page.getByRole("dialog")).toBeHidden();
  await expectTableReady(page.getByTestId("suppliers-table"));
});

test("loading state renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers?previewLoading=1", { waitUntil: "load" });
  await expect(page.getByLabel("Loading suppliers")).toBeVisible();
});

test("empty filtered state renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers?q=zzznomatchzzz", { waitUntil: "load" });
  await expect(page.getByText("No suppliers match your filters")).toBeVisible();
});

test("controlled error state renders and recovers", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/suppliers?previewError=1", { waitUntil: "load" });
  await expect(page.getByText("Could not load suppliers")).toBeVisible();
  await page.getByRole("button", { name: "Try again" }).click();
  await page.waitForURL((url) => !url.searchParams.has("previewError"), { timeout: 15_000 });
  await expect(page.getByRole("heading", { name: "Suppliers", level: 1 })).toBeVisible({
    timeout: 30_000,
  });
  await expectTableReady(page.getByTestId("suppliers-table"));
});

test("mobile view has no horizontal overflow", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/testdash/suppliers", { waitUntil: "load" });
  const overflow = await page.evaluate(() => {
    return document.documentElement.scrollWidth > document.documentElement.clientWidth;
  });
  expect(overflow).toBe(false);
});

test("invalid URL values do not crash", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto(
    "/testdash/suppliers?category=invalid&sort=notafield&pageSize=999&page=-1&direction=sideways",
    { waitUntil: "load" },
  );
  await expect(page.getByRole("heading", { name: "Suppliers", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expect(page.getByTestId("suppliers-table")).toBeVisible();
});

test("customers route remains functional", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Customers", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expectTableReady(page.getByTestId("customers-table"));
});

test("payments route remains functional", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/payments", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Payments", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expectTableReady(page.getByTestId("payments-table"));
});

test("overview route remains functional", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Dashboard", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
});
