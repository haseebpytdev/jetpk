import { expect, type Page } from "@playwright/test";
import { themeStorageValue, type ThemeMode } from "./visual-audit/jp-ui-02-scenarios";
import { setupJpUi04aScenario } from "./visual-audit/jp-ui-04a-fixtures";
import type { JpUi04aScenario } from "./visual-audit/jp-ui-04a-scenarios";
import { resultsQuery } from "./visual-audit/jp-ui-01-fixtures";

export async function applyBookingTheme(page: Page, theme: ThemeMode) {
  const { preference, emulateDark } = themeStorageValue(theme);
  await page.emulateMedia({ colorScheme: emulateDark ? "dark" : "light" });
  await page.addInitScript((pref) => {
    localStorage.setItem("jp-theme-preference", pref);
  }, preference);
}

export async function assertNoHorizontalOverflow(page: Page) {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(1);
}

export async function loadScenario(page: Page, scenario: JpUi04aScenario) {
  await setupJpUi04aScenario(page, scenario);
  await applyBookingTheme(page, scenario.theme);
  await page.setViewportSize({ width: scenario.viewport.width, height: scenario.viewport.height });
  await page.goto(scenario.route, { waitUntil: "load", timeout: 60_000 });
  if (scenario.waitForTestId) {
    await expect(page.getByTestId(scenario.waitForTestId).first()).toBeVisible({ timeout: 30_000 });
  }
  if (scenario.action) {
    await scenario.action(page);
  }
}

export const RESULTS_ROUTE = `/flights/results?${resultsQuery()}`;
