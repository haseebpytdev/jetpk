import { test, expect } from "@playwright/test";
import { getUsersModule } from "@/services/user-service";
import { containsSensitiveKeys } from "@/lib/read-only/sensitive-fields";
import { closeDrawerWithEscape } from "./helpers";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/admin/dashboard/users", { timeout: 120_000 })).ok()).toBeTruthy();
});

const baseQuery = {
  search: "",
  status: "all" as const,
  userType: "all" as const,
  department: "",
  role: "",
  mfa: "all" as const,
  verification: "all" as const,
  securityState: "all" as const,
  validationState: "all" as const,
  page: 1,
  pageSize: 25,
  sort: "fullName" as const,
  direction: "asc" as const,
  selected: null,
  state: "",
  previewError: false,
  previewLoading: false,
  previewEmpty: false,
};

test("fixture users module loads", async () => {
  const result = await getUsersModule(baseQuery);
  expect(result.table.rows.length).toBeGreaterThan(0);
  expect(containsSensitiveKeys(result)).toBe(false);
});

test("fixture source notice on users", async ({ page }) => {
  await page.goto("/admin/dashboard/users?dataSourcePreview=fixture", { waitUntil: "load" });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("users table at 1280px", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  await expect(page.getByTestId("users-table")).toBeVisible({ timeout: 60_000 });
});

test("users mobile cards at 1024px", async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  await expect(page.getByTestId("users-table")).toBeVisible({ timeout: 60_000 });
});

test("users filter URL sync", async ({ page }) => {
  await page.goto("/admin/dashboard/users?status=active", { waitUntil: "load" });
  await expect(page).toHaveURL(/status=active/);
});

test("users drawer deep link", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/users?selected=JP-USR-0001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 30_000 });
});

test("users drawer Escape closes", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/users?selected=JP-USR-0001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 30_000 });
  await closeDrawerWithEscape(page, /selected=JP-USR-0001/);
});

test("users forbidden preview", async ({ page }) => {
  await page.goto("/admin/dashboard/users?dataSourcePreview=forbidden", { waitUntil: "load" });
  await expect(page.getByText(/Access denied/i)).toBeVisible();
});

test("users no sensitive fields in fixture", async () => {
  const result = await getUsersModule(baseQuery);
  const payload = JSON.stringify(result);
  expect(payload).not.toContain("password_hash");
  expect(payload).not.toContain("mfa_secret");
});

test("users live read-only notice", async ({ page }) => {
  await page.goto("/admin/dashboard/users?dataSourcePreview=live", { waitUntil: "load" });
  await expect(page.getByTestId("live-readonly-notice")).toBeVisible();
});

test("users no overflow at 390px", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  expect(overflow).toBeFalsy();
});
