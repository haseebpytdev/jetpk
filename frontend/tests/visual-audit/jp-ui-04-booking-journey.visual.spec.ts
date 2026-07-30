import path from "node:path";
import { mkdirSync } from "node:fs";
import { test, expect } from "@playwright/test";
import { setupJpUi04Family } from "./jp-ui-04-fixtures";
import {
  applyTheme,
  applyZoom,
  assertResolvedTheme,
  attachPageMonitors,
  captureRecords,
  captureScenario,
  writeManifest,
} from "./jp-ui-04-helpers";
import { EXPECTED_SCENARIO_COUNT, JP_UI_04_SCENARIOS } from "./jp-ui-04-scenarios";

test.describe.configure({ mode: "serial" });

test.beforeAll(() => {
  mkdirSync(path.resolve(process.cwd(), ".visual-audit", "jp-ui-04"), { recursive: true });
});

test.afterAll(() => {
  writeManifest();
  const expectedCount = Number(process.env.JP_UI_04_EXPECTED_COUNT ?? EXPECTED_SCENARIO_COUNT);
  expect(captureRecords.length, "capture count must match expected scenario count").toBe(expectedCount);
  const failed = captureRecords.filter((record) => record.result === "failed");
  expect(failed, `failed captures: ${failed.map((record) => record.id).join(", ")}`).toHaveLength(0);
});

for (const scenario of JP_UI_04_SCENARIOS) {
  test(`jp-ui-04 ${scenario.id} ${scenario.theme} @ ${scenario.viewport.name} [${scenario.state}]`, async ({ page }) => {
    await setupJpUi04Family(page, scenario.family, scenario.state);

    const monitors = attachPageMonitors(page);
    const themeInfo = await applyTheme(page, scenario.theme);
    await page.setViewportSize({ width: scenario.viewport.width, height: scenario.viewport.height });

    await page.goto(scenario.route, { waitUntil: "load", timeout: 60_000 });

    if (scenario.waitForTestId) {
      await expect(page.getByTestId(scenario.waitForTestId).first()).toBeVisible({ timeout: 30_000 });
    } else {
      await expect(page.locator("#main-content").first()).toBeVisible({ timeout: 30_000 });
    }

    if (scenario.action) {
      await scenario.action(page);
      await page.waitForTimeout(250);
    }

    for (const forbidden of scenario.forbiddenTestIds ?? []) {
      await expect(page.getByTestId(forbidden)).toHaveCount(0);
    }

    await applyZoom(page, scenario.zoom);
    const resolvedTheme = await assertResolvedTheme(page, scenario.theme);

    const zoomLabel = String(Math.round(scenario.zoom * 100)).padStart(3, "0");
    const screenshotName = `${scenario.id}__${scenario.family}__${scenario.theme}__${scenario.viewport.name}__${scenario.state}__z${zoomLabel}.png`;

    await captureScenario(page, {
      id: scenario.id,
      family: scenario.family,
      route: scenario.route,
      theme: scenario.theme,
      resolvedTheme,
      colorScheme: themeInfo.colorScheme as "light" | "dark",
      viewport: scenario.viewport.name,
      width: scenario.viewport.width,
      height: scenario.viewport.height,
      zoom: scenario.zoom,
      state: scenario.state,
      screenshot: screenshotName,
      monitors,
      fullPage: scenario.fullPage,
    });
  });
}
