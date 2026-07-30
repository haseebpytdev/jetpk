import { test, expect } from "@playwright/test";
import { themeStorageValue } from "./visual-audit/jp-ui-02-scenarios";
import { setupPublicBaseline } from "./visual-audit/jp-ui-03a-fixtures";

const THEME_KEY = "jp-theme-preference";

async function applyTheme(page: import("@playwright/test").Page, theme: "light" | "dark" | "system-light" | "system-dark") {
  const { preference, emulateDark } = themeStorageValue(theme);
  await page.emulateMedia({ colorScheme: emulateDark ? "dark" : "light" });
  await page.addInitScript((pref) => {
    localStorage.setItem("jp-theme-preference", pref);
  }, preference);
}

async function assertNoHorizontalOverflow(page: import("@playwright/test").Page) {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(1);
}

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

for (const theme of ["light", "dark", "system-light", "system-dark"] as const) {
  test(`resolved theme is correct for ${theme}`, async ({ page }) => {
    await setupPublicBaseline(page);
    await applyTheme(page, theme);
    await page.goto("/", { waitUntil: "load" });
    const expected = theme === "dark" || theme === "system-dark" ? "dark" : "light";
    await expect(page.locator("html")).toHaveAttribute("data-theme", expected);
    await expect(page.getByTestId("theme-switch")).toHaveAttribute(
      "data-theme-preference",
      theme.startsWith("system") ? "system" : theme,
    );
  });
}

test("explicit light ignores browser scheme change", async ({ page }) => {
  await setupPublicBaseline(page);
  await applyTheme(page, "light");
  await page.goto("/", { waitUntil: "load" });
  await page.emulateMedia({ colorScheme: "dark" });
  await page.waitForTimeout(200);
  await expect(page.locator("html")).toHaveAttribute("data-theme", "light");
});

test("explicit dark ignores browser scheme change", async ({ page }) => {
  await setupPublicBaseline(page);
  await applyTheme(page, "dark");
  await page.goto("/", { waitUntil: "load" });
  await page.emulateMedia({ colorScheme: "light" });
  await page.waitForTimeout(200);
  await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
});

test("system preference tracks browser scheme changes", async ({ page }) => {
  await setupPublicBaseline(page);
  await applyTheme(page, "system-light");
  await page.goto("/", { waitUntil: "load" });
  await expect(page.locator("html")).toHaveAttribute("data-theme", "light");
  await page.emulateMedia({ colorScheme: "dark" });
  await page.waitForTimeout(300);
  await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
});

test("theme persists across public navigation", async ({ page }) => {
  await setupPublicBaseline(page);
  await applyTheme(page, "dark");
  await page.goto("/", { waitUntil: "load" });
  await page.goto("/about-us", { waitUntil: "load" });
  await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
});

test("invalid stored theme value is ignored", async ({ page }) => {
  await setupPublicBaseline(page);
  await page.goto("/", { waitUntil: "domcontentloaded" });
  await page.evaluate(() => localStorage.setItem("jp-theme-preference", "invalid-value"));
  await page.reload({ waitUntil: "load" });
  await expect(page.getByTestId("theme-switch")).toHaveAttribute("data-theme-preference", "system");
});

test("no hydration warnings on themed homepage", async ({ page }) => {
  const hydrationWarnings: string[] = [];
  page.on("console", (msg) => {
    if (msg.type() === "error" && /hydration/i.test(msg.text())) {
      hydrationWarnings.push(msg.text());
    }
  });
  await setupPublicBaseline(page);
  await applyTheme(page, "dark");
  await page.goto("/", { waitUntil: "load" });
  expect(hydrationWarnings).toEqual([]);
});

for (const viewport of [
  { name: "320x700", width: 320, height: 700 },
  { name: "375x812", width: 375, height: 812 },
  { name: "390x844", width: 390, height: 844 },
]) {
  test(`homepage has no horizontal overflow at ${viewport.name}`, async ({ page }) => {
    await setupPublicBaseline(page);
    await applyTheme(page, "dark");
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    await page.goto("/", { waitUntil: "load" });
    await assertNoHorizontalOverflow(page);
  });

  test(`about page has no horizontal overflow at ${viewport.name}`, async ({ page }) => {
    await setupPublicBaseline(page);
    await applyTheme(page, "dark");
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    await page.goto("/about-us", { waitUntil: "load" });
    await assertNoHorizontalOverflow(page);
  });

  test(`support page has no horizontal overflow at ${viewport.name}`, async ({ page }) => {
    await setupPublicBaseline(page);
    await applyTheme(page, "dark");
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    await page.goto("/support", { waitUntil: "load" });
    await assertNoHorizontalOverflow(page);
  });
}

test("homepage at 150% zoom has no horizontal overflow", async ({ page }) => {
  await setupPublicBaseline(page);
  await applyTheme(page, "light");
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/", { waitUntil: "load" });
  await page.evaluate(() => {
    document.documentElement.style.zoom = "1.5";
  });
  await assertNoHorizontalOverflow(page);
});

test("skip link is visible when focused", async ({ page }) => {
  await setupPublicBaseline(page);
  await page.goto("/", { waitUntil: "load" });
  await page.keyboard.press("Tab");
  const skipLink = page.getByRole("link", { name: /skip to main content/i });
  await expect(skipLink).toBeFocused();
});

test("theme switch focus ring is visible in dark mode", async ({ page }) => {
  await setupPublicBaseline(page);
  await applyTheme(page, "dark");
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/", { waitUntil: "load" });
  const themeSwitch = page.getByTestId("theme-switch");
  await themeSwitch.focus();
  await expect(themeSwitch).toBeFocused();
});

test("mobile drawer opens and closes with Escape", async ({ page }) => {
  await setupPublicBaseline(page);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/", { waitUntil: "load" });
  await page.getByRole("button", { name: "Open navigation menu" }).click();
  await expect(page.getByRole("dialog", { name: "Mobile navigation" })).toBeVisible();
  await page.keyboard.press("Escape");
  await expect(page.getByRole("dialog", { name: "Mobile navigation" })).toBeHidden();
});

test("only one theme switch on desktop homepage", async ({ page }) => {
  await setupPublicBaseline(page);
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/", { waitUntil: "load" });
  await expect(page.getByTestId("theme-switch")).toHaveCount(1);
});

test("reduced motion disables decorative hero animation", async ({ page }) => {
  await setupPublicBaseline(page);
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.goto("/", { waitUntil: "load" });
  const animationState = await page
    .getByRole("img", { name: "Decorative flight path" })
    .first()
    .evaluate((element) => {
      const styles = getComputedStyle(element);
      return { animationName: styles.animationName, animationDuration: styles.animationDuration };
    });
  expect(
    animationState.animationName === "none" ||
      animationState.animationDuration === "0s" ||
      animationState.animationDuration === "0.01ms",
  ).toBeTruthy();
});
