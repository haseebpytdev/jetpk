import { test, expect } from "@playwright/test";
import { getAgentsPage } from "@/services/agent-service";
import { containsSensitiveKeys } from "@/lib/read-only/sensitive-fields";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/testdash/agents", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("fixture agents page loads", async () => {
  const result = await getAgentsPage({
    q: "",
    accountStatus: "all",
    verificationStatus: "all",
    commercialStatus: "all",
    settlementStatus: "all",
    agentType: "all",
    city: "",
    countryRegion: "",
    hasOutstandingBalance: "all",
    hasPendingCommission: "all",
    hasBookings: "all",
    activityFrom: "",
    activityTo: "",
    page: 1,
    pageSize: 20,
    sort: "agentName",
    direction: "asc",
    selectedId: null,
    previewError: false,
    previewLoading: false,
  });
  expect(result.agents.length).toBeGreaterThan(0);
  expect(containsSensitiveKeys(result)).toBe(false);
});

test("fixture source notice on agents", async ({ page }) => {
  await page.goto("/testdash/agents?dataSourcePreview=fixture", { waitUntil: "load" });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("agents table at 1280px", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents", { waitUntil: "load" });
  await expect(page.getByTestId("agents-table")).toBeVisible({ timeout: 60_000 });
});

test("agents cards at 1024px", async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto("/testdash/agents", { waitUntil: "load" });
  await expect(page.getByTestId("agents-mobile-cards")).toBeVisible({ timeout: 60_000 });
});

test("agent filter URL sync", async ({ page }) => {
  await page.goto("/testdash/agents?accountStatus=Active&sort=bookingCount", { waitUntil: "load" });
  await expect(page).toHaveURL(/accountStatus=Active/);
});

test("agents drawer opens", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/agents?id=JP-AG-60001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 30_000 });
  await expect(page.getByTestId("agent-drawer-content")).toBeVisible();
});

test("agents forbidden preview", async ({ page }) => {
  await page.goto("/testdash/agents?dataSourcePreview=forbidden", { waitUntil: "load" });
  await expect(page.getByText(/Access denied/i)).toBeVisible();
});

test("agents live read-only notice", async ({ page }) => {
  await page.goto("/testdash/agents?dataSourcePreview=live", { waitUntil: "load" });
  await expect(page.getByTestId("live-readonly-notice")).toBeVisible();
});

test("agents stale preview", async ({ page }) => {
  await page.goto("/testdash/agents?dataSourcePreview=stale", { waitUntil: "load" });
  await expect(page.getByTestId("stale-data-notice")).toBeVisible();
});

test("agents error preview", async ({ page }) => {
  await page.goto("/testdash/agents?dataSourcePreview=error", { waitUntil: "load" });
  await expect(page.getByText(/Unable to load data/i)).toBeVisible();
});

test("agents no overflow at 390px", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/testdash/agents", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  expect(overflow).toBeFalsy();
});

test("agents pagination URL sync", async ({ page }) => {
  await page.goto("/testdash/agents?page=2&pageSize=10", { waitUntil: "load" });
  await expect(page).toHaveURL(/page=2/);
});
