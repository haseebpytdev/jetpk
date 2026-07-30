import { test, expect } from "@playwright/test";
import { setupJpUi04aScenario } from "./visual-audit/jp-ui-04a-fixtures";
import { resultsQuery } from "./visual-audit/jp-ui-01-fixtures";

const RESULTS_ROUTE = `/flights/results?${resultsQuery()}`;

function fareScenario(fixtureId: string, waitForTestId?: string) {
  return {
    id: `fare-${fixtureId}`,
    family: "fare" as const,
    route: RESULTS_ROUTE,
    theme: "light" as const,
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: fixtureId,
    fixtureId,
    waitForTestId,
  };
}

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("four fare families use carousel controls", async ({ page }) => {
  await setupJpUi04aScenario(page, fareScenario("fare-four-families", "flight-result-card"));
  await page.goto(RESULTS_ROUTE, { waitUntil: "load" });
  await page.getByTestId("flight-details-trigger").first().click();
  await expect(page.getByTestId("fare-family-details")).toBeVisible();
  await expect(page.getByTestId("fare-family-details").getByRole("button")).toHaveCount(4);
});

test("fare revalidation shows busy state", async ({ page }) => {
  await setupJpUi04aScenario(page, fareScenario("fare-revalidating"));
  await page.goto(RESULTS_ROUTE, { waitUntil: "load" });
  await page.getByTestId("flight-details-trigger").first().click();
  await page.getByTestId("continue-to-passengers").click();
  await expect(page.getByTestId("revalidation-status")).toBeVisible();
});

test("fare price change requires explicit acceptance", async ({ page }) => {
  await setupJpUi04aScenario(page, fareScenario("fare-price-changed"));
  await page.goto(RESULTS_ROUTE, { waitUntil: "load" });
  await page.getByTestId("flight-details-trigger").first().click();
  await page.getByTestId("continue-to-passengers").click();
  await expect(page.getByTestId("fare-change-dialog")).toBeVisible();
  await expect(page.getByText("Accept new fare")).toBeVisible();
});

test("unavailable fare blocks continuation", async ({ page }) => {
  await setupJpUi04aScenario(page, fareScenario("fare-unavailable"));
  await page.goto(RESULTS_ROUTE, { waitUntil: "load" });
  await page.getByTestId("flight-details-trigger").first().click();
  await page.getByTestId("continue-to-passengers").click();
  await expect(page.getByTestId("offer-unavailable-state")).toBeVisible();
});

test("expired fare session shows honest recovery", async ({ page }) => {
  await setupJpUi04aScenario(page, fareScenario("fare-expired", "flight-result-card"));
  await page.goto(RESULTS_ROUTE, { waitUntil: "load" });
  await page.getByTestId("flight-details-trigger").first().click();
  await expect(page.getByTestId("offer-expired-state")).toBeVisible();
});
