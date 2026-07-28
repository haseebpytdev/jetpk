import { test, expect } from "@playwright/test";
import {
  changeCmsPreviewMode,
  closeDrawerWithEscape,
  expectCmsReady,
  expectFiltersReady,
  fillSearchInput,
  selectFilterOption,
  applyCmsFiltersAndWait,
} from "./helpers";

const ASSET_ID = "JP-CMS-AS-002";
const MISSING_ALT_ASSET = "JP-CMS-AS-001";

test.beforeAll(async ({ request }) => {
  await request.get("/admin/dashboard/cms/assets", { timeout: 120_000 });
});

test("asset table renders", async ({ page }) => {
  await page.goto("/admin/dashboard/cms/assets", { waitUntil: "load" });
  await expect(page.getByTestId("cms-table")).toBeVisible();
});

test("asset filters work", async ({ page }) => {
  await page.goto("/admin/dashboard/cms/assets", { waitUntil: "load" });
  await expectFiltersReady(page);
  await selectFilterOption(page.locator("#cms-asset-status"), "unapproved");
  await applyCmsFiltersAndWait(page, /assetStatus=unapproved/);
});

test("asset drawer opens", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/assets?selected=${ASSET_ID}`, { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  await expect(page.getByTestId("cms-asset-drawer")).toBeVisible();
});

test("dimensions display", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/assets?selected=${ASSET_ID}`, { waitUntil: "load" });
  const drawer = page.getByTestId("cms-asset-drawer");
  await expect(drawer.getByText("Dimensions")).toBeVisible();
});

test("aspect ratio displays", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/assets?selected=${ASSET_ID}`, { waitUntil: "load" });
  const drawer = page.getByTestId("cms-asset-drawer");
  await expect(drawer.getByText("Aspect ratio")).toBeVisible();
});

test("variants display", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/assets?selected=${ASSET_ID}`, { waitUntil: "load" });
  const drawer = page.getByTestId("cms-asset-drawer");
  await expect(drawer.getByText("Variant availability")).toBeVisible();
});

test("alt-text state displays", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/assets?selected=${MISSING_ALT_ASSET}`, { waitUntil: "load" });
  const drawer = page.getByTestId("cms-asset-drawer");
  await expect(drawer.getByTestId("cms-validation-summary").getByText(/alt text/i).first()).toBeVisible();
});

test("focal point displays", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/assets?selected=${ASSET_ID}`, { waitUntil: "load" });
  const drawer = page.getByTestId("cms-asset-drawer");
  await expect(drawer.getByText("Focal point")).toBeVisible();
});

test("usage references display", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/assets?selected=${ASSET_ID}`, { waitUntil: "load" });
  const drawer = page.getByTestId("cms-asset-drawer");
  await expect(drawer.getByText("Usage references")).toBeVisible();
});

test("approval status displays", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/assets?selected=${ASSET_ID}`, { waitUntil: "load" });
  const drawer = page.getByTestId("cms-asset-drawer");
  await expect(drawer.getByText("Approval")).toBeVisible();
});

test("no filesystem path appears", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/assets?selected=${ASSET_ID}`, { waitUntil: "load" });
  await expect(page.getByText(/C:\\/i)).toHaveCount(0);
  await expect(page.getByText(/public\//i)).toHaveCount(0);
});

test("no upload control exists", async ({ page }) => {
  await page.goto("/admin/dashboard/cms/assets", { waitUntil: "load" });
  await expect(page.locator('input[type="file"]')).toHaveCount(0);
});

test("no delete control exists", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/assets?selected=${ASSET_ID}`, { waitUntil: "load" });
  await expect(page.getByRole("button", { name: /delete/i })).toHaveCount(0);
});

test("preview mode changes", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/assets?selected=${ASSET_ID}`, { waitUntil: "load" });
  await changeCmsPreviewMode(page, "Mobile night");
});

test("drawer closes with Escape", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/assets?selected=${ASSET_ID}`, { waitUntil: "load" });
  await closeDrawerWithEscape(page, /selected=/);
});

test("mobile cards render", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/cms/assets", { waitUntil: "load" });
  await expect(page.getByTestId("cms-mobile-cards")).toBeVisible();
});

test("no horizontal overflow at 360px", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/admin/dashboard/cms/assets", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflow).toBe(false);
});

test("loading state renders", async ({ page }) => {
  await page.goto("/admin/dashboard/cms/assets?previewLoading=1", { waitUntil: "load" });
  await expect(page.getByTestId("cms-loading-state")).toBeVisible();
});

test("empty state renders", async ({ page }) => {
  await page.goto("/admin/dashboard/cms/assets?previewEmpty=1", { waitUntil: "load" });
  await expect(page.getByText("No CMS records match filters")).toBeVisible();
});

test("validation summary for missing alt", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/assets?selected=${MISSING_ALT_ASSET}`, { waitUntil: "load" });
  const drawer = page.getByTestId("cms-asset-drawer");
  await expect(drawer.getByTestId("cms-validation-summary").getByText(/alt text/i).first()).toBeVisible();
});

test("pagination works", async ({ page }) => {
  await page.goto("/admin/dashboard/cms/assets?page=2", { waitUntil: "load" });
  await expect(page).toHaveURL(/page=2/);
});

test("search filter applies", async ({ page }) => {
  await page.goto("/admin/dashboard/cms/assets", { waitUntil: "load" });
  await expectFiltersReady(page);
  const search = page.locator("#cms-search");
  await fillSearchInput(search, "jetpk-hero");
  const apply = page.getByRole("button", { name: "Apply filters" });
  await Promise.all([page.waitForURL(/search=jetpk-hero/, { timeout: 30_000 }), apply.click()]);
  await expectCmsReady(page);
});

test("no cross-brand text", async ({ page }) => {
  await page.goto("/admin/dashboard/cms/assets", { waitUntil: "load" });
  await expectCmsReady(page);
  await expect(page.getByText(/Parwaaz/i)).toHaveCount(0);
  await expect(page.getByText(/YoursDomain/i)).toHaveCount(0);
});
