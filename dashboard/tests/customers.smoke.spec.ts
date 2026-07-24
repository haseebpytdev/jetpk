import { test, expect } from "@playwright/test";
import { CUSTOMER_FIXTURE_COUNT } from "../mocks/customer-fixtures";
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

test("customers route loads", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Customers", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expect(page.getByTestId("customers-table")).toBeVisible();
});

test("navigation link works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash", { waitUntil: "load" });
  await page.getByRole("link", { name: "Customers", exact: true }).click();
  await page.waitForURL(/\/testdash\/customers/, { timeout: 15_000 });
  await expect(page.getByRole("heading", { name: "Customers", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
});

for (const viewport of viewports.filter((v) => v.width >= 1280)) {
  test(`customers route renders at desktop ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto("/testdash/customers", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Customers", level: 1 })).toBeVisible({
      timeout: 60_000,
    });
    await expect(page.getByTestId("customers-table")).toBeVisible();
    await expect(page.getByText(/Preview data/i).first()).toBeVisible();
  });
}

for (const viewport of viewports.filter((v) => v.width < 768)) {
  test(`customers route renders at mobile ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto("/testdash/customers", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Customers", level: 1 })).toBeVisible({
      timeout: 60_000,
    });
    await expect(page.getByTestId("customers-mobile-cards")).toBeVisible();
  });
}

test("heading and summaries render", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers", { waitUntil: "load" });
  const summary = page.getByLabel("Customer summary metrics");
  await expect(summary).toBeVisible();
  await expect(summary.getByText("Total customers", { exact: true })).toBeVisible();
  await expect(summary.getByText("Active customers", { exact: true })).toBeVisible();
  await expect(summary.getByText("Lifetime value", { exact: true })).toBeVisible();
});

test("deterministic fixture count", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers?pageSize=50", { waitUntil: "load" });
  const table = page.getByTestId("customers-table");
  await expectTableReady(table);
  await expect(table.locator("tbody tr")).toHaveCount(CUSTOMER_FIXTURE_COUNT);
});

test("search filters customers", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers", { waitUntil: "load" });
  const table = page.getByTestId("customers-table");
  await expectTableReady(table);
  const search = page.locator("#customers-search");
  await fillSearchInput(search, "JP-CU-40001");
  await applySearchAndWaitForRow(page, search, table, /q=JP-CU-40001/, "JP-CU-40001");
  await expect(table.getByText("JP-CU-40002")).not.toBeVisible();
});

test("account-status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers", { waitUntil: "load" });
  const table = page.getByTestId("customers-table");
  await expectTableReady(table);
  const accountStatus = page.locator("#filter-account-status");
  await selectAndApplyFilter(page, table, accountStatus, "Suspended", /accountStatus=Suspended/, "Ayesha Khan");
  await expect(table.getByText("Hassan Ali")).not.toBeVisible();
});

test("verification-status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers", { waitUntil: "load" });
  const table = page.getByTestId("customers-table");
  await expectTableReady(table);
  const verification = page.locator("#filter-verification-status");
  await selectAndApplyFilter(page, table, verification, "Pending", /verificationStatus=Pending/, "JP-CU-40002");
  await expect(table.getByText("JP-CU-40001")).not.toBeVisible();
});

test("customer-type filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers", { waitUntil: "load" });
  const table = page.getByTestId("customers-table");
  await expectTableReady(table);
  const customerType = page.locator("#filter-customer-type");
  await selectAndApplyFilter(page, table, customerType, "Family", /customerType=Family/, "JP-CU-40002");
  await expect(table.getByText("JP-CU-40001")).not.toBeVisible();
});

test("outstanding-balance filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers", { waitUntil: "load" });
  const table = page.getByTestId("customers-table");
  await expectTableReady(table);
  const outstanding = page.locator("#filter-outstanding");
  await selectAndApplyFilter(page, table, outstanding, "yes", /hasOutstandingBalance=yes/, "Hassan Ali");
  await expect(table.getByText("Ayesha Khan")).not.toBeVisible();
});

test("sorting changes ordering", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers?sort=totalBookedValue&direction=desc&pageSize=50", {
    waitUntil: "load",
  });
  const table = page.getByTestId("customers-table");
  await expectTableReady(table);
  const firstRow = table.locator("tbody tr").first();
  await expect(firstRow).toContainText("JP-CU-40016");
});

test("pagination works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers?pageSize=10", { waitUntil: "load" });
  const table = page.getByTestId("customers-table");
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
  await page.goto("/testdash/customers?q=JP-CU-40001&pageSize=50", { waitUntil: "load" });
  const table = page.getByTestId("customers-table");
  await expect(table.getByText("JP-CU-40002")).not.toBeVisible();
  await page.getByRole("button", { name: "Clear all" }).click();
  await page.waitForURL((url) => !url.search.includes("q=JP-CU-40001"), { timeout: 15_000 });
  await expectTableReady(table);
  await expect(table.getByText("JP-CU-40002")).toBeVisible();
});

test("URL state survives reload", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers?q=Ayesha&accountStatus=Active", { waitUntil: "load" });
  await page.reload({ waitUntil: "load" });
  await expect(page).toHaveURL(/q=Ayesha/);
  await expect(page).toHaveURL(/accountStatus=Active/);
  await expect(page.locator("#customers-search")).toHaveValue("Ayesha");
});

test("browser back and forward preserves state", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers", { waitUntil: "load" });
  const table = page.getByTestId("customers-table");
  await expectTableReady(table);
  const search = page.locator("#customers-search");
  await fillSearchInput(search, "JP-CU-40005");
  await applySearchAndWaitForRow(page, search, table, /q=JP-CU-40005/, "JP-CU-40005");
  await page.goBack();
  await page.waitForURL((url) => !url.search.includes("q=JP-CU-40005"), { timeout: 15_000 });
  await expectTableReady(table);
  await page.goForward();
  await page.waitForURL(/q=JP-CU-40005/, { timeout: 15_000 });
  await expect(table.getByText("JP-CU-40005")).toBeVisible();
});

test("desktop table renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers", { waitUntil: "load" });
  await expect(page.getByTestId("customers-table")).toBeVisible();
  await expect(page.getByTestId("customers-mobile-cards")).toBeHidden();
});

test("mobile cards render", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/testdash/customers", { waitUntil: "load" });
  const cards = page.getByTestId("customers-mobile-cards");
  await expect(cards).toBeVisible();
  await expect(cards.getByText("JP-CU-40025")).toBeVisible();
});

test("drawer opens", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers", { waitUntil: "load" });
  const table = page.getByTestId("customers-table");
  await expectTableReady(table);
  await table.getByRole("button", { name: "View" }).first().click();
  await page.waitForURL(/id=JP-CU-/, { timeout: 15_000 });
  await expect(page.getByRole("dialog")).toBeVisible();
});

test("drawer content shows customer details", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers?id=JP-CU-40001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  const content = page.getByTestId("customer-drawer-content");
  await expect(content).toContainText("JP-CU-40001");
  await expect(content).toContainText("Ayesha Khan");
  await expect(content).toContainText("ayesha.khan@example.com");
});

test("drawer shows linked bookings", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers?id=JP-CU-40001", { waitUntil: "load" });
  const content = page.getByTestId("customer-drawer-content");
  await expect(content).toContainText("JP-BK-10001");
});

test("drawer shows linked payments", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers?id=JP-CU-40001", { waitUntil: "load" });
  const content = page.getByTestId("customer-drawer-content");
  await expect(content).toContainText("JP-TX-20001");
});

test("drawer closes through close control", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers?id=JP-CU-40001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  await closeDrawerWithButton(page, "Close customer details", /id=JP-CU-40001/);
});

test("drawer closes using Escape", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers?id=JP-CU-40001", { waitUntil: "load" });
  await closeDrawerWithEscape(page, /id=JP-CU-40001/);
});

test("invalid customer ID does not crash", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers?id=JP-CU-INVALID", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Customers", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expect(page.getByRole("dialog")).toBeHidden();
  await expectTableReady(page.getByTestId("customers-table"));
});

test("loading state renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers?previewLoading=1", { waitUntil: "load" });
  await expect(page.getByLabel("Loading customers")).toBeVisible();
});

test("empty filtered state renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers?q=zzznomatchzzz", { waitUntil: "load" });
  await expect(page.getByText("No customers match your filters")).toBeVisible();
});

test("controlled error state renders and recovers", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers?previewError=1", { waitUntil: "load" });
  await expect(page.getByText("Could not load customers")).toBeVisible();
  await page.getByRole("button", { name: "Try again" }).click();
  await page.waitForURL((url) => !url.searchParams.has("previewError"), { timeout: 15_000 });
  await expect(page.getByRole("heading", { name: "Customers", level: 1 })).toBeVisible({
    timeout: 30_000,
  });
  await expectTableReady(page.getByTestId("customers-table"));
});

test("mobile view has no horizontal overflow", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/testdash/customers", { waitUntil: "load" });
  const overflow = await page.evaluate(() => {
    return document.documentElement.scrollWidth > document.documentElement.clientWidth;
  });
  expect(overflow).toBe(false);
});

test("invalid URL values do not crash", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto(
    "/testdash/customers?accountStatus=invalid&sort=notafield&pageSize=999&page=-1&direction=sideways",
    { waitUntil: "load" },
  );
  await expect(page.getByRole("heading", { name: "Customers", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expect(page.getByTestId("customers-table")).toBeVisible();
});

test("payments route remains functional", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/payments", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Payments", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expectTableReady(page.getByTestId("payments-table"));
});

test("bookings route remains functional", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Bookings", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expectTableReady(page.getByTestId("bookings-table"));
});

test("overview route remains functional", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Dashboard", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
});
