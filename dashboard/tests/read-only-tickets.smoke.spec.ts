import { test, expect } from "@playwright/test";
import { closeDrawerWithEscape } from "./helpers";
import { getTicketsPage } from "@/services/ticket-service";
import { containsSensitiveKeys } from "@/lib/read-only/sensitive-fields";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/testdash/tickets", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("fixture tickets page loads", async () => {
  const result = await getTicketsPage({
    q: "",
    documentType: "all",
    channel: "all",
    airline: "",
    supplier: "",
    issueStatus: "all",
    fulfilmentStatus: "all",
    paymentStatus: "all",
    refundEligibility: "all",
    voidStatus: "all",
    hasAgent: "all",
    travelFrom: "",
    travelTo: "",
    issueFrom: "",
    issueTo: "",
    page: 1,
    pageSize: 20,
    sort: "newest",
    direction: "desc",
    selectedId: null,
    previewError: false,
    previewLoading: false,
  });
  expect(result.tickets.length).toBeGreaterThan(0);
  expect(containsSensitiveKeys(result)).toBe(false);
});

test("fixture source notice on tickets", async ({ page }) => {
  await page.goto("/testdash/tickets?dataSourcePreview=fixture", { waitUntil: "load" });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("tickets table at 1280px", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  await expect(page.getByTestId("tickets-table")).toBeVisible({ timeout: 60_000 });
});

test("tickets cards at 1024px", async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  await expect(page.getByTestId("tickets-mobile-cards")).toBeVisible({ timeout: 60_000 });
});

test("ticket filter URL sync", async ({ page }) => {
  await page.goto("/testdash/tickets?issueStatus=Issued&documentType=E-Ticket", { waitUntil: "load" });
  await expect(page).toHaveURL(/issueStatus=Issued/);
});

test("ticket drawer opens", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?id=JP-TK-80001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 30_000 });
  await expect(page.getByTestId("ticket-drawer-content")).toBeVisible();
});

test("ticket drawer closes with Escape", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/tickets?id=JP-TK-80001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 30_000 });
  await closeDrawerWithEscape(page, /id=JP-TK-80001/);
});

test("tickets forbidden preview", async ({ page }) => {
  await page.goto("/testdash/tickets?dataSourcePreview=forbidden", { waitUntil: "load" });
  await expect(page.getByText(/Access denied/i)).toBeVisible();
});

test("tickets live read-only notice", async ({ page }) => {
  await page.goto("/testdash/tickets?dataSourcePreview=live", { waitUntil: "load" });
  await expect(page.getByTestId("live-readonly-notice")).toBeVisible();
});

test("tickets masked external ids in fixtures", async () => {
  const result = await getTicketsPage({
    q: "",
    documentType: "all",
    channel: "all",
    airline: "",
    supplier: "",
    issueStatus: "all",
    fulfilmentStatus: "all",
    paymentStatus: "all",
    refundEligibility: "all",
    voidStatus: "all",
    hasAgent: "all",
    travelFrom: "",
    travelTo: "",
    issueFrom: "",
    issueTo: "",
    page: 1,
    pageSize: 10,
    sort: "newest",
    direction: "desc",
    selectedId: null,
    previewError: false,
    previewLoading: false,
  });
  const first = result.tickets[0];
  expect(first.maskedExternalId).toBeTruthy();
  expect(first.maskedExternalId).not.toMatch(/^\d{13}$/);
});

test("tickets no overflow at 390px", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  expect(overflow).toBeFalsy();
});
