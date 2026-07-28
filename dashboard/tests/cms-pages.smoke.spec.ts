import { test, expect } from "@playwright/test";
import {
  applyCmsFiltersAndWait,
  clickCmsSortHeader,
  closeDrawerWithEscape,
  expectCmsReady,
  expectFiltersReady,
  openCmsRecordDrawer,
  resetCmsFiltersAndWait,
  selectFilterOption,
  fillSearchInput,
  changeCmsPreviewMode,
} from "./helpers";

const PAGE_ID = "JP-CMS-PG-001";

test.beforeAll(async ({ request }) => {
  await request.get("/testdash/cms/pages", { timeout: 120_000 });
});

test("pages route renders table", async ({ page }) => {
  await page.goto("/testdash/cms/pages", { waitUntil: "load" });
  await expectCmsReady(page);
  await expect(page.getByTestId("cms-table")).toBeVisible();
});

test("page filters render", async ({ page }) => {
  await page.goto("/testdash/cms/pages", { waitUntil: "load" });
  await expectFiltersReady(page);
});

test("page type filter updates URL", async ({ page }) => {
  await page.goto("/testdash/cms/pages", { waitUntil: "load" });
  await expectFiltersReady(page);
  await selectFilterOption(page.locator("#cms-page-type"), "homepage");
  const apply = page.getByRole("button", { name: "Apply filters" });
  await Promise.all([page.waitForURL(/pageType=homepage/, { timeout: 30_000 }), apply.click()]);
  await expectCmsReady(page);
});

test("page sorting works", async ({ page }) => {
  await page.goto("/testdash/cms/pages", { waitUntil: "load" });
  await clickCmsSortHeader(page, "Title", "title");
});

test("mobile cards render", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/testdash/cms/pages", { waitUntil: "load" });
  await expect(page.getByTestId("cms-mobile-cards")).toBeVisible();
});

test("page drawer opens", async ({ page }) => {
  await page.goto(`/testdash/cms/pages?selected=${PAGE_ID}`, { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible();
  await expect(page.getByTestId("cms-page-drawer")).toBeVisible();
});

test("page drawer shows structured metadata", async ({ page }) => {
  await page.goto(`/testdash/cms/pages?selected=${PAGE_ID}`, { waitUntil: "load" });
  const drawer = page.getByTestId("cms-page-drawer");
  await expect(drawer.getByText("SEO metadata")).toBeVisible();
  await expect(drawer.getByText("Publication", { exact: true })).toBeVisible();
});

test("ordered sections display in composition", async ({ page }) => {
  await page.goto(`/testdash/cms/pages?selected=${PAGE_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("cms-page-composition")).toBeVisible();
  await expect(page.getByLabel("Ordered sections")).toBeVisible();
});

test("revision history displays", async ({ page }) => {
  await page.goto(`/testdash/cms/pages?selected=${PAGE_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("cms-revision-timeline")).toBeVisible();
});

test("page preview mode changes", async ({ page }) => {
  await page.goto(`/testdash/cms/pages?selected=${PAGE_ID}`, { waitUntil: "load" });
  await changeCmsPreviewMode(page, "Mobile night", /previewMode=mobile_night/);
});

test("desktop day preview renders", async ({ page }) => {
  await page.goto(`/testdash/cms/pages?selected=${PAGE_ID}&previewMode=desktop_day`, { waitUntil: "load" });
  await expect(page.getByTestId("cms-preview-frame")).toBeVisible();
});

test("desktop night preview renders", async ({ page }) => {
  await page.goto(`/testdash/cms/pages?selected=${PAGE_ID}&previewMode=desktop_night`, { waitUntil: "load" });
  await expect(page.getByTestId("cms-preview-frame").locator("[data-theme='night']")).toBeVisible();
});

test("mobile day preview renders", async ({ page }) => {
  await page.goto(`/testdash/cms/pages?selected=${PAGE_ID}&previewMode=mobile_day`, { waitUntil: "load" });
  await expect(page.getByTestId("cms-preview-frame")).toBeVisible();
});

test("mobile night preview renders", async ({ page }) => {
  await page.goto(`/testdash/cms/pages?selected=${PAGE_ID}&previewMode=mobile_night`, { waitUntil: "load" });
  await expect(page.getByTestId("cms-preview-frame")).toBeVisible();
});

test("preview is labelled dashboard-only", async ({ page }) => {
  await page.goto(`/testdash/cms/pages?selected=${PAGE_ID}`, { waitUntil: "load" });
  await expect(page.getByRole("dialog").getByText(/Dashboard preview only/i)).toBeVisible();
});

test("no raw HTML execution in page drawer", async ({ page }) => {
  await page.goto(`/testdash/cms/pages?selected=${PAGE_ID}`, { waitUntil: "load" });
  await expect(page.getByRole("dialog").locator("script")).toHaveCount(0);
});

test("drawer closes with Escape", async ({ page }) => {
  await page.goto(`/testdash/cms/pages?selected=${PAGE_ID}`, { waitUntil: "load" });
  await closeDrawerWithEscape(page, /selected=/);
});

test("pagination updates URL", async ({ page }) => {
  await page.goto("/testdash/cms/pages?page=2", { waitUntil: "load" });
  await expect(page).toHaveURL(/page=2/);
});

test("reset filters works", async ({ page }) => {
  await page.goto("/testdash/cms/pages?pageType=homepage", { waitUntil: "load" });
  await resetCmsFiltersAndWait(page, /pageType=homepage/);
});

test("search filter applies", async ({ page }) => {
  await page.goto("/testdash/cms/pages", { waitUntil: "load" });
  await expectFiltersReady(page);
  await fillSearchInput(page.locator("#cms-search"), "Homepage");
  await applyCmsFiltersAndWait(page, /search=Homepage/i);
});

test("open drawer from table", async ({ page }) => {
  await page.goto("/testdash/cms/pages", { waitUntil: "load" });
  await openCmsRecordDrawer(page, PAGE_ID);
  await expect(page.getByTestId("cms-page-drawer")).toBeVisible();
});

test("no horizontal overflow at 360px", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/testdash/cms/pages", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflow).toBe(false);
});

test("loading state on pages", async ({ page }) => {
  await page.goto("/testdash/cms/pages?previewLoading=1", { waitUntil: "load" });
  await expect(page.getByTestId("cms-loading-state")).toBeVisible();
});

test("empty state on pages", async ({ page }) => {
  await page.goto("/testdash/cms/pages?previewEmpty=1", { waitUntil: "load" });
  await expect(page.getByText("No CMS records match filters")).toBeVisible();
});

test("no save or publish button", async ({ page }) => {
  await page.goto(`/testdash/cms/pages?selected=${PAGE_ID}`, { waitUntil: "load" });
  await expect(page.getByRole("button", { name: /^save$/i })).toHaveCount(0);
  await expect(page.getByRole("button", { name: /publish/i })).toHaveCount(0);
});
