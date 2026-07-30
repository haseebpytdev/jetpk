import { test, expect } from "@playwright/test";
import { setupJpUi04aScenario } from "./visual-audit/jp-ui-04a-fixtures";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("review shows authoritative total from fixture", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "review-complete",
    family: "review",
    route: "/booking/review",
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "complete",
    fixtureId: "review-complete",
    waitForTestId: "booking-review-page",
  });
  await page.goto("/booking/review", { waitUntil: "load" });
  await expect(page.getByTestId("order-summary-total").filter({ hasText: "124,999" })).toBeVisible();
});

test("review consent blocked shows alert", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "review-blocked",
    family: "review",
    route: "/booking/review",
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "blocked",
    fixtureId: "review-blocked",
    waitForTestId: "booking-review-page",
  });
  await page.goto("/booking/review", { waitUntil: "load" });
  await expect(page.getByText(/accept the terms/i)).toBeVisible();
  await expect(page.getByTestId("review-continue-button")).toBeDisabled();
});

test("review fare-change panel is visible", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "review-fare-change",
    family: "review",
    route: "/booking/review",
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "fare-change",
    fixtureId: "review-fare-change",
    waitForTestId: "fare-change-panel",
  });
  await page.goto("/booking/review", { waitUntil: "load" });
  await expect(page.getByTestId("fare-change-panel")).toBeVisible();
});

test("review creation failure shows safe generic notice", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "review-creation-failure",
    family: "review",
    route: "/booking/review",
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "creation-failure",
    fixtureId: "review-creation-failure",
    waitForTestId: "booking-review-page",
  });
  await page.goto("/booking/review", { waitUntil: "load" });
  await expect(page.getByText(/could not complete your booking/i)).toBeVisible();
  await expect(page.getByTestId("pnr-value")).toHaveCount(0);
});
