import { test, expect } from "@playwright/test";
import { setupJpUi04aScenario } from "./visual-audit/jp-ui-04a-fixtures";
import { resultsQuery } from "./visual-audit/jp-ui-01-fixtures";
import { assertNoHorizontalOverflow } from "./jp-ui-04a-test-helpers";

const BASE = `/flights/results?${resultsQuery()}`;

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("lowest price sort shows cheapest fixture first", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "sort-lowest",
    family: "results",
    route: BASE,
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "sort",
    fixtureId: "results-present",
  });
  await page.goto(BASE, { waitUntil: "load" });
  await page.getByTestId("results-sort-tabs").getByRole("tab", { name: "Lowest Price" }).click();
  await expect(page.getByTestId("flight-result-card").first()).toContainText("134,047");
});

test("direct-only filter removes connecting offers", async ({ page }) => {
  const query = new URLSearchParams({ ...Object.fromEntries(new URLSearchParams(resultsQuery())), stops: "direct" }).toString();
  await setupJpUi04aScenario(page, {
    id: "direct-only",
    family: "results",
    route: `/flights/results?${query}`,
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "direct",
    fixtureId: "results-present",
  });
  await page.goto(`/flights/results?${query}`, { waitUntil: "load" });
  await expect(page.getByTestId("search-summary-bar")).toBeVisible();
  await expect(page.getByText("Direct flights only", { exact: false })).toBeVisible();
});

test("expired search shows recovery state", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "expired-search",
    family: "results",
    route: BASE,
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "expired",
    fixtureId: "results-expired",
    waitForTestId: "expired-search",
  });
  await page.goto(BASE, { waitUntil: "load" });
  await expect(page.getByTestId("expired-search")).toBeVisible();
});

test("return pair view shows outbound options", async ({ page }) => {
  const query = new URLSearchParams({
    ...Object.fromEntries(new URLSearchParams(resultsQuery())),
    trip_type: "return",
    return: "2026-09-01",
  }).toString();
  await setupJpUi04aScenario(page, {
    id: "pair-view",
    family: "results",
    route: `/flights/results?${query}`,
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "pair",
    fixtureId: "results-return-split",
    waitForTestId: "outbound-option-card",
  });
  await page.goto(`/flights/results?${query}`, { waitUntil: "load" });
  await expect(page.getByTestId("outbound-option-card")).toBeVisible();
  await assertNoHorizontalOverflow(page);
});

test("group ticketing route is separate from standard results", async ({ page }) => {
  await page.addInitScript(() => {
    window.__jpResetGroupSearchFacetsCache?.();
  });
  await setupJpUi04aScenario(page, {
    id: "groups-separation",
    family: "results",
    route: "/groups/search?sector=SKT-SHJ&date_from=2026-08-15",
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "groups",
    fixtureId: "groups-search",
    waitForTestId: "group-result-card",
  });
  await page.goto("/groups/search?sector=SKT-SHJ&date_from=2026-08-15", { waitUntil: "load" });
  await expect(page.getByTestId("group-result-card")).toBeVisible();
  await expect(page.getByTestId("flight-result-card")).toHaveCount(0);
});
