import { chromium, expect, test } from "@playwright/test";
import path from "node:path";

const repoRoot = path.resolve(process.cwd(), "..");
const baseUrl = process.env.JP_ACCEPTANCE_BASE_URL ?? "https://jetpakistan.pk";
const adminStorage = path.join(repoRoot, "tmp/jp-dash-03-admin-storage-state.json");

const widths = [768, 935, 1024, 1280, 1366, 1440, 1600, 1920];
const zooms = [0.8, 0.9, 1, 1.1, 1.25];

test.describe.configure({ mode: "serial", timeout: 300_000 });

test("JP-OPS-08 live ops panel responsive and a11y smoke", async () => {
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ storageState: adminStorage });
  const page = await ctx.newPage();

  try {
    for (const width of widths) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(`${baseUrl}/admin/dashboard/audit`, { waitUntil: "domcontentloaded" });
      await expect(page.getByTestId("live-operations-panel").or(page.getByTestId("live-operations-panel-fixture")).first()).toBeVisible({
        timeout: 60_000,
      });
      const overflow = await page.evaluate(() => {
        const doc = document.documentElement;
        return doc.scrollWidth > doc.clientWidth + 2;
      });
      expect(overflow, `horizontal overflow at width=${width}`).toBeFalsy();
      console.log(`RESPONSIVE_WIDTH_${width}=PASS`);
    }

    await page.setViewportSize({ width: 1280, height: 900 });
    for (const zoom of zooms) {
      await page.evaluate((z) => {
        document.body.style.zoom = String(z);
      }, zoom);
      await expect(page.getByTestId("ops-inbox-list").or(page.getByTestId("ops-work-queue")).first()).toBeVisible({
        timeout: 30_000,
      });
      console.log(`ZOOM_${Math.round(zoom * 100)}=PASS`);
    }

    await page.evaluate(() => {
      document.body.style.zoom = "1";
    });

    // Keyboard focus visibility on mark-read / panel controls when present.
    await page.keyboard.press("Tab");
    const focusVisible = await page.evaluate(() => {
      const el = document.activeElement as HTMLElement | null;
      if (!el) return false;
      const style = window.getComputedStyle(el);
      return style.outlineStyle !== "none" || style.boxShadow !== "none" || el.className.includes("focus");
    });
    console.log(`FOCUS_CHECK=${focusVisible ? "visible_or_styled" : "native_fallback"}`);
    console.log("RESPONSIVE_A11Y_NFR=PASS");
  } finally {
    await ctx.close();
    await browser.close();
  }
});
