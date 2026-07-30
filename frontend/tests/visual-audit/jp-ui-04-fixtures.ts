import type { Page } from "@playwright/test";
import {
  mockCsrf,
  MOCK_SEARCH_ID,
  resultsQuery,
  setupResultsMocks,
  setupScenarioMocks,
} from "./jp-ui-01-fixtures";

export { MOCK_SEARCH_ID, resultsQuery };

export async function setupJpUi04Baseline(page: Page): Promise<void> {
  await mockCsrf(page);
}

export async function setupJpUi04Results(page: Page, state: string): Promise<void> {
  await mockCsrf(page);

  if (state === "loading") {
    await page.route("**/laravel/flights/results/data**", async () => {
      // Intentionally slow — loading skeleton scenario
    });
    return;
  }

  if (state === "empty") {
    await page.route("**/laravel/flights/results/data**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          search_id: "jp-ui-04-audit",
          page: 1,
          per_page: 12,
          total: 0,
          has_more: false,
          filters: {},
          offers: [],
          warnings: [],
          empty_message: "No flights match your search.",
        }),
      });
    });
    return;
  }

  if (state === "partial") {
    await page.route("**/laravel/flights/results/data**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          search_id: "jp-ui-04-audit",
          page: 1,
          per_page: 12,
          total: 1,
          has_more: false,
          filters: { stops: [{ value: "direct", label: "Nonstop", count: 1 }] },
          offers: [
            {
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
              segments: [{ origin_airport_code: "LHE", destination_airport_code: "DXB" }],
            },
          ],
          warnings: [{ type: "partial", message: "One supplier did not return results." }],
          empty_message: "",
        }),
      });
    });
    return;
  }

  if (state === "expired") {
    await page.route("**/laravel/flights/results/data**", async (route) => {
      await route.fulfill({
        status: 410,
        contentType: "application/json",
        body: JSON.stringify({ message: "Search expired" }),
      });
    });
    return;
  }

  await setupResultsMocks(page, state === "branded");
}

export async function setupJpUi04Family(page: Page, family: string, state: string): Promise<void> {
  switch (family) {
    case "results":
      await setupJpUi04Results(page, state);
      break;
    case "passengers":
    case "shared":
      await setupScenarioMocks(page, "passengers");
      break;
    case "review":
      await setupScenarioMocks(page, "review");
      break;
    case "payment":
      await setupScenarioMocks(page, "payment");
      if (state === "abhipay") {
        await page.unroute("**/laravel/booking/checkout-state?**");
        await page.route("**/laravel/booking/checkout-state?**", async (route) => {
          await route.fulfill({
            status: 200,
            contentType: "application/json",
            body: JSON.stringify({
              ok: true,
              booking_session: {
                id: "jp-ui-01-audit-session",
                status: "payment",
                server_time: new Date().toISOString(),
                progress: [
                  { key: "flight_selected", label: "Flight Selected", state: "completed", href: null },
                  { key: "passenger_details", label: "Passenger Details", state: "completed", href: null },
                  { key: "seat_extras", label: "Seat & Extras", state: "skipped", href: null },
                  { key: "review", label: "Review", state: "completed", href: null },
                  { key: "payment", label: "Payment", state: "current", href: null },
                  { key: "confirmation", label: "Confirmation", state: "upcoming", href: null },
                ],
              },
              booking_reference: "JPAUDIT01",
              booking_method: "online_card",
              payment_method_code: "card",
              booking_status: { code: "pending", label: "Pending", terminal: false },
              payment_status: { code: "not_started", label: "Unpaid", terminal: false },
              pricing: {
                currency: "PKR",
                base_fare: 100000,
                taxes: 20000,
                service_charges: 4999,
                total: 124999,
                formatted_total: "Rs. 124,999",
              },
              card_payment: {
                can_start: true,
                show_pay_button: true,
                payable_amount: 124999,
                currency: "PKR",
                formatted_amount: "Rs. 124,999",
                payment_status_label: "Unpaid",
                start_endpoint: "/booking/1/pay/card",
                ticketing_note: "Ticket will be issued after payment confirmation.",
                blocked_message: null,
              },
              manual_payment: null,
              itinerary: {
                trip_type: "one_way",
                origin: "LHE",
                destination: "DXB",
                depart_date: "2026-08-15",
                airline_name: "Audit Airline",
                cabin: "economy",
                total_formatted: "114,999",
                currency: "PKR",
                segments: [],
                return_segments: [],
              },
              passengers: [{ title: "Mr", first_name: "Audit", last_name: "Traveler", passenger_type: "adult" }],
              contact: { name: "Audit Traveler", email: "audit@example.com", phone: "+923001234567", country: "PK" },
              documents_portal: [],
              support: { support_url: "/support", lookup_url: "/lookup-booking" },
            }),
          });
        });
      }
      break;
    case "success":
      await setupScenarioMocks(page, "confirmation");
      break;
    default:
      await setupJpUi04Baseline(page);
  }
}
