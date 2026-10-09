import { expect, test } from "@playwright/test";
import { expectTableReady } from "./helpers";

test.describe("JP-OPS-09 booking management navigation", () => {
  test("bookings View opens full page not drawer", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 720 });
    await page.goto("/admin/dashboard/bookings", { waitUntil: "load" });
    const table = page.getByTestId("bookings-table");
    await expectTableReady(table);
    const view = table.locator("tbody tr").first().getByRole("button");
    await view.click();
    await expect(page).toHaveURL(/\/admin\/dashboard\/bookings\/[^/?]+$/);
    await expect(page.getByTestId("booking-detail-page")).toBeVisible();
    await expect(page.getByRole("dialog")).toHaveCount(0);
    await expect(page.getByTestId("booking-management-section-nav")).toBeVisible();
  });

  test("pnrs View with linked booking opens booking management page", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 720 });
    await page.goto("/admin/dashboard/pnrs", { waitUntil: "load" });
    const table = page.getByTestId("pnrs-table");
    await expectTableReady(table);
    const view = table.locator("tbody tr").first().getByRole("button");
    await view.click();
    await expect(page).toHaveURL(/\/admin\/dashboard\/bookings\//);
    await expect(page.getByTestId("booking-detail-page")).toBeVisible();
    await expect(page.getByRole("dialog")).toHaveCount(0);
  });
});
