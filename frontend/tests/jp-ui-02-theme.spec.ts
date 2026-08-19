import { test, expect } from "@playwright/test";

const THEME_KEY = "jp-theme-preference";

async function clearThemePreference(page: import("@playwright/test").Page) {
  await page.goto("/", { waitUntil: "domcontentloaded" });
  await page.evaluate((key) => localStorage.removeItem(key), THEME_KEY);
}

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("theme defaults to light (DAY)", async ({ page }) => {
  await clearThemePreference(page);
  await page.emulateMedia({ colorScheme: "dark" });
  await page.reload({ waitUntil: "load" });
  await expect(page.locator("html")).toHaveAttribute("data-theme", "light");
  await expect(page.getByTestId("theme-switch")).toHaveAttribute("data-theme-preference", "light");
});

test("explicit dark preference persists after reload", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  const themeSwitch = page.getByTestId("theme-switch");
  await themeSwitch.click();
  await expect(themeSwitch).toHaveAttribute("data-theme-preference", "dark");
  await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
  await page.reload({ waitUntil: "load" });
  await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
  await expect(page.getByTestId("theme-switch")).toHaveAttribute("data-theme-preference", "dark");
});

test("invalid stored theme value falls back to light", async ({ page }) => {
  await page.goto("/", { waitUntil: "domcontentloaded" });
  await page.evaluate(() => localStorage.setItem("jp-theme-preference", "invalid-value"));
  await page.reload({ waitUntil: "load" });
  await expect(page.getByTestId("theme-switch")).toHaveAttribute("data-theme-preference", "light");
});

test("theme switch is keyboard operable", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await clearThemePreference(page);
  await page.reload({ waitUntil: "load" });
  const themeSwitch = page.getByTestId("theme-switch");
  await themeSwitch.focus();
  await page.keyboard.press("Enter");
  await expect(themeSwitch).toHaveAttribute("data-theme-preference", "dark");
});

test("only one theme switch on desktop homepage", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/", { waitUntil: "load" });
  await expect(page.getByTestId("theme-switch")).toHaveCount(1);
});

test("theme switch appears in mobile drawer", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/", { waitUntil: "load" });
  await page.getByRole("button", { name: "Open navigation menu" }).click();
  await expect(page.getByRole("dialog", { name: "Mobile navigation" }).getByTestId("theme-switch")).toBeVisible();
});

test("theme persists across public to login navigation", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/", { waitUntil: "load" });
  const themeSwitch = page.getByTestId("theme-switch");
  await themeSwitch.click();
  await expect(themeSwitch).toHaveAttribute("data-theme-preference", "dark");
  await page.goto("/login", { waitUntil: "load" });
  await expect(page.getByTestId("theme-switch")).toHaveAttribute("data-theme-preference", "dark");
  await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
});

test("no hydration mismatch console errors on theme pages", async ({ page }) => {
  const errors: string[] = [];
  page.on("console", (msg) => {
    if (msg.type() === "error" && /hydration/i.test(msg.text())) {
      errors.push(msg.text());
    }
  });
  await page.goto("/", { waitUntil: "load" });
  await page.getByTestId("theme-switch").click();
  expect(errors).toEqual([]);
});
