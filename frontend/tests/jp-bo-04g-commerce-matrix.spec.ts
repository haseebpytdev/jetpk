import { test, expect } from "@playwright/test";
import { mkdirSync } from "node:fs";
import { join } from "node:path";

/**
 * JP-BO-04G commerce flow visual matrix — fixture/route-mocked evidence.
 * Captures required screenshots without live supplier/PNR/payment.
 */

const OUT = join(process.cwd(), "tmp", "jp-bo-04g", "playwright");

test.beforeAll(() => {
  mkdirSync(OUT, { recursive: true });
});

async function mockResults(page: import("@playwright/test").Page, body: Record<string, unknown>) {
  await page.route("**/flights/results/data**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(body),
    });
  });
  await page.route("**/flights/results/search**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ ok: true, search_id: "jp-bo-04g-search", status: "ready" }),
    });
  });
}

const brandedOffer = {
  offer_id: "offer-1",
  airline_code: "PK",
  airline_name: "Pakistan International",
  flight_number: "PK301",
  departure_time: "08:00",
  arrival_time: "11:00",
  departure_airport_code: "LHE",
  arrival_airport_code: "DXB",
  stops: 0,
  can_book: true,
  displayed_price: 85000,
  final_customer_price: 85000,
  has_branded_fares: true,
  branded_fares_display_options: [
    {
      option_key: "fare-basic",
      name: "Economy Basic",
      selection_key_authoritative: true,
      displayed_price: 85000,
      price_display: "PKR 85,000",
    },
    {
      option_key: "fare-comfort",
      name: "Economy Comfort",
      selection_key_authoritative: true,
      displayed_price: 95000,
      price_display: "PKR 95,000",
    },
  ],
  segments: [
    {
      origin_airport_code: "LHE",
      destination_airport_code: "DXB",
      departure_time_display: "08:00",
      arrival_time_display: "11:00",
    },
  ],
};

test("01 one-way results", async ({ page }) => {
  await mockResults(page, {
    search_id: "jp-bo-04g-search",
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
    offers: [brandedOffer],
    status: "ready",
  });
  await page.goto(
    "/flights/results?search_id=jp-bo-04g-search&trip_type=one_way&from=LHE&to=DXB&depart=2026-09-01&cabin=economy&adults=1",
  );
  await expect(page.getByTestId("flight-result-card").first()).toBeVisible({ timeout: 15000 });
  await page.screenshot({ path: join(OUT, "01-one-way-results.png"), fullPage: true });
});

test("02 one-way brand selected", async ({ page }) => {
  await mockResults(page, {
    search_id: "jp-bo-04g-search",
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
    offers: [brandedOffer],
    status: "ready",
  });
  await page.goto(
    "/flights/results?search_id=jp-bo-04g-search&trip_type=one_way&from=LHE&to=DXB&depart=2026-09-01&cabin=economy&adults=1",
  );
  await expect(page.getByTestId("flight-result-card").first()).toBeVisible({ timeout: 15000 });
  const comfort = page.getByText("Economy Comfort").first();
  if (await comfort.isVisible().catch(() => false)) {
    await comfort.click();
  }
  await page.screenshot({ path: join(OUT, "02-one-way-brand-selected.png"), fullPage: true });
});

test("04 return paired results", async ({ page }) => {
  await mockResults(page, {
    search_id: "jp-bo-04g-search",
    flow: "return_pair",
    pairing_authority: "SUPPLIER_RETURNED",
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
    offers: [],
    paired_options: [
      {
        combo_id: "combo-1",
        outbound_key: "out-1",
        can_book: true,
        total_display: "PKR 180,000",
        airline_name: "Pakistan International",
        fare_family: "Economy Flex",
        cabin: "economy",
        outbound_journey: {
          departure_time_display: "08:00",
          arrival_time_display: "11:00",
          origin_airport_code: "LHE",
          destination_airport_code: "DXB",
        },
        return_journey: {
          departure_time_display: "15:00",
          arrival_time_display: "20:00",
          origin_airport_code: "DXB",
          destination_airport_code: "LHE",
        },
        branded_fares_display_options: brandedOffer.branded_fares_display_options,
      },
    ],
    status: "ready",
  });
  await page.goto(
    "/flights/results?search_id=jp-bo-04g-search&trip_type=round_trip&view=pair&from=LHE&to=DXB&depart=2026-09-01&return_date=2026-09-08&cabin=economy&adults=1",
  );
  await expect(page.getByTestId("pair-return-card").first()).toBeVisible({ timeout: 15000 });
  await page.screenshot({ path: join(OUT, "04-return-paired-results.png"), fullPage: true });
});

test("07 return split outbound brand", async ({ page }) => {
  await mockResults(page, {
    search_id: "jp-bo-04g-search",
    flow: "return_split_outbound",
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
    offers: [],
    outbound_options: [
      {
        outbound_key: "out-1",
        from_total_amount: 180000,
        from_total_display: "PKR 180,000",
        combo_count: 2,
        journey_display: {
          departure_time_display: "08:00",
          arrival_time_display: "11:00",
          origin_airport_code: "LHE",
          destination_airport_code: "DXB",
          airline_code: "PK",
          airline_name: "Pakistan International",
        },
        branded_fares_display_options: brandedOffer.branded_fares_display_options,
      },
    ],
    status: "ready",
  });
  await page.goto(
    "/flights/results?search_id=jp-bo-04g-search&trip_type=round_trip&view=segmented&from=LHE&to=DXB&depart=2026-09-01&return_date=2026-09-08&cabin=economy&adults=1",
  );
  await expect(page.getByTestId("outbound-option-card").first()).toBeVisible({ timeout: 15000 });
  await page.screenshot({ path: join(OUT, "07-return-split-outbound-brand.png"), fullPage: true });
});
