import { test, expect } from "@playwright/test";
import { getCmsModule } from "@/services/cms-service";
import { containsSensitiveKeys } from "@/lib/read-only/sensitive-fields";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/admin/dashboard/cms", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("fixture CMS overview loads", async () => {
  const result = await getCmsModule(
    {
      status: "all",
      pageType: "all",
      sectionType: "all",
      themeMode: "all",
      locale: "all",
      assetStatus: "all",
      bannerFamily: "all",
      noticeSeverity: "all",
      validationState: "all",
      audience: "all",
      placement: "",
      search: "",
      page: 1,
      pageSize: 25,
      sort: "title",
      direction: "asc",
      selected: null,
      previewMode: "desktop_day",
      previewError: false,
      previewLoading: false,
      previewEmpty: false,
    },
    "overview",
  );
  expect(result.metrics.length).toBeGreaterThan(0);
  expect(containsSensitiveKeys(result)).toBe(false);
});

test("fixture source notice on CMS", async ({ page }) => {
  await page.goto("/admin/dashboard/cms?dataSourcePreview=fixture", { waitUntil: "load" });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("CMS pages table at 1280px", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/cms/pages", { waitUntil: "load" });
  await expect(page.getByTestId("cms-table").or(page.getByTestId("cms-metric-grid"))).toBeVisible({ timeout: 60_000 });
});

test("CMS cards at 1024px", async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto("/admin/dashboard/cms/pages", { waitUntil: "load" });
  await expect(page.locator("main")).toBeVisible({ timeout: 60_000 });
});

test("CMS filter URL sync", async ({ page }) => {
  await page.goto("/admin/dashboard/cms/pages?status=published", { waitUntil: "load" });
  await expect(page).toHaveURL(/status=published/);
});

test("CMS drawer deep link", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/cms/pages?selected=JP-CMS-PG-001", { waitUntil: "load" });
  await expect(page.getByRole("dialog").or(page.locator("[data-testid='cms-page-preview']"))).toBeVisible({ timeout: 30_000 });
});

test("CMS forbidden preview state", async ({ page }) => {
  await page.goto("/admin/dashboard/cms?dataSourcePreview=forbidden", { waitUntil: "load" });
  await expect(page.getByText(/Access denied/i)).toBeVisible();
});

test("CMS no overflow at 390px", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/cms", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  expect(overflow).toBeFalsy();
});

test("CMS live read-only notice", async ({ page }) => {
  await page.goto("/admin/dashboard/cms?dataSourcePreview=live", { waitUntil: "load" });
  await expect(page.getByTestId("live-readonly-notice")).toBeVisible();
});

test("CMS sanitized content has no script tags in fixture", async () => {
  const result = await getCmsModule(
    {
      status: "all",
      pageType: "all",
      sectionType: "all",
      themeMode: "all",
      locale: "all",
      assetStatus: "all",
      bannerFamily: "all",
      noticeSeverity: "all",
      validationState: "all",
      audience: "all",
      placement: "",
      search: "",
      page: 1,
      pageSize: 25,
      sort: "title",
      direction: "asc",
      selected: null,
      previewMode: "desktop_day",
      previewError: false,
      previewLoading: false,
      previewEmpty: false,
    },
    "pages",
  );
  const serialized = JSON.stringify(result);
  expect(serialized).not.toContain("<script>");
});
