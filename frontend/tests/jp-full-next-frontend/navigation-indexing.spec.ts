import { expect, test } from "@playwright/test";
import { assertNoHorizontalOverflow } from "./helpers";

test.describe("JP-FULL-NEXT-FRONTEND-01B navigation and indexing", () => {
  test("header has no unsupported capabilities", async ({ page }) => {
    await page.goto("/");
    await expect(page.getByRole("navigation", { name: "Primary" })).toBeVisible();
    await expect(page.getByRole("link", { name: /Flight Status/i })).toHaveCount(0);
    await expect(page.getByRole("link", { name: /^Hotels$/i })).toHaveCount(0);
    await expect(page.getByRole("link", { name: /^Offers$/i })).toHaveCount(0);
    await expect(page.locator('a[href="#"]')).toHaveCount(0);
  });

  test("footer has no fake newsletter", async ({ page }) => {
    await page.goto("/");
    await expect(page.getByText("Stay Updated")).toHaveCount(0);
    await expect(page.getByRole("button", { name: "Subscribe" })).toHaveCount(0);
  });

  test("robots.txt disallows private routes", async ({ request }) => {
    const response = await request.get("/robots.txt");
    expect(response.ok()).toBeTruthy();
    const body = await response.text();
    expect(body).toMatch(/Disallow: \/customer/);
    expect(body).toMatch(/Disallow: \/flights\/fare-selection/);
    expect(body).toMatch(/Disallow: \/verify-email/);
  });

  test("verify-email and fare-selection excluded from indexing", async ({ page }) => {
    await page.goto("/verify-email");
    let robots = await page.locator('meta[name="robots"]').getAttribute("content");
    expect(robots ?? "").toMatch(/noindex/i);
    await page.goto("/flights/fare-selection");
    robots = await page.locator('meta[name="robots"]').getAttribute("content");
    expect(robots ?? "").toMatch(/noindex/i);
  });

  test("mobile homepage has no horizontal overflow", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/");
    await assertNoHorizontalOverflow(page);
  });
});
