import path from "node:path";
import { mkdirSync } from "node:fs";
import { test, expect } from "@playwright/test";
import { setupJpUi04aScenario } from "./jp-ui-04a-fixtures";
import {
  AUDIT_ROOT,
  applyTheme,
  applyZoom,
  assertResolvedTheme,
  attachPageMonitors,
  captureRecords,
  captureScenario,
  writeManifest,
} from "./jp-ui-04a-helpers";
import { EXPECTED_SCENARIO_COUNT, JP_UI_04A_SCENARIOS } from "./jp-ui-04a-scenarios";

test.describe.configure({ mode: "serial" });

test.beforeAll(() => {
  mkdirSync(AUDIT_ROOT, { recursive: true });
});

test.afterAll(() => {
  writeManifest();
  const expectedCount = Number(process.env.JP_UI_04A_EXPECTED_COUNT ?? EXPECTED_SCENARIO_COUNT);
  expect(captureRecords.length, "capture count must match expected scenario count").toBe(expectedCount);
  const failed = captureRecords.filter((record) => record.result === "failed");
  expect(failed, `failed captures: ${failed.map((record) => record.id).join(", ")}`).toHaveLength(0);
});

for (const scenario of JP_UI_04A_SCENARIOS) {
  test(`jp-ui-04a ${scenario.id} ${scenario.theme} @ ${scenario.viewport.name} [${scenario.state}]`, async ({ page }) => {
    const startedAt = Date.now();
    await setupJpUi04aScenario(page, scenario);

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

    await applyZoom(page, scenario.zoom);
    const actualResolvedTheme = await assertResolvedTheme(page, scenario.theme);

    const zoomLabel = String(Math.round(scenario.zoom * 100)).padStart(3, "0");
    const screenshotName = `${scenario.id}__${scenario.family}__${scenario.theme}__${scenario.viewport.name}__${scenario.state}__z${zoomLabel}.png`;

    await captureScenario(page, {
      id: scenario.id,
      family: scenario.family,
      route: scenario.route,
      fixtureId: scenario.fixtureId,
      operationalState: scenario.state,
      themePreference: scenario.theme,
      browserColorScheme: themeInfo.colorScheme,
      expectedResolvedTheme: themeInfo.expectedResolvedTheme,
      actualResolvedTheme,
      viewport: scenario.viewport.name,
      width: scenario.viewport.width,
      height: scenario.viewport.height,
      zoom: scenario.zoom,
      screenshot: screenshotName,
      monitors,
      forbiddenTestIds: scenario.forbiddenTestIds,
      fullPage: scenario.fullPage,
      startedAt,
    });
  });
}
