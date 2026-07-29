import { mkdirSync, writeFileSync } from "node:fs";
import path from "node:path";
import { test, expect } from "@playwright/test";
import { setupScenarioMocks, resultsQuery } from "./jp-ui-01-fixtures";
import { sessionFixtureCookieName } from "../../features/auth/server/session-fixture";
import {
  JP_UI_02_SCENARIOS,
  JP_UI_02_VIEWPORTS,
  JP_UI_02_ZOOM_VIEWPORTS,
  themeStorageValue,
} from "./jp-ui-02-scenarios";

const AUDIT_ROOT = path.resolve(process.cwd(), ".visual-audit", "jp-ui-02");
const MANIFEST_PATH = path.join(AUDIT_ROOT, "capture-manifest.json");

type CaptureRecord = {
  scenarioKey: string;
  route: string;
  theme: string;
  viewport: string;
  zoom: number;
  screenshot: string;
  timestamp: string;
};

const captures: CaptureRecord[] = [];

test.describe.configure({ mode: "serial" });

test.beforeAll(() => {
  mkdirSync(AUDIT_ROOT, { recursive: true });
});

test.afterAll(() => {
  writeFileSync(
    MANIFEST_PATH,
    JSON.stringify({ generatedAt: new Date().toISOString(), captureCount: captures.length, captures }, null, 2),
    "utf8",
  );
});

async function applyTheme(page: import("@playwright/test").Page, theme: (typeof JP_UI_02_SCENARIOS)[number]["theme"]) {
  const { preference, emulateDark } = themeStorageValue(theme);
  await page.emulateMedia({ colorScheme: emulateDark ? "dark" : "light" });
  await page.addInitScript((pref) => {
    localStorage.setItem("jp-theme-preference", pref);
  }, preference);
}

async function applyPortalFixture(page: import("@playwright/test").Page, setup: "customer" | "agent") {
  const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:3002";
  await page.context().addCookies([
    { name: sessionFixtureCookieName, value: setup, url: baseURL },
  ]);
}

for (const scenario of JP_UI_02_SCENARIOS) {
  for (const viewport of JP_UI_02_VIEWPORTS) {
    test(`jp-ui-02 ${scenario.id} ${scenario.theme} @ ${viewport.name}`, async ({ page }) => {
      if (scenario.setup) {
        if (scenario.setup === "customer" || scenario.setup === "agent") {
          await applyPortalFixture(page, scenario.setup);
        } else {
          await setupScenarioMocks(page, scenario.setup);
        }
      }

      await applyTheme(page, scenario.theme);
      await page.setViewportSize({ width: viewport.width, height: viewport.height });

      const route =
        scenario.id === "flight-results"
          ? `/flights/results?${resultsQuery()}`
          : scenario.route;

      await page.goto(route, { waitUntil: "load", timeout: 60_000 });

      if (scenario.waitForTestId) {
        await expect(page.getByTestId(scenario.waitForTestId).first()).toBeVisible({ timeout: 30_000 });
      } else {
        await expect(page.locator("#main-content").first()).toBeVisible({ timeout: 30_000 });
      }

      const screenshotName = `${scenario.id}__${scenario.theme}__${viewport.name}.png`;
      await page.screenshot({ path: path.join(AUDIT_ROOT, screenshotName), fullPage: scenario.fullPage });

      captures.push({
        scenarioKey: scenario.id,
        route,
        theme: scenario.theme,
        viewport: viewport.name,
        zoom: 1,
        screenshot: screenshotName,
        timestamp: new Date().toISOString(),
      });
    });
  }
}

for (const scenario of JP_UI_02_SCENARIOS.filter((s) => s.id === "homepage" && s.theme === "light")) {
  for (const viewport of JP_UI_02_ZOOM_VIEWPORTS) {
    test(`jp-ui-02 ${scenario.id} zoom @ ${viewport.name}`, async ({ page }) => {
      await setupScenarioMocks(page, "public");
      await applyTheme(page, scenario.theme);
      await page.setViewportSize({ width: viewport.width, height: viewport.height });
      await page.goto("/", { waitUntil: "load" });
      await page.evaluate((factor) => {
        document.documentElement.style.zoom = String(factor);
      }, viewport.zoom);
      await expect(page.getByTestId("search-module")).toBeVisible();
      const screenshotName = `${scenario.id}__${scenario.theme}__${viewport.name}.png`;
      await page.screenshot({ path: path.join(AUDIT_ROOT, screenshotName), fullPage: true });
      captures.push({
        scenarioKey: scenario.id,
        route: "/",
        theme: scenario.theme,
        viewport: viewport.name,
        zoom: viewport.zoom,
        screenshot: screenshotName,
        timestamp: new Date().toISOString(),
      });
    });
  }
}
