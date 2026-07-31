import { mkdirSync } from "node:fs";
import { test, expect } from "@playwright/test";
import { setupJpUi06Scenario, resolveJpUi06Route } from "./jp-ui-06-fixtures";
import {
  AUDIT_ROOT,
  applyTheme,
  applyZoom,
  appendThemeToRoute,
  assertResolvedTheme,
  attachPageMonitors,
  captureRecords,
  captureScenario,
  pauseAnimations,
  waitForFontsAndImages,
  writeManifest,
} from "./jp-ui-06-helpers";
import { EXPECTED_SCENARIO_COUNT, JP_UI_06_SCENARIOS } from "./jp-ui-06-scenarios";

const SERVER_PORT = Number(process.env.JP_UI_06_PORT ?? process.env.PLAYWRIGHT_PORT ?? "3002");
const EXPECTED_RUN_COUNT = Number(process.env.JP_UI_06_EXPECTED_COUNT ?? String(EXPECTED_SCENARIO_COUNT));

test.describe.configure({ mode: "serial" });

test.describe("JP-UI-06 blueprint visual matrix", () => {
  test.beforeAll(() => {
    mkdirSync(AUDIT_ROOT, { recursive: true });
  });

  for (const scenario of JP_UI_06_SCENARIOS) {
    test(scenario.id, async ({ page }) => {
      const startedAt = Date.now();
      await setupJpUi06Scenario(page, scenario);
      const themeMeta = await applyTheme(page, scenario.theme);
      const route = resolveJpUi06Route(scenario);
      const url = appendThemeToRoute(route, scenario.theme);
      await page.setViewportSize({ width: scenario.viewport.width, height: scenario.viewport.height });
      await page.goto(url, { waitUntil: "networkidle" });
      if (scenario.theme === "dark" || scenario.theme === "system-dark") {
        await page.reload({ waitUntil: "networkidle" });
      }
      await pauseAnimations(page);
      await waitForFontsAndImages(page);
      const monitors = attachPageMonitors(page);
      if (scenario.action) await scenario.action(page);
      if (scenario.waitForTestId) {
        await page.getByTestId(scenario.waitForTestId).first().waitFor({ state: "visible", timeout: 15_000 });
      }
      await applyZoom(page, scenario.zoom);
      const actualResolvedTheme = await assertResolvedTheme(page, scenario.theme);
      const screenshot = `${scenario.id}.png`;
      await captureScenario(page, {
        id: scenario.id,
        family: scenario.family,
        route,
        fixtureId: scenario.fixtureId,
        comparisonMode: scenario.comparisonMode,
        wave: scenario.wave,
        operationalState: scenario.state,
        themePreference: scenario.theme,
        browserColorScheme: themeMeta.colorScheme,
        expectedResolvedTheme: themeMeta.expectedResolvedTheme,
        actualResolvedTheme,
        viewport: scenario.viewport.name,
        width: scenario.viewport.width,
        height: scenario.viewport.height,
        zoom: scenario.zoom,
        screenshot,
        serverPort: SERVER_PORT,
        monitors,
        fullPage: scenario.fullPage,
        startedAt,
      });
    });
  }

  test.afterAll(() => {
    writeManifest(SERVER_PORT);
    expect(captureRecords.length).toBe(EXPECTED_RUN_COUNT);
    expect(captureRecords.filter((r) => r.result !== "passed").length).toBe(0);
  });
});

const OVERFLOW_PROBES = [
  { name: "320x700", width: 320, height: 700 },
  { name: "375x812", width: 375, height: 812 },
  { name: "768x1024", width: 768, height: 1024 },
  { name: "1024x900", width: 1024, height: 900 },
];

test.describe("JP-UI-06 overflow probes (no PNG persistence)", () => {
  for (const scenario of JP_UI_06_SCENARIOS.filter((s) => s.state === "canonical-light-desktop")) {
    for (const probe of OVERFLOW_PROBES) {
      test(`overflow-${scenario.family}-${probe.name}`, async ({ page }) => {
        await setupJpUi06Scenario(page, scenario);
        await applyTheme(page, "light");
        const route = resolveJpUi06Route(scenario);
        await page.setViewportSize({ width: probe.width, height: probe.height });
        await page.goto(appendThemeToRoute(route, "light"), { waitUntil: "networkidle" });
        await pauseAnimations(page);
        if (scenario.waitForTestId) {
          await page.getByTestId(scenario.waitForTestId).first().waitFor({ state: "visible", timeout: 15_000 });
        }
        const overflow = await page.evaluate(() => {
          const viewportWidth = window.innerWidth;
          return Math.max(
            0,
            document.documentElement.scrollWidth - viewportWidth,
            document.body.scrollWidth - viewportWidth,
          );
        });
        expect(overflow).toBeLessThanOrEqual(1);
      });
    }
  }
});
