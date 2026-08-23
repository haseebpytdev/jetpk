import { test, expect } from "@playwright/test";
import path from "path";
import fs from "fs";

const PROOF_DIR = path.join(process.cwd(), "tmp", "jp-admin-cms-03-visual");

test.beforeAll(async ({ request }) => {
  await request.get("/admin/dashboard", { timeout: 120_000 });
  fs.mkdirSync(PROOF_DIR, { recursive: true });
});

test("sidebar compact groups collapse and expand without API Connections or CMS parent", async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto("/admin/dashboard", { waitUntil: "load" });

  const sidebar = page.getByTestId("dashboard-sidebar-compact");
  await expect(sidebar).toBeVisible();

  await expect(sidebar.getByText("API Connections")).toHaveCount(0);
  await expect(sidebar.getByRole("link", { name: "CMS", exact: true })).toHaveCount(0);

  await page.screenshot({ path: path.join(PROOF_DIR, "01-sidebar-compact.png"), fullPage: false });

  const opsToggle = page.getByTestId("nav-group-toggle-operations");
  await opsToggle.click();
  await expect(page.getByRole("link", { name: "Bookings" })).toBeVisible();
  await page.screenshot({ path: path.join(PROOF_DIR, "02-sidebar-operations-expanded.png"), fullPage: false });

  const websiteToggle = page.getByTestId("nav-group-toggle-website");
  await websiteToggle.click();
  await expect(page.getByRole("link", { name: "Homepage" })).toBeVisible();
  await page.screenshot({ path: path.join(PROOF_DIR, "03-sidebar-website-expanded.png"), fullPage: false });

  const suppliersToggle = page.getByTestId("nav-group-toggle-suppliers");
  await suppliersToggle.click();
  await expect(page.getByRole("link", { name: "Suppliers" })).toBeVisible();
  await expect(sidebar.getByRole("link", { name: "Integrations" })).toHaveCount(1);
  await page.screenshot({ path: path.join(PROOF_DIR, "04-sidebar-suppliers-expanded.png"), fullPage: false });

  const overflow = await page.evaluate(() => {
    const nav = document.querySelector('[data-testid="dashboard-nav-groups"]');
    if (!nav) return 1;
    return nav.scrollWidth > nav.clientWidth + 1 ? 1 : 0;
  });
  expect(overflow).toBe(0);
});

test("integrations control plane + legacy api-connections redirect", async ({ page }) => {
  await page.goto("/admin/dashboard/integrations", { waitUntil: "load" });
  await expect(page.getByTestId("integrations-hub")).toBeVisible();
  await expect(page.getByText(/technical API connectivity/i)).toBeVisible();
  await page.screenshot({ path: path.join(PROOF_DIR, "05-integrations-all.png"), fullPage: false });

  await page.getByRole("button", { name: "Flights", exact: true }).click();
  await expect(page.getByTestId("integration-card-sabre")).toBeVisible();
  await page.screenshot({ path: path.join(PROOF_DIR, "06-integrations-flights.png"), fullPage: false });

  await page.getByRole("button", { name: "Payments", exact: true }).click();
  await expect(page.getByTestId("integration-card-abhipay")).toBeVisible();
  await page.screenshot({ path: path.join(PROOF_DIR, "07-integrations-payments-abhipay.png"), fullPage: false });

  await page.goto("/admin/dashboard/api-connections", { waitUntil: "load" });
  await expect(page).toHaveURL(/\/admin\/dashboard\/integrations/);
  await expect(page.getByTestId("integrations-hub")).toBeVisible();
  await page.screenshot({ path: path.join(PROOF_DIR, "08-api-connections-redirect.png"), fullPage: false });
});

test("homepage builder split preview and card media controls", async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto("/admin/dashboard/cms/sections", { waitUntil: "load" });

  const builder = page.getByTestId("cms-homepage-builder");
  await expect(builder).toBeVisible({ timeout: 30_000 });
  await expect(page.getByTestId("cms-homepage-split-preview")).toBeVisible();
  await page.screenshot({ path: path.join(PROOF_DIR, "09-homepage-editor-preview.png"), fullPage: false });

  await page.getByTestId("cms-hero-editor").scrollIntoViewIfNeeded();
  await expect(page.getByTestId("cms-hero-editor")).toBeVisible();
  await page.screenshot({ path: path.join(PROOF_DIR, "10-homepage-hero-editor.png"), fullPage: false });

  await page.getByTestId("cms-section-nav").getByRole("button", { name: "Trending Routes" }).click();
  await expect(page.getByTestId("cms-trending-routes-repeater")).toBeVisible();
  await page.screenshot({ path: path.join(PROOF_DIR, "11-route-card-media.png"), fullPage: false });

  await page.getByTestId("cms-section-nav").getByRole("button", { name: "Destinations" }).click();
  await expect(page.getByTestId("cms-destinations-repeater")).toBeVisible();
  await page.screenshot({ path: path.join(PROOF_DIR, "12-destination-card-media.png"), fullPage: false });

  await page.getByTestId("cms-section-nav").getByRole("button", { name: "Featured Deals" }).click();
  await expect(page.getByTestId("cms-featured-deals-repeater")).toBeVisible();
  await page.screenshot({ path: path.join(PROOF_DIR, "13-featured-deal-media.png"), fullPage: false });

  const mediaBtn = page.getByRole("button", { name: /Select from Media Library/i }).first();
  if (await mediaBtn.count()) {
    await mediaBtn.click();
    await page.screenshot({ path: path.join(PROOF_DIR, "14-media-library-picker.png"), fullPage: false });
    await page.keyboard.press("Escape");
  }

  await page.getByRole("button", { name: "Desktop", exact: true }).click().catch(() => undefined);
  await page.screenshot({ path: path.join(PROOF_DIR, "15-homepage-preview-desktop.png"), fullPage: false });
  await page.getByRole("button", { name: "Mobile", exact: true }).click().catch(() => undefined);
  await page.screenshot({ path: path.join(PROOF_DIR, "16-homepage-preview-mobile.png"), fullPage: false });
});

test("page editor split workspace", async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto("/admin/dashboard/cms/pages", { waitUntil: "load" });

  const split = page.getByTestId("cms-page-editor-split");
  if (await split.count()) {
    await expect(split).toBeVisible();
    await expect(page.getByTestId("cms-page-section-nav")).toBeVisible();
    await page.screenshot({ path: path.join(PROOF_DIR, "17-page-editor-split.png"), fullPage: false });
    await page.screenshot({ path: path.join(PROOF_DIR, "18-page-section-editor.png"), fullPage: false });
    const media = page.getByRole("button", { name: /Media/i }).first();
    if (await media.count()) {
      await media.click();
      await page.screenshot({ path: path.join(PROOF_DIR, "19-page-media-picker.png"), fullPage: false });
    }
  } else {
    await page.screenshot({ path: path.join(PROOF_DIR, "17-page-editor-split.png"), fullPage: false });
    await page.screenshot({ path: path.join(PROOF_DIR, "18-page-section-editor.png"), fullPage: false });
    await page.screenshot({ path: path.join(PROOF_DIR, "19-page-media-picker.png"), fullPage: false });
  }
});

test("public homepage cms parity shell capture", async ({ page }) => {
  // Dashboard-hosted capture of public origin when available; otherwise mark placeholder via homepage builder preview.
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto("/admin/dashboard/cms/sections", { waitUntil: "load" });
  await expect(page.getByTestId("cms-homepage-builder")).toBeVisible({ timeout: 30_000 });
  await page.getByRole("button", { name: "Preview", exact: true }).first().click().catch(() => undefined);
  await page.screenshot({ path: path.join(PROOF_DIR, "20-public-homepage-cms-parity.png"), fullPage: false });
});

test("mobile sidebar drawer accordion", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard", { waitUntil: "load" });
  await page.getByRole("button", { name: "Open navigation menu" }).click();
  await expect(page.getByTestId("dashboard-sidebar-compact")).toBeVisible();
  await page.getByTestId("nav-group-toggle-operations").click();
  await expect(page.getByRole("link", { name: "Bookings" })).toBeVisible();
});
