import { test, expect } from "@playwright/test";
import { setupJpUi04aScenario } from "./visual-audit/jp-ui-04a-fixtures";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("confirmed success shows booking reference", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "success-confirmed",
    family: "success",
    route: "/booking/confirmation",
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "confirmed",
    fixtureId: "success-confirmed",
    waitForTestId: "booking-confirmation-page",
  });
  await page.goto("/booking/confirmation", { waitUntil: "load" });
  await expect(page.getByTestId("booking-reference")).toContainText("JPAUDIT04A");
});

test("pnr pending does not show fake PNR", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "success-pnr-pending",
    family: "success",
    route: "/booking/confirmation",
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "pnr-pending",
    fixtureId: "success-pnr-pending",
    waitForTestId: "booking-confirmation-page",
  });
  await page.goto("/booking/confirmation", { waitUntil: "load" });
  await expect(page.getByTestId("pnr-value")).toHaveCount(0);
});

test("ticketed state can show ticket number from fixture", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "success-ticketed",
    family: "success",
    route: "/booking/confirmation",
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "ticketed",
    fixtureId: "success-ticketed",
    waitForTestId: "booking-confirmation-page",
  });
  await page.goto("/booking/confirmation", { waitUntil: "load" });
  await expect(page.getByTestId("ticket-number-row")).toBeVisible();
});

test("invoice action hidden when unavailable", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "success-no-invoice",
    family: "success",
    route: "/booking/confirmation",
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "no-invoice",
    fixtureId: "success-no-invoice",
    waitForTestId: "booking-confirmation-page",
    forbiddenTestIds: ["invoice-download-action"],
  });
  await page.goto("/booking/confirmation", { waitUntil: "load" });
  await expect(page.getByTestId("action-view_invoice")).toHaveCount(0);
});

test("not found success state is safe", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "success-not-found",
    family: "success",
    route: "/booking/confirmation",
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "not-found",
    fixtureId: "success-not-found",
    waitForTestId: "missing-booking-session",
  });
  await page.goto("/booking/confirmation", { waitUntil: "load" });
  await expect(page.getByTestId("missing-booking-session")).toBeVisible();
});

test("confirmation page has noindex meta", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "success-noindex",
    family: "success",
    route: "/booking/confirmation",
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "noindex",
    fixtureId: "success-confirmed",
    waitForTestId: "booking-confirmation-page",
  });
  await page.goto("/booking/confirmation", { waitUntil: "load" });
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute("content", /noindex/i);
});
