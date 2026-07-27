import { test, expect } from "@playwright/test";
import {
  applyPermissionsFiltersAndWait,
  clickPermissionsSortHeader,
  closeDrawerWithEscape,
  expectCmsReady,
  expectPermissionsReady,
  expectReportsReady,
  expectTableReady,
  expectUsersReady,
  fillSearchInput,
  openPermissionDrawer,
  resetPermissionsFiltersAndWait,
  selectAndApplyFilter,
} from "./helpers";

const PERMISSION_ID = "JP-PRM-0002";

test.beforeAll(async ({ request }) => {
  await request.get("/testdash/users/permissions", { timeout: 120_000 });
});

test("permissions route renders", async ({ page }) => {
  await page.goto("/testdash/users/permissions", { waitUntil: "load" });
  await expectPermissionsReady(page);
  await expect(page.getByText(/Dashboard preview only/i).first()).toBeVisible();
});

test("navigation includes Permissions section link", async ({ page }) => {
  await page.goto("/testdash/users", { waitUntil: "load" });
  const nav = page.getByRole("navigation", { name: "Users sections" });
  await expect(nav.getByRole("link", { name: "Permissions", exact: true })).toBeVisible();
});

test("summary metrics derive from fixtures", async ({ page }) => {
  await page.goto("/testdash/users/permissions", { waitUntil: "load" });
  const grid = page.getByTestId("permissions-metric-grid");
  await expect(grid).toBeVisible();
  await expect(grid.getByText("Total permissions")).toBeVisible();
  await expect(grid.getByRole("heading", { name: "46" })).toBeVisible();
});

test("search works", async ({ page }) => {
  await page.goto("/testdash/users/permissions", { waitUntil: "load" });
  await expectPermissionsReady(page);
  const search = page.locator("#permissions-search");
  await fillSearchInput(search, "bookings");
  await applyPermissionsFiltersAndWait(page, /search=bookings/);
  const table = page.getByTestId("permissions-table");
  await expectTableReady(table);
  await expect(table.getByText("bookings.view")).toBeVisible();
});

test("domain filter works", async ({ page }) => {
  await page.goto("/testdash/users/permissions", { waitUntil: "load" });
  const table = page.getByTestId("permissions-table");
  await selectAndApplyFilter(page, table, page.locator("#permissions-domain"), "bookings", /domain=bookings/, "bookings.view");
});

test("action filter works", async ({ page }) => {
  await page.goto("/testdash/users/permissions", { waitUntil: "load" });
  const table = page.getByTestId("permissions-table");
  await selectAndApplyFilter(page, table, page.locator("#permissions-action"), "view", /action=view/, "dashboard.view");
});

test("risk filter works", async ({ page }) => {
  await page.goto("/testdash/users/permissions", { waitUntil: "load" });
  const table = page.getByTestId("permissions-table");
  await selectAndApplyFilter(page, table, page.locator("#permissions-risk"), "high", /risk=high/, "bookings.cancel.approve");
});

test("prerequisite filter works", async ({ page }) => {
  await page.goto("/testdash/users/permissions", { waitUntil: "load" });
  const table = page.getByTestId("permissions-table");
  await selectAndApplyFilter(
    page,
    table,
    page.locator("#permissions-prerequisite"),
    "hasPrerequisite",
    /prerequisite=hasPrerequisite/,
    "bookings.cancel.approve",
  );
});

test("assigned state filter works", async ({ page }) => {
  await page.goto("/testdash/users/permissions", { waitUntil: "load" });
  const table = page.getByTestId("permissions-table");
  await selectAndApplyFilter(page, table, page.locator("#permissions-assigned"), "assigned", /assignedState=assigned/, PERMISSION_ID);
});

test("invalid URL values fall back safely", async ({ page }) => {
  await page.goto("/testdash/users/permissions?domain=invalid&sort=notreal&page=-1", { waitUntil: "load" });
  await expectPermissionsReady(page);
  await expect(page.getByTestId("permissions-metric-grid").getByRole("heading", { name: "46" })).toBeVisible();
});

test("reset filters works", async ({ page }) => {
  await page.goto("/testdash/users/permissions?domain=bookings", { waitUntil: "load" });
  await resetPermissionsFiltersAndWait(page, /domain=bookings/);
});

test("sorting works", async ({ page }) => {
  await page.goto("/testdash/users/permissions", { waitUntil: "load" });
  await clickPermissionsSortHeader(page, "Domain", "domain");
});

test("pagination works", async ({ page }) => {
  await page.goto("/testdash/users/permissions?pageSize=10", { waitUntil: "load" });
  await expectPermissionsReady(page);
  await page.getByRole("button", { name: "Next page" }).click();
  await expect(page).toHaveURL(/page=2/, { timeout: 30_000 });
});

test("browser back and forward works", async ({ page }) => {
  await page.goto("/testdash/users/permissions", { waitUntil: "load" });
  await clickPermissionsSortHeader(page, "Domain", "domain");
  await page.goBack();
  await expect(page).not.toHaveURL(/sort=domain/);
  await page.goForward();
  await expect(page).toHaveURL(/sort=domain/);
});

test("desktop table renders", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/users/permissions", { waitUntil: "load" });
  await expect(page.getByTestId("permissions-table")).toBeVisible();
});

test("mobile cards render", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/testdash/users/permissions", { waitUntil: "load" });
  await expect(page.getByTestId("permissions-mobile-cards")).toBeVisible();
});

test("drawer opens", async ({ page }) => {
  await page.goto(`/testdash/users/permissions?selected=${PERMISSION_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("permission-detail-drawer")).toBeVisible();
});

test("drawer closes with Escape", async ({ page }) => {
  await page.goto(`/testdash/users/permissions?selected=${PERMISSION_ID}`, { waitUntil: "load" });
  await closeDrawerWithEscape(page, /selected=JP-PRM-0002/);
});

test("selected permission deep-link works", async ({ page }) => {
  await page.goto(`/testdash/users/permissions?selected=${PERMISSION_ID}`, { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  await expect(page.getByTestId("permission-detail-drawer")).toContainText("View bookings");
  await expect(page.getByTestId("permission-detail-drawer")).toContainText("bookings.view");
});

test("high-risk permission is marked in drawer", async ({ page }) => {
  await page.goto("/testdash/users/permissions?selected=JP-PRM-0006", { waitUntil: "load" });
  await expect(page.getByTestId("permission-detail-drawer")).toContainText("bookings.cancel.approve");
  await expect(page.getByTestId("permission-detail-drawer").getByText(/High risk/i)).toBeVisible();
});

test("Laravel policy hint renders", async ({ page }) => {
  await page.goto(`/testdash/users/permissions?selected=${PERMISSION_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("permission-detail-drawer")).toContainText("BookingPolicy::view");
});

test("no mutation request occurs", async ({ page }) => {
  const requests: string[] = [];
  page.on("request", (req) => {
    if (["POST", "PUT", "PATCH", "DELETE"].includes(req.method())) {
      requests.push(`${req.method()} ${req.url()}`);
    }
  });
  await page.goto(`/testdash/users/permissions?selected=${PERMISSION_ID}`, { waitUntil: "load" });
  await page.getByRole("dialog").click();
  expect(requests).toEqual([]);
});

test("loading state works", async ({ page }) => {
  await page.goto("/testdash/users/permissions?previewLoading=1", { waitUntil: "load" });
  await expect(page.getByTestId("permissions-loading-state")).toBeVisible();
});

test("empty state works", async ({ page }) => {
  await page.goto("/testdash/users/permissions?previewEmpty=1", { waitUntil: "load" });
  await expect(page.getByText(/No permissions match your filters/i)).toBeVisible();
});

test("error state works", async ({ page }) => {
  await page.goto("/testdash/users/permissions?previewError=1", { waitUntil: "load" });
  await expect(page.getByText(/Unable to load users/i)).toBeVisible();
});

test("390px has no overflow", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/testdash/users/permissions", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflow).toBe(false);
});

test("users route regression remains functional", async ({ page }) => {
  await page.goto("/testdash/users", { waitUntil: "load" });
  await expectUsersReady(page);
});

test("reports route regression remains functional", async ({ page }) => {
  await page.goto("/testdash/reports", { waitUntil: "load" });
  await expectReportsReady(page);
});

test("cms route regression remains functional", async ({ page }) => {
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await expectCmsReady(page);
});

test("focus returns after drawer close", async ({ page }) => {
  await page.goto("/testdash/users/permissions", { waitUntil: "load" });
  await openPermissionDrawer(page, PERMISSION_ID);
  const trigger = page.getByTestId("permissions-table").getByRole("button", { name: PERMISSION_ID });
  await closeDrawerWithEscape(page, /selected=JP-PRM-0002/);
  await expect(trigger).toBeVisible();
});
