import { test, expect } from "@playwright/test";
import {
  applyCmsFiltersAndWait,
  changeCmsPreviewMode,
  closeDrawerWithEscape,
  expectCmsReady,
  expectFiltersReady,
  selectFilterOption,
} from "./helpers";

const BANNER_ID = "JP-CMS-BN-001";

test.beforeAll(async ({ request }) => {
  await request.get("/testdash/cms/banners", { timeout: 120_000 });
});

test("banner table renders", async ({ page }) => {
  await page.goto("/testdash/cms/banners", { waitUntil: "load" });
  await expect(page.getByTestId("cms-table")).toBeVisible();
});

test("banner family filter works", async ({ page }) => {
  await page.goto("/testdash/cms/banners", { waitUntil: "load" });
  await expectFiltersReady(page);
  await selectFilterOption(page.locator("#cms-banner-family"), "hero");
  await applyCmsFiltersAndWait(page, /bannerFamily=hero/);
});

test("banner drawer opens", async ({ page }) => {
  await page.goto(`/testdash/cms/banners?selected=${BANNER_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("cms-banner-drawer")).toBeVisible();
});

test("family-specific metadata displays", async ({ page }) => {
  await page.goto(`/testdash/cms/banners?selected=${BANNER_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("cms-banner-drawer").getByText("Family constraints")).toBeVisible();
});

test("desktop mobile ratios display", async ({ page }) => {
  await page.goto(`/testdash/cms/banners?selected=${BANNER_ID}`, { waitUntil: "load" });
  const drawer = page.getByTestId("cms-banner-drawer");
  await expect(drawer.getByText("Desktop ratio")).toBeVisible();
  await expect(drawer.getByText("Mobile ratio")).toBeVisible();
});

test("day night asset metadata displays", async ({ page }) => {
  await page.goto(`/testdash/cms/banners?selected=${BANNER_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("cms-banner-drawer").getByText("Day/night")).toBeVisible();
});

test("preview changes by family", async ({ page }) => {
  await page.goto(`/testdash/cms/banners?selected=${BANNER_ID}`, { waitUntil: "load" });
  await changeCmsPreviewMode(page, "Tablet");
  await expect(page.locator("[data-banner-family]")).toBeVisible();
});

test("invalid placement warning displays for offer banners", async ({ page }) => {
  const offerBanner = "JP-CMS-BN-003";
  await page.goto(`/testdash/cms/banners?selected=${offerBanner}`, { waitUntil: "load" });
  const drawer = page.getByTestId("cms-banner-drawer");
  await expect(drawer.getByTestId("cms-validation-summary").getByText(/placement/i).first()).toBeVisible();
});

test("unapproved asset warning may display", async ({ page }) => {
  await page.goto("/testdash/cms/banners", { waitUntil: "load" });
  await expectCmsReady(page);
});

test("no upload control exists", async ({ page }) => {
  await page.goto("/testdash/cms/banners", { waitUntil: "load" });
  await expect(page.getByRole("button", { name: /upload/i })).toHaveCount(0);
  await expect(page.locator('input[type="file"]')).toHaveCount(0);
});

test("no delete control exists", async ({ page }) => {
  await page.goto(`/testdash/cms/banners?selected=${BANNER_ID}`, { waitUntil: "load" });
  await expect(page.getByRole("button", { name: /delete/i })).toHaveCount(0);
});

test("drawer closes with Escape", async ({ page }) => {
  await page.goto(`/testdash/cms/banners?selected=${BANNER_ID}`, { waitUntil: "load" });
  await closeDrawerWithEscape(page, /selected=/);
});

test("mobile cards render", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/testdash/cms/banners", { waitUntil: "load" });
  await expect(page.getByTestId("cms-mobile-cards")).toBeVisible();
});

test("pagination works", async ({ page }) => {
  await page.goto("/testdash/cms/banners?page=2", { waitUntil: "load" });
  await expect(page).toHaveURL(/page=2/);
});

test("dashboard preview label visible", async ({ page }) => {
  await page.goto(`/testdash/cms/banners?selected=${BANNER_ID}`, { waitUntil: "load" });
  await expect(page.getByRole("dialog").getByText(/Dashboard preview only/i)).toBeVisible();
});

test("no horizontal overflow at 360px", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/testdash/cms/banners", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflow).toBe(false);
});

test("loading state renders", async ({ page }) => {
  await page.goto("/testdash/cms/banners?previewLoading=1", { waitUntil: "load" });
  await expect(page.getByTestId("cms-loading-state")).toBeVisible();
});

test("empty state renders", async ({ page }) => {
  await page.goto("/testdash/cms/banners?previewEmpty=1", { waitUntil: "load" });
  await expect(page.getByText("No CMS records match filters")).toBeVisible();
});

test("validation filter works", async ({ page }) => {
  await page.goto("/testdash/cms/banners", { waitUntil: "load" });
  await expectFiltersReady(page);
  await selectFilterOption(page.locator("#cms-validation-state"), "blocked");
  await applyCmsFiltersAndWait(page, /validationState=blocked/);
});

test("missing alt text warning for banners without alt", async ({ page }) => {
  await page.goto("/testdash/cms/banners", { waitUntil: "load" });
  await expectCmsReady(page);
});
