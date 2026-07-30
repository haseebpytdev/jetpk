import { test, expect } from "@playwright/test";
import { setupJpUi04aScenario } from "./visual-audit/jp-ui-04a-fixtures";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("manual payment page shows authoritative amount", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "payment-manual",
    family: "payment",
    route: "/booking/payment/manual",
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "manual",
    fixtureId: "payment-manual",
    waitForTestId: "manual-payment-page",
  });
  await page.goto("/booking/payment/manual", { waitUntil: "load" });
  await expect(page.getByTestId("manual-amount-due")).toContainText("124,999");
  await expect(page.getByTestId("embedded-card-form")).toHaveCount(0);
});

test("abhipay page has no embedded card form", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "payment-abhipay",
    family: "payment",
    route: "/booking/payment/card",
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "abhipay",
    fixtureId: "payment-abhipay",
    waitForTestId: "card-payment-page",
    forbiddenTestIds: ["embedded-card-form"],
  });
  await page.goto("/booking/payment/card", { waitUntil: "load" });
  await expect(page.getByTestId("card-pay-button")).toBeVisible();
  await expect(page.getByTestId("embedded-card-form")).toHaveCount(0);
});

test("payment failed state is honest", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "payment-failed",
    family: "payment",
    route: "/booking/payment/card",
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "failed",
    fixtureId: "payment-failed",
    waitForTestId: "card-payment-page",
  });
  await page.goto("/booking/payment/card", { waitUntil: "load" });
  await expect(page.getByTestId("card-payment-status")).toContainText(/failed/i);
});

test("expired payment session blocks checkout", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "payment-expired",
    family: "payment",
    route: "/booking/payment/manual",
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "expired",
    fixtureId: "payment-expired",
    waitForTestId: "missing-booking-session",
  });
  await page.goto("/booking/payment/manual", { waitUntil: "load" });
  await expect(page.getByTestId("missing-booking-session")).toBeVisible();
});

test("provider unavailable blocks pay button", async ({ page }) => {
  await setupJpUi04aScenario(page, {
    id: "payment-provider-unavailable",
    family: "payment",
    route: "/booking/payment/card",
    theme: "light",
    viewport: { name: "1440x900", width: 1440, height: 900 },
    zoom: 1,
    state: "provider-unavailable",
    fixtureId: "payment-provider-unavailable",
    waitForTestId: "card-payment-page",
  });
  await page.goto("/booking/payment/card", { waitUntil: "load" });
  await expect(page.getByTestId("card-pay-button")).toHaveCount(0);
});
