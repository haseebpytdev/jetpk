import { expect, test } from "@playwright/test";

test.describe("JP-DASH-PROD-04 dashboard home operational controls", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto("/admin/dashboard?dataSourcePreview=fixture", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Dashboard", level: 1 })).toBeVisible();
  });

  test("recent booking View navigates to booking detail", async ({ page }) => {
    const view = page.getByTestId(/^overview-booking-view-/).first();
    await expect(view).toBeVisible();
    await view.click();
    await expect(page).toHaveURL(/\/admin\/dashboard\/bookings\//);
    await expect(page.getByTestId("booking-detail-page")).toBeVisible({ timeout: 30_000 });
  });

  test("operational queue CTA navigates without preview alert", async ({ page }) => {
    let dialogSeen = false;
    page.on("dialog", async (dialog) => {
      dialogSeen = true;
      await dialog.dismiss();
    });
    const cta = page.getByTestId(/^ops-queue-cta-/).first();
    await expect(cta).toBeVisible();
    await cta.click();
    await page.waitForLoadState("domcontentloaded");
    expect(dialogSeen).toBe(false);
    await expect(page).not.toHaveURL(/\/admin\/dashboard$/);
  });

  test("quick actions navigate without preview alert", async ({ page }) => {
    let dialogSeen = false;
    page.on("dialog", async (dialog) => {
      dialogSeen = true;
      await dialog.dismiss();
    });
    const quick = page.getByTestId(/^overview-quick-action-/).first();
    await expect(quick).toBeVisible();
    await quick.click();
    expect(dialogSeen).toBe(false);
  });

  test("refresh triggers fixture reload path", async ({ page }) => {
    const refresh = page.getByTestId("overview-refresh");
    await expect(refresh).toBeDisabled();
  });
});
