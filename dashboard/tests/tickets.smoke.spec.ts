import { test, expect } from "@playwright/test";
import { TICKET_FIXTURE_COUNT } from "../mocks/ticket-fixtures";
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

test("tickets route loads", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Tickets & Documents", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expect(page.getByTestId("tickets-table")).toBeVisible();
});

test("navigation link works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash", { waitUntil: "load" });
  await page.getByRole("link", { name: "Tickets", exact: true }).click();
  await page.waitForURL(/\/testdash\/tickets/, { timeout: 15_000 });
  await expect(page.getByRole("heading", { name: "Tickets & Documents", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
});

for (const viewport of viewports.filter((v) => v.width >= 1280)) {
  test(`tickets route renders at desktop ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto("/testdash/tickets", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Tickets & Documents", level: 1 })).toBeVisible({
      timeout: 60_000,
    });
    await expect(page.getByTestId("tickets-table")).toBeVisible();
    await expect(page.getByText(/Preview data/i).first()).toBeVisible();
  });
}

for (const viewport of viewports.filter((v) => v.width < 768)) {
  test(`tickets route renders at mobile ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto("/testdash/tickets", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Tickets & Documents", level: 1 })).toBeVisible({
      timeout: 60_000,
    });
    await expect(page.getByTestId("tickets-mobile-cards")).toBeVisible();
  });
}

test("heading and summaries render", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  const summary = page.getByLabel("Ticket summary metrics");
  await expect(summary).toBeVisible();
  await expect(summary.getByText("Total documents", { exact: true })).toBeVisible();
  await expect(summary.getByText("Issued", { exact: true })).toBeVisible();
  await expect(summary.getByText("Document value", { exact: true })).toBeVisible();
});

test("deterministic fixture count", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?pageSize=50", { waitUntil: "load" });
  const table = page.getByTestId("tickets-table");
  await expectTableReady(table);
  await expect(page.getByText(`of ${TICKET_FIXTURE_COUNT}`)).toBeVisible();
  await expect(table.locator("tbody tr")).toHaveCount(50);
});

test("search filters tickets", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  const table = page.getByTestId("tickets-table");
  await expectTableReady(table);
  const search = page.locator("#tickets-search");
  await fillSearchInput(search, "JP-TK-80001");
  await applySearchAndWaitForRow(page, search, table, /q=JP-TK-80001/, "JP-TK-80001");
  await expect(table.getByText("JP-TK-80002")).not.toBeVisible();
});

test("document-type filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  const table = page.getByTestId("tickets-table");
  await expectTableReady(table);
  const documentType = page.locator("#filter-document-type");
  await selectAndApplyFilter(page, table, documentType, "Refund Document", /documentType=Refund/, "JP-TK-80005");
  await expect(table.getByText("JP-TK-80001")).not.toBeVisible();
});

test("channel filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  const table = page.getByTestId("tickets-table");
  await expectTableReady(table);
  const channel = page.locator("#filter-channel");
  await selectAndApplyFilter(page, table, channel, "One API", /channel=One/, "JP-TK-80007");
  await expect(table.getByText("JP-TK-80001")).not.toBeVisible();
});

test("airline filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  const table = page.getByTestId("tickets-table");
  await expectTableReady(table);
  const airline = page.locator("#filter-airline");
  await selectAndApplyFilter(page, table, airline, "Emirates", /airline=Emirates/, "JP-TK-80001");
  await expect(table.getByText("JP-TK-80002")).not.toBeVisible();
});

test("supplier filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  const table = page.getByTestId("tickets-table");
  await expectTableReady(table);
  const supplier = page.locator("#filter-supplier");
  await selectAndApplyFilter(page, table, supplier, "Duffel", /supplier=Duffel/, "JP-TK-80002");
  await expect(table.getByText("JP-TK-80001")).not.toBeVisible();
});

test("issue-status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  const table = page.getByTestId("tickets-table");
  await expectTableReady(table);
  const issueStatus = page.locator("#filter-issue-status");
  await selectAndApplyFilter(page, table, issueStatus, "Blocked", /issueStatus=Blocked/, "JP-TK-80028");
  await expect(table.getByText("JP-TK-80001")).not.toBeVisible();
});

test("fulfilment-status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  const table = page.getByTestId("tickets-table");
  await expectTableReady(table);
  const fulfilmentStatus = page.locator("#filter-fulfilment-status");
  await selectAndApplyFilter(
    page,
    table,
    fulfilmentStatus,
    "Refunded",
    /fulfilmentStatus=Refunded/,
    "JP-TK-80006",
  );
  await expect(table.getByText("JP-TK-80001")).not.toBeVisible();
});

test("payment-status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  const table = page.getByTestId("tickets-table");
  await expectTableReady(table);
  const paymentStatus = page.locator("#filter-payment-status");
  await selectAndApplyFilter(page, table, paymentStatus, "Refunded", /paymentStatus=Refunded/, "JP-TK-80005");
  await expect(table.getByText("JP-TK-80001")).not.toBeVisible();
});

test("refund-eligibility filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  const table = page.getByTestId("tickets-table");
  await expectTableReady(table);
  const refundEligibility = page.locator("#filter-refund-eligibility");
  await selectAndApplyFilter(
    page,
    table,
    refundEligibility,
    "Already Refunded",
    /refundEligibility=Already/,
    "JP-TK-80030",
  );
  await expect(table.getByText("JP-TK-80001")).not.toBeVisible();
});

test("void-status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  const table = page.getByTestId("tickets-table");
  await expectTableReady(table);
  const voidStatus = page.locator("#filter-void-status");
  await selectAndApplyFilter(page, table, voidStatus, "Voided", /voidStatus=Voided/, "JP-TK-80037");
  await expect(table.getByText("JP-TK-80001")).not.toBeVisible();
});

test("has-agent filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  const table = page.getByTestId("tickets-table");
  await expectTableReady(table);
  const hasAgent = page.locator("#filter-has-agent");
  await selectAndApplyFilter(page, table, hasAgent, "yes", /hasAgent=yes/, "JP-TK-80002");
  await expect(table.getByText("JP-TK-80001")).not.toBeVisible();
});

test("sorting changes ordering", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?sort=totalValue&direction=desc&pageSize=50", {
    waitUntil: "load",
  });
  const table = page.getByTestId("tickets-table");
  await expectTableReady(table);
  const firstRow = table.locator("tbody tr").first();
  await expect(firstRow).toContainText("JP-TK-80034");
});

test("pagination works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?pageSize=10", { waitUntil: "load" });
  const table = page.getByTestId("tickets-table");
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
  await page.goto("/testdash/tickets?q=JP-TK-80001&pageSize=50", { waitUntil: "load" });
  const table = page.getByTestId("tickets-table");
  await expect(table.getByText("JP-TK-80002")).not.toBeVisible();
  await page.getByRole("button", { name: "Clear all" }).click();
  await page.waitForURL((url) => !url.search.includes("q=JP-TK-80001"), { timeout: 15_000 });
  await expectTableReady(table);
  await expect(table.getByText("JP-TK-80002")).toBeVisible();
});

test("URL state survives reload", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?q=Emirates&documentType=E-Ticket", { waitUntil: "load" });
  await page.reload({ waitUntil: "load" });
  await expect(page).toHaveURL(/q=Emirates/);
  await expect(page).toHaveURL(/documentType=E-Ticket/);
  await expect(page.locator("#tickets-search")).toHaveValue("Emirates");
});

test("browser back and forward preserves state", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  const table = page.getByTestId("tickets-table");
  await expectTableReady(table);
  const search = page.locator("#tickets-search");
  await fillSearchInput(search, "JP-TK-80005");
  await applySearchAndWaitForRow(page, search, table, /q=JP-TK-80005/, "JP-TK-80005");
  await page.goBack();
  await page.waitForURL((url) => !url.search.includes("q=JP-TK-80005"), { timeout: 15_000 });
  await expectTableReady(table);
  await page.goForward();
  await page.waitForURL(/q=JP-TK-80005/, { timeout: 15_000 });
  await expect(table.getByText("JP-TK-80005")).toBeVisible();
});

test("desktop table renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  await expect(page.getByTestId("tickets-table")).toBeVisible();
  await expect(page.getByTestId("tickets-mobile-cards")).toBeHidden();
});

test("mobile cards render", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  const cards = page.getByTestId("tickets-mobile-cards");
  await expect(cards).toBeVisible();
  await expect(cards.getByText("JP-TK-80051")).toBeVisible();
});

test("drawer opens", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  const table = page.getByTestId("tickets-table");
  await expectTableReady(table);
  await table.getByRole("button", { name: /^JP-TK-/ }).first().click();
  await page.waitForURL(/id=JP-TK-/, { timeout: 15_000 });
  await expect(page.getByRole("dialog")).toBeVisible();
});

test("drawer content shows ticket details", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?id=JP-TK-80001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  const content = page.getByTestId("ticket-drawer-content");
  await expect(content).toContainText("JP-TK-80001");
  await expect(content).toContainText("Ayesha Khan");
  await expect(content).toContainText("157-XXXXXXX100");
});

test("drawer shows masked identifier", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?id=JP-TK-80001", { waitUntil: "load" });
  const content = page.getByTestId("ticket-drawer-content");
  await expect(content.getByText("157-XXXXXXX100")).toBeVisible();
});

test("drawer shows informational-only notice", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?id=JP-TK-80001", { waitUntil: "load" });
  const content = page.getByTestId("ticket-drawer-content");
  await expect(content).toContainText("Informational preview only");
  await expect(content).toContainText("issue, reissue, exchange, void, and refund actions are not available");
});

test("drawer shows linked booking", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?id=JP-TK-80001", { waitUntil: "load" });
  const content = page.getByTestId("ticket-drawer-content");
  await expect(content).toContainText("JP-BK-10001");
});

test("drawer shows linked PNR/order", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?id=JP-TK-80001", { waitUntil: "load" });
  const content = page.getByTestId("ticket-drawer-content");
  await expect(content).toContainText("JP-PN-70001");
});

test("drawer shows linked customer", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?id=JP-TK-80001", { waitUntil: "load" });
  const content = page.getByTestId("ticket-drawer-content");
  await expect(content).toContainText("JP-CU-40001");
});

test("drawer shows linked agent", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?id=JP-TK-80002", { waitUntil: "load" });
  const content = page.getByTestId("ticket-drawer-content");
  await expect(content).toContainText("JP-AG-60001");
});

test("drawer shows linked supplier", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?id=JP-TK-80001", { waitUntil: "load" });
  const content = page.getByTestId("ticket-drawer-content");
  await expect(content).toContainText("JP-SU-50001");
});

test("drawer shows linked payments", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?id=JP-TK-80001", { waitUntil: "load" });
  const content = page.getByTestId("ticket-drawer-content");
  await expect(content).toContainText("JP-TX-20001");
});

test("drawer closes through close control", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?id=JP-TK-80001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  await closeDrawerWithButton(page, "Close ticket details", /id=JP-TK-80001/);
});

test("drawer closes using Escape", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?id=JP-TK-80001", { waitUntil: "load" });
  await closeDrawerWithEscape(page, /id=JP-TK-80001/);
});

test("invalid ticket ID does not crash", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?id=JP-TK-INVALID", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Tickets & Documents", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expect(page.getByRole("dialog")).toBeHidden();
  await expectTableReady(page.getByTestId("tickets-table"));
});

test("loading state renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?previewLoading=1", { waitUntil: "load" });
  await expect(page.getByLabel("Loading tickets")).toBeVisible();
});

test("empty filtered state renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?q=zzznomatchzzz", { waitUntil: "load" });
  await expect(page.getByText("No tickets or documents match your filters")).toBeVisible();
});

test("controlled error state renders and recovers", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?previewError=1", { waitUntil: "load" });
  await expect(page.getByText("Could not load tickets")).toBeVisible();
  await page.getByRole("button", { name: "Try again" }).click();
  await page.waitForURL((url) => !url.searchParams.has("previewError"), { timeout: 15_000 });
  await expect(page.getByRole("heading", { name: "Tickets & Documents", level: 1 })).toBeVisible({
    timeout: 30_000,
  });
  await expectTableReady(page.getByTestId("tickets-table"));
});

test("mobile view has no horizontal overflow", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  const overflow = await page.evaluate(() => {
    return document.documentElement.scrollWidth > document.documentElement.clientWidth;
  });
  expect(overflow).toBe(false);
});

test("invalid URL values do not crash", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto(
    "/testdash/tickets?documentType=invalid&sort=notafield&pageSize=999&page=-1&direction=sideways",
    { waitUntil: "load" },
  );
  await expect(page.getByRole("heading", { name: "Tickets & Documents", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expect(page.getByTestId("tickets-table")).toBeVisible();
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
