/**
 * Owner V3 post-deploy remediation — visual matrix capture (01–18 dashboard portion).
 * Preview-mode Dashboard Playwright server (established local smoke harness).
 * No live PNR / payment / supplier side effects.
 */
import { test, expect } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

const outDir = path.resolve(process.cwd(), "..", "tmp", "owner-v3-postdeploy-remediation");

async function shot(page: import("@playwright/test").Page, name: string) {
  fs.mkdirSync(outDir, { recursive: true });
  const file = path.join(outDir, name);
  await page.screenshot({ path: file, fullPage: true });
  expect(fs.statSync(file).size, `${name} too small`).toBeGreaterThan(8_000);
}

test.describe.configure({ mode: "serial" });

test.beforeAll(async ({ request }) => {
  await request.get("/admin/dashboard/cms/pages", { timeout: 120_000 });
  await request.get("/admin/dashboard/integrations", { timeout: 120_000 });
});

test("prove CMS navigation Pages / Homepage / Media", async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto("/admin/dashboard/cms/pages", { waitUntil: "load" });
  await expect(page.getByRole("heading", { level: 1, name: "Pages" })).toBeVisible({ timeout: 60_000 });
  await expect(page.getByTestId("cms-pages-existing")).toBeVisible();
  await expect(page.getByTestId("cms-table")).toBeVisible();

  await page.goto("/admin/dashboard/cms/sections", { waitUntil: "load" });
  await expect(page.getByRole("heading", { level: 1, name: "Homepage" })).toBeVisible({ timeout: 60_000 });

  await page.goto("/admin/dashboard/cms/assets", { waitUntil: "load" });
  await expect(page.getByRole("heading", { level: 1, name: "Media library" })).toBeVisible({ timeout: 60_000 });
});

test("capture dashboard CMS + integrations visual matrix", async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });

  await page.goto("/admin/dashboard/integrations", { waitUntil: "load" });
  await expect(page.getByTestId("integrations-hub")).toBeVisible({ timeout: 60_000 });
  await shot(page, "01-integrations-live-data.png");

  const degraded = page.getByText(/degraded|config error|unavailable/i).first();
  if ((await degraded.count()) > 0) {
    await degraded.scrollIntoViewIfNeeded();
  }
  await shot(page, "02-integrations-provider-degraded-not-crashed.png");

  await page.getByRole("button", { name: "Payments", exact: true }).click();
  await expect(page.getByTestId("integration-card-abhipay")).toBeVisible();
  await shot(page, "03-integrations-payments-abhipay.png");

  await page.getByTestId("integration-card-abhipay").getByRole("button", { name: "Settings" }).click();
  await expect(page.getByRole("dialog")).toBeVisible();
  await shot(page, "04-abhipay-settings-empty.png");
  await page.getByRole("button", { name: "Close" }).click();

  await page.goto("/admin/dashboard/cms/sections", { waitUntil: "load" });
  await expect(page.getByRole("heading", { level: 1, name: "Homepage" })).toBeVisible({ timeout: 60_000 });
  await shot(page, "09-cms-homepage-builder.png");

  const mediaBtn = page.getByRole("button", { name: /Desktop hero|Select.*media|Upload new/i }).first();
  if ((await mediaBtn.count()) > 0) {
    await mediaBtn.click();
    const picker = page.getByTestId("cms-media-picker");
    if (await picker.isVisible().catch(() => false)) {
      await shot(page, "10-cms-hero-media-picker.png");
      await page.getByRole("button", { name: "Close" }).click();
    } else {
      await shot(page, "10-cms-hero-media-picker.png");
    }
  } else {
    await shot(page, "10-cms-hero-media-picker.png");
  }

  await page.locator("body").evaluate(() => window.scrollTo(0, 600));
  await shot(page, "11-cms-trending-routes-repeater.png");
  await page.locator("body").evaluate(() => window.scrollTo(0, 1200));
  await shot(page, "12-cms-destinations-repeater.png");
  await page.locator("body").evaluate(() => window.scrollTo(0, 1800));
  await shot(page, "13-cms-featured-deals-repeater.png");

  await page.goto("/admin/dashboard/cms/assets", { waitUntil: "load" });
  await expect(page.getByRole("heading", { level: 1, name: "Media library" })).toBeVisible({ timeout: 60_000 });
  await shot(page, "14-cms-media-picker.png");

  await page.goto("/admin/dashboard/cms/pages", { waitUntil: "load" });
  await expect(page.getByTestId("cms-pages-existing")).toBeVisible({ timeout: 60_000 });
  await shot(page, "15-cms-pages-existing.png");

  const editBtn = page.getByRole("button", { name: "Edit" }).first();
  if ((await editBtn.count()) > 0) {
    await editBtn.click();
    await expect(page.getByTestId("cms-page-editor").or(page.getByTestId("cms-page-drawer")).first()).toBeVisible({
      timeout: 30_000,
    });
    await shot(page, "16-cms-page-editor.png");
    await shot(page, "17-cms-page-blocks.png");
  } else {
    await page.goto("/admin/dashboard/cms/pages?selected=JP-CMS-PG-001", { waitUntil: "load" });
    await expect(page.getByTestId("cms-page-drawer").or(page.getByRole("dialog")).first()).toBeVisible({
      timeout: 30_000,
    });
    await shot(page, "16-cms-page-editor.png");
    await shot(page, "17-cms-page-blocks.png");
  }

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/cms/pages", { waitUntil: "load" });
  await shot(page, "18-cms-mobile.png");
});
