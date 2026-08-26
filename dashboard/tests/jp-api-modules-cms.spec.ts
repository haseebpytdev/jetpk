/**
 * API & Modules configured-connections UX + Add Connection modal + Sabre/Al-Haider surfaces.
 * Runs against dashboard preview fixtures when live mode is off.
 */
import { expect, test } from "@playwright/test";

test.describe("JP API Modules CMS surface", () => {
  test("API & Modules opens configured workspace and Add Connection modal", async ({ page }) => {
    await page.goto("/admin/dashboard/integrations");
    await expect(page.getByTestId("api-modules-hub")).toBeVisible({ timeout: 30_000 });
    await expect(page.getByRole("heading", { name: "API & Modules" })).toBeVisible();

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
      await expect(page.getByText(/Existing token \/ Manual token|Authentication mode/i).first()).toBeVisible();
      const endpoint = page.getByTestId("api-create-endpoint");
      if (await endpoint.count()) {
        await expect(endpoint).toHaveValue(/alhaidertravel\.pk/);
      }
    }

    await page.getByTestId("api-connection-create-close").click();
    await expect(page.getByTestId("api-connection-create-modal")).toHaveCount(0);
  });
});
