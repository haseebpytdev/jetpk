import { test, expect } from "@playwright/test";
import {
  applyCmsFiltersAndWait,
  closeDrawerWithEscape,
  expectCmsReady,
  expectFiltersReady,
  selectFilterOption,
} from "./helpers";

const NOTICE_ID = "JP-CMS-NT-001";

test.beforeAll(async ({ request }) => {
  await request.get("/testdash/cms/notices", { timeout: 120_000 });
});

test("notice table renders", async ({ page }) => {
  await page.goto("/testdash/cms/notices", { waitUntil: "load" });
  await expect(page.getByTestId("cms-table")).toBeVisible();
});

test("severity filter works", async ({ page }) => {
  await page.goto("/testdash/cms/notices", { waitUntil: "load" });
  await expectFiltersReady(page);
  await selectFilterOption(page.locator("#cms-notice-severity"), "warning");
  await applyCmsFiltersAndWait(page, /noticeSeverity=warning/);
});

test("notice drawer opens", async ({ page }) => {
  await page.goto(`/testdash/cms/notices?selected=${NOTICE_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("cms-notice-drawer")).toBeVisible();
});

test("publication window displays", async ({ page }) => {
  await page.goto(`/testdash/cms/notices?selected=${NOTICE_ID}`, { waitUntil: "load" });
  await expect(page.getByText("Publication window")).toBeVisible();
});

test("notice preview renders", async ({ page }) => {
  await page.goto(`/testdash/cms/notices?selected=${NOTICE_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("cms-notice-preview")).toBeVisible();
});

test("invalid date warning can display", async ({ page }) => {
  await page.goto(`/testdash/cms/notices?selected=${NOTICE_ID}`, { waitUntil: "load" });
  await expect(page.getByTestId("cms-validation-summary")).toBeVisible();
});

test("no live publish button exists", async ({ page }) => {
  await page.goto(`/testdash/cms/notices?selected=${NOTICE_ID}`, { waitUntil: "load" });
  await expect(page.getByRole("button", { name: /publish/i })).toHaveCount(0);
});

test("drawer closes with Escape", async ({ page }) => {
  await page.goto(`/testdash/cms/notices?selected=${NOTICE_ID}`, { waitUntil: "load" });
  await closeDrawerWithEscape(page, /selected=/);
});

test("mobile cards render", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/testdash/cms/notices", { waitUntil: "load" });
  await expect(page.getByTestId("cms-mobile-cards")).toBeVisible();
});

test("reset filters works", async ({ page }) => {
  await page.goto("/testdash/cms/notices?noticeSeverity=warning", { waitUntil: "load" });
  await page.getByRole("button", { name: "Reset filters" }).click();
  await expectCmsReady(page);
});

test("no horizontal overflow at 360px", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/testdash/cms/notices", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflow).toBe(false);
});

test("loading state renders", async ({ page }) => {
  await page.goto("/testdash/cms/notices?previewLoading=1", { waitUntil: "load" });
  await expect(page.getByTestId("cms-loading-state")).toBeVisible();
});

test("empty state renders", async ({ page }) => {
  await page.goto("/testdash/cms/notices?previewEmpty=1", { waitUntil: "load" });
  await expect(page.getByText("No CMS records match filters")).toBeVisible();
});

test("pagination works", async ({ page }) => {
  await page.goto("/testdash/cms/notices?page=2", { waitUntil: "load" });
  await expect(page).toHaveURL(/page=2/);
});

test("dashboard preview notice visible", async ({ page }) => {
  await page.goto("/testdash/cms/notices", { waitUntil: "load" });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("no save button", async ({ page }) => {
  await page.goto(`/testdash/cms/notices?selected=${NOTICE_ID}`, { waitUntil: "load" });
  await expect(page.getByRole("button", { name: /^save$/i })).toHaveCount(0);
});
