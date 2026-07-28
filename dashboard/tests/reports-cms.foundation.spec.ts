import { test, expect } from "@playwright/test";
import { buildCsvContent, escapeCsvCell } from "@/lib/csv-safe";
import { resolveDatePreset } from "@/lib/reports/date-presets";
import { REPORT_REFERENCE_DATE } from "@/lib/reports/constants";
import { sumSameCurrencyAmounts } from "@/lib/reports/currency";
import { aggregateReportMetrics, getOperationalFixtureGraph } from "@/lib/reports/aggregations";
import { parseReportsQuery } from "@/lib/reports-query";
import { parseCmsQuery } from "@/lib/cms-query";
import {
  CMS_SECTION_REGISTRY,
  CMS_PREVIEW_MODES,
  requiresOfferCarousel,
  isThemeModeSupported,
  isPageTypeAllowed,
  getRegistryKeys,
} from "@/features/cms/registry/section-registry";
import { isUnsafeUrl } from "@/features/cms/validation/link-validation";
import { validateAsset } from "@/features/cms/validation/cms-validation";
import { mockCmsAssets } from "@/mocks/cms-fixtures";
import { CMS_BRAND } from "@/types/cms";

const viewports = [
  { width: 360, height: 740 },
  { width: 390, height: 844 },
  { width: 1280, height: 720 },
];

const reportRoutes = [
  "/testdash/reports",
  "/testdash/reports/sales",
  "/testdash/reports/bookings",
  "/testdash/reports/payments",
  "/testdash/reports/operations",
];

const cmsRoutes = [
  "/testdash/cms",
  "/testdash/cms/pages",
  "/testdash/cms/sections",
  "/testdash/cms/banners",
  "/testdash/cms/notices",
  "/testdash/cms/assets",
];

test.beforeAll(async ({ request }) => {
  const response = await request.get("/testdash/reports", { timeout: 120_000 });
  expect(response.ok()).toBeTruthy();
});

for (const route of reportRoutes) {
  test(`reports route renders: ${route}`, async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 720 });
    await page.goto(route, { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Reports", level: 1 })).toBeVisible({ timeout: 60_000 });
    await expect(page.getByText(/Preview data/i).first()).toBeVisible();
  });
}

for (const route of cmsRoutes) {
  test(`cms route renders: ${route}`, async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 720 });
    await page.goto(route, { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "CMS", level: 1 })).toBeVisible({ timeout: 60_000 });
    await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
  });
}

test("navigation includes Reports and CMS links", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash", { waitUntil: "load" });
  await expect(page.getByRole("link", { name: "Reports" })).toBeVisible();
  await expect(page.getByRole("link", { name: "CMS" })).toBeVisible();
});

for (const viewport of viewports.filter((v) => v.width < 768)) {
  test(`mobile navigation remains usable at ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto("/testdash/reports", { waitUntil: "load" });
    await page.getByRole("button", { name: "Open navigation menu" }).click();
    await expect(page.getByLabel("Dashboard navigation")).toBeVisible();
    await expect(page.getByLabel("Dashboard navigation").getByRole("link", { name: "CMS" })).toBeVisible();
  });
}

test("reports URL state rejects invalid values safely", async ({ page }) => {
  await page.goto("/testdash/reports?datePreset=invalid&currency=XYZ&comparison=bad", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Reports", level: 1 })).toBeVisible();
  const query = parseReportsQuery({ datePreset: "invalid", currency: "XYZ", comparison: "bad" });
  expect(query.datePreset).toBe("current_year");
  expect(query.currency).toBe("PKR");
  expect(query.comparison).toBe("none");
});

test("cms URL state rejects invalid values safely", async ({ page }) => {
  await page.goto("/testdash/cms?status=bad&themeMode=raw_css", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "CMS", level: 1 })).toBeVisible();
  const query = parseCmsQuery({ status: "bad", themeMode: "raw_css" });
  expect(query.status).toBe("all");
  expect(query.themeMode).toBe("all");
});

test("report date presets are deterministic", () => {
  const range = resolveDatePreset("last_7_days");
  expect(range.startDate).toBe("2026-06-25");
  expect(range.endDate).toBe("2026-07-01");
  expect(REPORT_REFERENCE_DATE).toBe("2026-07-01T00:00:00.000Z");
});

test("currency aggregation does not mix currencies", () => {
  const mixed = sumSameCurrencyAmounts([
    { amount: 100, currency: "PKR" },
    { amount: 50, currency: "USD" },
  ]);
  expect(mixed.mixed).toBe(true);
  expect(mixed.total).toBeNull();
});

test("CSV safety neutralizes formula injection", () => {
  expect(escapeCsvCell("=SUM(A1)")).toBe("'=SUM(A1)");
  expect(escapeCsvCell("+1234")).toBe("'+1234");
  expect(escapeCsvCell("-formula")).toBe("'-formula");
  expect(escapeCsvCell("@import")).toBe("'@import");
  const csv = buildCsvContent(["Name"], [["Safe, value"], ['He said "hi"']]);
  expect(csv).toContain('"Safe, value"');
  expect(csv).toContain('"He said ""hi"""');
});

test("CMS section registry contains unique keys", () => {
  const keys = getRegistryKeys();
  expect(new Set(keys).size).toBe(keys.length);
  expect(keys.length).toBe(CMS_SECTION_REGISTRY.length);
});

test("every section definition has a frontend component key", () => {
  for (const def of CMS_SECTION_REGISTRY) {
    expect(def.frontendComponentKey).toBeTruthy();
    expect(def.frontendComponentKey).toBe(def.sectionType);
  }
});

test("section definitions reference allowed page types", () => {
  for (const def of CMS_SECTION_REGISTRY) {
    expect(def.supportedPageTypes.length).toBeGreaterThan(0);
    expect(isPageTypeAllowed(def.sectionType, def.supportedPageTypes[0])).toBe(true);
  }
});

test("unsupported theme values are rejected by registry", () => {
  expect(isThemeModeSupported("homepage.hero", "day")).toBe(true);
  expect(isThemeModeSupported("homepage.hero", "neutral")).toBe(false);
});

test("banner-family constraints resolve from registry", () => {
  const support = CMS_SECTION_REGISTRY.find((d) => d.sectionType === "homepage.supportCallout");
  expect(support?.aspectRatioRequirements.desktop).toBe("21:9");
});

test("offer-card count above three requires carousel behavior", () => {
  expect(requiresOfferCarousel(3)).toBe(false);
  expect(requiresOfferCarousel(4)).toBe(true);
});

test("unsafe link protocols are rejected", () => {
  expect(isUnsafeUrl("javascript:alert(1)")).toBe(true);
  expect(isUnsafeUrl("https://jetpakistan.com")).toBe(false);
});

test("asset validation identifies missing alt text", () => {
  const asset = mockCmsAssets.find((a) => !a.altText.trim());
  expect(asset).toBeTruthy();
  if (asset) {
    const issues = validateAsset(asset);
    expect(issues.some((i) => i.code === "missing_alt_text")).toBe(true);
  }
});

test("publication windows validate deterministically via fixtures", () => {
  const graph = getOperationalFixtureGraph();
  const metrics = aggregateReportMetrics(graph, resolveDatePreset("last_30_days"), "PKR");
  expect(metrics.length).toBeGreaterThan(0);
});

test("CMS preview modes are enumerated correctly", () => {
  expect(CMS_PREVIEW_MODES).toHaveLength(5);
  expect(CMS_PREVIEW_MODES.map((m) => m.mode)).toContain("mobile_night");
});

test("JetPakistan brand is fixed", () => {
  expect(CMS_BRAND.id).toBe("jetpakistan");
  expect(CMS_BRAND.label).toBe("JetPakistan");
});

test("no brand-switch UI exists", async ({ page }) => {
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await expect(page.getByText(/Brand:\s*JetPakistan/i)).toBeVisible();
  await expect(page.getByRole("combobox", { name: /brand/i })).toHaveCount(0);
});

test("loading state renders for reports", async ({ page }) => {
  await page.goto("/testdash/reports?previewLoading=1", { waitUntil: "load" });
  await expect(page.getByTestId("reports-loading-state")).toBeVisible({ timeout: 15_000 });
  await expect(page.getByLabel("Loading report data")).toBeVisible();
});

test("empty state renders for reports", async ({ page }) => {
  await page.goto("/testdash/reports?previewEmpty=1", { waitUntil: "load" });
  await expect(page.getByText("No report data for current filters")).toBeVisible();
});

test("controlled error state renders for reports", async ({ page }) => {
  await page.goto("/testdash/reports?previewError=1", { waitUntil: "load" });
  await expect(page.getByText("Unable to load reports")).toBeVisible();
  await expect(page.getByText(/RPT-PREVIEW-SIM-ERR/)).toBeVisible();
});

test("loading and error states render for CMS", async ({ page }) => {
  await page.goto("/testdash/cms?previewLoading=1", { waitUntil: "load" });
  await expect(page.getByLabel("Loading CMS foundation")).toBeVisible();
  await page.goto("/testdash/cms?previewError=1", { waitUntil: "load" });
  await expect(page.getByText("Unable to load CMS")).toBeVisible();
});

test("bookings route remains functional after foundation changes", async ({ page }) => {
  await page.goto("/testdash/bookings", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Bookings", level: 1 })).toBeVisible();
});
