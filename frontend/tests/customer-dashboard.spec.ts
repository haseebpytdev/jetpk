import { expect, test } from "@playwright/test";
import {
  mockCustomerPortalApis,
  setPortalSessionFixture,
  setupCustomerPortalSession,
} from "./helpers/portal-session-fixtures";

test.describe("JP-FE-11 customer dashboard", () => {
  test.beforeEach(async ({ page }) => {
    await setupCustomerPortalSession(page);
  });

  test("customer dashboard shell and overview load", async ({ page }) => {
    await page.goto("/customer/dashboard");
    await expect(page.getByTestId("customer-dashboard-shell")).toBeVisible();
    await expect(page.getByTestId("customer-dashboard-overview")).toBeVisible();
    await expect(page.getByRole("heading", { name: /dashboard overview/i })).toBeVisible();
  });

  test("customer bookings list loads from Laravel JSON", async ({ page }) => {
    await page.goto("/customer/bookings");
    await expect(page.getByTestId("customer-bookings-list")).toBeVisible();
    await expect(page.getByText("BKG-1001")).toBeVisible();
  });

  test("agent is rejected from customer dashboard", async ({ page }) => {
    await setPortalSessionFixture(page, "agent");
    await mockCustomerPortalApis(page);
    await page.goto("/customer/dashboard");
    await expect(page).toHaveURL(/\/agent\/dashboard$/);
  });

  test("unauthenticated user is redirected to login", async ({ page }) => {
    await setPortalSessionFixture(page, "anonymous");
    await page.goto("/customer/dashboard");
    await expect(page).toHaveURL(/\/login$/);
  });

  test("/customer redirects to dashboard", async ({ page }) => {
    await page.goto("/customer");
    await expect(page).toHaveURL(/\/customer\/dashboard$/);
  });

  test("customer travelers page loads", async ({ page }) => {
    await page.route("**/laravel/customer/travelers?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          travelers: [],
          default_traveler: null,
          pagination: { current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null },
          countries: [{ code: "PK", name: "Pakistan" }],
          create_url: "/laravel/customer/travelers",
        }),
      });
    });
    await page.goto("/customer/travelers");
    await expect(page.getByRole("heading", { name: "Saved travelers", exact: true })).toBeVisible();
    await expect(page.getByTestId("customer-empty-state")).toBeVisible();
  });
});
