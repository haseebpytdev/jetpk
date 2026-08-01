import { test, expect } from "@playwright/test";

const LAB_PATH = "/__dev/jetpk-theme-lab";
const OUTPUT_DIR = ".visual-audit/jp-public-next-theme-02";

const SCENARIOS = [
  { name: "lab-1440-light", viewport: { width: 1440, height: 1200 }, theme: "light" },
  { name: "lab-1440-dark", viewport: { width: 1440, height: 1200 }, theme: "dark" },
  { name: "lab-390-light", viewport: { width: 390, height: 844 }, theme: "light" },
  { name: "lab-390-dark", viewport: { width: 390, height: 844 }, theme: "dark" },
] as const;

test.beforeAll(async ({ request }) => {
  expect((await request.get(LAB_PATH, { timeout: 120_000 })).ok()).toBeTruthy();
});

for (const scenario of SCENARIOS) {
  test(`capture ${scenario.name}`, async ({ page }) => {
    await page.setViewportSize(scenario.viewport);
    await page.goto(LAB_PATH, { waitUntil: "networkidle" });

    if (scenario.theme === "dark") {
      await page.getByTestId("jp-v2-theme-toggle").click();
      await expect(page.getByTestId("jp-theme-v2-root")).toHaveAttribute("data-jp-theme", "dark");
    }

    await page.screenshot({
      path: `${OUTPUT_DIR}/${scenario.name}.png`,
      fullPage: true,
    });
  });
}
