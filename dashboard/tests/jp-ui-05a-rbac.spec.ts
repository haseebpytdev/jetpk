import { expect, test } from "@playwright/test";

test.describe("JP-UI-05A dashboard platform staff RBAC", () => {
  test("platform staff permitted route renders bookings workspace", async ({ page }) => {
    await page.goto("/staff/dashboard/bookings?dataSourcePreview=fixture&jpui05a=staff-permitted");
    await expect(page.getByTestId("bookings-filters")).toBeVisible({ timeout: 30_000 });
    await expect(page.getByTestId("dashboard-shell")).toBeVisible();
  });

  test("platform staff forbidden route shows access denied", async ({ page }) => {
    await page.goto("/staff/dashboard/users?dataSourcePreview=forbidden&jpui05a=staff-forbidden");
    await expect(page.getByTestId("dashboard-shell")).toBeVisible({ timeout: 30_000 });
    await expect(page.getByRole("heading", { name: "Access denied" })).toBeVisible();
    await expect(page.getByTestId("data-source-preview-gate")).toBeVisible();
  });

  test("dashboard private routes are noindex", async ({ page }) => {
    await page.goto("/admin/dashboard?dataSourcePreview=fixture&jpui05a=noindex");
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute("content", /noindex/i);
  });
});
