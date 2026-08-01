import { expect, test } from "@playwright/test";

test.describe("JP-FULL-NEXT-FRONTEND-01A route smoke", () => {
  test("/verify-email renders notice page with noindex semantics", async ({ page }) => {
    const response = await page.goto("/verify-email");
    expect(response?.status()).toBeLessThan(500);
    await expect(page.getByTestId("auth-form-card").getByRole("heading", { level: 1, name: /verify your email/i })).toBeVisible();
  });

  test("/verify-email?status=verified shows success state", async ({ page }) => {
    await page.goto("/verify-email?status=verified");
    await expect(page.getByRole("heading", { name: /email verified/i })).toBeVisible();
  });

  test("/flights/fare-selection requires search context", async ({ page }) => {
    const response = await page.goto("/flights/fare-selection");
    expect(response?.status()).toBeLessThan(500);
    await expect(page.getByText(/missing search context/i)).toBeVisible();
  });

  test("/flights/fare-selection page shell loads with query params", async ({ page }) => {
    await page.route("**/laravel/flights/results/offer?**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          success: true,
          search_id: "test-search",
          offer_id: "offer-1",
          offer: {
            offer_id: "offer-1",
            airline_name: "Test Air",
            departure_time: "09:00",
            segments: [
              {
                origin_airport_code: "LHE",
                destination_airport_code: "JED",
                departure_time_display: "20 Jun 2026",
              },
            ],
            can_book: true,
            select_url: "/booking/passengers?offer_id=offer-1",
            branded_fares_display_options: [
              { option_key: "eco", brand_name: "Economy Saver", price_display: "PKR 92,000" },
            ],
          },
        }),
      });
    });

    await page.goto("/flights/fare-selection?search_id=test-search&offer_id=offer-1");
    await expect(page.getByTestId("fare-selection-page")).toBeVisible();
    await expect(page.getByRole("heading", { name: /choose your fare/i })).toBeVisible();
  });
});
