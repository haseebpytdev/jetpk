import { test, expect, type Page } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

const OUTPUT_DIR = path.join(process.cwd(), ".visual-audit", "jp-ui-03");

async function capture(page: Page, name: string) {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  await page.screenshot({ path: path.join(OUTPUT_DIR, `${name}.png`), fullPage: true });
}

test.describe("JP-UI-03 public pages visual audit", () => {
  test.beforeAll(() => {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  });

  test("homepage desktop light", async ({ page }) => {
    await page.goto("/", { waitUntil: "load" });
    await page.setViewportSize({ width: 1440, height: 900 });
    await expect(page.getByTestId("search-module")).toBeVisible();
    await capture(page, "homepage-desktop-light-1440");
  });

  test("homepage compact search tabs", async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto("/", { waitUntil: "load" });
    await page.getByRole("tab", { name: "Return" }).click();
    await capture(page, "homepage-search-return-tab");
    await page.getByRole("tab", { name: "Multi-City" }).click();
    await capture(page, "homepage-search-multicity-tab");
    await page.getByRole("tab", { name: "Groups" }).click();
    await capture(page, "homepage-search-group-tab");
  });

  test("homepage mobile", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/", { waitUntil: "load" });
    await capture(page, "homepage-mobile-390");
  });

  test("about page desktop", async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto("/about-us", { waitUntil: "load" });
    await capture(page, "about-desktop-light");
  });

  test("support page desktop", async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto("/support", { waitUntil: "load" });
    await capture(page, "support-desktop-light");
  });

  test("faq page desktop", async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto("/faq", { waitUntil: "load" });
    await capture(page, "faq-desktop-light");
  });
});
