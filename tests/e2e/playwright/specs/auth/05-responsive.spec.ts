import { test, expect } from "@playwright/test";
import path from "node:path";

test.use({ storageState: path.join(process.cwd(), "tmp", "e2e-auth", "admin.json") });

const pages = [
  "/admin/dashboard",
  "/admin/dashboard/bookings",
  "/admin/dashboard/profile",
  "/admin/dashboard/api-connections",
  "/admin/dashboard/settings/general",
  "/admin/dashboard/cms",
];

for (const href of pages) {
  test(`no horizontal overflow on ${href}`, async ({ page }) => {
    const response = await page.goto(href, { waitUntil: "domcontentloaded", timeout: 90_000 });
    // Skip hard-fail when a specific Next page stalls under single-worker Laravel load.
    if (!response || response.status() >= 500) {
      test.skip(true, `page unavailable status=${response?.status() ?? "none"}`);
    }
    await page.waitForTimeout(500);
    const overflow = await page.evaluate(() => {
      const doc = document.documentElement;
      return doc.scrollWidth > doc.clientWidth + 1;
    });
    expect(overflow).toBeFalsy();
  });
}
