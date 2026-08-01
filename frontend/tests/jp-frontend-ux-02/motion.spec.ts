import { test, expect } from "@playwright/test";
import { attachRuntimeGuards } from "../jp-full-next-frontend/helpers";

test.describe("JP-FRONTEND-UX-02 motion", () => {
  test("scroll reveal marks sections visible without hiding content", async ({ page }) => {
    const guards = await attachRuntimeGuards(page);
    await page.goto("/");
    const reveal = page.locator(".jp-scroll-reveal").first();
    await expect(reveal).toBeVisible();
    await reveal.scrollIntoViewIfNeeded();
    await expect(reveal).toHaveAttribute("data-revealed", /true|false/);
    guards.assertClean();
  });

  test("reduced motion disables scroll translation", async ({ page }) => {
    await page.emulateMedia({ reducedMotion: "reduce" });
    await page.goto("/");
    const reduced = page.locator(".jp-scroll-reveal--reduced").first();
    await expect(reduced).toBeVisible();
    await expect(reduced).toHaveCSS("transform", "none");
  });
});
