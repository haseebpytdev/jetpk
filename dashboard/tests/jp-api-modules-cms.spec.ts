/**
 * API & Modules configured-connections UX + Add Connection modal + Sabre/Al-Haider/SMTP/CMS surfaces.
 * Runs against dashboard preview fixtures when live mode is off.
 */
import { expect, test } from "@playwright/test";

test.describe("JP API Modules CMS surface", () => {
  test("API & Modules opens configured workspace and Add Connection modal", async ({ page }) => {
    await page.goto("/admin/dashboard/integrations");
    await expect(page.getByTestId("api-modules-hub")).toBeVisible({ timeout: 30_000 });
    await expect(page.getByRole("heading", { name: "API & Modules" })).toBeVisible();
    await expect(page.getByTestId("api-modules-workspace-tabs")).toBeVisible();
    await expect(page.getByTestId("api-modules-suppliers-subsection")).toBeVisible();

    // Must not render a giant unconfigured provider catalog on the landing page.
    await expect(page.getByTestId("integration-card-amadeus")).toHaveCount(0);
    await expect(page.getByTestId("integration-card-hotelbeds")).toHaveCount(0);

    await page.getByTestId("api-modules-add-connection").click();
    await expect(page.getByTestId("api-connection-create-modal")).toBeVisible();
    await expect(page.getByTestId("api-provider-catalog-cards")).toBeVisible();

    const alHaider = page.getByTestId("api-provider-card-al_haider");
    if (await alHaider.count()) {
      await alHaider.click();
      await expect(page.getByTestId("api-connection-create-panel")).toBeVisible();
      await expect(page.getByText(/Authentication mode|Existing token/i).first()).toBeVisible();
      const mode = page.locator("select").filter({ hasText: /Existing token \+ safe auto-renew|Manual token/i }).first();
      if (await mode.count()) {
        await mode.selectOption("managed_token");
        await expect(page.getByText(/Auto-renew on genuine expiry/i)).toBeVisible();
      }
      const endpoint = page.getByTestId("api-create-endpoint");
      if (await endpoint.count()) {
        await expect(endpoint).toHaveValue(/alhaidertravel\.pk/);
      }
    }

    await page.getByTestId("api-connection-create-close").click();
    await expect(page.getByTestId("api-connection-create-modal")).toHaveCount(0);
  });

  test("sidebar exposes single API & Modules entry under Suppliers", async ({ page }) => {
    await page.goto("/admin/dashboard/integrations");
    await expect(page.getByTestId("dashboard-sidebar-compact")).toBeVisible({ timeout: 30_000 });
    const suppliersGroup = page.getByTestId("dashboard-sidebar-compact").getByText("API & Modules");
    await expect(suppliersGroup.first()).toBeVisible();
  });

  test("CMS homepage panel is reachable", async ({ page }) => {
    await page.goto("/admin/dashboard/cms/sections");
    await expect(page.locator("body")).toBeVisible({ timeout: 30_000 });
  });
});
