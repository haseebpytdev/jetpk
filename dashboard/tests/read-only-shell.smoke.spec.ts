import { test, expect } from "@playwright/test";
import { getDashboardSession } from "@/services/session-service";
import { resolveDataSourceMode } from "@/lib/read-only/data-source";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/admin/dashboard", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("fixture session summary resolves in preview mode", async () => {
  expect(resolveDataSourceMode()).toBe("fixture");
  const session = await getDashboardSession();
  expect(session.displayName).toBeTruthy();
  expect(session.permissions).toContain("dashboard.view");
  expect(session.permissions).not.toContain("password");
});

test("shell renders authenticated fixture profile", async ({ page }) => {
  await page.goto("/admin/dashboard", { waitUntil: "load" });
  await expect(page.getByText("Preview Admin").first()).toBeVisible({ timeout: 60_000 });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("live read-only notice via preview gate", async ({ page }) => {
  await page.goto("/admin/dashboard?dataSourcePreview=live", { waitUntil: "load" });
  await expect(page.getByTestId("live-readonly-notice")).toBeVisible();
});

test("unauthorized state via preview gate", async ({ page }) => {
  await page.goto("/admin/dashboard?dataSourcePreview=unauthorized", { waitUntil: "load" });
  await expect(page.getByText(/Sign in required/i)).toBeVisible();
});

test("forbidden state via preview gate", async ({ page }) => {
  await page.goto("/admin/dashboard?dataSourcePreview=forbidden", { waitUntil: "load" });
  await expect(page.getByText(/Access denied/i)).toBeVisible();
});

test("unavailable state via preview gate", async ({ page }) => {
  await page.goto("/admin/dashboard?dataSourcePreview=unavailable", { waitUntil: "load" });
  await expect(page.getByText(/Service unavailable/i)).toBeVisible();
  await expect(page.getByText(/not shown as a fallback/i)).toBeVisible();
});

test("stale state via preview gate", async ({ page }) => {
  await page.goto("/admin/dashboard?dataSourcePreview=stale", { waitUntil: "load" });
  await expect(page.getByTestId("stale-data-notice")).toBeVisible();
});

test("session shell consistent at 1280px", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard", { waitUntil: "load" });
  await expect(page.locator(".max-w-\\[1600px\\]").first()).toBeVisible();
});

test("session shell consistent at 390px", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Dashboard", level: 1 })).toBeVisible();
});

test("preview mode badge present for regression", async ({ page }) => {
  await page.goto("/admin/dashboard", { waitUntil: "load" });
  await expect(page.getByTestId("preview-mode-badge")).toHaveText(/Preview mode/i);
});
