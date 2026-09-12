import { test, expect, type Page } from "@playwright/test";
import {
  assertInVisualViewport,
  assertMinTouchTarget,
  assertNoOverlap,
  getLocatorRect,
  getVisualViewport,
  scrollToPosition,
} from "./helpers/fab-geometry";

const VIEWPORTS = [
  { name: "MOBILE_320", width: 320, height: 568 },
  { name: "MOBILE_360", width: 360, height: 640 },
  { name: "MOBILE_375", width: 375, height: 667 },
  { name: "MOBILE_390", width: 390, height: 844 },
  { name: "MOBILE_412", width: 412, height: 915 },
  { name: "MOBILE_430", width: 430, height: 932 },
  { name: "MOBILE_LANDSCAPE_568", width: 568, height: 320 },
  { name: "MOBILE_LANDSCAPE_667", width: 667, height: 375 },
  { name: "MOBILE_LANDSCAPE_844", width: 844, height: 390 },
  { name: "MOBILE_LANDSCAPE_915", width: 915, height: 412 },
  { name: "TABLET_600", width: 600, height: 960 },
  { name: "TABLET_768", width: 768, height: 1024 },
  { name: "TABLET_820", width: 820, height: 1180 },
  { name: "TABLET_LANDSCAPE_960", width: 960, height: 600 },
  { name: "TABLET_LANDSCAPE_1024", width: 1024, height: 768 },
  { name: "TABLET_LANDSCAPE_1180", width: 1180, height: 820 },
  { name: "DESKTOP_1280", width: 1280, height: 720 },
];

const MOBILE_ROUTES = ["/", "/flights", "/groups", "/support", "/login"];

async function enableAssistant(page: Page) {
  const configBody = {
    brand_name: "JetPakistan",
    domain: "jetpakistan.pk",
    app_url: "http://127.0.0.1:3002",
    ai_assistant_enabled: true,
    contact: {
      phone: "+92 311 1222427",
      email: "ota@jetpakistan.com",
      whatsapp: "+92 311 1222427",
    },
    legal_paths: { terms: "/terms", privacy: "/privacy" },
    support_path: "/support",
    contact_path: "/contact",
    booking_lookup_path: "/lookup-booking",
    groups_path: "/groups",
    social_links: [],
    default_seo: { title: "JetPakistan", description: "JetPakistan", robots: "index,follow" },
    source: "laravel",
  };

  for (const pattern of ["**/laravel/api/public/content/config", "**/api/public/content/config"]) {
    await page.route(pattern, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(configBody),
      });
    });
  }
}

async function assertFabGeometry(page: Page, label: string) {
  const ask = page.getByTestId("ask-jetpakistan-fab");
  const dock = page.getByTestId("public-fab-trigger");
  const vv = await getVisualViewport(page);

  const askVisible = await ask.isVisible().catch(() => false);
  const dockVisible = await dock.isVisible().catch(() => false);

  if (askVisible) {
    const askRect = await getLocatorRect(ask);
    expect(askRect, `${label} ask rect`).not.toBeNull();
    assertInVisualViewport(askRect!, vv);
    assertMinTouchTarget(askRect!, `${label} ask`);
  }

  if (dockVisible) {
    const dockRect = await getLocatorRect(dock);
    expect(dockRect, `${label} dock rect`).not.toBeNull();
    assertInVisualViewport(dockRect!, vv);
    assertMinTouchTarget(dockRect!, `${label} dock`);
  }

  if (askVisible && dockVisible) {
    const askRect = await getLocatorRect(ask);
    const dockRect = await getLocatorRect(dock);
    assertNoOverlap(askRect!, dockRect!, label);
  }
}

for (const viewport of VIEWPORTS) {
  const isMobile = viewport.width < 1024;

  test.describe(`JP-FAB-GEOMETRY ${viewport.name}`, () => {
    test.beforeEach(async ({ page }) => {
      await page.setViewportSize({ width: viewport.width, height: viewport.height });
      if (!process.env.PLAYWRIGHT_PRODUCTION_URL) {
        await enableAssistant(page);
      }
    });

    if (isMobile) {
      for (const route of MOBILE_ROUTES) {
        for (const scrollPos of ["top", "middle", "footer"] as const) {
          test(`${route} @ ${scrollPos}`, async ({ page }) => {
            await page.goto(route, { waitUntil: "load" });
            await scrollToPosition(page, scrollPos);
            await assertFabGeometry(page, `${viewport.name} ${route} ${scrollPos}`);
          });
        }
      }

      test("dock open — no panel overlap", async ({ page }) => {
        await page.goto("/", { waitUntil: "load" });
        const dock = page.getByTestId("public-fab-trigger");
        await expect(dock).toBeVisible({ timeout: 30_000 });
        await dock.click();
        await page.waitForTimeout(300);

        const ask = page.getByTestId("ask-jetpakistan-fab");
        const panel = page.locator(".jp-public-fab-panel");
        const vv = await getVisualViewport(page);

        const askHidden = await ask.evaluate((el) => {
          const style = window.getComputedStyle(el);
          return style.visibility === "hidden" || style.pointerEvents === "none";
        });
        expect(askHidden, "Ask FAB must hide while dock panel is open").toBe(true);

        const panelRect = await getLocatorRect(panel);
        if (panelRect) {
          assertInVisualViewport(panelRect, vv);
        }
      });

      test("ask open — dock hidden", async ({ page }) => {
        await page.goto("/", { waitUntil: "load" });
        const ask = page.getByTestId("ask-jetpakistan-fab");
        await expect(ask).toBeVisible({ timeout: 30_000 });
        await ask.click();
        await expect(page.locator("html")).toHaveAttribute("data-jp-ask-open", "1", {
          timeout: 10_000,
        });

        const dock = page.getByTestId("public-fab-dock");
        const hidden = await dock.evaluate((el) => {
          const style = window.getComputedStyle(el);
          return style.visibility === "hidden" || style.pointerEvents === "none";
        });
        expect(hidden).toBe(true);
      });
    } else {
      test("desktop — FABs hidden on lg+", async ({ page }) => {
        await page.goto("/", { waitUntil: "load" });
        await expect(page.getByTestId("public-fab-dock")).toBeHidden();
      });
    }
  });
}
