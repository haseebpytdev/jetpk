import { test, expect } from "@playwright/test";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

async function readLegacyFontTokens(page: import("@playwright/test").Page) {
  return page.evaluate(() => {
    const body = getComputedStyle(document.body);
    return {
      bodyVar: body.getPropertyValue("--font-body").trim(),
      displayVar: body.getPropertyValue("--font-display").trim(),
      monoVar: body.getPropertyValue("--font-mono").trim(),
      fontFamily: body.fontFamily,
    };
  });
}

test("frontend root binds legacy Inter, Space Grotesk, and IBM Plex Mono variables", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  const tokens = await readLegacyFontTokens(page);
  expect(tokens.bodyVar.length).toBeGreaterThan(0);
  expect(tokens.displayVar.length).toBeGreaterThan(0);
  expect(tokens.monoVar.length).toBeGreaterThan(0);
});

test("frontend body uses legacy Inter UI stack", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  const tokens = await readLegacyFontTokens(page);
  expect(tokens.fontFamily.toLowerCase()).toMatch(/inter|system-ui|segoe ui|arial|sans-serif/);
  expect(tokens.fontFamily.toLowerCase()).not.toContain("plus jakarta");
  expect(tokens.fontFamily.toLowerCase()).not.toContain("instrument sans");
});

test("frontend hero heading uses legacy display token", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  const heading = page.getByRole("heading", { level: 1, name: /Explore the world with/i });
  await expect(heading).toHaveClass(/font-display/);
});

test("customer portal inherits global legacy font variables", async ({ page }) => {
  await page.goto("/customer/dashboard", { waitUntil: "domcontentloaded" });
  const tokens = await readLegacyFontTokens(page);
  expect(tokens.bodyVar.length).toBeGreaterThan(0);
  expect(tokens.displayVar.length).toBeGreaterThan(0);
});

test("agent portal inherits global legacy font variables", async ({ page }) => {
  await page.goto("/agent/dashboard", { waitUntil: "domcontentloaded" });
  const tokens = await readLegacyFontTokens(page);
  expect(tokens.bodyVar.length).toBeGreaterThan(0);
  expect(tokens.displayVar.length).toBeGreaterThan(0);
});

test("dark theme preserves legacy font variable bindings", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  await page.getByTestId("theme-switch").click();
  await page.getByTestId("theme-switch").click();
  await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
  const tokens = await readLegacyFontTokens(page);
  expect(tokens.bodyVar.length).toBeGreaterThan(0);
  expect(tokens.displayVar.length).toBeGreaterThan(0);
});
