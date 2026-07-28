import { test, expect } from "@playwright/test";
import { PNR_FIXTURE_COUNT } from "../mocks/pnr-fixtures";
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

test("pnrs route loads", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "PNRs & Orders", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expect(page.getByTestId("pnrs-table")).toBeVisible();
});

test("navigation link works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash", { waitUntil: "load" });
  await page.getByRole("link", { name: "PNRs", exact: true }).click();
  await page.waitForURL(/\/testdash\/pnrs/, { timeout: 15_000 });
  await expect(page.getByRole("heading", { name: "PNRs & Orders", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
});

for (const viewport of viewports.filter((v) => v.width >= 1280)) {
  test(`pnrs route renders at desktop ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto("/testdash/pnrs", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "PNRs & Orders", level: 1 })).toBeVisible({
      timeout: 60_000,
    });
    await expect(page.getByTestId("pnrs-table")).toBeVisible();
    await expect(page.getByText(/Preview data/i).first()).toBeVisible();
  });
}

for (const viewport of viewports.filter((v) => v.width < 768)) {
  test(`pnrs route renders at mobile ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto("/testdash/pnrs", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "PNRs & Orders", level: 1 })).toBeVisible({
      timeout: 60_000,
    });
    await expect(page.getByTestId("pnrs-mobile-cards")).toBeVisible();
  });
}

test("heading and summaries render", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs", { waitUntil: "load" });
  const summary = page.getByLabel("PNR summary metrics");
  await expect(summary).toBeVisible();
  await expect(summary.getByText("Total records", { exact: true })).toBeVisible();
  await expect(summary.getByText("GDS PNRs", { exact: true })).toBeVisible();
  await expect(summary.getByText("NDC orders", { exact: true })).toBeVisible();
});

test("deterministic fixture count", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?pageSize=50", { waitUntil: "load" });
  const table = page.getByTestId("pnrs-table");
  await expectTableReady(table);
  await expect(table.locator("tbody tr")).toHaveCount(PNR_FIXTURE_COUNT);
});

test("search filters pnrs", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs", { waitUntil: "load" });
  const table = page.getByTestId("pnrs-table");
  await expectTableReady(table);
  const search = page.locator("#pnrs-search");
  await fillSearchInput(search, "JP-PN-70001");
  await applySearchAndWaitForRow(page, search, table, /q=JP-PN-70001/, "JP-PN-70001");
  await expect(table.getByText("JP-PN-70002")).not.toBeVisible();
});

test("reference-type filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs", { waitUntil: "load" });
  const table = page.getByTestId("pnrs-table");
  await expectTableReady(table);
  const referenceType = page.locator("#filter-reference-type");
  await selectAndApplyFilter(page, table, referenceType, "GDS PNR", /referenceType=GDS/, "ABC123");
  await expect(table.getByText("NDC-QR-7K2M9")).not.toBeVisible();
});

test("channel filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs", { waitUntil: "load" });
  const table = page.getByTestId("pnrs-table");
  await expectTableReady(table);
  const channel = page.locator("#filter-channel");
  await selectAndApplyFilter(page, table, channel, "Sabre GDS", /channel=Sabre/, "ABC123");
  await expect(table.getByText("NDC-QR-7K2M9")).not.toBeVisible();
});

test("supplier filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs", { waitUntil: "load" });
  const table = page.getByTestId("pnrs-table");
  await expectTableReady(table);
  const supplier = page.locator("#filter-supplier");
  await selectAndApplyFilter(page, table, supplier, "Duffel", /supplier=Duffel/, "NDC-QR-7K2M9");
  await expect(table.getByText("ABC123")).not.toBeVisible();
});

test("lifecycle-status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs", { waitUntil: "load" });
  const table = page.getByTestId("pnrs-table");
  await expectTableReady(table);
  const lifecycle = page.locator("#filter-lifecycle-status");
  await selectAndApplyFilter(
    page,
    table,
    lifecycle,
    "Review Required",
    /lifecycleStatus=Review/,
    "MAN-EM-44219",
  );
  await expect(table.getByText("ABC123")).not.toBeVisible();
});

test("fulfilment-status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs", { waitUntil: "load" });
  const table = page.getByTestId("pnrs-table");
  await expectTableReady(table);
  const fulfilment = page.locator("#filter-fulfilment-status");
  await selectAndApplyFilter(
    page,
    table,
    fulfilment,
    "Pending",
    /fulfilmentStatus=Pending/,
    "NDC-QR-7K2M9",
  );
  await expect(table.getByText("ABC123")).not.toBeVisible();
});

test("ticketing-status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs", { waitUntil: "load" });
  const table = page.getByTestId("pnrs-table");
  await expectTableReady(table);
  const ticketing = page.locator("#filter-ticketing-status");
  await selectAndApplyFilter(
    page,
    table,
    ticketing,
    "Ticketing Blocked",
    /ticketingStatus=Ticketing/,
    "WY9K2P",
  );
  await expect(table.getByText("NDC-QR-7K2M9")).not.toBeVisible();
});

test("payment-status filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs", { waitUntil: "load" });
  const table = page.getByTestId("pnrs-table");
  await expectTableReady(table);
  const payment = page.locator("#filter-payment-status");
  await selectAndApplyFilter(page, table, payment, "Paid", /paymentStatus=Paid/, "ABC123");
  await expect(table.getByText("IATI-AB-33017")).not.toBeVisible();
});

test("has-agent filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs", { waitUntil: "load" });
  const table = page.getByTestId("pnrs-table");
  await expectTableReady(table);
  const hasAgent = page.locator("#filter-has-agent");
  await selectAndApplyFilter(page, table, hasAgent, "yes", /hasAgent=yes/, "DEF456");
  await expect(table.getByText("ABC123")).not.toBeVisible();
});

test("review-required filter works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs", { waitUntil: "load" });
  const table = page.getByTestId("pnrs-table");
  await expectTableReady(table);
  const review = page.locator("#filter-review-required");
  await selectAndApplyFilter(page, table, review, "yes", /reviewRequired=yes/, "MAN-EM-44219");
  await expect(table.getByText("ABC123")).not.toBeVisible();
});

test("sorting changes ordering", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?sort=bookingValue&direction=desc&pageSize=50", {
    waitUntil: "load",
  });
  const table = page.getByTestId("pnrs-table");
  await expectTableReady(table);
  const firstRow = table.locator("tbody tr").first();
  await expect(firstRow).toContainText("JP-PN-70016");
});

test("pagination works", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?pageSize=10", { waitUntil: "load" });
  const table = page.getByTestId("pnrs-table");
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
  await page.goto("/testdash/pnrs?q=JP-PN-70001&pageSize=50", { waitUntil: "load" });
  const table = page.getByTestId("pnrs-table");
  await expect(table.getByText("JP-PN-70002")).not.toBeVisible();
  await page.getByRole("button", { name: "Clear all" }).click();
  await page.waitForURL((url) => !url.search.includes("q=JP-PN-70001"), { timeout: 15_000 });
  await expectTableReady(table);
  await expect(table.getByText("JP-PN-70002")).toBeVisible();
});

test("URL state survives reload", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?q=ABC123&referenceType=GDS+PNR", { waitUntil: "load" });
  await page.reload({ waitUntil: "load" });
  await expect(page).toHaveURL(/q=ABC123/);
  await expect(page).toHaveURL(/referenceType=GDS/);
  await expect(page.locator("#pnrs-search")).toHaveValue("ABC123");
});

test("browser back and forward preserves state", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs", { waitUntil: "load" });
  const table = page.getByTestId("pnrs-table");
  await expectTableReady(table);
  const search = page.locator("#pnrs-search");
  await fillSearchInput(search, "JP-PN-70005");
  await applySearchAndWaitForRow(page, search, table, /q=JP-PN-70005/, "JP-PN-70005");
  await page.goBack();
  await page.waitForURL((url) => !url.search.includes("q=JP-PN-70005"), { timeout: 15_000 });
  await expectTableReady(table);
  await page.goForward();
  await page.waitForURL(/q=JP-PN-70005/, { timeout: 15_000 });
  await expect(table.getByText("JP-PN-70005")).toBeVisible();
});

test("desktop table renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs", { waitUntil: "load" });
  await expect(page.getByTestId("pnrs-table")).toBeVisible();
  await expect(page.getByTestId("pnrs-mobile-cards")).toBeHidden();
});

test("mobile cards render", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/testdash/pnrs", { waitUntil: "load" });
  const cards = page.getByTestId("pnrs-mobile-cards");
  await expect(cards).toBeVisible();
  await expect(cards.getByText("ABC123")).toBeVisible();
});

test("drawer opens", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs", { waitUntil: "load" });
  const table = page.getByTestId("pnrs-table");
  await expectTableReady(table);
  await table.getByRole("button", { name: /^JP-PN-/ }).first().click();
  await page.waitForURL(/id=JP-PN-/, { timeout: 15_000 });
  await expect(page.getByRole("dialog")).toBeVisible();
});

test("drawer content shows pnr details", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?id=JP-PN-70001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  const content = page.getByTestId("pnr-drawer-content");
  await expect(content).toContainText("JP-PN-70001");
  await expect(content).toContainText("ABC123");
  await expect(content).toContainText("Ayesha Khan");
});

test("drawer shows GDS versus NDC distinction", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?id=JP-PN-70001", { waitUntil: "load" });
  const gdsContent = page.getByTestId("pnr-drawer-content");
  await expect(gdsContent).toContainText("traditional GDS passenger name record");
  await expect(gdsContent).toContainText("GDS PNR");

  await page.goto("/testdash/pnrs?id=JP-PN-70002", { waitUntil: "load" });
  const ndcContent = page.getByTestId("pnr-drawer-content");
  await expect(ndcContent).toContainText("NDC order reference");
  await expect(ndcContent).toContainText("not a traditional GDS PNR");
});

test("drawer shows safe ticketing limitation note", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?id=JP-PN-70040", { waitUntil: "load" });
  const content = page.getByTestId("pnr-drawer-content");
  await expect(content.getByTestId("gds-ticketing-limitation-note")).toBeVisible();
  await expect(content).toContainText("printer designation is pending");
  await expect(content).not.toContainText("LNIATA");
});

test("drawer shows abstract cancellation eligibility", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?id=JP-PN-70001", { waitUntil: "load" });
  const content = page.getByTestId("pnr-drawer-content");
  await expect(content.getByText("Cancellation eligibility")).toBeVisible();
  await expect(content).toContainText("Display-only fixture status");
});

test("drawer shows linked booking", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?id=JP-PN-70001", { waitUntil: "load" });
  const content = page.getByTestId("pnr-drawer-content");
  await expect(content).toContainText("JP-BK-10001");
});

test("drawer shows linked customer", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?id=JP-PN-70001", { waitUntil: "load" });
  const content = page.getByTestId("pnr-drawer-content");
  await expect(content).toContainText("JP-CU-40001");
});

test("drawer shows linked agent", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?id=JP-PN-70002", { waitUntil: "load" });
  const content = page.getByTestId("pnr-drawer-content");
  await expect(content).toContainText("JP-AG-60001");
});

test("drawer shows linked supplier", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?id=JP-PN-70001", { waitUntil: "load" });
  const content = page.getByTestId("pnr-drawer-content");
  await expect(content).toContainText("Sabre");
});

test("drawer shows linked payments", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?id=JP-PN-70001", { waitUntil: "load" });
  const content = page.getByTestId("pnr-drawer-content");
  await expect(content).toContainText("JP-TX-20001");
});

test("drawer shows linked tickets", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?id=JP-PN-70001", { waitUntil: "load" });
  const content = page.getByTestId("pnr-drawer-content");
  await expect(content).toContainText("JP-TK-80001");
});

test("drawer closes through close control", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?id=JP-PN-70001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  await closeDrawerWithButton(page, "Close PNR details", /id=JP-PN-70001/);
});

test("drawer closes using Escape", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?id=JP-PN-70001", { waitUntil: "load" });
  await closeDrawerWithEscape(page, /id=JP-PN-70001/);
});

test("invalid record ID does not crash", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?id=JP-PN-INVALID", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "PNRs & Orders", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expect(page.getByRole("dialog")).toBeHidden();
  await expectTableReady(page.getByTestId("pnrs-table"));
});

test("loading state renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?previewLoading=1", { waitUntil: "load" });
  await expect(page.getByLabel("Loading PNRs and orders")).toBeVisible();
});

test("empty filtered state renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?q=zzznomatchzzz", { waitUntil: "load" });
  await expect(page.getByText("No PNRs or orders match your filters")).toBeVisible();
});

test("controlled error state renders and recovers", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/pnrs?previewError=1", { waitUntil: "load" });
  await expect(page.getByText("Could not load PNRs and orders")).toBeVisible();
  await page.getByRole("button", { name: "Try again" }).click();
  await page.waitForURL((url) => !url.searchParams.has("previewError"), { timeout: 15_000 });
  await expect(page.getByRole("heading", { name: "PNRs & Orders", level: 1 })).toBeVisible({
    timeout: 30_000,
  });
  await expectTableReady(page.getByTestId("pnrs-table"));
});

test("mobile view has no horizontal overflow", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/testdash/pnrs", { waitUntil: "load" });
  const overflow = await page.evaluate(() => {
    return document.documentElement.scrollWidth > document.documentElement.clientWidth;
  });
  expect(overflow).toBe(false);
});

test("invalid URL values do not crash", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto(
    "/testdash/pnrs?referenceType=invalid&sort=notafield&pageSize=999&page=-1&direction=sideways",
    { waitUntil: "load" },
  );
  await expect(page.getByRole("heading", { name: "PNRs & Orders", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expect(page.getByTestId("pnrs-table")).toBeVisible();
});

test("bookings route remains functional", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/bookings", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Bookings", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expectTableReady(page.getByTestId("bookings-table"));
});

test("customers route remains functional", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/customers", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Customers", level: 1 })).toBeVisible({
    timeout: 60_000,
  });
  await expectTableReady(page.getByTestId("customers-table"));
});
