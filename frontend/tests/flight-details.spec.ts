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
  options?: {
    detailsStatus?: number;
    detailsBody?: Record<string, unknown>;
    offerOverrides?: Record<string, unknown>;
  },
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

  // Revalidation POSTs call ensureLaravelCsrfToken(); without this mock the
  // csrf-token fetch can hang against the Playwright Next smoke server.
  await page.route("**/laravel/api/public/content/csrf-token**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ csrf_token: "test-csrf-token" }),
      headers: { "set-cookie": "XSRF-TOKEN=test-csrf-token; Path=/" },
    });
  });

  await page.route("**/laravel/flights/results/nearby-dates**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ available: false, dates: [] }),
    });
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
        offers: [mockOfferWithDetails(options?.offerOverrides)],
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
    await page.getByRole("tab", { name: "Fare Details" }).click();
    await expect(page.getByTestId("price-breakdown")).toBeVisible();
    await expect(page.getByTestId("price-breakdown")).not.toContainText("Agency markup");
    await expect(page.getByTestId("price-breakdown")).toContainText("PKR 134,047");
  });

  test("renders fare rules accordion and segment details", async ({ page }) => {
    await setupResultsAndDetails(page);
    await page.getByTestId("flight-details-trigger").click();
    await page.getByRole("tab", { name: "Fare Policy" }).click();
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
    await expect.poll(() => resolveDetails).toBeTruthy();
    resolveDetails?.();
    await page.getByRole("tab", { name: "Fare Details" }).click();
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

  test("details request omits fare_option_key for standard offers", async ({ page }) => {
    let detailsUrl = "";
    await setupResultsAndDetails(page);
    await page.unroute("**/laravel/flights/results/offer**");
    await page.route("**/laravel/flights/results/offer**", async (route) => {
      detailsUrl = route.request().url();
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(mockDetailsBody()),
      });
    });
    await page.getByTestId("flight-details-trigger").click();
    await expect(page.getByTestId("flight-details-drawer")).toBeVisible();
    await expect.poll(() => detailsUrl).not.toBe("");
    const parsed = new URL(detailsUrl);
    expect(parsed.pathname).toContain("/laravel/flights/results/offer");
    expect(parsed.searchParams.get("search_id")).toBeTruthy();
    expect(parsed.searchParams.get("offer_id")).toBeTruthy();
    expect(parsed.searchParams.has("fare_option_key")).toBe(false);
    expect(parsed.search).not.toMatch(/localhost|127\.0\.0\.1/);
  });

  test("synthetic display option renders but Details never serializes its option_key", async ({ page }) => {
    const syntheticOption = {
      option_key: "standard-fare-display-only",
      name: "Economy Fare",
      displayed_price: 134047,
      price_display: "134,047 PKR",
      baggage: "30kg checked",
      is_synthetic_default: true,
      selection_key_authoritative: false,
    };
    const offerOverrides = {
      has_fare_choice_options: true,
      branded_fares_display_options: [syntheticOption],
      fare_family_options_display: [syntheticOption],
    };
    const detailsUrls: string[] = [];
    await setupResultsAndDetails(page, {
      offerOverrides,
      detailsBody: mockDetailsBody({ offer: mockOfferWithDetails(offerOverrides) }),
    });
    await page.unroute("**/laravel/flights/results/offer**");
    await page.route("**/laravel/flights/results/offer**", async (route) => {
      detailsUrls.push(route.request().url());
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(mockDetailsBody({ offer: mockOfferWithDetails(offerOverrides) })),
      });
    });

    await page.getByTestId("flight-details-trigger").click();
    await expect(page.getByTestId("flight-details-drawer")).toBeVisible();
    // Synthetic-only catalogs render as Current fare (not Unavailable branded UI).
    await expect(page.getByTestId("fare-family-details").getByText("Current fare").first()).toBeVisible();
    await expect(page.getByTestId("fare-family-details").getByRole("button", { name: /Select fare|Not selectable/i })).toHaveCount(0);
    await expect(page.getByText("Unavailable")).toHaveCount(0);
    await expect.poll(() => detailsUrls.length).toBe(1);
    expect(new URL(detailsUrls[0]).searchParams.has("fare_option_key")).toBe(false);
  });

  test("synthetic display option renders but Book Now initial fetch omits its option_key", async ({ page }) => {
    const syntheticOption = {
      option_key: "standard-fare-display-only",
      name: "Standard Fare",
      displayed_price: 134047,
      price_display: "134,047 PKR",
      is_synthetic_default: true,
      selection_key_authoritative: false,
    };
    const offerOverrides = {
      has_fare_choice_options: true,
      branded_fares_display_options: [syntheticOption],
      fare_family_options_display: [syntheticOption],
    };
    let detailsUrl = "";
    await setupResultsAndDetails(page, {
      offerOverrides,
      detailsBody: mockDetailsBody({ offer: mockOfferWithDetails(offerOverrides) }),
    });
    await page.unroute("**/laravel/flights/results/offer**");
    await page.route("**/laravel/flights/results/offer**", async (route) => {
      detailsUrl = route.request().url();
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(mockDetailsBody({ offer: mockOfferWithDetails(offerOverrides) })),
      });
    });

    await page.getByTestId("book-now-trigger").click();
    await expect(page.getByTestId("flight-details-drawer")).toBeVisible();
    await expect(page.getByRole("heading", { name: "Choose your flight & fare" })).toBeVisible();
    await expect(page.getByTestId("fare-family-details").getByText("Current fare").first()).toBeVisible();
    await expect.poll(() => detailsUrl).not.toBe("");
    expect(new URL(detailsUrl).searchParams.has("fare_option_key")).toBe(false);
    await expect(page).toHaveURL(/\/flights\/results/);
  });

  test("details request sends branded fare_option_key when families exist", async ({ page }) => {
    const brandedOptions = [
      {
        option_key: "eco-basic",
        name: "Economy Basic",
        displayed_price: 120000,
        price_display: "120,000 PKR",
        baggage: "23kg checked",
        selection_key_authoritative: true,
      },
      {
        option_key: "eco-flex",
        name: "Economy Flex",
        displayed_price: 135000,
        price_display: "135,000 PKR",
        baggage: "30kg checked",
        selection_key_authoritative: true,
      },
    ];
    const detailsUrls: string[] = [];
    let releaseStaleBasic: (() => void) | undefined;
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
          offers: [
            mockOfferWithDetails({
              has_branded_fares: true,
              branded_fares_display_options: brandedOptions,
              fare_family_options_display: brandedOptions,
            }),
          ],
          filters: {},
          warnings: [],
          search_freshness: {},
        }),
      });
    });

    await page.route("**/laravel/flights/results/offer**", async (route) => {
      detailsUrls.push(route.request().url());
      const fareKey = new URL(route.request().url()).searchParams.get("fare_option_key");
      if (fareKey === "eco-basic" && detailsUrls.length > 1) {
        await new Promise<void>((resolve) => { releaseStaleBasic = resolve; });
      }
      const selected = fareKey === "eco-flex" ? brandedOptions[1] : brandedOptions[0];
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(
          mockDetailsBody({
            offer: mockOfferWithDetails({
              displayed_price: selected.displayed_price,
              final_customer_price: selected.displayed_price,
              baggage: selected.baggage,
              fallback_details: {
                baggage: { checked: selected.baggage, cabin: "7kg" },
                fare_breakdown: { base_fare: Number(selected.displayed_price) - 20000, taxes: 20000, grand_total: selected.displayed_price, displayed_price: selected.displayed_price },
                fare_rules: { refund_rule: fareKey === "eco-flex" ? "Refundable with fee" : "Non-refundable", change_rule: "Changes with penalty" },
              },
              has_branded_fares: true,
              branded_fares_display_options: brandedOptions,
              fare_family_options_display: brandedOptions,
            }),
          }),
        ),
      });
    });

    await page.goto(`/flights/results?${query.toString()}`);
    await expect(page.getByTestId("flight-result-card")).toBeVisible();
    await page.getByTestId("flight-details-trigger").click();
    await expect(page.getByTestId("flight-details-drawer")).toBeVisible();
    await expect.poll(() => detailsUrls.length).toBeGreaterThan(0);
    expect(detailsUrls[0]).toContain("fare_option_key=eco-basic");
    await page.evaluate(() => {
      const cards = [...document.querySelectorAll<HTMLElement>('[data-fare-family-card]')];
      for (const card of cards.slice(0, 2)) {
        [...card.querySelectorAll<HTMLButtonElement>("button")].find((button) => button.textContent?.trim() === "Select fare")?.click();
      }
    });
    await expect.poll(() => detailsUrls.some((url) => url.includes("fare_option_key=eco-flex"))).toBe(true);
    await expect(page.getByRole("listitem").filter({ hasText: "Economy Flex" }).getByRole("button", { name: "Selected" })).toHaveAttribute("aria-pressed", "true");
    await page.getByRole("tab", { name: "Fare Details" }).click();
    await expect(page.getByTestId("price-breakdown")).toContainText("PKR 135,000");
    releaseStaleBasic?.();
    await page.waitForTimeout(50);
    await expect(page.getByRole("listitem").filter({ hasText: "Economy Flex" }).getByRole("button", { name: "Selected" })).toHaveAttribute("aria-pressed", "true");
    await expect(page.getByTestId("price-breakdown")).toContainText("PKR 135,000");
  });

  test("mobile viewport renders details without horizontal scroll", async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 800 });
    await setupResultsAndDetails(page);
    await page.getByTestId("flight-details-trigger").click();
    await expect(page.getByTestId("flight-details-drawer")).toBeVisible();
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);
  });

  test("mobile fare modal locks the page while its content and fare carousel remain scrollable", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const brandedOptions = ["Saver", "Value", "Flex", "Comfort"].map((name, index) => ({
      option_key: `economy-${name.toLowerCase()}`,
      name: `Economy ${name}`,
      displayed_price: 120000 + index * 10000,
      price_display: `${120 + index * 10},000 PKR`,
      baggage: `${23 + index * 5}kg checked`,
      cabin_baggage: "7kg cabin",
      refundable: index >= 2,
      changeable: index >= 1,
      selection_key_authoritative: true,
    }));

    await setupResultsAndDetails(page, {
      detailsBody: mockDetailsBody({
        offer: mockOfferWithDetails({
          has_branded_fares: true,
          branded_fares_display_options: brandedOptions,
          fare_family_options_display: brandedOptions,
        }),
      }),
    });
    const priorScrollY = await page.getByTestId("book-now-trigger").evaluate((button) => {
      const spacer = document.createElement("div");
      spacer.style.height = "1800px";
      spacer.style.width = "1px";
      spacer.style.pointerEvents = "none";
      document.body.appendChild(spacer);
      void spacer.offsetHeight;
      window.scrollTo(0, 420);
      const scrollY = window.scrollY;
      if (button instanceof HTMLElement) button.click();
      return scrollY;
    });
    await expect(page.getByTestId("flight-details-drawer")).toBeVisible();
    await expect.poll(() => page.evaluate(() => document.body.style.overflow)).toBe("hidden");
    await expect.poll(() => page.evaluate(() => document.documentElement.style.overflow)).toBe("hidden");
    await expect(page.getByRole("list", { name: "Fare family options" })).toBeVisible();

    const lockedScrollY = await page.evaluate(() => window.scrollY);
    await page.mouse.move(4, 4);
    await page.mouse.wheel(0, 600);
    expect(await page.evaluate(() => window.scrollY)).toBe(lockedScrollY);

    const scrollSurface = page.getByTestId("flight-details-scroll-surface");
    const modalScroll = await scrollSurface.evaluate((node) => {
      node.scrollTop = Math.min(360, node.scrollHeight - node.clientHeight);
      return {
        scrollTop: node.scrollTop,
        scrollHeight: node.scrollHeight,
        clientHeight: node.clientHeight,
      };
    });
    expect(modalScroll.scrollHeight).toBeGreaterThan(modalScroll.clientHeight);
    expect(modalScroll.scrollTop).toBeGreaterThan(0);
    expect(await page.evaluate(() => window.scrollY)).toBe(lockedScrollY);

    const fareList = page.getByRole("list", { name: "Fare family options" });
    await expect(fareList.locator("[data-fare-family-card]")).toHaveCount(4);
    const carousel = await fareList.evaluate((node) => {
      const firstCard = node.querySelector<HTMLElement>("[data-fare-family-card]");
      node.scrollLeft = Math.min(180, node.scrollWidth - node.clientWidth);
      return {
        scrollLeft: node.scrollLeft,
        scrollWidth: node.scrollWidth,
        clientWidth: node.clientWidth,
        firstCardWidth: firstCard?.getBoundingClientRect().width ?? 0,
      };
    });
    expect(carousel.scrollWidth).toBeGreaterThan(carousel.clientWidth);
    expect(carousel.scrollLeft).toBeGreaterThan(0);
    expect(carousel.firstCardWidth).toBeGreaterThan(carousel.clientWidth * 0.8);
    expect(carousel.firstCardWidth).toBeLessThan(carousel.clientWidth);
    expect(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth)).toBe(false);

    await page.keyboard.press("Escape");
    await expect(page.getByTestId("flight-details-drawer")).toHaveCount(0);
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(priorScrollY);
    expect(await page.evaluate(() => document.body.style.overflow)).toBe("");
    expect(await page.evaluate(() => document.documentElement.style.overflow)).toBe("");
    await page.evaluate(() => window.scrollTo(0, 100));
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(0);
  });
});
