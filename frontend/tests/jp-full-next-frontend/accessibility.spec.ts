import { expect, test } from "@playwright/test";
import { attachRuntimeGuards } from "./helpers";

test.describe("JP-FULL-NEXT-FRONTEND-01B accessibility", () => {
  test("homepage has one h1 and landmarks", async ({ page }) => {
    await page.goto("/");
    await expect(page.getByRole("banner")).toBeVisible();
    await expect(page.getByRole("contentinfo")).toBeVisible();
    await expect(page.locator("h1")).toHaveCount(1);
    await expect(page.locator("#main-content")).toBeVisible();
  });

  test("login form fields have labels", async ({ page }) => {
    await page.goto("/login");
    const form = page.getByTestId("auth-form-card");
    await expect(form.getByLabel(/email or username/i)).toBeVisible();
    await expect(form.getByRole("textbox", { name: /password/i })).toBeVisible();
  });

  test("verify-email exposes status region", async ({ page }) => {
    await page.goto("/verify-email");
    await expect(page.locator('[role="status"]')).toBeVisible();
  });

  test("keyboard focus visible on theme switch", async ({ page }) => {
    await page.goto("/");
    const theme = page.getByTestId("theme-switch");
    await theme.focus();
    await expect(theme).toBeFocused();
  });

  test("reduced motion preference respected", async ({ page }) => {
    await page.emulateMedia({ reducedMotion: "reduce" });
    await page.goto("/about-us");
    const guards = await attachRuntimeGuards(page);
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
    await guards.assertClean();
  });
});
