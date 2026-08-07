import { expect, test } from "@playwright/test";
import {
  mockAgentPortalApis,
  mockCustomerPortalApis,
  setPortalSessionFixture,
} from "./helpers/portal-session-fixtures";

const viewports = [
  { name: "390x844", width: 390, height: 844 },
  { name: "768x1024", width: 768, height: 1024 },
  { name: "1280x800", width: 1280, height: 800 },
] as const;

for (const viewport of viewports) {
  test(`customer portal interior renders at ${viewport.name}`, async ({ page }) => {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    await setPortalSessionFixture(page, "customer");
    await mockCustomerPortalApis(page);
    await page.goto("/customer/dashboard", { waitUntil: "load" });
    await expect(page.getByTestId("customer-dashboard-shell")).toBeVisible();
    await expect(page.getByTestId("customer-dashboard-overview")).toBeVisible();
    await expect(page).not.toHaveURL(/\/login$/);
  });

  test(`agent portal interior renders at ${viewport.name}`, async ({ page }) => {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    await setPortalSessionFixture(page, "agent");
    await mockAgentPortalApis(page);
    await page.goto("/agent/dashboard", { waitUntil: "load" });
    await expect(page.getByTestId("agent-dashboard-shell")).toBeVisible();
    await expect(page.getByTestId("agent-dashboard-overview")).toBeVisible();
    await expect(page).not.toHaveURL(/\/login$/);
  });
}

test("agent staff portal interior renders with fixture session", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await setPortalSessionFixture(page, "agent_staff");
  await mockAgentPortalApis(page);
  await page.goto("/agent/dashboard", { waitUntil: "load" });
  await expect(page.getByTestId("agent-dashboard-shell")).toBeVisible();
  await expect(page).not.toHaveURL(/\/login$/);
});
