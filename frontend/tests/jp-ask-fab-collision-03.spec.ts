import { test, expect } from "@playwright/test";

const MOBILE = { width: 390, height: 844 };

test.describe("JP-ASK-FAB-03 collision", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto("/", { waitUntil: "domcontentloaded" });
    await page.waitForTimeout(1500);
  });

  test("Ask FAB and public dock do not overlap when both visible", async ({ page }) => {
    await page.setViewportSize(MOBILE);
    const ask = page.getByTestId("ask-jetpakistan-fab");
    const dock = page.getByTestId("public-fab-trigger");

    await expect(ask, "Ask JetPakistan FAB must be visible on the public homepage").toBeVisible();
    await expect(dock, "Public floating action dock must be visible on the public homepage").toBeVisible();

    const askBox = await ask.boundingBox();
    const dockBox = await dock.boundingBox();
    expect(askBox).not.toBeNull();
    expect(dockBox).not.toBeNull();

    const overlap = boxesOverlap(askBox!, dockBox!);
    expect(overlap).toBe(false);
  });

  test("dock hidden while Ask panel is open", async ({ page }) => {
    await page.setViewportSize(MOBILE);
    const ask = page.getByTestId("ask-jetpakistan-fab");
    await expect(ask, "Ask JetPakistan FAB must be visible on the public homepage").toBeVisible();

    await ask.click();
    await page.waitForTimeout(400);

    const dock = page.getByTestId("public-fab-dock");
    const hidden = await dock.evaluate((el) => {
      const style = window.getComputedStyle(el);
      return style.visibility === "hidden" || style.pointerEvents === "none";
    });
    expect(hidden).toBe(true);
  });
});

function boxesOverlap(
  a: { x: number; y: number; width: number; height: number },
  b: { x: number; y: number; width: number; height: number },
): boolean {
  return !(
    a.x + a.width <= b.x ||
    b.x + b.width <= a.x ||
    a.y + a.height <= b.y ||
    b.y + b.height <= a.y
  );
}
