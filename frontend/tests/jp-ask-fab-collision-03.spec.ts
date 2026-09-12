import { test, expect, type Page } from "@playwright/test";
import { assertNoOverlap, getLocatorRect } from "./helpers/fab-geometry";

const MOBILE = { width: 390, height: 844 };

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

test.describe("JP-ASK-FAB-03 collision", () => {
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize(MOBILE);
    await enableAssistant(page);
    await page.goto("/", { waitUntil: "load" });
  });

  test("Ask FAB and public dock do not overlap when both visible", async ({ page }) => {
    const ask = page.getByTestId("ask-jetpakistan-fab");
    const dock = page.getByTestId("public-fab-trigger");

    await expect(ask, "Ask JetPakistan FAB must be visible on the public homepage").toBeVisible({
      timeout: 30_000,
    });
    await expect(dock, "Public floating action dock must be visible on the public homepage").toBeVisible({
      timeout: 30_000,
    });

    await page.waitForFunction(() => {
      const askBottom = Number.parseFloat(
        getComputedStyle(document.documentElement).getPropertyValue("--jp-ask-fab-bottom"),
      );
      const dockBottom = Number.parseFloat(
        getComputedStyle(document.documentElement).getPropertyValue("--jp-dock-fab-bottom"),
      );
      return askBottom > dockBottom + 50;
    });

    const askBox = await getLocatorRect(ask);
    const dockBox = await getLocatorRect(dock);
    expect(askBox).not.toBeNull();
    expect(dockBox).not.toBeNull();
    assertNoOverlap(askBox!, dockBox!, "homepage collision");
  });

  test("dock hidden while Ask panel is open", async ({ page }) => {
    const ask = page.getByTestId("ask-jetpakistan-fab");
    await expect(ask, "Ask JetPakistan FAB must be visible on the public homepage").toBeVisible({
      timeout: 30_000,
    });

    await ask.click();
    await page.waitForTimeout(400);

    const dock = page.getByTestId("public-fab-dock");
    const hidden = await dock.evaluate((el) => {
      const style = window.getComputedStyle(el);
      return style.visibility === "hidden" || style.pointerEvents === "none";
    });
    expect(hidden).toBe(true);
  });
});
