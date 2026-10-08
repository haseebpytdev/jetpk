import { expect, test } from "@playwright/test";

test.describe("JP-DASH-PROD-04 payment detail drawer UI", () => {
  test("opens drawer from table View and deep link", async ({ page }) => {
    await page.goto("/admin/dashboard/payments?dataSourcePreview=fixture", { waitUntil: "load" });
    await page.getByTestId("payments-filters").waitFor({ state: "visible" });
    await page.locator("table tbody").getByRole("button", { name: "View" }).first().click();
    await expect(page).toHaveURL(/transactionId=/, { timeout: 15_000 });
    await expect(
      page.getByTestId("payment-drawer-content").or(page.getByTestId("payment-drawer-missing")),
    ).toBeVisible({ timeout: 15_000 });

    await page.goto("/admin/dashboard/payments?dataSourcePreview=fixture&transactionId=JP-TX-20025", {
      waitUntil: "load",
    });
    await expect(page.getByTestId("payment-drawer-content")).toBeVisible({ timeout: 15_000 });
    await expect(
      page.getByTestId("payment-review-actions").or(page.getByTestId("payment-actions-unavailable")),
    ).toBeVisible();
  });
});
