import { expect, test } from "@playwright/test";
import { attachRuntimeGuards } from "./helpers";

test.describe("JP-FULL-NEXT-FRONTEND-01B verify email", () => {
  test("notice state", async ({ page }) => {
    const guards = await attachRuntimeGuards(page);
    await page.goto("/verify-email");
    await expect(page.getByTestId("auth-form-card").getByRole("heading", { level: 1, name: /verify your email/i })).toBeVisible();
    await guards.assertClean();
  });

  test("verified result", async ({ page }) => {
    await page.goto("/verify-email?status=verified");
    await expect(page.getByRole("heading", { name: /email verified/i })).toBeVisible();
  });

  test("already verified", async ({ page }) => {
    await page.goto("/verify-email?status=already-verified");
    await expect(page.getByRole("heading", { name: /already verified/i })).toBeVisible();
  });

  test("expired", async ({ page }) => {
    await page.goto("/verify-email?status=expired");
    await expect(page.getByRole("heading", { name: /expired/i })).toBeVisible();
  });

  test("invalid", async ({ page }) => {
    await page.goto("/verify-email?status=invalid");
    await expect(page.getByRole("heading", { name: /invalid verification/i })).toBeVisible();
  });

  test("no signed hash rendered in page body", async ({ page }) => {
    await page.goto("/verify-email?status=verified");
    const body = await page.locator("body").innerText();
    expect(body).not.toMatch(/[a-f0-9]{40,}/i);
  });

  test("noindex robots meta", async ({ page }) => {
    await page.goto("/verify-email");
    const robots = await page.locator('meta[name="robots"]').getAttribute("content");
    expect(robots ?? "").toMatch(/noindex/i);
  });

  test("login-next link present", async ({ page }) => {
    await page.goto("/verify-email");
    await expect(page.getByRole("link", { name: /continue to sign in/i })).toHaveAttribute("href", "/login");
  });
});
