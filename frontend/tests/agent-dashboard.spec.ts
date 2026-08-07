import { expect, test } from "@playwright/test";
import {
  mockAgentPortalApis,
  setPortalSessionFixture,
  setupAgentPortalSession,
} from "./helpers/portal-session-fixtures";

test.describe("JP-FE-12 agent dashboard", () => {
  test.beforeEach(async ({ page }) => {
    await setupAgentPortalSession(page);
  });

  test("agent dashboard shell and overview load", async ({ page }) => {
    await page.goto("/agent/dashboard");
    await expect(page.getByTestId("agent-dashboard-shell")).toBeVisible();
    await expect(page.getByTestId("agent-dashboard-overview")).toBeVisible();
    await expect(page.getByRole("heading", { name: /dashboard overview/i })).toBeVisible();
    await expect(page.getByTestId("agent-dashboard-shell").getByText("Agency owner", { exact: true })).toBeVisible();
  });

  test("agent bookings list loads from Laravel JSON", async ({ page }) => {
    await page.goto("/agent/bookings");
    await expect(page.getByTestId("agent-bookings-list")).toBeVisible();
    await expect(page.getByText("BKG-AGENT-1001")).toBeVisible();
  });

  test("customer is rejected from agent dashboard", async ({ page }) => {
    await setPortalSessionFixture(page, "customer");
    await mockAgentPortalApis(page);
    await page.goto("/agent/dashboard");
    await expect(page).toHaveURL(/\/customer\/dashboard$/);
  });

  test("unauthenticated user is redirected to login", async ({ page }) => {
    await setPortalSessionFixture(page, "anonymous");
    await page.goto("/agent/dashboard");
    await expect(page).toHaveURL(/\/login$/);
  });

  test("/agent redirects to dashboard", async ({ page }) => {
    await page.goto("/agent");
    await expect(page).toHaveURL(/\/agent\/dashboard$/);
  });
});
