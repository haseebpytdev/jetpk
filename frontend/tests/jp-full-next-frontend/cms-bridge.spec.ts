import { expect, test } from "@playwright/test";
import { attachRuntimeGuards } from "./helpers";

test.describe("JP-FULL-NEXT-FRONTEND-01B CMS bridge", () => {
  test("about page renders CMS V2 managed content", async ({ page }) => {
    const guards = await attachRuntimeGuards(page);
    await page.goto("/about-us", { waitUntil: "load" });
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
    await expect(page.getByRole("heading", { name: "Our story" })).toBeVisible();
    await expect(page.locator("#main-content [onclick], #main-content [onerror]")).toHaveCount(0);
    await guards.assertClean();
  });

  test("faq page renders sanitized CMS content", async ({ page }) => {
    await page.goto("/faq", { waitUntil: "load" });
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
    await expect(page.locator("#main-content [onclick], #main-content [onerror]")).toHaveCount(0);
  });

  test("contact remains canonical dedicated page", async ({ page }) => {
    const response = await page.goto("/contact");
    expect(response?.status()).toBeLessThan(500);
    await expect(page).toHaveURL(/\/contact$/);
    await expect(page.getByTestId("contact-form")).toBeVisible();
  });

  test("verify-email reserved from CMS catch-all", async ({ page }) => {
    const response = await page.goto("/verify-email");
    expect(response?.status()).toBe(200);
    await expect(page.getByTestId("auth-form-card").getByRole("heading", { level: 1 })).toBeVisible();
  });

  test("CMS dark theme renders readable content", async ({ page }) => {
    await page.addInitScript(() => localStorage.setItem("jp-theme-preference", "dark"));
    await page.emulateMedia({ colorScheme: "dark" });
    await page.goto("/about-us", { waitUntil: "load" });
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
  });
});
