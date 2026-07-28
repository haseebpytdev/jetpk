import { test, expect } from "@playwright/test";
import { getPermissionsModule } from "@/services/permission-service";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/testdash/users/permissions", { timeout: 120_000 })).ok()).toBeTruthy();
});

const baseQuery = {
  search: "",
  domain: "all" as const,
  action: "all" as const,
  risk: "all" as const,
  effect: "all" as const,
  scope: "all" as const,
  prerequisite: "all" as const,
  assignedState: "all" as const,
  validationState: "all" as const,
  page: 1,
  pageSize: 25,
  sort: "key" as const,
  direction: "asc" as const,
  selected: null,
  state: "",
  previewError: false,
  previewLoading: false,
  previewEmpty: false,
};

test("fixture permissions module loads", async () => {
  const result = await getPermissionsModule(baseQuery);
  expect(result.table.rows.length).toBeGreaterThan(0);
});

test("permissions source notice", async ({ page }) => {
  await page.goto("/testdash/users/permissions?dataSourcePreview=fixture", { waitUntil: "load" });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("permissions filter state URL sync", async ({ page }) => {
  await page.goto("/testdash/users/permissions?domain=bookings", { waitUntil: "load" });
  await expect(page).toHaveURL(/domain=bookings/);
});

test("permissions table at 1280px", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/users/permissions", { waitUntil: "load" });
  await expect(page.getByTestId("permissions-table")).toBeVisible({ timeout: 60_000 });
});

test("permissions drawer deep link", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/users/permissions?selected=JP-PRM-0001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 30_000 });
});

test("high-risk permission metadata in fixture", async () => {
  const result = await getPermissionsModule(baseQuery);
  expect(result.table.rows.some((p) => p.isHighRisk)).toBeTruthy();
});

test("permissions live read-only notice", async ({ page }) => {
  await page.goto("/testdash/users/permissions?dataSourcePreview=live", { waitUntil: "load" });
  await expect(page.getByTestId("live-readonly-notice")).toBeVisible();
});

test("permissions no overflow at 390px", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/testdash/users/permissions", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  expect(overflow).toBeFalsy();
});
