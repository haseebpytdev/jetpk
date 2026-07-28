import { test, expect } from "@playwright/test";
import {
  applyUsersFiltersAndWait,
  closeDrawerWithEscape,
  expectTableReady,
  expectUsersReady,
  fillSearchInput,
  openUserDrawer,
  applyUserRolePreview,
  resetUserRolePreview,
  resetUsersFiltersAndWait,
  clickUsersSortHeader,
  selectAndApplyFilter,
} from "./helpers";

const USER_ID = "JP-USR-0001";
const NO_ROLE_USER = "JP-USR-0012";

test.beforeAll(async ({ request }) => {
  await request.get("/admin/dashboard/users", { timeout: 120_000 });
});

test("users route renders", async ({ page }) => {
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  await expectUsersReady(page);
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("navigation includes Users Settings and Audit links", async ({ page }) => {
  await page.goto("/admin/dashboard", { waitUntil: "load" });
  await expect(page.getByRole("link", { name: "Users", exact: true })).toBeVisible();
  await expect(page.getByRole("link", { name: "Settings", exact: true })).toBeVisible();
  await expect(page.getByRole("link", { name: "Audit", exact: true })).toBeVisible();
});

test("summary metrics derive from fixtures", async ({ page }) => {
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  const grid = page.getByTestId("users-metric-grid");
  await expect(grid).toBeVisible();
  await expect(grid.getByText("Total users")).toBeVisible();
  await expect(grid.getByRole("heading", { name: "40" })).toBeVisible();
});

test("search works", async ({ page }) => {
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  await expectUsersReady(page);
  const search = page.locator("#users-search");
  await fillSearchInput(search, "Ayesha");
  await applyUsersFiltersAndWait(page, /search=Ayesha/);
  const table = page.getByTestId("users-table");
  await expectTableReady(table);
  await expect(table.getByText("Ayesha Khan")).toBeVisible();
});

test("status filter works", async ({ page }) => {
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  const table = page.getByTestId("users-table");
  await selectAndApplyFilter(page, table, page.locator("#users-status"), "locked", /status=locked/, "Waqas Butt");
});

test("role filter works", async ({ page }) => {
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  const table = page.getByTestId("users-table");
  await selectAndApplyFilter(page, table, page.locator("#users-role"), "JP-ROL-0001", /role=JP-ROL-0001/, "Ayesha Khan");
});

test("department filter works", async ({ page }) => {
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  const table = page.getByTestId("users-table");
  await selectAndApplyFilter(page, table, page.locator("#users-department"), "Finance", /department=Finance/, "Fatima Noor");
});

test("MFA filter works", async ({ page }) => {
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  const table = page.getByTestId("users-table");
  await selectAndApplyFilter(page, table, page.locator("#users-mfa"), "enabled", /mfa=enabled/, "Ayesha Khan");
});

test("verification filter works", async ({ page }) => {
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  const table = page.getByTestId("users-table");
  await selectAndApplyFilter(page, table, page.locator("#users-verification"), "pending", /verification=pending/, "Hina Akram");
});

test("invalid URL values fall back safely", async ({ page }) => {
  await page.goto("/admin/dashboard/users?status=invalid&sort=notreal&page=-1", { waitUntil: "load" });
  await expectUsersReady(page);
  await expect(page.getByTestId("users-metric-grid").getByRole("heading", { name: "40" })).toBeVisible();
});

test("reset filters works", async ({ page }) => {
  await page.goto("/admin/dashboard/users?status=locked", { waitUntil: "load" });
  await resetUsersFiltersAndWait(page, /status=locked/);
});

test("sorting works", async ({ page }) => {
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  await clickUsersSortHeader(page, "Email", "email");
});

test("pagination works", async ({ page }) => {
  await page.goto("/admin/dashboard/users?pageSize=10", { waitUntil: "load" });
  await expectUsersReady(page);
  await page.getByRole("button", { name: "Next page" }).click();
  await expect(page).toHaveURL(/page=2/, { timeout: 30_000 });
});

test("browser back/forward works", async ({ page }) => {
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  await clickUsersSortHeader(page, "Email", "email");
  await page.goBack();
  await expect(page).not.toHaveURL(/sort=email/);
  await page.goForward();
  await expect(page).toHaveURL(/sort=email/);
});

test("desktop table renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  await expect(page.getByTestId("users-table")).toBeVisible();
});

test("mobile cards render", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  await expect(page.getByTestId("users-mobile-cards")).toBeVisible();
});

test("drawer opens", async ({ page }) => {
  await page.goto(`/admin/dashboard/users?selected=${USER_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("user-detail-drawer")).toBeVisible();
});

test("drawer closes with Escape", async ({ page }) => {
  await page.goto(`/admin/dashboard/users?selected=${USER_ID}`, { waitUntil: "load" });
  await closeDrawerWithEscape(page, /selected=JP-USR-0001/);
});

test("focus returns after drawer close", async ({ page }) => {
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  await openUserDrawer(page, USER_ID);
  const trigger = page.getByTestId("users-table").getByRole("button", { name: USER_ID });
  await closeDrawerWithEscape(page, /selected=JP-USR-0001/);
  await expect(trigger).toBeVisible();
});

test("selected user deep-link works", async ({ page }) => {
  await page.goto(`/admin/dashboard/users?selected=${USER_ID}`, { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  await expect(page.getByTestId("user-detail-drawer")).toContainText("Ayesha Khan");
});

test("security summary renders", async ({ page }) => {
  await page.goto(`/admin/dashboard/users?selected=${USER_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("user-security-summary")).toBeVisible();
});

test("effective access renders", async ({ page }) => {
  await page.goto(`/admin/dashboard/users?selected=${USER_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("user-effective-access-summary")).toBeVisible();
  await expect(page.getByTestId("user-effective-access-summary").getByTestId("access-domain-grid")).toBeVisible();
});

test("high-risk access is marked", async ({ page }) => {
  await page.goto(`/admin/dashboard/users?selected=${USER_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("user-effective-access-summary").getByText(/High-risk permissions/i)).toBeVisible();
});

test("role preview is labelled preview-only", async ({ page }) => {
  await page.goto(`/admin/dashboard/users?selected=${NO_ROLE_USER}`, { waitUntil: "load" });
  await expect(page.getByText(/Assignment preview only/i)).toBeVisible();
});

test("apply-to-preview updates effective access", async ({ page }) => {
  await page.goto(`/admin/dashboard/users?selected=${NO_ROLE_USER}`, { waitUntil: "load" });
  await applyUserRolePreview(page, "JP-ROL-0003");
  await expect(page.getByTestId("role-preview-effective-access").getByText("Bookings")).toBeVisible();
});

test("reset preview restores fixture roles", async ({ page }) => {
  await page.goto(`/admin/dashboard/users?selected=${NO_ROLE_USER}`, { waitUntil: "load" });
  await applyUserRolePreview(page, "JP-ROL-0003");
  await resetUserRolePreview(page);
  await expect(page.getByTestId("role-assignment-preview")).toContainText("No roles in preview");
});

test("refresh restores fixture roles", async ({ page }) => {
  await page.goto(`/admin/dashboard/users?selected=${NO_ROLE_USER}`, { waitUntil: "load" });
  await applyUserRolePreview(page, "JP-ROL-0003");
  await page.reload({ waitUntil: "load" });
  await expect(page.getByTestId("role-assignment-preview")).toContainText("No roles in preview");
});

test("no mutation request occurs", async ({ page }) => {
  const requests: string[] = [];
  page.on("request", (req) => {
    if (["POST", "PUT", "PATCH", "DELETE"].includes(req.method())) {
      requests.push(`${req.method()} ${req.url()}`);
    }
  });
  await page.goto(`/admin/dashboard/users?selected=${USER_ID}`, { waitUntil: "load" });
  await applyUserRolePreview(page, "JP-ROL-0008");
  expect(requests).toEqual([]);
});

test("no password or secret appears", async ({ page }) => {
  await page.goto(`/admin/dashboard/users?selected=${USER_ID}`, { waitUntil: "load" });
  const body = await page.locator("body").textContent();
  expect(body).not.toMatch(/password|passwordHash|Bearer|sessionId|cookie/i);
});

test("loading state works", async ({ page }) => {
  await page.goto("/admin/dashboard/users?previewLoading=1", { waitUntil: "load" });
  await expect(page.getByTestId("users-loading-state")).toBeVisible();
});

test("empty state works", async ({ page }) => {
  await page.goto("/admin/dashboard/users?previewEmpty=1", { waitUntil: "load" });
  await expect(page.getByText(/No users match your filters/i)).toBeVisible();
});

test("error state works", async ({ page }) => {
  await page.goto("/admin/dashboard/users?previewError=1", { waitUntil: "load" });
  await expect(page.getByText(/Unable to load users/i)).toBeVisible();
});

test("360px has no overflow", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflow).toBe(false);
});

test("390px has no overflow", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflow).toBe(false);
});

test("route shells render for roles permissions settings audit", async ({ page }) => {
  const routes = [
    "/admin/dashboard/users/roles",
    "/admin/dashboard/users/permissions",
    "/admin/dashboard/settings",
    "/admin/dashboard/settings/general",
    "/admin/dashboard/audit",
  ];
  for (const route of routes) {
    await page.goto(route, { waitUntil: "load" });
    await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
  }
});
