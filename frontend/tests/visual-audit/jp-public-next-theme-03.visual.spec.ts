import { test, expect } from "@playwright/test";

const HOMEPAGE_PATH = "/__dev/jetpk-homepage-v2";
const OUTPUT_DIR = ".visual-audit/jp-public-next-theme-03";

const SCENARIOS = [
  { name: "homepage-1122-light", viewport: { width: 1122, height: 1402 }, theme: "light" as const },
  { name: "homepage-1440-light", viewport: { width: 1440, height: 1200 }, theme: "light" as const },
  { name: "homepage-1440-dark", viewport: { width: 1440, height: 1200 }, theme: "dark" as const },
  { name: "homepage-768-light", viewport: { width: 768, height: 1024 }, theme: "light" as const },
  { name: "homepage-768-dark", viewport: { width: 768, height: 1024 }, theme: "dark" as const },
  { name: "homepage-390-light", viewport: { width: 390, height: 844 }, theme: "light" as const },
  { name: "homepage-390-dark", viewport: { width: 390, height: 844 }, theme: "dark" as const },
] as const;

test.beforeAll(async ({ request }) => {
  expect((await request.get(HOMEPAGE_PATH, { timeout: 120_000 })).ok()).toBeTruthy();
});

for (const scenario of SCENARIOS) {
  test(`capture ${scenario.name}`, async ({ page }) => {
    await page.setViewportSize(scenario.viewport);
    await page.goto(HOMEPAGE_PATH, { waitUntil: "networkidle" });

    if (scenario.theme === "dark") {
      await page.getByTestId("jp-hp-theme-toggle").click();
      await expect(page.getByTestId("jp-theme-v2-root")).toHaveAttribute("data-jp-theme", "dark");
    }

    await page.screenshot({
      path: `${OUTPUT_DIR}/${scenario.name}.png`,
      fullPage: true,
    });
  });
}

test("capture contact sheet landmarks", async ({ page }) => {
  await page.setViewportSize({ width: 1122, height: 1402 });
  await page.goto(HOMEPAGE_PATH, { waitUntil: "networkidle" });

  const landmarks = await page.evaluate(() => {
    const measure = (selector: string) => {
      const el = document.querySelector(selector);
      if (!el) return null;
      const rect = el.getBoundingClientRect();
      return {
        x: Math.round(rect.x),
        y: Math.round(rect.y),
        width: Math.round(rect.width),
        height: Math.round(rect.height),
      };
    };

    return {
      header: measure('[data-testid="jp-hp-header"]'),
      hero: measure('[data-testid="jp-hp-hero"]'),
      search: measure('[data-testid="jp-hp-search-panel"]'),
      benefits: measure('[data-testid="jp-hp-benefits"]'),
      discover: measure('[data-testid="jp-hp-discover"]'),
      destinations: measure('[data-testid="jp-hp-destinations"]'),
      offers: measure('[data-testid="jp-hp-offers"]'),
      why: measure('[data-testid="jp-hp-why"]'),
      support: measure('[data-testid="jp-hp-support"]'),
      inspiration: measure('[data-testid="jp-hp-inspiration"]'),
      footer: measure('[data-testid="jp-hp-footer"]'),
    };
  });

  await page.evaluate(async (data) => {
    await fetch("/api/visual-audit-geometry", { method: "POST", body: JSON.stringify(data) }).catch(() => {});
  }, landmarks);

  const fs = await import("node:fs");
  const path = await import("node:path");
  const outDir = path.join(process.cwd(), OUTPUT_DIR, "geometry");
  fs.mkdirSync(outDir, { recursive: true });
  fs.writeFileSync(
    path.join(outDir, "homepage-canonical-light-geometry.json"),
    JSON.stringify({ viewport: { width: 1122, height: 1402 }, landmarks }, null, 2),
  );
});
