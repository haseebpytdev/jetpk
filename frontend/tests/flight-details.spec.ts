import { test, expect } from "@playwright/test";

const MOCK_SEARCH_ID = "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee";

function mockOfferWithDetails(overrides: Record<string, unknown> = {}) {
  return {
    offer_id: "offer-1",
    airline_code: "EK",
    airline_name: "Emirates",
    airline_logo_url: null,
    departure_time: "08:30",
    arrival_time: "11:45",
    duration: "3h 15m",
    stops: 1,
    stops_label_display: "1 stop",
    displayed_price: 134047,
    price_display: "134,047 PKR",
    base_fare: 110000,
    taxes: 20000,
    markup: 2500,
    service_fee: 1547,
    final_customer_price: 134047,
    can_book: true,
    refundable: false,
    baggage: "30kg checked",
    refund_rule: "Non-refundable",
    change_rule: "Changes with penalty",
    segments: [
      {
        origin_airport_code: "ISB",
        destination_airport_code: "DXB",
        departure_time_display: "08:30",
        arrival_time_display: "11:45",
        flight_number: "EK612",
        marketing_carrier_code: "EK",
        layover_after_display: "1h 15m layover in DXB",
      },
      {
        origin_airport_code: "DXB",
        destination_airport_code: "LHR",
        departure_time_display: "13:00",
        arrival_time_display: "17:30",
        flight_number: "EK001",
        marketing_carrier_code: "EK",
      },
    ],
    layovers_display: [{ airport_code: "DXB", duration_display: "1h 15m", overnight: false }],
    select_url: "/booking/passengers",
    has_branded_fares: false,
    fare_family_options_display: [],
    fallback_details: {
      baggage: { checked: "30kg", cabin: "7kg" },
      fare_breakdown: { base_fare: 110000, taxes: 20000, grand_total: 134047 },
      fare_rules: { refund_rule: "Non-refundable", change_rule: "Changes with penalty", rule_lines: ["No show penalty applies"] },
    },
    ...overrides,
  };
}

function mockDetailsBody(overrides: Record<string, unknown> = {}) {
  return {
    success: true,
    search_id: MOCK_SEARCH_ID,
    offer_id: "offer-1",
    flow: "one_way",
    revalidation_required: false,
    offer: mockOfferWithDetails(),
    search_freshness: { expires_display: "Results expire in 25 minutes" },
    ...overrides,
  };
}

async function setupResultsAndDetails(
  page: import("@playwright/test").Page,
  options?: { detailsStatus?: number; detailsBody?: Record<string, unknown> },
) {
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  const depart = tomorrow.toISOString().slice(0, 10);
  const query = new URLSearchParams({
    search_id: MOCK_SEARCH_ID,
    trip_type: "one_way",
    from: "ISB",
    to: "DXB",
    depart,
    adults: "1",
    children: "0",
    infants: "0",
    cabin: "economy",
  });

  await page.route("**/laravel/flights/results/data**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        search_id: MOCK_SEARCH_ID,
        page: 1,
        per_page: 12,
        total: 1,
        has_more: false,
        offers: [mockOfferWithDetails()],
        filters: {},
        warnings: [],
        search_freshness: {},
      }),
    });
  });

  await page.route("**/laravel/flights/results/offer**", async (route) => {
    const status = options?.detailsStatus ?? 200;
    await route.fulfill({
      status,
      contentType: "application/json",
      body: JSON.stringify(
        status === 410
          ? (options?.detailsBody ?? { success: false, message: "Search expired", status: "expired_search" })
          : (options?.detailsBody ?? mockDetailsBody()),
      ),
    });
  });

  await page.goto(`/flights/results?${query.toString()}`);
  await expect(page.getByTestId("flight-result-card")).toBeVisible();
}

async function openSabreRevalidationDrawer(page: import("@playwright/test").Page) {
  await setupResultsAndDetails(page);
  await page.unroute("**/laravel/flights/results/offer**");
  await page.route("**/laravel/flights/results/offer**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(
        mockDetailsBody({
          revalidation_required: true,
          offer: mockOfferWithDetails({ supplier_provider: "sabre", provider: "sabre" }),
        }),
      ),
    });
  });
  await page.getByTestId("flight-details-trigger").click();
  await expect(page.getByTestId("flight-details-drawer")).toBeVisible();
}

test.describe("JP-FE-06 flight details", () => {
  test("opens details drawer from result card", async ({ page }) => {
    await setupResultsAndDetails(page);
    await page.getByTestId("flight-details-trigger").click();
    await expect(page.getByTestId("flight-details-drawer")).toBeVisible();
    await expect(page.getByTestId("route-timeline")).toBeVisible();
    await expect(page.getByTestId("baggage-details")).toBeVisible();
    await expect(page.getByTestId("price-breakdown")).toBeVisible();
  });

  test("renders fare rules accordion and segment details", async ({ page }) => {
    await setupResultsAndDetails(page);
    await page.getByTestId("flight-details-trigger").click();
    await expect(page.getByTestId("fare-rules-accordion")).toBeVisible();
    await expect(page.getByTestId("segment-details")).toBeVisible();
    await expect(page.getByTestId("fare-rules-accordion").getByText("Non-refundable")).toBeVisible();
  });

  test("closes drawer with Escape and preserves results list", async ({ page }) => {
    await setupResultsAndDetails(page);
    await page.getByTestId("flight-details-trigger").click();
    await expect(page.getByTestId("flight-details-drawer")).toBeVisible();
    await page.keyboard.press("Escape");
    await expect(page.getByTestId("flight-details-drawer")).toHaveCount(0);
    await expect(page.getByTestId("flight-result-card")).toBeVisible();
  });

  test("shows loading then details content", async ({ page }) => {
    await setupResultsAndDetails(page);
    let resolveDetails: (() => void) | undefined;
    await page.unroute("**/laravel/flights/results/offer**");
    await page.route("**/laravel/flights/results/offer**", async (route) => {
      await new Promise<void>((resolve) => {
        resolveDetails = resolve;
      });
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(mockDetailsBody()),
      });
    });
    await page.getByTestId("flight-details-trigger").click();
    await expect(page.getByTestId("flight-details-drawer")).toBeVisible();
    resolveDetails?.();
    await expect(page.getByTestId("price-breakdown")).toBeVisible();
  });

  test("shows expired state when details load returns 410", async ({ page }) => {
    await setupResultsAndDetails(page);
    await page.unroute("**/laravel/flights/results/offer**");
    await page.route("**/laravel/flights/results/offer**", async (route) => {
      await route.fulfill({
        status: 410,
        contentType: "application/json",
        body: JSON.stringify({
          success: false,
          status: "expired_search",
          message: "This fare search has expired. Please search again.",
        }),
      });
    });
    await page.getByTestId("flight-details-trigger").click();
    await expect(page.getByTestId("offer-expired-state")).toBeVisible();
    await expect(page.getByText("This fare search has expired. Please search again.")).toBeVisible();
  });

  test("shows expired state when revalidation returns 410", async ({ page }) => {
    await setupResultsAndDetails(page);
    await page.route("**/laravel/flights/results/offer**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(
          mockDetailsBody({
            revalidation_required: true,
            offer: mockOfferWithDetails({ supplier_provider: "iati", provider: "iati" }),
          }),
        ),
      });
    });
    await page.route("**/laravel/flights/results/revalidate-offer**", async (route) => {
      await route.fulfill({
        status: 410,
        contentType: "application/json",
        body: JSON.stringify({
          success: false,
          status: "expired_search",
          message: "This fare search has expired. Please search again.",
        }),
      });
    });
    await page.getByTestId("flight-details-trigger").click();
    await page.getByTestId("continue-to-passengers").click();
    await expect(page.getByTestId("offer-expired-state")).toBeVisible();
  });

  test("duffel continue prepares checkout without calling revalidation", async ({ page }) => {
    let revalidateCalled = false;
    await page.route("**/laravel/flights/results/revalidate-offer**", async (route) => {
      revalidateCalled = true;
      await route.fulfill({ status: 422, body: JSON.stringify({ success: false }) });
    });
    await setupResultsAndDetails(page);
    await page.getByTestId("flight-details-trigger").click();
    const continueButton = page.getByTestId("continue-to-passengers");
    await expect(continueButton).toBeEnabled();
    await continueButton.click();
    expect(revalidateCalled).toBe(false);
  });

  test("fare-change dialog blocks auto-continue for IATI revalidation", async ({ page }) => {
    await setupResultsAndDetails(page);
    await page.route("**/laravel/flights/results/revalidate-offer**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          success: true,
          status: "fare_changed",
          requires_fare_change_acceptance: true,
          passengers_url: "/booking/passengers?offer_id=offer-1",
          revalidation: {
            price_changed: true,
            original_total: 134047,
            confirmed_total: 139000,
            old_total: 134047,
            new_total: 139000,
            currency: "PKR",
            revalidation_status: "changed",
          },
        }),
      });
    });
    await page.unroute("**/laravel/flights/results/offer**");
    await page.route("**/laravel/flights/results/offer**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(
          mockDetailsBody({
            revalidation_required: true,
            offer: mockOfferWithDetails({ supplier_provider: "iati", provider: "iati" }),
          }),
        ),
      });
    });
    await page.getByTestId("flight-details-trigger").click();
    await page.getByTestId("continue-to-passengers").click();
    await expect(page.getByTestId("fare-change-dialog")).toBeVisible();
    await expect(page.getByText("Accept new fare")).toBeVisible();
    await expect(page).toHaveURL(/\/flights\/results/);
  });

  test("sabre fare-change dialog shows authoritative old and new totals", async ({ page }) => {
    await openSabreRevalidationDrawer(page);
    await page.route("**/laravel/flights/results/revalidate-offer**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          success: true,
          status: "fare_changed",
          requires_fare_change_acceptance: true,
          passengers_url: "/booking/passengers?offer_id=offer-1",
          revalidation: {
            price_changed: true,
            original_total: 134047,
            confirmed_total: 141500,
            old_total: 134047,
            new_total: 141500,
            currency: "PKR",
            revalidation_status: "changed",
            provider: "sabre",
          },
        }),
      });
    });
    await page.getByTestId("continue-to-passengers").click();
    const dialog = page.getByTestId("fare-change-dialog");
    await expect(dialog).toBeVisible();
    await expect(dialog.getByText("134,047 PKR")).toBeVisible();
    await expect(dialog.getByText("141,500 PKR")).toBeVisible();
    await expect(page).toHaveURL(/\/flights\/results/);
  });

  test("sabre fare-change acceptance posts accept_fare_change to Laravel", async ({ page }) => {
    await openSabreRevalidationDrawer(page);
    let acceptPosted = false;
    await page.route("**/laravel/flights/results/revalidate-offer**", async (route) => {
      const body = route.request().postData() ?? "";
      if (body.includes("accept_fare_change")) {
        acceptPosted = true;
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            success: true,
            status: "success",
            passengers_url: "/booking/passengers?offer_id=offer-1",
            revalidation: { price_changed: false, revalidation_status: "valid" },
          }),
        });
        return;
      }
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          success: true,
          status: "fare_changed",
          requires_fare_change_acceptance: true,
          passengers_url: "/booking/passengers?offer_id=offer-1",
          revalidation: {
            price_changed: true,
            original_total: 134047,
            confirmed_total: 141500,
            currency: "PKR",
            revalidation_status: "changed",
          },
        }),
      });
    });
    await page.getByTestId("continue-to-passengers").click();
    await expect(page.getByTestId("fare-change-dialog")).toBeVisible();
    await page.getByText("Accept new fare").click();
    await expect.poll(() => acceptPosted).toBe(true);
  });

  test("sabre fare-change acceptance handles second price change", async ({ page }) => {
    await openSabreRevalidationDrawer(page);
    await page.route("**/laravel/flights/results/revalidate-offer**", async (route) => {
      const body = route.request().postData() ?? "";
      if (body.includes("accept_fare_change")) {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            success: true,
            status: "fare_changed",
            requires_fare_change_acceptance: true,
            passengers_url: "/booking/passengers?offer_id=offer-1",
            revalidation: {
              price_changed: true,
              original_total: 141500,
              confirmed_total: 145000,
              currency: "PKR",
              revalidation_status: "changed",
            },
          }),
        });
        return;
      }
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          success: true,
          status: "fare_changed",
          requires_fare_change_acceptance: true,
          passengers_url: "/booking/passengers?offer_id=offer-1",
          revalidation: {
            price_changed: true,
            original_total: 134047,
            confirmed_total: 141500,
            currency: "PKR",
            revalidation_status: "changed",
          },
        }),
      });
    });
    await page.getByTestId("continue-to-passengers").click();
    await expect(page.getByTestId("fare-change-dialog")).toBeVisible();
    await page.getByText("Accept new fare").click();
    await expect(page.getByTestId("fare-change-dialog")).toBeVisible();
    await expect(page.getByText("145,000 PKR")).toBeVisible();
    await expect(page).toHaveURL(/\/flights\/results/);
  });

  test("sabre fare-change acceptance handles second failure", async ({ page }) => {
    await openSabreRevalidationDrawer(page);
    await page.route("**/laravel/flights/results/revalidate-offer**", async (route) => {
      const body = route.request().postData() ?? "";
      if (body.includes("accept_fare_change")) {
        await route.fulfill({
          status: 422,
          contentType: "application/json",
          body: JSON.stringify({
            success: false,
            status: "failed",
            message: "We could not confirm this fare with the airline.",
          }),
        });
        return;
      }
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          success: true,
          status: "fare_changed",
          requires_fare_change_acceptance: true,
          passengers_url: "/booking/passengers?offer_id=offer-1",
          revalidation: {
            price_changed: true,
            original_total: 134047,
            confirmed_total: 141500,
            currency: "PKR",
            revalidation_status: "changed",
          },
        }),
      });
    });
    await page.getByTestId("continue-to-passengers").click();
    await expect(page.getByTestId("fare-change-dialog")).toBeVisible();
    await page.getByText("Accept new fare").click();
    await expect(page.getByTestId("offer-unavailable-state")).toBeVisible();
    await expect(page).toHaveURL(/\/flights\/results/);
  });

  test("mobile viewport renders details without horizontal scroll", async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 800 });
    await setupResultsAndDetails(page);
    await page.getByTestId("flight-details-trigger").click();
    await expect(page.getByTestId("flight-details-drawer")).toBeVisible();
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);
  });
});
