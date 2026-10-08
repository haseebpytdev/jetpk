import { expect, test } from "@playwright/test";

test.describe("JP-DASH-PROD-04 payment detail drawer UI", () => {
  test("opens drawer for fixture transaction from table View", async ({ page }) => {
    await page.goto("/admin/dashboard/payments?dataSourcePreview=fixture", { waitUntil: "load" });
    await page.getByRole("button", { name: "View" }).first().click();
    await expect(page).toHaveURL(/selectedTransactionId=/);
    await expect(page.getByTestId("payment-drawer-content")).toBeVisible({ timeout: 15_000 });
    await expect(page.getByTestId("payment-review-actions").or(page.getByTestId("payment-actions-unavailable"))).toBeVisible();
  });
});
