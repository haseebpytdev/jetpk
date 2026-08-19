import { test, expect } from "@playwright/test";

/**
 * JetPakistan internal theme must be authoritative.
 * OS/browser prefers-color-scheme must never activate dark styling alone.
 */

async function setInternalTheme(page: import("@playwright/test").Page, theme: "light" | "dark") {
  await page.addInitScript((pref) => {
    localStorage.setItem("jp-theme-preference", pref);
  }, theme);
}

test.describe("homepage theme ownership", () => {
  test("JP_DAY + OS_DARK stays light (data-theme and search panel)", async ({ page }) => {
    await setInternalTheme(page, "light");
    await page.emulateMedia({ colorScheme: "dark" });
    await page.goto("/", { waitUntil: "load" });

    await expect(page.locator("html")).toHaveAttribute("data-theme", "light");
    await expect(page.getByTestId("theme-switch")).toHaveAttribute("data-theme-preference", "light");

    const searchBg = await page.getByTestId("search-module").evaluate((el) => getComputedStyle(el).backgroundColor);
    // Whitish/silver panel — not charcoal slate
    expect(searchBg).toMatch(/rgba?\(/);
    const match = searchBg.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
    expect(match).toBeTruthy();
    const [, r, g, b] = match!.map(Number);
    expect(r).toBeGreaterThan(200);
    expect(g).toBeGreaterThan(200);
    expect(b).toBeGreaterThan(200);
  });

  test("JP_DAY + OS_LIGHT stays light", async ({ page }) => {
    await setInternalTheme(page, "light");
    await page.emulateMedia({ colorScheme: "light" });
    await page.goto("/", { waitUntil: "load" });
    await expect(page.locator("html")).toHaveAttribute("data-theme", "light");
  });

  test("JP_NIGHT activates only via internal preference", async ({ page }) => {
    await setInternalTheme(page, "dark");
    await page.emulateMedia({ colorScheme: "light" });
    await page.goto("/", { waitUntil: "load" });
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    await expect(page.getByTestId("theme-switch")).toHaveAttribute("data-theme-preference", "dark");
  });

  test("legacy system preference resolves to light", async ({ page }) => {
    await page.addInitScript(() => {
      localStorage.setItem("jp-theme-preference", "system");
    });
    await page.emulateMedia({ colorScheme: "dark" });
    await page.goto("/", { waitUntil: "load" });
    await expect(page.locator("html")).toHaveAttribute("data-theme", "light");
    await expect(page.getByTestId("theme-switch")).toHaveAttribute("data-theme-preference", "light");
  });

  test("canonical PNG logo loads (not generated SVG text mark)", async ({ page }) => {
    await setInternalTheme(page, "light");
    await page.goto("/", { waitUntil: "load" });
    const logo = page.getByRole("banner").getByTestId("jetpakistan-header-logo");
    await expect(logo).toHaveAttribute("src", /\/client-assets\/jetpk\/logo\/logo\.png/);
    const res = await page.request.get("/client-assets/jetpk/logo/logo.png");
    expect(res.ok()).toBeTruthy();
    const body = await res.body();
    expect(body[0]).toBe(0x89);
    expect(body.length).toBeGreaterThan(10_000);
  });

  test("desktop card rails expose 4-card capacity", async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await setInternalTheme(page, "light");
    await page.goto("/", { waitUntil: "load" });

    const rails = page.locator("[data-full-card-count]");
    const count = await rails.count();
    expect(count).toBeGreaterThan(0);
    for (let i = 0; i < count; i += 1) {
      await expect(rails.nth(i)).toHaveAttribute("data-full-card-count", "4");
    }

    const routeCards = page.getByTestId("route-card");
    const routeCount = await routeCards.count();
    if (routeCount >= 4) {
      await expect(routeCards.nth(3)).toBeVisible();
      await expect(page.getByRole("button", { name: "Scroll routes left" })).toHaveCount(0);
    }

    const destCards = page.getByTestId("destination-card");
    const destCount = await destCards.count();
    if (destCount >= 4) {
      await expect(destCards.nth(3)).toBeVisible();
      await expect(page.getByRole("button", { name: "Scroll destinations left" })).toHaveCount(0);
    }

    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
    );
    expect(overflow).toBeLessThanOrEqual(1);
  });
});
