import { test, expect } from "@playwright/test";
import path from "node:path";

const repoRoot = path.resolve(__dirname, "../..");
const adminState = path.join(repoRoot, "tmp/jp-dash-03-admin-storage-state.json");

test.use({
  baseURL: process.env.PLAYWRIGHT_BASE_URL ?? "https://jetpakistan.pk",
  storageState: adminState,
});

test.describe("JP-DASH-PROD-01 production operational cert", () => {
  test.skip(!process.env.JP_PROD_CERT, "Set JP_PROD_CERT=1 to run against production");

  test("admin dashboard loads live", async ({ page }) => {
    await page.goto("/admin/dashboard", { waitUntil: "domcontentloaded" });
    await expect(page.getByRole("heading", { name: "Dashboard", level: 1 })).toBeVisible();
  });

  test("bookings shows QA fixture", async ({ page }) => {
    await page.goto("/admin/dashboard/bookings", { waitUntil: "domcontentloaded" });
    await expect(page.getByText(/JPQA-20261008/i).first()).toBeVisible({ timeout: 30000 });
  });

  test("api connections modal opens", async ({ page }) => {
    await page.goto("/admin/dashboard/api-connections", { waitUntil: "domcontentloaded" });
    await page.getByTestId("api-connection-add-card").click();
    await expect(page.getByTestId("api-connection-create-modal")).toBeVisible({ timeout: 20_000 });
    await expect(page.getByTestId("api-provider-catalog-cards")).toBeVisible();
  });
});
