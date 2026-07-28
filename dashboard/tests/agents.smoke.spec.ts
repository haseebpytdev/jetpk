import { test, expect } from "@playwright/test";
import { AGENT_FIXTURE_COUNT } from "../mocks/agent-fixtures";
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

test("agents route loads", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Agents", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expect(page.getByTestId("agents-table")).toBeVisible();
});

test("navigation link works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash", { waitUntil: "load" });
  await page.getByRole("link", { name: "Agents", exact: true }).click();
  await page.waitForURL(/\/testdash\/agents/, { timeout: 15_000 });
  await expect(page.getByRole("heading", { name: "Agents", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
});

for (const viewport of viewports.filter((v) => v.width >= 1280)) {
  test(`agents route renders at desktop ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto("/testdash/agents", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Agents", level: 1 })).toBeVisible({
      timeout: 60_000,
    });
    await expect(page.getByTestId("agents-table")).toBeVisible();
    await expect(page.getByText(/Preview data/i).first()).toBeVisible();
  });
}

for (const viewport of viewports.filter((v) => v.width < 768)) {
  test(`agents route renders at mobile ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto("/testdash/agents", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Agents", level: 1 })).toBeVisible({
      timeout: 60_000,
    });
    await expect(page.getByTestId("agents-mobile-cards")).toBeVisible();
  });
}

test("heading and summaries render", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents", { waitUntil: "load" });
  const summary = page.getByLabel("Agent summary metrics");
  await expect(summary).toBeVisible();
  await expect(summary.getByText("Total agents", { exact: true })).toBeVisible();
  await expect(summary.getByText("Active agents", { exact: true })).toBeVisible();
  await expect(summary.getByText("Gross booking value", { exact: true })).toBeVisible();
});

test("deterministic fixture count", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents?pageSize=50", { waitUntil: "load" });
  const table = page.getByTestId("agents-table");
  await expectTableReady(table);
  await expect(table.locator("tbody tr")).toHaveCount(AGENT_FIXTURE_COUNT);
});

test("search filters agents", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents", { waitUntil: "load" });
  const table = page.getByTestId("agents-table");
  await expectTableReady(table);
  const search = page.locator("#agents-search");
  await fillSearchInput(search, "JP-AG-60001");
  await applySearchAndWaitForRow(page, search, table, /q=JP-AG-60001/, "JP-AG-60001");
  await expect(table.getByText("JP-AG-60002")).not.toBeVisible();
});

test("account-status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents", { waitUntil: "load" });
  const table = page.getByTestId("agents-table");
  await expectTableReady(table);
  const accountStatus = page.locator("#filter-account-status");
  await selectAndApplyFilter(
    page,
    table,
    accountStatus,
    "Suspended",
    /accountStatus=Suspended/,
    "JP-AG-60009",
  );
  await expect(table.getByText("JP-AG-60001")).not.toBeVisible();
});

test("verification-status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents", { waitUntil: "load" });
  const table = page.getByTestId("agents-table");
  await expectTableReady(table);
  const verification = page.locator("#filter-verification-status");
  await selectAndApplyFilter(
    page,
    table,
    verification,
    "Pending",
    /verificationStatus=Pending/,
    "JP-AG-60003",
  );
  await expect(table.getByText("JP-AG-60001")).not.toBeVisible();
});

test("commercial-status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents", { waitUntil: "load" });
  const table = page.getByTestId("agents-table");
  await expectTableReady(table);
  const commercial = page.locator("#filter-commercial-status");
  await selectAndApplyFilter(
    page,
    table,
    commercial,
    "Preferred",
    /commercialStatus=Preferred/,
    "JP-AG-60001",
  );
  await expect(table.getByText("JP-AG-60002")).not.toBeVisible();
});

test("settlement-status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents", { waitUntil: "load" });
  const table = page.getByTestId("agents-table");
  await expectTableReady(table);
  const settlement = page.locator("#filter-settlement-status");
  await selectAndApplyFilter(
    page,
    table,
    settlement,
    "Overdue",
    /settlementStatus=Overdue/,
    "JP-AG-60004",
  );
  await expect(table.getByText("JP-AG-60001")).not.toBeVisible();
});

test("agent-type filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents", { waitUntil: "load" });
  const table = page.getByTestId("agents-table");
  await expectTableReady(table);
  const agentType = page.locator("#filter-agent-type");
  await selectAndApplyFilter(
    page,
    table,
    agentType,
    "Corporate Agent",
    /agentType=Corporate\+Agent/,
    "JP-AG-60002",
  );
  await expect(table.getByText("JP-AG-60001")).not.toBeVisible();
});

test("outstanding-balance filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents", { waitUntil: "load" });
  const table = page.getByTestId("agents-table");
  await expectTableReady(table);
  const outstanding = page.locator("#filter-outstanding");
  await selectAndApplyFilter(
    page,
    table,
    outstanding,
    "yes",
    /hasOutstandingBalance=yes/,
    "JP-AG-60002",
  );
  await expect(table.getByText("JP-AG-60026")).not.toBeVisible();
});

test("pending-commission filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents", { waitUntil: "load" });
  const table = page.getByTestId("agents-table");
  await expectTableReady(table);
  const pendingCommission = page.locator("#filter-pending-commission");
  await selectAndApplyFilter(
    page,
    table,
    pendingCommission,
    "yes",
    /hasPendingCommission=yes/,
    "JP-AG-60001",
  );
  await expect(table.getByText("JP-AG-60026")).not.toBeVisible();
});

test("sorting changes ordering", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents?sort=grossBookingValue&direction=desc&pageSize=50", {
    waitUntil: "load",
  });
  const table = page.getByTestId("agents-table");
  await expectTableReady(table);
  const firstRow = table.locator("tbody tr").first();
  await expect(firstRow).toContainText("JP-AG-60004");
});

test("pagination works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents?pageSize=10", { waitUntil: "load" });
  const table = page.getByTestId("agents-table");
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
  await page.goto("/testdash/agents?q=JP-AG-60001&pageSize=50", { waitUntil: "load" });
  const table = page.getByTestId("agents-table");
  await expect(table.getByText("JP-AG-60002")).not.toBeVisible();
  await page.getByRole("button", { name: "Clear all" }).click();
  await page.waitForURL((url) => !url.search.includes("q=JP-AG-60001"), { timeout: 15_000 });
  await expectTableReady(table);
  await expect(table.getByText("JP-AG-60002")).toBeVisible();
});

test("URL state survives reload", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents?q=Lahore&accountStatus=Active", { waitUntil: "load" });
  await page.reload({ waitUntil: "load" });
  await expect(page).toHaveURL(/q=Lahore/);
  await expect(page).toHaveURL(/accountStatus=Active/);
  await expect(page.locator("#agents-search")).toHaveValue("Lahore");
});

test("browser back and forward preserves state", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents", { waitUntil: "load" });
  const table = page.getByTestId("agents-table");
  await expectTableReady(table);
  const search = page.locator("#agents-search");
  await fillSearchInput(search, "JP-AG-60005");
  await applySearchAndWaitForRow(page, search, table, /q=JP-AG-60005/, "JP-AG-60005");
  await page.goBack();
  await page.waitForURL((url) => !url.search.includes("q=JP-AG-60005"), { timeout: 15_000 });
  await expectTableReady(table);
  await page.goForward();
  await page.waitForURL(/q=JP-AG-60005/, { timeout: 15_000 });
  await expect(table.getByText("JP-AG-60005")).toBeVisible();
});

test("desktop table renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents", { waitUntil: "load" });
  await expect(page.getByTestId("agents-table")).toBeVisible();
  await expect(page.getByTestId("agents-mobile-cards")).toBeHidden();
});

test("mobile cards render", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/testdash/agents", { waitUntil: "load" });
  const cards = page.getByTestId("agents-mobile-cards");
  await expect(cards).toBeVisible();
  await expect(cards.getByText("JP-AG-60026")).toBeVisible();
});

test("drawer opens", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents", { waitUntil: "load" });
  const table = page.getByTestId("agents-table");
  await expectTableReady(table);
  await table.getByRole("button", { name: "View" }).first().click();
  await page.waitForURL(/id=JP-AG-/, { timeout: 15_000 });
  await expect(page.getByRole("dialog")).toBeVisible();
});

test("drawer content shows agent details", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents?id=JP-AG-60001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  const content = page.getByTestId("agent-drawer-content");
  await expect(content).toContainText("JP-AG-60001");
  await expect(content).toContainText("Lahore Central Travel Agency");
  await expect(content).toContainText("lahore.central@agents-preview.example.com");
});

test("drawer shows linked customers", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents?id=JP-AG-60001", { waitUntil: "load" });
  const content = page.getByTestId("agent-drawer-content");
  await expect(content).toContainText("JP-CU-40002");
});

test("drawer shows linked bookings", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents?id=JP-AG-60001", { waitUntil: "load" });
  const content = page.getByTestId("agent-drawer-content");
  await expect(content).toContainText("JP-BK-10002");
});

test("drawer shows linked payments", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents?id=JP-AG-60001", { waitUntil: "load" });
  const content = page.getByTestId("agent-drawer-content");
  await expect(content.getByRole("link", { name: "JP-TX-20002" })).toBeVisible();
});

test("drawer shows linked PNRs and orders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents?id=JP-AG-60001", { waitUntil: "load" });
  const content = page.getByTestId("agent-drawer-content");
  await expect(content).toContainText("JP-PN-70002");
});

test("drawer shows linked tickets and documents", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents?id=JP-AG-60001", { waitUntil: "load" });
  const content = page.getByTestId("agent-drawer-content");
  await expect(content.getByRole("link", { name: "JP-TK-80008" })).toBeVisible();
});

test("drawer closes through close control", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents?id=JP-AG-60001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  await closeDrawerWithButton(page, "Close agent details", /id=JP-AG-60001/);
});

test("drawer closes using Escape", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents?id=JP-AG-60001", { waitUntil: "load" });
  await closeDrawerWithEscape(page, /id=JP-AG-60001/);
});

test("invalid agent ID does not crash", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents?id=JP-AG-INVALID", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Agents", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expect(page.getByRole("dialog")).toBeHidden();
  await expectTableReady(page.getByTestId("agents-table"));
});

test("loading state renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents?previewLoading=1", { waitUntil: "load" });
  await expect(page.getByLabel("Loading agents")).toBeVisible();
});

test("empty filtered state renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents?q=zzznomatchzzz", { waitUntil: "load" });
  await expect(page.getByText("No agents match your filters")).toBeVisible();
});

test("controlled error state renders and recovers", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents?previewError=1", { waitUntil: "load" });
  await expect(page.getByText("Could not load agents")).toBeVisible();
  const retry = page.getByRole("button", { name: "Try again" });
  await Promise.all([
    page.waitForURL((url) => !url.searchParams.has("previewError"), { timeout: 30_000, waitUntil: "commit" }),
    retry.click(),
  ]);
  await expect(page.getByRole("heading", { name: "Agents", level: 1 })).toBeVisible({
    timeout: 30_000,
  });
  await expectTableReady(page.getByTestId("agents-table"));
});

test("mobile view has no horizontal overflow", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/testdash/agents", { waitUntil: "load" });
  const overflow = await page.evaluate(() => {
    return document.documentElement.scrollWidth > document.documentElement.clientWidth;
  });
  expect(overflow).toBe(false);
});

test("invalid URL values do not crash", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto(
    "/testdash/agents?accountStatus=invalid&sort=notafield&pageSize=999&page=-1&direction=sideways",
    { waitUntil: "load" },
  );
  await expect(page.getByRole("heading", { name: "Agents", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expect(page.getByTestId("agents-table")).toBeVisible();
});

test("customers route remains functional", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Customers", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expectTableReady(page.getByTestId("customers-table"));
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
