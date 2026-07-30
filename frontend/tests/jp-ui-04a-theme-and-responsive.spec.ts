import { test, expect } from "@playwright/test";
import { themeStorageValue } from "./visual-audit/jp-ui-02-scenarios";
import { setupJpUi04aScenario } from "./visual-audit/jp-ui-04a-fixtures";
import { resultsQuery } from "./visual-audit/jp-ui-01-fixtures";
import { applyBookingTheme, assertNoHorizontalOverflow } from "./jp-ui-04a-test-helpers";

const RESULTS_ROUTE = `/flights/results?${resultsQuery()}`;

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

for (const theme of ["light", "dark", "system-light", "system-dark"] as const) {
  test(`booking results resolve ${theme} theme`, async ({ page }) => {
    await setupJpUi04aScenario(page, {
      id: `theme-${theme}`,
      family: "results",
      route: RESULTS_ROUTE,
      theme,
      viewport: { name: "1440x900", width: 1440, height: 900 },
      zoom: 1,
      state: "theme",
      fixtureId: "results-present",
      waitForTestId: "search-summary-bar",
    });
    await applyBookingTheme(page, theme);
    await page.goto(RESULTS_ROUTE, { waitUntil: "load" });
    const expected = theme === "dark" || theme === "system-dark" ? "dark" : "light";
    await expect(page.locator("html")).toHaveAttribute("data-theme", expected);
    await expect(page.getByTestId("theme-switch")).toHaveAttribute(
      "data-theme-preference",
      theme.startsWith("system") ? "system" : theme,
    );
  });
}

test("theme persists from results to passengers", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "theme-persist-passengers",
    family: "passengers",
    route: "/booking/passengers?search_id=audit-search&offer_id=audit-offer&from=LHE&to=DXB&depart=2026-08-15&adults=1",
    theme: "dark",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "persist",
    fixtureId: "passengers-one-adult",
    waitForTestId: "standard-passengers-form",
  });
  await applyBookingTheme(page, "dark");
  await page.goto(RESULTS_ROUTE, { waitUntil: "load" });
  await page.goto("/booking/passengers?search_id=audit-search&offer_id=audit-offer&from=LHE&to=DXB&depart=2026-08-15&adults=1", {
    waitUntil: "load",
  });
  await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
});

test("reduced motion keeps results usable", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "reduced-motion-results",
    family: "results",
    route: RESULTS_ROUTE,
    theme: "light",
    viewport: { name: "390x844", width: 390, height: 844 },
    zoom: 1,
    state: "reduced-motion",
    fixtureId: "results-present",
    waitForTestId: "flight-result-card",
  });
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(RESULTS_ROUTE, { waitUntil: "load" });
  await expect(page.getByTestId("flight-result-card").first()).toBeVisible();
  await page.getByTestId("open-mobile-filters").click();
  await expect(page.getByTestId("mobile-filter-drawer")).toBeVisible();
  await assertNoHorizontalOverflow(page);
});

test("150% zoom on results has no horizontal overflow", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "zoom-150-results",
    family: "results",
    route: RESULTS_ROUTE,
    theme: "light",
    viewport: { name: "1280x900", width: 1280, height: 900 },
    zoom: 1.5,
    state: "zoom",
    fixtureId: "results-present",
    waitForTestId: "flight-result-card",
  });
  await applyBookingTheme(page, "light");
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto(RESULTS_ROUTE, { waitUntil: "load" });
  await page.evaluate(() => {
    document.documentElement.style.zoom = "1.5";
  });
  await assertNoHorizontalOverflow(page);
});

test("system preference follows browser scheme on booking review", async ({ page }) => {
  const { preference } = themeStorageValue("system-light");
  await setupJpUi04aScenario(page, {
    id: "system-review",
    family: "review",
    route: "/booking/review",
    theme: "system-light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "system",
    fixtureId: "review-complete",
    waitForTestId: "booking-review-page",
  });
  await page.emulateMedia({ colorScheme: "light" });
  await page.addInitScript((pref) => localStorage.setItem("jp-theme-preference", pref), preference);
  await page.goto("/booking/review", { waitUntil: "load" });
  await expect(page.locator("html")).toHaveAttribute("data-theme", "light");
  await page.emulateMedia({ colorScheme: "dark" });
  await page.waitForTimeout(300);
  await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
});
