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
  const logoResponse = await page.request.get("/client-assets/jetpk/logo/logo.svg");
  expect(logoResponse.ok()).toBeTruthy();
  await expect(page.getByRole("navigation", { name: "Primary" })).toBeVisible();
  await expect(page.getByTestId("theme-switch")).toBeVisible();
});

test("mobile menu opens, closes, and locks background scroll", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/", { waitUntil: "load" });

  const menuButton = page.getByRole("button", { name: "Open navigation menu" });
  await menuButton.click();

  const dialog = page.getByRole("dialog", { name: "Mobile navigation" });
  await expect(dialog).toBeVisible();
  await expect(page.locator("body")).toHaveCSS("overflow", "hidden");

  await dialog.getByRole("button", { name: "Close navigation menu" }).click();
  await expect(dialog).toBeHidden();
  await expect(page.locator("body")).not.toHaveCSS("overflow", "hidden");
});

test("escape closes mobile menu and returns focus to trigger", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/", { waitUntil: "load" });

  const menuButton = page.getByRole("button", { name: "Open navigation menu" });
  await menuButton.click();
  await expect(page.getByRole("dialog", { name: "Mobile navigation" })).toBeVisible();

  await page.keyboard.press("Escape");
  await expect(page.getByRole("dialog", { name: "Mobile navigation" })).toBeHidden();
  await expect(menuButton).toBeFocused();
});

test("keyboard navigation reaches primary header controls", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/", { waitUntil: "load" });

  await page.keyboard.press("Tab");
  await expect(page.getByRole("link", { name: "Skip to main content" })).toBeFocused();

  await page.keyboard.press("Tab");
  await expect(page.getByRole("link", { name: "JetPakistan home" })).toBeFocused();
});

test("reduced motion preference disables flight-path animation", async ({ page }) => {
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.goto("/", { waitUntil: "load" });

  const animationState = await page.getByRole("img", { name: "Decorative flight path" }).evaluate((element) => {
    const styles = getComputedStyle(element);
    return {
      animationName: styles.animationName,
      animationDuration: styles.animationDuration,
    };
  });

  expect(
    animationState.animationName === "none" ||
      animationState.animationDuration === "0s" ||
      animationState.animationDuration === "0.01ms",
  ).toBeTruthy();
});
