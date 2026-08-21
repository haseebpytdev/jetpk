import { expect, test } from "@playwright/test";

/**
 * Wave-6 Cluster C — stop/layover tooltip must never crash the results page.
 */
test.describe("wave-6 stop tooltip crash closure", () => {
  test("malformed layover metadata and repeated hover survive", async ({ page }) => {
    const errors: string[] = [];
    page.on("pageerror", (error) => errors.push(error.message));

    await page.route("**/laravel/flights/results/nearby-dates**", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ dates: [] }) });
    });
    await page.route("**/laravel/flights/results/data**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          search_id: "wave6-stop-tooltip",
          page: 1,
          per_page: 12,
          total: 2,
          has_more: false,
          offers: [
            {
              offer_id: "connected-1",
              airline_code: "EY",
              airline_name: "Etihad Airways",
              departure_time: "08:10",
              arrival_time: "14:40",
              duration: "5h 30m",
              stops: 1,
              stops_label_display: "1 Stop",
              // Deliberately messy optional metadata shapes.
              layover_summary_display: "1h 20m layover · AUH",
              layovers_display: [
                { airport_code: "AUH", airport_city: null, duration_minutes: 80 },
                null,
                { airport_code: "", city: "", duration_display: "" },
              ],
              displayed_price: 155488,
              final_customer_price: 155488,
              can_book: true,
              segments: [
                {
                  origin_airport_code: "ISB",
                  destination_airport_code: "AUH",
                  departure_time_display: "08:10",
                  arrival_time_display: "10:30",
                },
                {
                  origin_airport_code: "AUH",
                  destination_airport_code: "DXB",
                  departure_time_display: "11:50",
                  arrival_time_display: "14:40",
                },
              ],
            },
            {
              offer_id: "connected-2",
              airline_code: "QR",
              airline_name: "Qatar Airways",
              departure_time: "09:00",
              arrival_time: "16:10",
              duration: "6h 10m",
              stops: 2,
              stops_label_display: "2 Stops",
              layover_summary_display: ["45m layover · DOH", "1h 05m layover · BAH"],
              layovers_display: [],
              displayed_price: 164368,
              final_customer_price: 164368,
              can_book: true,
              segments: [
                { origin_airport_code: "ISB", destination_airport_code: "DOH", departure_time_display: "09:00", arrival_time_display: "11:00" },
                { origin_airport_code: "DOH", destination_airport_code: "BAH", departure_time_display: "11:45", arrival_time_display: "12:30" },
                { origin_airport_code: "BAH", destination_airport_code: "DXB", departure_time_display: "13:35", arrival_time_display: "16:10" },
              ],
            },
          ],
          filters: { stops: [], airlines: [], departure_windows: [], arrival_windows: [], refundable: [], baggage_options: [], fare_families: [], duration_buckets: [], layover_airports: [] },
        }),
      });
    });

    await page.goto("/flights/results?search_id=wave6-stop-tooltip");
    await expect(page.getByTestId("flight-result-card").first()).toBeVisible();
    await expect(page.getByText("Something went wrong")).toHaveCount(0);

    const stopButtons = page.getByRole("button", { name: /layover/i });
    await expect(stopButtons.first()).toBeVisible();

    for (let i = 0; i < 20; i += 1) {
      await stopButtons.first().hover();
      await page.mouse.move(5, 5);
    }

    await stopButtons.first().focus();
    await page.keyboard.press("Enter");
    await page.keyboard.press("Escape");
    await page.keyboard.press(" ");
    await page.keyboard.press("Escape");

    await expect(page.getByText("Something went wrong")).toHaveCount(0);
    expect(errors.filter((message) => /maximum update depth|cannot read|is not a function/i.test(message))).toEqual([]);
  });
});
