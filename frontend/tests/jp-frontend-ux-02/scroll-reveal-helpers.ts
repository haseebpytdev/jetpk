import { expect, type Page } from "@playwright/test";

export async function waitForHomepageLayout(page: Page): Promise<void> {
  await page.waitForLoadState("networkidle");
  await page.evaluate(async () => {
    await document.fonts?.ready;
  });
}

export async function revealAllScrollTargets(page: Page): Promise<void> {
  const targets = page.locator(".jp-scroll-reveal");
  const count = await targets.count();

  for (let index = 0; index < count; index += 1) {
    const target = targets.nth(index);
    await target.scrollIntoViewIfNeeded();
    await expect(target).toHaveAttribute("data-revealed", "true", { timeout: 5000 });
  }

  const hiddenCount = await page.locator(
    '.jp-scroll-reveal.jp-scroll-reveal--armed[data-revealed="false"]',
  ).count();
  expect(hiddenCount).toBe(0);
}

export async function assertHomepageMarketingSections(page: Page): Promise<void> {
  await expect(page.getByRole("heading", { name: /Destinations on the Rise/i })).toBeVisible();
  await expect(page.getByRole("heading", { name: /Featured Offers/i })).toBeVisible();
  await expect(page.getByRole("heading", { name: /Why JetPakistan/i })).toBeVisible();
  await expect(page.locator("footer")).toBeVisible();
}
