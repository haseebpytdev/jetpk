import { test, expect } from "@playwright/test";
import { CMS_FIXTURE_COUNTS } from "@/mocks/cms-fixtures";
import {
  closeDrawerWithEscape,
  expectCmsReady,
  navigateCmsSection,
} from "./helpers";

const viewports = [
  { width: 360, height: 740 },
  { width: 390, height: 844 },
  { width: 768, height: 1024 },
  { width: 1280, height: 720 },
];

test.beforeAll(async ({ request }) => {
  const response = await request.get("/testdash/cms", { timeout: 120_000 });
  expect(response.ok()).toBeTruthy();
});

test("cms overview route renders metrics", async ({ page }) => {
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await expectCmsReady(page);
  await expect(page.getByTestId("cms-metric-grid")).toBeVisible();
  await expect(page.getByText("Total pages")).toBeVisible();
});

test("overview metrics match fixture counts", async ({ page }) => {
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await expectCmsReady(page);
  await expect(page.getByTestId("cms-metric-grid")).toContainText(String(CMS_FIXTURE_COUNTS.pages));
});

test("publication distribution renders", async ({ page }) => {
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await expect(page.getByTestId("cms-distribution-publication")).toBeVisible();
});

test("validation health renders", async ({ page }) => {
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await expect(page.getByTestId("cms-distribution-validation")).toBeVisible();
});

test("theme coverage renders", async ({ page }) => {
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await expect(page.getByTestId("cms-distribution-theme")).toBeVisible();
});

test("asset status renders", async ({ page }) => {
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await expect(page.getByTestId("cms-distribution-assets")).toBeVisible();
});

test("recent revisions render", async ({ page }) => {
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await expect(page.getByTestId("cms-revision-timeline")).toBeVisible();
});

test("attention queue links correctly", async ({ page }) => {
  await page.goto("/testdash/cms", { waitUntil: "load" });
  const queue = page.getByTestId("cms-attention-queue");
  await expect(queue).toBeVisible();
  const firstLink = queue.getByRole("link").first();
  await expect(firstLink).toHaveAttribute("href", /\/testdash\/cms\//);
});

test("cms navigation between sections", async ({ page }) => {
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await navigateCmsSection(page, "Pages", /\/testdash\/cms\/pages/);
  await navigateCmsSection(page, "Sections", /\/testdash\/cms\/sections/);
  await navigateCmsSection(page, "Assets", /\/testdash\/cms\/assets/);
});

for (const viewport of viewports.filter((v) => v.width <= 390)) {
  test(`no horizontal overflow on overview at ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto("/testdash/cms", { waitUntil: "load" });
    await expectCmsReady(page);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    expect(overflow).toBe(false);
  });
}

test("loading state renders", async ({ page }) => {
  await page.goto("/testdash/cms?previewLoading=1", { waitUntil: "load" });
  await expect(page.getByLabel("Loading CMS foundation")).toBeVisible();
});

test("empty state renders", async ({ page }) => {
  await page.goto("/testdash/cms/pages?previewEmpty=1", { waitUntil: "load" });
  await expect(page.getByText("No CMS records match filters")).toBeVisible();
});

test("controlled error state renders", async ({ page }) => {
  await page.goto("/testdash/cms?previewError=1", { waitUntil: "load" });
  await expect(page.getByText("Unable to load CMS")).toBeVisible();
});

test("no brand selector exists on overview", async ({ page }) => {
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await expect(page.getByText(/Brand:\s*JetPakistan/i)).toBeVisible();
  await expect(page.getByRole("combobox", { name: /brand/i })).toHaveCount(0);
});

test("dashboard preview notice is visible", async ({ page }) => {
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("scheduled queue panel renders", async ({ page }) => {
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await expect(page.getByTestId("cms-scheduled-queue")).toBeVisible();
});

test("content type distribution renders", async ({ page }) => {
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await expect(page.getByTestId("cms-distribution-content-type")).toBeVisible();
});

test("reports route remains functional", async ({ page }) => {
  await page.goto("/testdash/reports", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Reports", level: 1 })).toBeVisible();
});

test("keyboard focus is visible on overview nav", async ({ page }) => {
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await page.keyboard.press("Tab");
  const focused = page.locator(":focus-visible");
  await expect(focused).toBeVisible();
});

test("mobile navigation includes CMS", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await page.getByRole("button", { name: "Open navigation menu" }).click();
  await expect(page.getByLabel("Dashboard navigation").getByRole("link", { name: "CMS" })).toBeVisible();
});

test("invalid URL state falls back on overview", async ({ page }) => {
  await page.goto("/testdash/cms?status=bad&previewMode=invalid", { waitUntil: "load" });
  await expectCmsReady(page);
});

test("browser back from pages to overview", async ({ page }) => {
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await navigateCmsSection(page, "Pages", /\/testdash\/cms\/pages/);
  await page.goBack();
  await expect(page).toHaveURL(/\/testdash\/cms\/?$/, { timeout: 30_000 });
  await expectCmsReady(page);
});

test("no mutation API calls on overview", async ({ page }) => {
  const mutations: string[] = [];
  page.on("request", (req) => {
    if (["POST", "PUT", "PATCH", "DELETE"].includes(req.method())) {
      mutations.push(req.url());
    }
  });
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await expectCmsReady(page);
  expect(mutations).toHaveLength(0);
});

test("attention queue is read-only", async ({ page }) => {
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await expect(page.getByRole("button", { name: /publish/i })).toHaveCount(0);
  await expect(page.getByRole("button", { name: /^save$/i })).toHaveCount(0);
});
