import { mkdirSync } from "node:fs";
import { test, expect } from "@playwright/test";
import { resolveJpUi05Route, setupJpUi05Scenario } from "./jp-ui-05-fixtures";
import {
  AUDIT_ROOT,
  applyTheme,
  applyZoom,
  assertResolvedTheme,
  attachPageMonitors,
  captureRecords,
  captureScenario,
  loadExistingManifestCaptures,
  writeManifest,
} from "./jp-ui-05-helpers";
import { DASHBOARD_SCENARIOS, EXPECTED_SCENARIO_COUNT } from "./jp-ui-05-scenarios";

const dashboardBaseURL = process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:3003";

test.use({ baseURL: dashboardBaseURL });

test.describe.configure({ mode: "serial" });

test.beforeAll(() => {
  mkdirSync(AUDIT_ROOT, { recursive: true });
  if (process.env.JP_UI_05_MERGE_MANIFEST === "true") {
    loadExistingManifestCaptures();
  }
});

test.afterAll(() => {
  writeManifest();
  const expectedCount =
    process.env.JP_UI_05_MERGE_MANIFEST === "true"
      ? EXPECTED_SCENARIO_COUNT
      : Number(process.env.JP_UI_05_EXPECTED_COUNT ?? DASHBOARD_SCENARIOS.length);
  expect(captureRecords.length, "capture count must match expected scenario count").toBe(expectedCount);
  const failed = captureRecords.filter((record) => record.result === "failed");
  expect(failed, `failed captures: ${failed.map((record) => record.id).join(", ")}`).toHaveLength(0);
});

for (const scenario of DASHBOARD_SCENARIOS) {
  test(`jp-ui-05 ${scenario.id} ${scenario.theme} @ ${scenario.viewport.name} [${scenario.state}]`, async ({ page }) => {
    const startedAt = Date.now();
    await setupJpUi05Scenario(page, scenario);

    const monitors = attachPageMonitors(page);
    const themeInfo = await applyTheme(page, scenario.theme);
    await page.setViewportSize({ width: scenario.viewport.width, height: scenario.viewport.height });

    const route = resolveJpUi05Route(scenario);
    await page.goto(route, { waitUntil: "load", timeout: 60_000 });
    await page.waitForLoadState("domcontentloaded");

    if (scenario.waitForTestId) {
      await expect(page.getByTestId(scenario.waitForTestId).first()).toBeVisible({ timeout: 30_000 });
    } else {
      await expect(page.locator("body")).toBeVisible({ timeout: 30_000 });
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
      route,
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
