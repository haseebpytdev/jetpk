import { test, expect } from "@playwright/test";
import { setupJpUi04aScenario } from "./visual-audit/jp-ui-04a-fixtures";

const PASSENGERS_ROUTE =
  "/booking/passengers?search_id=audit-search&offer_id=audit-offer&from=LHE&to=DXB&depart=2026-08-15&adults=1";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("passenger validation summary appears on empty submit", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "passengers-validation",
    family: "passengers",
    route: PASSENGERS_ROUTE,
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "validation",
    fixtureId: "passengers-one-adult",
    waitForTestId: "standard-passengers-form",
  });
  await page.goto(PASSENGERS_ROUTE, { waitUntil: "load" });
  await page.getByTestId("save-and-continue").click();
  await expect(page.getByTestId("passenger-validation-summary")).toBeVisible();
});

test("expired passenger session shows offer-expired state", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "passengers-expired",
    family: "passengers",
    route: PASSENGERS_ROUTE,
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "expired",
    fixtureId: "passengers-expired",
    waitForTestId: "offer-expired",
  });
  await page.goto(PASSENGERS_ROUTE, { waitUntil: "load" });
  await expect(page.getByTestId("offer-expired")).toBeVisible();
});

test("save failure is not presented as success", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "passengers-save-failure",
    family: "passengers",
    route: PASSENGERS_ROUTE,
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "save-failure",
    fixtureId: "passengers-save-failure",
    waitForTestId: "standard-passengers-form",
  });
  await page.goto(PASSENGERS_ROUTE, { waitUntil: "load" });
  await page.getByTestId("save-and-continue").click();
  await expect(page).toHaveURL(/\/booking\/passengers/);
  await expect(page.getByTestId("standard-passengers-form")).toBeVisible();
});

test("mixed passenger types render three cards", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "passengers-mixed",
    family: "passengers",
    route: PASSENGERS_ROUTE,
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "mixed",
    fixtureId: "passengers-mixed",
    waitForTestId: "standard-passengers-form",
  });
  await page.goto(PASSENGERS_ROUTE, { waitUntil: "load" });
  await expect(page.getByTestId("passenger-card-0")).toBeVisible();
  await expect(page.getByTestId("passenger-card-1")).toBeVisible();
  await expect(page.getByTestId("passenger-card-2")).toBeVisible();
});

test("passenger page does not leak PII into URL beyond query contract", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "passengers-pii",
    family: "passengers",
    route: PASSENGERS_ROUTE,
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "pii",
    fixtureId: "passengers-one-adult",
    waitForTestId: "standard-passengers-form",
  });
  await page.goto(PASSENGERS_ROUTE, { waitUntil: "load" });
  expect(page.url()).not.toMatch(/first_name|last_name|email=/i);
  const storage = await page.evaluate(() => JSON.stringify(localStorage));
  expect(storage).not.toMatch(/Audit Traveler|audit@example.com/i);
});
