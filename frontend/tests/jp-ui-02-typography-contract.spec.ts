import { test, expect } from "@playwright/test";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

async function readSemanticFontTokens(page: import("@playwright/test").Page) {
  return page.evaluate(() => {
    const bodyStyle = getComputedStyle(document.body);
    return {
      bodyVar: bodyStyle.getPropertyValue("--font-body").trim(),
      displayVar: bodyStyle.getPropertyValue("--font-display").trim(),
      bodyUsesSansClass: document.body.className.includes("font-sans"),
    };
  });
}

test("frontend layout binds shared next/font body and display variables", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  const tokens = await readSemanticFontTokens(page);
  expect(tokens.bodyVar.length).toBeGreaterThan(0);
  expect(tokens.displayVar.length).toBeGreaterThan(0);
});

test("frontend body applies shared UI font stack from tokens", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  const tokens = await readSemanticFontTokens(page);
  expect(tokens.bodyVar.length).toBeGreaterThan(0);
  const fontFamily = await page.evaluate(() => getComputedStyle(document.body).fontFamily);
  const normalized = fontFamily.toLowerCase();
  expect(normalized).toMatch(/inter|system-ui|segoe ui|arial|helvetica|sans-serif/);
});

test("frontend hero heading uses shared display font token class", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  const heading = page.getByRole("heading", { level: 1, name: /Explore the world with/i });
  await expect(heading).toHaveClass(/font-display/);
});

test("login page preserves shared font variable bindings", async ({ page }) => {
  await page.goto("/login", { waitUntil: "load" });
  const tokens = await readSemanticFontTokens(page);
  expect(tokens.bodyVar.length).toBeGreaterThan(0);
  expect(tokens.displayVar.length).toBeGreaterThan(0);
});

test("dark theme preserves shared font variable bindings", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  await page.getByTestId("theme-switch").click();
  await page.getByTestId("theme-switch").click();
  await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
  const tokens = await readSemanticFontTokens(page);
  expect(tokens.bodyVar.length).toBeGreaterThan(0);
  expect(tokens.displayVar.length).toBeGreaterThan(0);
  await expect(page.getByRole("heading", { level: 1, name: /Explore the world with/i })).toHaveClass(
    /font-display/,
  );
});
