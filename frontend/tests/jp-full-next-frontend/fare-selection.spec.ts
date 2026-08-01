import { expect, test } from "@playwright/test";
import { attachRuntimeGuards } from "./helpers";

test.describe("JP-FULL-NEXT-FRONTEND-01B fare selection", () => {
  test("missing search context shows recovery", async ({ page }) => {
    const guards = await attachRuntimeGuards(page);
    await page.goto("/flights/fare-selection");
    await expect(page.getByText(/missing search context/i)).toBeVisible();
    await guards.assertClean();
  });

  test("invalid offer reference shows error state", async ({ page }) => {
    await page.route("**/laravel/flights/results/offer?**", async (route) => {
      await route.fulfill({
        status: 404,
        contentType: "application/json",
        body: JSON.stringify({ success: false, message: "Offer not found." }),
      });
    });
    await page.goto("/flights/fare-selection?search_id=test&offer_id=missing");
    await expect(page.getByText(/unable to load fare options|offer not found/i)).toBeVisible();
  });

  test("expired offer shows expired state", async ({ page }) => {
    await page.route("**/laravel/flights/results/offer?**", async (route) => {
      await route.fulfill({
        status: 410,
        contentType: "application/json",
        body: JSON.stringify({ success: false, message: "This search has expired." }),
      });
    });
    await page.goto("/flights/fare-selection?search_id=test&offer_id=expired");
    await expect(page.getByRole("heading", { name: /search expired/i })).toBeVisible();
  });

  test("successful one-way load shows fare families and continue", async ({ page }) => {
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
            supplier_provider: "iati",
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
              { option_key: "flex", brand_name: "Economy Flex", price_display: "PKR 112,000" },
            ],
          },
        }),
      });
    });

    await page.goto("/flights/fare-selection?search_id=test-search&offer_id=offer-1");
    await expect(page.getByTestId("fare-selection-page")).toBeVisible();
    await expect(page.getByTestId("fare-family-details")).toBeVisible();
    await expect(page.getByRole("button", { name: /continue to passengers/i })).toBeVisible();
  });

  test("page has noindex robots meta", async ({ page }) => {
    await page.goto("/flights/fare-selection");
    const robots = await page.locator('meta[name="robots"]').getAttribute("content");
    expect(robots ?? "").toMatch(/noindex/i);
  });

  test("no raw card fields on fare selection page", async ({ page }) => {
    await page.goto("/flights/fare-selection?search_id=a&offer_id=b");
    await expect(page.locator('input[autocomplete="cc-number"]')).toHaveCount(0);
    await expect(page.locator('input[name*="cvv" i]')).toHaveCount(0);
  });
});
