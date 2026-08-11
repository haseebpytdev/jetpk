import { test, expect } from "@playwright/test";

test("dashboard sidebar shows JetPakistan brand fallback without configured logo", async ({ page }) => {
  await page.goto("/admin/dashboard", { waitUntil: "load" });
  await expect(page.getByTestId("dashboard-portal-label")).toBeVisible();
  await expect(page.getByLabel("Dashboard navigation").getByText("JetPakistan")).toBeVisible();
  await expect(page.getByTestId("dashboard-brand-logo")).toHaveCount(0);
});

test("staff dashboard sidebar uses the same branding contract", async ({ page }) => {
  await page.goto("/staff/dashboard", { waitUntil: "load" });
  await expect(page.getByTestId("dashboard-portal-label")).toBeVisible();
  await expect(page.getByLabel("Dashboard navigation").getByText("JetPakistan")).toBeVisible();
});
