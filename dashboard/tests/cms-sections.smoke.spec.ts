import { test, expect } from "@playwright/test";
import {
  applyCmsLocalPreview,
  applyCmsFiltersAndWait,
  changeCmsPreviewMode,
  clickCmsSortHeader,
  closeDrawerWithEscape,
  expectCmsReady,
  expectFiltersReady,
  expectTableReady,
  fillSearchInput,
  resetCmsLocalPreview,
  selectFilterOption,
} from "./helpers";

const SECTION_ID = "JP-CMS-SC-001";
const ORIGINAL_HEADING = "Homepage — hero";

test.beforeAll(async ({ request }) => {
  await request.get("/admin/dashboard/cms/sections", { timeout: 120_000 });
});

test("sections table renders", async ({ page }) => {
  await page.goto("/admin/dashboard/cms/sections", { waitUntil: "load" });
  await expect(page.getByTestId("cms-table")).toBeVisible();
});

test("section-type filter works", async ({ page }) => {
  await page.goto("/admin/dashboard/cms/sections", { waitUntil: "load" });
  await expectFiltersReady(page);
  await selectFilterOption(page.locator("#cms-section-type"), "homepage.hero");
  await applyCmsFiltersAndWait(page, /sectionType=homepage\.hero/);
});

test("component keys visible in table", async ({ page }) => {
  await page.goto("/admin/dashboard/cms/sections?sectionType=homepage.hero", { waitUntil: "load" });
  await expectCmsReady(page);
  await expect(page.getByTestId("cms-table").locator("code").filter({ hasText: "homepage.hero" }).first()).toBeVisible();
});

test("section drawer opens", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/sections?selected=${SECTION_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("cms-section-drawer")).toBeVisible();
});

test("validation issues display", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/sections?selected=${SECTION_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("cms-validation-summary")).toBeVisible();
});

test("theme metadata displays", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/sections?selected=${SECTION_ID}`, { waitUntil: "load" });
  await expect(page.getByText("Theme & layout")).toBeVisible();
});

test("asset references display", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/sections?selected=${SECTION_ID}`, { waitUntil: "load" });
  await expect(page.getByText("Referenced assets")).toBeVisible();
});

test("future Next.js mapping displays", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/sections?selected=${SECTION_ID}`, { waitUntil: "load" });
  await expect(page.getByText("Future Next.js mapping")).toBeVisible();
});

test("local preview form works", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/sections?selected=${SECTION_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("cms-local-preview-form")).toBeVisible();
});

test("unsaved preview notice appears", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/sections?selected=${SECTION_ID}`, { waitUntil: "load" });
  await applyCmsLocalPreview(page, "Preview heading change");
  await expect(page.getByText("Unsaved preview")).toBeVisible();
});

test("apply-to-preview updates preview", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/sections?selected=${SECTION_ID}`, { waitUntil: "load" });
  await applyCmsLocalPreview(page, "Updated hero heading");
  const preview = page.getByTestId("cms-hero-preview");
  await preview.scrollIntoViewIfNeeded();
  await expect(preview.getByText("Updated hero heading")).toBeVisible({ timeout: 15_000 });
});

test("reset preview restores fixture data", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/sections?selected=${SECTION_ID}`, { waitUntil: "load" });
  await applyCmsLocalPreview(page, "Temporary heading");
  await resetCmsLocalPreview(page);
  await expect(page.getByTestId("cms-hero-preview")).toContainText(ORIGINAL_HEADING);
});

test("refresh restores fixture content", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/sections?selected=${SECTION_ID}`, { waitUntil: "load" });
  await applyCmsLocalPreview(page, "Temporary heading");
  await page.reload({ waitUntil: "load" });
  await expect(page.getByTestId("cms-hero-preview")).toContainText(ORIGINAL_HEADING);
});

test("unsafe link is rejected", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/sections?selected=${SECTION_ID}`, { waitUntil: "load" });
  const form = page.getByTestId("cms-local-preview-form");
  await fillSearchInput(form.locator("#cms-preview-cta-value"), "javascript:alert(1)");
  await form.getByRole("button", { name: "Apply to preview" }).click();
  await expect(form.getByRole("alert")).toContainText(/unsafe/i);
});

test("theme mode filter applies", async ({ page }) => {
  await page.goto("/admin/dashboard/cms/sections", { waitUntil: "load" });
  await expectFiltersReady(page);
  await selectFilterOption(page.locator("#cms-theme-mode"), "day");
  await applyCmsFiltersAndWait(page, /themeMode=day/);
});

test("hero preview changes viewport theme", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/sections?selected=${SECTION_ID}`, { waitUntil: "load" });
  await changeCmsPreviewMode(page, "Desktop night", /previewMode=desktop_night/);
});

test("flight-search logic is not editable", async ({ page }) => {
  const flightSection = "JP-CMS-SC-002";
  await page.goto(`/admin/dashboard/cms/sections?selected=${flightSection}`, { waitUntil: "load" });
  const boundary = page.getByTestId("flight-search-boundary");
  await expect(boundary).toBeVisible();
  await expect(boundary).toContainText(/not CMS-controlled/i);
});

test("more than three offers require carousel", async ({ page }) => {
  const offersSection = mockOffersSectionId();
  await page.goto(`/admin/dashboard/cms/sections?selected=${offersSection}`, { waitUntil: "load" });
  await expect(page.getByTestId("cms-featured-offers-preview").getByLabel("Previous offers")).toBeVisible();
});

test("static pricing label is shown for offers", async ({ page }) => {
  const offersSection = mockOffersSectionId();
  await page.goto(`/admin/dashboard/cms/sections?selected=${offersSection}`, { waitUntil: "load" });
  await expectCmsReady(page);
  const preview = page.getByTestId("cms-featured-offers-preview");
  await expect(preview.getByText(/Indicative pricing/i)).toBeVisible();
  await expect(preview.getByText(/static preview fares only/i)).toBeVisible();
});

test("drawer closes with Escape", async ({ page }) => {
  await page.goto(`/admin/dashboard/cms/sections?selected=${SECTION_ID}`, { waitUntil: "load" });
  await closeDrawerWithEscape(page, /selected=/);
});

test("mobile cards render", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/cms/sections", { waitUntil: "load" });
  await expect(page.getByTestId("cms-mobile-cards")).toBeVisible();
});

test("sorting works", async ({ page }) => {
  await page.goto("/admin/dashboard/cms/sections", { waitUntil: "load" });
  await clickCmsSortHeader(page, "Order", "order");
});

test("no horizontal overflow at 360px", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/admin/dashboard/cms/sections", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflow).toBe(false);
});

function mockOffersSectionId(): string {
  return "JP-CMS-SC-003";
}
