import type { Page } from "@playwright/test";
import type { JpUi06Scenario } from "./jp-ui-06-scenarios";
import { mockCsrf, setupResultsMocks, MOCK_SEARCH_ID } from "./jp-ui-01-fixtures";
import { ABOUT_FULL, mockManagedPage, setupPublicBaseline } from "./jp-ui-03a-fixtures";
import { setupJpUi04aScenario } from "./jp-ui-04a-fixtures";
import type { JpUi04aScenario } from "./jp-ui-04a-scenarios";
import { setupJpUi05Scenario } from "./jp-ui-05-fixtures";
import type { JpUi05Scenario } from "./jp-ui-05-scenarios";

const FARE_SELECTION_QUERY = `search_id=${MOCK_SEARCH_ID}&offer_id=audit-offer-1&fare_option_key=fare-1`;

async function setupFareSelectionFixture(page: Page): Promise<void> {
  await mockCsrf(page);
  await setupResultsMocks(page);
  await page.route("**/laravel/flights/results/offer?**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        search_id: MOCK_SEARCH_ID,
        offer_id: "audit-offer-1",
        flow: "one_way",
        revalidation_required: true,
        offer: {
          offer_id: "audit-offer-1",
          airline_code: "EK",
          airline_name: "Audit Airline",
          departure_time: "08:30",
          arrival_time: "11:45",
          duration: "3h 15m",
          stops: 0,
          stops_label_display: "Nonstop",
          displayed_price: 134047,
          price_display: "134,047 PKR",
          can_book: true,
          supplier_provider: "iati",
          provider: "iati",
          select_url: "/booking/passengers",
          segments: [{ origin_airport_code: "LHE", destination_airport_code: "DXB", flight_number: "EK612" }],
          has_branded_fares: true,
          branded_fares_display_options: [
            { option_key: "fare-1", name: "Economy Saver", price_display: "92,000 PKR", displayed_price: 92000, baggage: "20kg", refund_rule: "Non-refundable", change_rule: "Changes with fee" },
            { option_key: "fare-2", name: "Economy Flex", price_display: "112,000 PKR", displayed_price: 112000, baggage: "30kg", refund_rule: "Refundable with fee", change_rule: "Changes with fee" },
            { option_key: "fare-3", name: "Business", price_display: "198,000 PKR", displayed_price: 198000, baggage: "40kg", refund_rule: "Refundable", change_rule: "Free changes" },
          ],
        },
        search_freshness: { expires_display: "Results expire in 25 minutes" },
      }),
    });
  });
  await page.route("**/laravel/flights/results/revalidate-offer**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ success: true, status: "ok", passengers_url: `/booking/passengers?${FARE_SELECTION_QUERY}` }),
    });
  });
}

function as04a(family: JpUi04aScenario["family"], fixtureId: string): JpUi04aScenario {
  return {
    id: "jp-ui-06-bridge",
    family,
    route: "/",
    theme: "light",
    viewport: { name: "1122x1330", width: 1122, height: 1330 },
    zoom: 1,
    state: "layout",
    fixtureId,
  };
}

function as05(family: JpUi05Scenario["family"], fixtureId: string): JpUi05Scenario {
  return {
    id: "jp-ui-06-bridge",
    application: "frontend",
    family,
    route: "/",
    theme: "light",
    viewport: { name: "1122x1330", width: 1122, height: 1330 },
    zoom: 1,
    state: "layout",
    fixtureId,
  };
}

export function resolveJpUi06Route(scenario: JpUi06Scenario): string {
  if (scenario.family === "flight-results") {
    return `/flights/results?search_id=${MOCK_SEARCH_ID}`;
  }
  if (scenario.family === "fare-selection") {
    return `/flights/fare-selection?${FARE_SELECTION_QUERY}`;
  }
  if (scenario.family === "passenger-details") {
    return `/booking/passengers?search_id=${MOCK_SEARCH_ID}&offer_id=audit-offer-1&fare_option_key=fare-1`;
  }
  if (scenario.family === "seat-selection-capability-unavailable") {
    return `/booking/passengers?search_id=${MOCK_SEARCH_ID}&offer_id=audit-offer-1&fare_option_key=fare-1`;
  }
  return scenario.route;
}

export async function setupJpUi06Scenario(page: Page, scenario: JpUi06Scenario): Promise<void> {
  await mockCsrf(page);

  switch (scenario.family) {
    case "homepage":
      await setupPublicBaseline(page);
      break;
    case "about":
      await setupPublicBaseline(page);
      await mockManagedPage(page, "about", ABOUT_FULL);
      break;
    case "support":
      await setupPublicBaseline(page);
      await page.route("**/laravel/api/public/content/pages/support", async (route) => {
        await route.fulfill({ status: 404, contentType: "application/json", body: JSON.stringify({ message: "Not found" }) });
      });
      break;
    case "flight-results":
      await setupJpUi04aScenario(page, as04a("results", "results-default"));
      break;
    case "fare-selection":
      await setupFareSelectionFixture(page);
      break;
    case "passenger-details":
      await setupJpUi04aScenario(page, as04a("passengers", "passengers-default"));
      break;
    case "seat-selection-capability-unavailable":
      await setupJpUi04aScenario(page, as04a("passengers", "passengers-default"));
      break;
    case "review":
      await setupJpUi04aScenario(page, as04a("review", "review-default"));
      break;
    case "payment":
      await setupJpUi04aScenario(page, as04a("payment", "payment-card-ready"));
      break;
    case "booking-success":
      await setupJpUi04aScenario(page, as04a("success", "success-paid"));
      break;
    case "login":
      await setupJpUi05Scenario(page, as05("login", "auth-logged-out"));
      break;
    case "signup":
      await setupJpUi05Scenario(page, as05("signup", "auth-logged-out"));
      break;
    case "manage-booking":
      await setupJpUi05Scenario(page, as05("manage", "lookup-default"));
      break;
    default:
      await setupPublicBaseline(page);
  }
}
