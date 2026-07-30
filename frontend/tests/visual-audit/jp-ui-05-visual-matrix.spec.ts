import { mkdirSync } from "node:fs";
import { test, expect } from "@playwright/test";
import { setupJpUi05Scenario } from "./jp-ui-05-fixtures";
import {
  AUDIT_ROOT,
  applyTheme,
  applyZoom,
  appendThemeToRoute,
  assertResolvedTheme,
  attachPageMonitors,
  captureRecords,
  captureScenario,
  writeManifest,
} from "./jp-ui-05-helpers";
import { EXPECTED_SCENARIO_COUNT, FRONTEND_SCENARIOS } from "./jp-ui-05-scenarios";

test.describe.configure({ mode: "serial" });

test.beforeAll(() => {
  mkdirSync(AUDIT_ROOT, { recursive: true });
});

test.afterAll(() => {
  writeManifest();
  const expectedCount = Number(process.env.JP_UI_05_EXPECTED_COUNT ?? FRONTEND_SCENARIOS.length);
  expect(captureRecords.length, "capture count must match expected frontend scenario count").toBe(expectedCount);
  const failed = captureRecords.filter((record) => record.result === "failed");
  expect(failed, `failed captures: ${failed.map((record) => record.id).join(", ")}`).toHaveLength(0);
});

for (const scenario of FRONTEND_SCENARIOS) {
  test(`jp-ui-05 ${scenario.id} ${scenario.theme} @ ${scenario.viewport.name} [${scenario.state}]`, async ({ page }) => {
    const startedAt = Date.now();
    await setupJpUi05Scenario(page, scenario);

    const themeInfo = await applyTheme(page, scenario.theme);
    await page.setViewportSize({ width: scenario.viewport.width, height: scenario.viewport.height });

    const monitors = attachPageMonitors(page);

    await page.goto(appendThemeToRoute(scenario.route, scenario.theme), { waitUntil: "load", timeout: 60_000 });

    if (scenario.waitForTestId) {
      const target = page.getByTestId(scenario.waitForTestId).first();
      if (scenario.waitForTestId.endsWith("-list")) {
        await expect(target.locator("article").first()).toBeVisible({ timeout: 30_000 });
      } else {
        await expect(target).toBeVisible({ timeout: 30_000 });
      }
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
      application: scenario.application,
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

if (FRONTEND_SCENARIOS.length !== EXPECTED_SCENARIO_COUNT - 20) {
  throw new Error(`Frontend scenario registry drift: expected ${EXPECTED_SCENARIO_COUNT - 20}, found ${FRONTEND_SCENARIOS.length}`);
}
