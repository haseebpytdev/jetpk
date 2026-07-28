import { test, expect } from "@playwright/test";
import {
  applyRolePermissionPreview,
  applyRolesFiltersAndWait,
  clickRolesSortHeader,
  closeDrawerWithEscape,
  expectCmsReady,
  expectReportsReady,
  expectRolesReady,
  expectTableReady,
  expectUsersReady,
  fillSearchInput,
  resetRolePermissionPreview,
  resetRolesFiltersAndWait,
  selectAndApplyFilter,
} from "./helpers";

const ROLE_ID = "JP-ROL-0001";
const PROTECTED_ROLE = "JP-ROL-0009";

test.beforeAll(async ({ request }) => {
  await request.get("/admin/dashboard/users/roles", { timeout: 120_000 });
});

test("roles route renders", async ({ page }) => {
  await page.goto("/admin/dashboard/users/roles", { waitUntil: "load" });
  await expectRolesReady(page);
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("navigation includes Roles section link", async ({ page }) => {
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  const nav = page.getByRole("navigation", { name: "Users sections" });
  await expect(nav.getByRole("link", { name: "Roles", exact: true })).toBeVisible();
});

test("summary metrics derive from fixtures", async ({ page }) => {
  await page.goto("/admin/dashboard/users/roles", { waitUntil: "load" });
  const grid = page.getByTestId("roles-metric-grid");
  await expect(grid).toBeVisible();
  await expect(grid.getByText("Total roles")).toBeVisible();
  await expect(grid.getByRole("heading", { name: "14" })).toBeVisible();
});

test("search works", async ({ page }) => {
  await page.goto("/admin/dashboard/users/roles", { waitUntil: "load" });
  await expectRolesReady(page);
  const search = page.locator("#roles-search");
  await fillSearchInput(search, "Super");
  await applyRolesFiltersAndWait(page, /search=Super/);
  const table = page.getByTestId("roles-table");
  await expectTableReady(table);
  await expect(table.getByText("Super Administrator")).toBeVisible();
});

test("category filter works", async ({ page }) => {
  await page.goto("/admin/dashboard/users/roles", { waitUntil: "load" });
  const table = page.getByTestId("roles-table");
  await selectAndApplyFilter(page, table, page.locator("#roles-category"), "audit", /category=audit/, "Read-only Auditor");
});

test("status filter works", async ({ page }) => {
  await page.goto("/admin/dashboard/users/roles", { waitUntil: "load" });
  const table = page.getByTestId("roles-table");
  await selectAndApplyFilter(page, table, page.locator("#roles-status"), "draft", /status=draft/, "JP-ROL-0011");
});

test("system role type filter works", async ({ page }) => {
  await page.goto("/admin/dashboard/users/roles", { waitUntil: "load" });
  const table = page.getByTestId("roles-table");
  await selectAndApplyFilter(page, table, page.locator("#roles-type"), "system", /roleType=system/, "Super Administrator");
});

test("protected filter works", async ({ page }) => {
  await page.goto("/admin/dashboard/users/roles", { waitUntil: "load" });
  const table = page.getByTestId("roles-table");
  await selectAndApplyFilter(page, table, page.locator("#roles-protected"), "protected", /protected=protected/, PROTECTED_ROLE);
});

test("high-risk filter works", async ({ page }) => {
  await page.goto("/admin/dashboard/users/roles", { waitUntil: "load" });
  const table = page.getByTestId("roles-table");
  await selectAndApplyFilter(page, table, page.locator("#roles-risk"), "highRisk", /risk=highRisk/, ROLE_ID);
});

test("validation filter works", async ({ page }) => {
  await page.goto("/admin/dashboard/users/roles", { waitUntil: "load" });
  const table = page.getByTestId("roles-table");
  await selectAndApplyFilter(page, table, page.locator("#roles-validation"), "warning", /validationState=warning/, "Overly Broad Role");
});

test("invalid URL values fall back safely", async ({ page }) => {
  await page.goto("/admin/dashboard/users/roles?status=invalid&sort=notreal&page=-1", { waitUntil: "load" });
  await expectRolesReady(page);
  await expect(page.getByTestId("roles-metric-grid").getByRole("heading", { name: "14" })).toBeVisible();
});

test("reset filters works", async ({ page }) => {
  await page.goto("/admin/dashboard/users/roles?category=audit", { waitUntil: "load" });
  await resetRolesFiltersAndWait(page, /category=audit/);
});

test("sorting works", async ({ page }) => {
  await page.goto("/admin/dashboard/users/roles", { waitUntil: "load" });
  await clickRolesSortHeader(page, "Assigned users", "assignedUserCount");
});

test("pagination works", async ({ page }) => {
  await page.goto("/admin/dashboard/users/roles?pageSize=10", { waitUntil: "load" });
  await expectRolesReady(page);
  const pagination = page.getByRole("navigation", { name: "Roles pagination" });
  const next = pagination.getByRole("button", { name: "Next page" });
  await expect(next).toBeEnabled();
  for (let attempt = 0; attempt < 3; attempt += 1) {
    try {
      await Promise.all([page.waitForURL(/page=2/, { timeout: 30_000, waitUntil: "commit" }), next.click()]);
      await expect(page).toHaveURL(/page=2/);
      return;
    } catch (error) {
      if (attempt === 2) {
        throw error;
      }
      await expectRolesReady(page);
    }
  }
});

test("desktop table renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/users/roles", { waitUntil: "load" });
  await expect(page.getByTestId("roles-table")).toBeVisible();
});

test("mobile cards render", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/users/roles", { waitUntil: "load" });
  await expect(page.getByTestId("roles-mobile-cards")).toBeVisible();
});

test("permission matrix renders", async ({ page }) => {
  await page.goto("/admin/dashboard/users/roles", { waitUntil: "load" });
  await expect(page.getByTestId("role-permission-matrix")).toBeVisible();
  await expect(page.locator("#matrix-domain-filter")).toBeVisible();
});

test("drawer opens", async ({ page }) => {
  await page.goto(`/admin/dashboard/users/roles?selected=${ROLE_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("role-detail-drawer")).toBeVisible();
});

test("drawer closes with Escape", async ({ page }) => {
  await page.goto(`/admin/dashboard/users/roles?selected=${ROLE_ID}`, { waitUntil: "load" });
  await closeDrawerWithEscape(page, /selected=JP-ROL-0001/);
});

test("focus returns after drawer close", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/users/roles", { waitUntil: "load" });
  await expectRolesReady(page);
  const table = page.getByTestId("roles-table");
  await expectTableReady(table);
  const trigger = table.getByRole("button", { name: ROLE_ID });
  await trigger.focus();
  await trigger.click();
  await expect(page).toHaveURL(new RegExp(`selected=${ROLE_ID}`), { timeout: 30_000 });
  await expect(page.getByRole("dialog")).toBeVisible();
  await expect(page.getByTestId("role-detail-drawer")).toBeVisible();
  await closeDrawerWithEscape(page, /selected=JP-ROL-0001/);
  await expect(trigger).toBeFocused();
});

test("selected role deep-link works", async ({ page }) => {
  await page.goto(`/admin/dashboard/users/roles?selected=${ROLE_ID}`, { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  await expect(page.getByTestId("role-detail-drawer")).toContainText("Super Administrator");
});

test("protected role is marked in drawer", async ({ page }) => {
  await page.goto(`/admin/dashboard/users/roles?selected=${PROTECTED_ROLE}`, { waitUntil: "load" });
  await expect(page.getByTestId("role-detail-drawer")).toContainText("Read-only Auditor");
  await expect(page.getByTestId("role-detail-drawer")).toContainText("Protected");
});

test("effective access renders", async ({ page }) => {
  await page.goto(`/admin/dashboard/users/roles?selected=${ROLE_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("role-effective-access-summary")).toBeVisible();
});

test("permission preview is labelled preview-only", async ({ page }) => {
  await page.goto(`/admin/dashboard/users/roles?selected=${ROLE_ID}`, { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Permission preview only" })).toBeVisible();
});

test("apply-to-preview adds permission chip", async ({ page }) => {
  await page.goto(`/admin/dashboard/users/roles?selected=JP-ROL-0003`, { waitUntil: "load" });
  await applyRolePermissionPreview(page, "reports.view");
  await expect(page.getByTestId("permission-assignment-preview").getByText("View reports")).toBeVisible();
});

test("reset preview restores fixture permissions", async ({ page }) => {
  await page.goto(`/admin/dashboard/users/roles?selected=JP-ROL-0003`, { waitUntil: "load" });
  await applyRolePermissionPreview(page, "reports.view");
  await resetRolePermissionPreview(page);
  await expect(
    page.getByTestId("permission-assignment-preview").getByRole("button", { name: /Remove View reports/i }),
  ).toHaveCount(0);
});

test("refresh restores fixture permissions", async ({ page }) => {
  await page.goto(`/admin/dashboard/users/roles?selected=JP-ROL-0003`, { waitUntil: "load" });
  await applyRolePermissionPreview(page, "reports.view");
  await page.reload({ waitUntil: "load" });
  await expect(
    page.getByTestId("permission-assignment-preview").getByRole("button", { name: /Remove View reports/i }),
  ).toHaveCount(0);
});

test("no mutation request occurs", async ({ page }) => {
  const requests: string[] = [];
  page.on("request", (req) => {
    if (["POST", "PUT", "PATCH", "DELETE"].includes(req.method())) {
      requests.push(`${req.method()} ${req.url()}`);
    }
  });
  await page.goto(`/admin/dashboard/users/roles?selected=JP-ROL-0003`, { waitUntil: "load" });
  await applyRolePermissionPreview(page, "reports.view");
  expect(requests).toEqual([]);
});

test("loading state works", async ({ page }) => {
  await page.goto("/admin/dashboard/users/roles?previewLoading=1", { waitUntil: "load" });
  await expect(page.getByTestId("roles-loading-state")).toBeVisible();
});

test("empty state works", async ({ page }) => {
  await page.goto("/admin/dashboard/users/roles?previewEmpty=1", { waitUntil: "load" });
  await expect(page.getByText(/No roles match your filters/i)).toBeVisible();
});

test("error state works", async ({ page }) => {
  await page.goto("/admin/dashboard/users/roles?previewError=1", { waitUntil: "load" });
  await expect(page.getByText(/Unable to load users/i)).toBeVisible();
});

test("360px has no overflow", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/admin/dashboard/users/roles", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflow).toBe(false);
});

test("users route regression remains functional", async ({ page }) => {
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  await expectUsersReady(page);
});

test("reports route regression remains functional", async ({ page }) => {
  await page.goto("/admin/dashboard/reports", { waitUntil: "load" });
  await expectReportsReady(page);
});

test("cms route regression remains functional", async ({ page }) => {
  await page.goto("/admin/dashboard/cms", { waitUntil: "load" });
  await expectCmsReady(page);
});
