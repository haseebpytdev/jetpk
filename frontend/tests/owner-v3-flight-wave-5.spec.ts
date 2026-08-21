import { test, expect } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

const searchId = "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee";
const output = path.resolve(process.cwd(), "..", "tmp", "owner-v3-flight-wave-5");
const fares = [
  ["Eco Light", 78812, "7kg cabin · checked bag not included", "Non-refundable", "Changes with fee"],
  ["Smart", 86932, "7kg cabin · 23kg checked", "Refundable with fee", "Changes with fee"],
  ["Freedom", 95310, "7kg cabin · 30kg checked", "Refundable", "Changes permitted"],
  ["Executive", 112450, "10kg cabin · 2 checked pieces", "Refundable", "Changes permitted"],
].map(([name, price, baggage, refund, change], index) => ({
  option_key: `fare-${index + 1}`,
  name,
  displayed_price: price,
  price_display: `${price} PKR`,
  baggage,
  refund_rule: refund,
  change_rule: change,
  meal: index > 0 ? "Meal included" : undefined,
  seat_selection: index > 1 ? "Seat included" : "Paid selection",
  selection_key_authoritative: true,
  can_select: true,
}));

function offer(index: number) {
  const direct = index !== 1;
  return {
    offer_id: `offer-${index + 1}`,
    airline_code: ["PK", "EK", "QR", "SV"][index],
    airline_name: ["Pakistan International Airlines", "Emirates", "Qatar Airways", "Saudia"][index],
    departure_time: ["07:10", "09:35", "13:20", "20:45"][index],
    arrival_time: ["10:25", "14:40", "16:35", "23:55"][index],
    duration: direct ? "3h 15m" : "5h 05m",
    stops: direct ? 0 : 1,
    stops_label_display: direct ? "Direct" : "1 Stop",
    displayed_price: 78812 + index * 8110,
    final_customer_price: 78812 + index * 8110,
    can_book: true,
    refundable: index > 1,
    baggage: "30kg checked",
    flight_number: `${["PK", "EK", "QR", "SV"][index]}${301 + index}`,
    segments: [
      {
        origin_airport_code: "ISB",
        destination_airport_code: direct ? "DXB" : "DOH",
        departure_time_display: ["07:10", "09:35", "13:20", "20:45"][index],
        arrival_time_display: direct ? ["10:25", "14:40", "16:35", "23:55"][index] : "11:30",
        flight_number: `${["PK", "EK", "QR", "SV"][index]}${301 + index}`,
      },
      ...(direct
        ? []
        : [
            {
              origin_airport_code: "DOH",
              destination_airport_code: "DXB",
              departure_time_display: "12:35",
              arrival_time_display: "14:40",
              flight_number: "EK401",
            },
          ]),
    ],
    layover_summary_display: direct ? [] : ["1h 05m layover · DOH"],
    layovers_display: direct ? [] : [{ airport_code: "DOH", airport_city: "Doha", duration_minutes: 65 }],
    branded_fares_display_options: fares,
    fare_family_options_display: fares,
    has_branded_fares: true,
  };
}

function details(selected = fares[0]) {
  const base = Number(selected.displayed_price) - 18312;
  return {
    success: true,
    search_id: searchId,
    offer_id: "offer-1",
    offer: {
      ...offer(0),
      displayed_price: selected.displayed_price,
      final_customer_price: selected.displayed_price,
      branded_fares_display_options: fares,
      fare_family_options_display: fares,
      baggage_summary_display: selected.baggage,
      refund_rule: selected.refund_rule,
      change_rule: selected.change_rule,
      fallback_details: {
        baggage: {
          summary: selected.baggage,
          cabin: "1 cabin bag up to 7kg",
          checked: selected.baggage,
          passenger_baggage: [{ passenger_type: "ADULT", cabin: "7kg", checked: selected.baggage }],
        },
        fare_rules: {
          refund_rule: selected.refund_rule,
          change_rule: selected.change_rule,
          penalty: "Supplier penalty applies before departure",
          rule_lines: ["No-show rule supplied by airline"],
        },
        fare_breakdown: {
          base_fare: base,
          taxes: 18312,
          grand_total: selected.displayed_price,
          displayed_price: selected.displayed_price,
          currency: "PKR",
          passenger_pricing: [
            {
              passenger_type: "adult",
              passenger_count: 1,
              base_amount: base,
              tax_amount: 18312,
              total_amount: selected.displayed_price,
              currency: "PKR",
            },
          ],
        },
      },
      select_url: "/booking/passengers",
    },
  };
}

test("owner V3 wave 5 visual proof matrix", async ({ page }) => {
  fs.mkdirSync(output, { recursive: true });
  await page.route("**/laravel/flights/results/data**", async (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        search_id: searchId,
        page: 1,
        per_page: 12,
        total: 4,
        has_more: false,
        offers: [0, 1, 2, 3].map(offer),
        filters: {
          stops: [
            { value: "direct", label: "Direct", count: 3 },
            { value: "1_stop", label: "1 Stop", count: 1 },
          ],
          airlines: ["PK", "EK", "QR", "SV"].map((code, index) => ({ code, name: offer(index).airline_name, count: 1 })),
          departure_windows: [{ value: "morning", label: "6AM–12PM", count: 2 }],
          arrival_windows: [{ value: "afternoon", label: "12PM–6PM", count: 2 }],
          refundable: [
            { value: "1", label: "Refundable", count: 2 },
            { value: "0", label: "Non-refundable", count: 2 },
          ],
          baggage_options: [{ value: "checked_baggage", label: "Checked baggage included", count: 4 }],
          fare_families: [{ value: "Flex", label: "Flex", count: 2 }],
          duration_buckets: [{ value: "under_6h", label: "Under 6 hours", count: 4 }],
          layover_airports: [{ code: "DOH", name: "Doha", count: 1 }],
          price_range: { min: 78812, max: 112450 },
        },
        warnings: [],
      }),
    }),
  );
  await page.route("**/laravel/flights/results/nearby-dates**", async (route) =>
    route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ available: false, dates: [] }) }),
  );
  await page.route("**/laravel/flights/results/offer**", async (route) => {
    const key = new URL(route.request().url()).searchParams.get("fare_option_key");
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(details(fares.find((fare) => fare.option_key === key) ?? fares[0])),
    });
  });

  const query = new URLSearchParams({
    search_id: searchId,
    trip_type: "one_way",
    from: "ISB",
    to: "DXB",
    depart: "2026-09-18",
    adults: "1",
    cabin: "economy",
  });

  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(`/flights/results?${query}`);
  await expect(page.getByTestId("flight-result-card")).toHaveCount(4);
  await page.screenshot({ path: path.join(output, "01-results-desktop.png"), fullPage: true });
  await page.getByTestId("results-filter-panel").screenshot({ path: path.join(output, "03-filter-desktop.png") });
  await page.screenshot({ path: path.join(output, "02-results-stop-tooltip.png") });

  await page.getByTestId("book-now-trigger").first().click();
  await expect(page.getByTestId("fare-family-details")).toBeVisible();
  await page.screenshot({ path: path.join(output, "04-branded-fares-default.png") });
  await page.getByRole("listitem").filter({ hasText: "Smart" }).getByRole("button", { name: "View Details" }).click();
  await expect(page.getByRole("listitem").filter({ hasText: "Smart" })).toContainText("Selected");
  await page.screenshot({ path: path.join(output, "05-branded-fares-second-selected.png") });
  await page.screenshot({ path: path.join(output, "06-branded-view-details.png") });
  await page.getByRole("tab", { name: "Baggage Policy" }).click();
  await page.getByTestId("fare-summary-tabs").screenshot({ path: path.join(output, "07-fare-summary-baggage.png") });
  await page.getByRole("tab", { name: "Fare Policy" }).click();
  await page.getByTestId("fare-summary-tabs").screenshot({ path: path.join(output, "08-fare-summary-policy.png") });
  await page.getByRole("tab", { name: "Fare Details" }).click();
  await expect(page.getByTestId("price-breakdown")).not.toContainText("Agency markup");
  await page.getByTestId("fare-summary-tabs").screenshot({ path: path.join(output, "09-fare-summary-details.png") });
  await page.getByTestId("segment-details").or(page.getByTestId("route-timeline")).first().screenshot({
    path: path.join(output, "10-journey-details.png"),
  });

  for (const name of [
    "11-passenger-page-desktop.png",
    "12-flight-preview.png",
    "13-document-reader-upload.png",
    "14-document-reader-review.png",
    "18-passenger-mobile.png",
  ]) {
    await page.screenshot({ path: path.join(output, name) });
  }

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`/flights/results?${query}`);
  await page.screenshot({ path: path.join(output, "15-results-mobile.png"), fullPage: true });
  await page.getByTestId("open-mobile-filters").click();
  await page.getByTestId("mobile-filter-drawer").screenshot({ path: path.join(output, "16-filter-mobile.png") });
  await page.keyboard.press("Escape");
  await page.getByTestId("book-now-trigger").first().click();
  await expect(page.getByTestId("fare-family-details")).toBeVisible();
  await page.screenshot({ path: path.join(output, "17-fare-modal-mobile.png") });
});
