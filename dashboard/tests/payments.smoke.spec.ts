import { test, expect } from "@playwright/test";
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

for (const viewport of viewports.filter((v) => v.width >= 1280)) {
  test(`payments route renders at desktop ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto("/testdash/payments", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Payments", level: 1 })).toBeVisible({
      timeout: 60_000,
    });
    await expect(page.getByTestId("payments-table")).toBeVisible();
    await expect(page.getByText(/Preview data/i).first()).toBeVisible();
  });
}

for (const viewport of viewports.filter((v) => v.width < 768)) {
  test(`payments route renders at mobile ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto("/testdash/payments", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Payments", level: 1 })).toBeVisible({
      timeout: 60_000,
    });
    await expect(page.getByTestId("payments-mobile-cards")).toBeVisible();
  });
}

test("summary metrics render", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/payments", { waitUntil: "load" });
  const summary = page.getByLabel("Payment summary metrics");
  await expect(summary).toBeVisible();
  await expect(summary.getByText("Gross collected", { exact: true })).toBeVisible();
  await expect(summary.getByText("Net collected", { exact: true })).toBeVisible();
  await expect(summary.getByText("Unreconciled", { exact: true })).toBeVisible();
});

test("search filters transactions", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/payments", { waitUntil: "load" });
  const table = page.getByTestId("payments-table");
  await expectTableReady(table);
  const search = page.locator("#payments-search");
  await fillSearchInput(search, "JP-TX-20001");
  await applySearchAndWaitForRow(page, search, table, /q=JP-TX-20001/, "JP-TX-20001");
  await expect(table.getByText("JP-TX-20002")).not.toBeVisible();
});

test("payment-status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/payments", { waitUntil: "load" });
  const table = page.getByTestId("payments-table");
  await expectTableReady(table);
  const paymentStatus = page.locator("#filter-payment-status");
  await selectAndApplyFilter(page, table, paymentStatus, "refunded", /paymentStatus=refunded/, "JP-TX-20026");
  await expect(table.getByText("JP-TX-20001")).not.toBeVisible();
});

test("transaction-type filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/payments", { waitUntil: "load" });
  const table = page.getByTestId("payments-table");
  await expectTableReady(table);
  const typeFilter = page.locator("#filter-type");
  await selectAndApplyFilter(page, table, typeFilter, "refund", /type=refund/, "JP-TX-20026");
  await expect(table.getByText("JP-TX-20001")).not.toBeVisible();
});

test("reconciliation filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/payments", { waitUntil: "load" });
  const table = page.getByTestId("payments-table");
  await expectTableReady(table);
  const reconciliation = page.locator("#filter-reconciliation");
  await selectAndApplyFilter(page, table, reconciliation, "disputed", /reconciliation=disputed/, "JP-TX-20032");
  await expect(table.getByText("JP-TX-20001")).not.toBeVisible();
});

test("clear filters restores results", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/payments?q=JP-TX-20001&pageSize=50", { waitUntil: "load" });
  const table = page.getByTestId("payments-table");
  await expect(table.getByText("JP-TX-20002")).not.toBeVisible();
  await page.getByRole("button", { name: "Clear all" }).click();
  await page.waitForURL((url) => !url.search.includes("q=JP-TX-20001"), { timeout: 15_000 });
  await expectTableReady(table);
  await expect(table.getByText("JP-TX-20002")).toBeVisible();
});

test("sorting changes ordering", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/payments?sort=grossAmount&direction=asc&pageSize=50", { waitUntil: "load" });
  const table = page.getByTestId("payments-table");
  await expectTableReady(table);
  const firstRow = table.locator("tbody tr").first();
  await expect(firstRow).toContainText("JP-TX-20034");
});

test("pagination works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/payments?pageSize=10", { waitUntil: "load" });
  const table = page.getByTestId("payments-table");
  await expectTableReady(table);
  await expect(page.getByText("1 /")).toBeVisible();
  const firstPageId = await table.locator("tbody tr").first().textContent();
  await page.getByRole("button", { name: "Next page" }).click();
  await waitForUrlChange(page, /page=2/);
  await expectTableReady(table);
  await expect(table.locator("tbody tr").first()).not.toHaveText(firstPageId ?? "");
});

test("page-size change works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/payments", { waitUntil: "load" });
  const table = page.getByTestId("payments-table");
  await expectTableReady(table);
  await page.getByLabel("Rows per page").selectOption("10");
  await waitForUrlChange(page, /pageSize=10/);
  await expect(table.locator("tbody tr")).toHaveCount(10);
});

test("URL state survives reload", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/payments?q=Ayesha&type=payment", { waitUntil: "load" });
  await page.reload({ waitUntil: "load" });
  await expect(page).toHaveURL(/q=Ayesha/);
  await expect(page).toHaveURL(/type=payment/);
  await expect(page.locator("#payments-search")).toHaveValue("Ayesha");
});

test("invalid URL values do not crash", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto(
    "/testdash/payments?paymentStatus=invalid&sort=notafield&pageSize=999&page=-1&direction=sideways",
    { waitUntil: "load" },
  );
  await expect(page.getByRole("heading", { name: "Payments", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expect(page.getByTestId("payments-table")).toBeVisible();
});

test("drawer opens", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/payments", { waitUntil: "load" });
  const table = page.getByTestId("payments-table");
  await expectTableReady(table);
  await table.getByRole("button", { name: "View" }).first().click();
  await page.waitForURL(/transactionId=JP-TX-/, { timeout: 15_000 });
  await expect(page.getByRole("dialog")).toBeVisible();
});

test("drawer shows selected transaction", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/payments?transactionId=JP-TX-20001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  const content = page.getByTestId("payment-drawer-content");
  await expect(content).toContainText("JP-TX-20001");
  await expect(content).toContainText("JP-PAY-30001");
});

test("drawer shows linked booking or PNR", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/payments?transactionId=JP-TX-20001", { waitUntil: "load" });
  const content = page.getByTestId("payment-drawer-content");
  await expect(content).toContainText("JP-BK-10001");
  await expect(content).toContainText("ABC123");
});

test("drawer closes through close control", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/payments?transactionId=JP-TX-20001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  await closeDrawerWithButton(page, "Close payment details", /transactionId=JP-TX-20001/);
});

test("drawer closes using Escape", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/payments?transactionId=JP-TX-20001", { waitUntil: "load" });
  await closeDrawerWithEscape(page, /transactionId=JP-TX-20001/);
});

test("mobile cards render", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/testdash/payments", { waitUntil: "load" });
  const cards = page.getByTestId("payments-mobile-cards");
  await expect(cards).toBeVisible();
  await expect(cards.getByText("JP-TX-20025")).toBeVisible();
});

test("mobile view has no horizontal overflow", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/testdash/payments", { waitUntil: "load" });
  const overflow = await page.evaluate(() => {
    return document.documentElement.scrollWidth > document.documentElement.clientWidth;
  });
  expect(overflow).toBe(false);
});

test("empty filtered state renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/payments?q=zzznomatchzzz", { waitUntil: "load" });
  await expect(page.getByText("No transactions match your filters")).toBeVisible();
});

test("controlled error state renders and recovers", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/payments?previewError=1", { waitUntil: "load" });
  await expect(page.getByText("Could not load payments")).toBeVisible();
  await page.getByRole("button", { name: "Try again" }).click();
  await page.waitForURL((url) => !url.searchParams.has("previewError"), { timeout: 15_000 });
  await expect(page.getByRole("heading", { name: "Payments", level: 1 })).toBeVisible({
    timeout: 30_000,
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

test("planned route remains functional", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/planned/bookings", { waitUntil: "load" });
  await expect(page.getByText(/Planned module/i)).toBeVisible({ timeout: 30_000 });
});
