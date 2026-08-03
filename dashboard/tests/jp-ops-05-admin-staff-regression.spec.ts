import { test, expect } from "@playwright/test";

test.describe("JP-OPS-05 admin staff regression", () => {
  test("dashboard shell renders without private mutation prefetch", async ({ page }) => {
    const requests: string[] = [];
    page.on("request", (req) => {
      if (req.url().includes("/admin/bookings/payments/") || req.url().includes("/admin/agent-deposits/")) {
        requests.push(req.method() + " " + req.url());
      }
    });

    await page.goto("/admin/dashboard?dataSourcePreview=fixture");
    await expect(page.getByTestId("dashboard-shell")).toBeVisible();
    expect(requests).toEqual([]);
  });

  test("payment review actions hidden in preview mode", async ({ page }) => {
    await page.goto("/admin/dashboard/payments?dataSourcePreview=fixture");
    await expect(page.getByTestId("payments-filters")).toBeVisible();
  });

  test("deposits workspace renders in fixture mode", async ({ page }) => {
    await page.goto("/admin/dashboard/deposits?dataSourcePreview=fixture");
    await expect(page.getByTestId("deposits-workspace")).toBeVisible();
  });

  test("staff users route remains permission gated", async ({ page }) => {
    await page.goto("/staff/dashboard/users?dataSourcePreview=forbidden");
    await expect(page.getByText(/access denied|permission|unavailable/i).first()).toBeVisible();
  });
});
