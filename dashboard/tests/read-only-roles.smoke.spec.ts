import { test, expect } from "@playwright/test";
import { getRolesModule } from "@/services/role-service";
import { closeDrawerWithEscape } from "./helpers";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/admin/dashboard/users/roles", { timeout: 120_000 })).ok()).toBeTruthy();
});

const baseQuery = {
  search: "",
  category: "all" as const,
  status: "all" as const,
  roleType: "all" as const,
  protected: "all" as const,
  risk: "all" as const,
  validationState: "all" as const,
  channelScope: "all" as const,
  assignedState: "all" as const,
  page: 1,
  pageSize: 25,
  sort: "name" as const,
  direction: "asc" as const,
  selected: null,
  compareA: null,
  compareB: null,
  matrixDomain: "",
  matrixRole: "",
  state: "",
  previewError: false,
  previewLoading: false,
  previewEmpty: false,
};

test("fixture roles module loads", async () => {
  const result = await getRolesModule(baseQuery);
  expect(result.table.rows.length).toBeGreaterThan(0);
});

test("roles source notice", async ({ page }) => {
  await page.goto("/admin/dashboard/users/roles?dataSourcePreview=fixture", { waitUntil: "load" });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("roles table at 1280px", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/users/roles", { waitUntil: "load" });
  await expect(page.getByTestId("roles-table")).toBeVisible({ timeout: 60_000 });
});

test("roles filter URL sync", async ({ page }) => {
  await page.goto("/admin/dashboard/users/roles?roleType=system", { waitUntil: "load" });
  await expect(page).toHaveURL(/roleType=system/);
});

test("roles drawer deep link", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/users/roles?selected=JP-ROL-0001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 30_000 });
});

test("roles drawer Escape focus", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/users/roles?selected=JP-ROL-0001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 30_000 });
  await closeDrawerWithEscape(page, /selected=JP-ROL-0001/);
});

test("roles matrix responsive at 1024px", async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto("/admin/dashboard/users/roles", { waitUntil: "load" });
  await expect(page.getByTestId("role-permission-matrix").first()).toBeVisible({ timeout: 60_000 });
});

test("roles live read-only notice", async ({ page }) => {
  await page.goto("/admin/dashboard/users/roles?dataSourcePreview=live", { waitUntil: "load" });
  await expect(page.getByTestId("live-readonly-notice")).toBeVisible();
});

test("protected role metadata in fixture", async () => {
  const result = await getRolesModule(baseQuery);
  expect(result.table.rows.some((r) => r.isProtected)).toBeTruthy();
});
