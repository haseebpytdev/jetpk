import { mkdirSync, writeFileSync } from "node:fs";
import path from "node:path";
import { test, expect } from "@playwright/test";
import {
  JP_UI_01_SCENARIOS,
  JP_UI_01_UNSUPPORTED_SCENARIOS,
  JP_UI_01_VIEWPORTS,
  JP_UI_01_ZOOM_VIEWPORTS,
} from "./jp-ui-01-scenarios";
import { resultsQuery, setupScenarioMocks } from "./jp-ui-01-fixtures";

const AUDIT_ROOT = path.resolve(process.cwd(), ".visual-audit", "jp-ui-01");
const MANIFEST_PATH = path.join(AUDIT_ROOT, "capture-manifest.json");

type CaptureRecord = {
  scenarioId: string;
  pageName: string;
  route: string;
  viewport: string;
  zoom: number;
  timestamp: string;
  screenshot: string;
  mockupKey: string | null;
  fixtureSetup: string | null;
};

const captures: CaptureRecord[] = [];

test.describe.configure({ mode: "serial" });

test.beforeAll(() => {
  mkdirSync(AUDIT_ROOT, { recursive: true });
});

test.afterAll(() => {
  writeFileSync(
    MANIFEST_PATH,
    JSON.stringify(
      {
        generatedAt: new Date().toISOString(),
        auditRoot: AUDIT_ROOT,
        captureCount: captures.length,
        unsupportedScenarios: JP_UI_01_UNSUPPORTED_SCENARIOS,
        captures,
      },
      null,
      2,
    ),
    "utf8",
  );
});

async function captureScenario(
  page: import("@playwright/test").Page,
  scenario: (typeof JP_UI_01_SCENARIOS)[number],
  viewportName: string,
  width: number,
  height: number,
  zoom = 1,
): Promise<void> {
  if (scenario.setup) {
    await setupScenarioMocks(page, scenario.setup);
  }

  await page.setViewportSize({ width, height });

  const route =
    scenario.id === "flight-results" || scenario.id === "fare-selection"
      ? `/flights/results?${resultsQuery()}`
      : scenario.route;

  await page.goto(route, { waitUntil: "load", timeout: 60_000 });

  if (zoom !== 1) {
    await page.evaluate((factor) => {
      document.documentElement.style.zoom = String(factor);
    }, zoom);
  }

  if (scenario.waitForTestId) {
    await expect(page.getByTestId(scenario.waitForTestId).first()).toBeVisible({ timeout: 30_000 });
  } else {
    await expect(page.locator("#main-content, main").first()).toBeVisible({ timeout: 30_000 });
  }

  const screenshotName = `${scenario.id}__${viewportName}${zoom !== 1 ? `__zoom-${Math.round(zoom * 100)}` : ""}.png`;
  const screenshotPath = path.join(AUDIT_ROOT, screenshotName);

  await page.screenshot({
    path: screenshotPath,
    fullPage: scenario.fullPage,
  });

  captures.push({
    scenarioId: scenario.id,
    pageName: scenario.pageName,
    route,
    viewport: viewportName,
    zoom,
    timestamp: new Date().toISOString(),
    screenshot: screenshotName,
    mockupKey: scenario.mockupKey,
    fixtureSetup: scenario.setup ?? null,
  });
}

for (const scenario of JP_UI_01_SCENARIOS) {
  for (const viewport of JP_UI_01_VIEWPORTS) {
    test(`capture ${scenario.id} @ ${viewport.name}`, async ({ page }) => {
      await captureScenario(page, scenario, viewport.name, viewport.width, viewport.height);
    });
  }
}

for (const scenario of JP_UI_01_SCENARIOS.filter((item) => item.setup !== "auth")) {
  for (const viewport of JP_UI_01_ZOOM_VIEWPORTS) {
    const zoom = viewport.name.endsWith("150") ? 1.5 : 1.25;
    test(`capture ${scenario.id} @ ${viewport.name}`, async ({ page }) => {
      await captureScenario(page, scenario, viewport.name, viewport.width, viewport.height, zoom);
    });
  }
}

test("manifest records unsupported seat-selection scenario", () => {
  expect(JP_UI_01_UNSUPPORTED_SCENARIOS).toHaveLength(1);
  expect(JP_UI_01_UNSUPPORTED_SCENARIOS[0]?.id).toBe("seat-selection");
});
