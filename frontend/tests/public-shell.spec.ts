import { test, expect } from "@playwright/test";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("public shell renders header, hero, and footer", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });

  await expect(page.getByRole("banner")).toBeVisible();
  await expect(page.getByRole("contentinfo")).toBeVisible();
  await expect(page.getByRole("heading", { level: 1, name: /Explore the world with/i })).toBeVisible();
  await expect(page.getByTestId("search-module")).toBeVisible();
  await expect(page.getByLabel("JetPakistan home")).toBeVisible();
  const headerLogo = page.getByRole("banner").getByTestId("jetpakistan-header-logo");
  await expect(headerLogo).toBeVisible();
  await expect(headerLogo).toHaveAttribute("alt", /JetPakistan/i);
  const logoResponse = await page.request.get("/client-assets/jetpk/logo/logo.png");
  expect(logoResponse.ok()).toBeTruthy();
  expect(logoResponse.headers()["content-type"] ?? "").toMatch(/image\/png/i);
  const logoBytes = await logoResponse.body();
  expect(logoBytes.length).toBeGreaterThan(10_000);
  // PNG magic
  expect(logoBytes[0]).toBe(0x89);
  expect(logoBytes[1]).toBe(0x50);
  expect(logoBytes[2]).toBe(0x4e);
  expect(logoBytes[3]).toBe(0x47);
  await expect(page.getByRole("navigation", { name: "Primary" })).toBeVisible();
  await expect(page.getByRole("banner").getByTestId("theme-switch")).toBeVisible();
});

test("mobile fab opens quick actions and closes on toggle", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/", { waitUntil: "load" });

  const fabTrigger = page.getByTestId("public-fab-trigger");
  await fabTrigger.click();

  const quickActions = page.getByRole("group", { name: "JetPakistan quick actions" });
  await expect(quickActions).toBeVisible();
  await expect(page.getByTestId("fab-account")).toBeVisible();

  await fabTrigger.click();
  await expect(quickActions).toBeHidden();
});

test("escape closes mobile fab and returns focus to trigger", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/", { waitUntil: "load" });

  const fabTrigger = page.getByTestId("public-fab-trigger");
  await fabTrigger.click();
  await expect(page.getByRole("group", { name: "JetPakistan quick actions" })).toBeVisible();

  await page.keyboard.press("Escape");
  await expect(page.getByRole("group", { name: "JetPakistan quick actions" })).toBeHidden();
  await expect(fabTrigger).toBeFocused();
});

test("keyboard navigation reaches primary header controls", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/", { waitUntil: "load" });

  await page.keyboard.press("Tab");
  await expect(page.getByRole("link", { name: "Skip to main content" })).toBeFocused();

  await page.keyboard.press("Tab");
  await expect(page.getByRole("link", { name: "JetPakistan home" })).toBeFocused();
});

test("reduced motion homepage omits decorative flight-path ornament", async ({ page }) => {
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.goto("/", { waitUntil: "load" });

  await expect(page.getByRole("img", { name: "Decorative flight path" })).toHaveCount(0);
  await expect(page.getByTestId("homepage-public-hero")).toBeVisible();
});
