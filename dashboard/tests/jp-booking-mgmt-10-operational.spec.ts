import { expect, test } from "@playwright/test";
import { expectTableReady } from "./helpers";

test.describe("JP-BOOKING-MGMT-10 booking management parity", () => {
  test("management page exposes operational sections", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 720 });
    await page.goto("/admin/dashboard/bookings/JP-BK-10001", { waitUntil: "load" });
    await expect(page.getByTestId("booking-detail-page")).toBeVisible();
    await expect(page.getByTestId("booking-section-booking-documents")).toBeVisible();
    await expect(page.getByTestId("booking-section-booking-assignment")).toBeVisible();
    await expect(page.getByTestId("booking-section-booking-cancellation")).toBeVisible();
    await expect(page.getByTestId("booking-section-booking-refunds")).toBeVisible();
    await expect(page.getByTestId("booking-section-booking-communication")).toBeVisible();
    await expect(page.getByTestId("booking-management-section-nav")).toBeVisible();
  });

  test("bookings list still opens full page", async ({ page }) => {
    await page.goto("/admin/dashboard/bookings", { waitUntil: "load" });
    const table = page.getByTestId("bookings-table");
    await expectTableReady(table);
    await table.locator("tbody tr").first().getByRole("button").click();
    await expect(page).toHaveURL(/\/admin\/dashboard\/bookings\/[^/?]+$/);
    await expect(page.getByRole("dialog")).toHaveCount(0);
  });
});
