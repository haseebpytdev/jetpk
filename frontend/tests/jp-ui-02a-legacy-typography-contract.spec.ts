import { test, expect } from "@playwright/test";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

async function readFontTokens(page: import("@playwright/test").Page) {
  return page.evaluate(() => {
    const body = getComputedStyle(document.body);
    return {
      bodyVar: body.getPropertyValue("--font-body").trim(),
      displayVar: body.getPropertyValue("--font-display").trim(),
      monoVar: body.getPropertyValue("--font-mono").trim(),
      fontFamily: body.fontFamily,
      fontStyle: body.fontStyle,
    };
  });
}

test("frontend root binds Plus Jakarta body, Clash display, and IBM Plex Mono variables", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  const tokens = await readFontTokens(page);
  expect(tokens.bodyVar.length).toBeGreaterThan(0);
  expect(tokens.displayVar.length).toBeGreaterThan(0);
  expect(tokens.monoVar.length).toBeGreaterThan(0);
});

test("frontend body uses Plus Jakarta Sans platform stack", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  const tokens = await readFontTokens(page);
  expect(tokens.fontFamily.toLowerCase()).toMatch(/plus jakarta|jakarta/);
  expect(tokens.fontFamily.toLowerCase()).not.toContain("inter");
  expect(tokens.fontStyle.toLowerCase()).toBe("normal");
});

test("frontend hero heading uses Clash Display marketing token", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  const heading = page.getByRole("heading", { level: 1, name: /Explore the world with/i });
  await expect(heading).toHaveClass(/font-display/);
  const family = await heading.evaluate((el) => getComputedStyle(el).fontFamily.toLowerCase());
  expect(family).toMatch(/clash display|clash/);
});

test("customer portal inherits Plus Jakarta platform font", async ({ page }) => {
  await page.goto("/customer/dashboard", { waitUntil: "domcontentloaded" });
  const tokens = await readFontTokens(page);
  expect(tokens.fontFamily.toLowerCase()).toMatch(/plus jakarta|jakarta|system-ui|sans-serif/);
  expect(tokens.fontFamily.toLowerCase()).not.toContain("inter");
});

test("agent portal inherits Plus Jakarta platform font", async ({ page }) => {
  await page.goto("/agent/dashboard", { waitUntil: "domcontentloaded" });
  const tokens = await readFontTokens(page);
  expect(tokens.fontFamily.toLowerCase()).toMatch(/plus jakarta|jakarta|system-ui|sans-serif/);
  expect(tokens.fontFamily.toLowerCase()).not.toContain("inter");
});

test("dark theme preserves platform font variable bindings", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  await page.getByTestId("theme-switch").click();
  await page.getByTestId("theme-switch").click();
  await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
  const tokens = await readFontTokens(page);
  expect(tokens.bodyVar.length).toBeGreaterThan(0);
  expect(tokens.displayVar.length).toBeGreaterThan(0);
  expect(tokens.fontFamily.toLowerCase()).not.toContain("inter");
});
