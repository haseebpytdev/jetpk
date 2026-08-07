import { test, expect } from "@playwright/test";

test("destination and offer cards use approved photography not fixture SVG", async ({ page }) => {
  test.setTimeout(120_000);
  await page.goto("/", { waitUntil: "domcontentloaded" });

  const destinations = page.getByRole("region", { name: "Trending route cards" });
  await expect(destinations).toBeVisible();
  await expect(destinations.locator("img").first()).toHaveAttribute("src", /destination-/);
  await expect(destinations.locator('img[src$=".svg"]')).toHaveCount(0);

  await expect(page.locator("img[src*=offer-gcc]").first()).toHaveAttribute("src", /offer-gcc/);
});
