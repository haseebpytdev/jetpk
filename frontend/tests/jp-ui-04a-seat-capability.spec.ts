import { test, expect } from "@playwright/test";
import { setupJpUi04aScenario } from "./visual-audit/jp-ui-04a-fixtures";

const PASSENGERS_ROUTE =
  "/booking/passengers?search_id=audit-search&offer_id=audit-offer&from=LHE&to=DXB&depart=2026-08-15&adults=1";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("seat map unsupported omits Seats step from progress", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "seats-unsupported",
    family: "seats",
    route: PASSENGERS_ROUTE,
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "unsupported",
    fixtureId: "seats-unsupported",
    waitForTestId: "booking-progress",
    forbiddenTestIds: ["seat-map", "seat-selection-page", "seat-map-canvas"],
  });
  await page.goto(PASSENGERS_ROUTE, { waitUntil: "load" });
  await expect(page.getByTestId("booking-progress")).toBeVisible();
  await expect(page.getByText("Seats", { exact: true })).toHaveCount(0);
  await expect(page.getByTestId("seat-map")).toHaveCount(0);
});

test("review fixture has no fake seat summary", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "review-no-seats",
    family: "review",
    route: "/booking/review",
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "no-seats",
    fixtureId: "review-no-seats",
    waitForTestId: "booking-review-page",
  });
  await page.goto("/booking/review", { waitUntil: "load" });
  await expect(page.getByText(/seat selection is not available/i)).toBeVisible();
  await expect(page.getByTestId("seat-map")).toHaveCount(0);
});
