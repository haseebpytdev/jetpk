import { test, expect } from "@playwright/test";

const MOCK_SEARCH_ID = "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee";

function baseResultsQuery(): string {
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  const depart = tomorrow.toISOString().slice(0, 10);
  return new URLSearchParams({
    search_id: MOCK_SEARCH_ID,
    trip_type: "one_way",
    from: "ISB",
    to: "DXB",
    depart,
    adults: "1",
    children: "0",
    infants: "0",
    cabin: "economy",
  }).toString();
}

function mockOffer(overrides: Record<string, unknown> = {}) {
  return {
    offer_id: "offer-1",
    airline_code: "EK",
    airline_name: "Emirates",
    airline_logo_url: null,
    departure_time: "08:30",
    arrival_time: "11:45",
    duration: "3h 15m",
    stops: 0,
    stops_label_display: "Nonstop",
    displayed_price: 134047,
    price_display: "134,047 PKR",
    can_book: true,
    refundable: false,
    baggage: "30kg checked",
    segments: [
      {
        origin_airport_code: "ISB",
        destination_airport_code: "DXB",
        departure_time_display: "08:30",
        arrival_time_display: "11:45",
        flight_number: "EK612",
      },
    ],
    select_url: "/booking/passengers",
    has_branded_fares: false,
    fare_family_options_display: [],
    ...overrides,
  };
}

function mockResultsBody(overrides: Record<string, unknown> = {}) {
  return {
    search_id: MOCK_SEARCH_ID,
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
    filters: {
      stops: [{ value: "direct", label: "Nonstop", count: 1 }],
      airlines: [{ code: "EK", name: "Emirates", count: 1 }],
      departure_windows: [{ value: "morning", label: "Morning", count: 1 }],
      refundable: [{ value: "0", label: "Non-refundable", count: 1 }],
    },
    offers: [mockOffer()],
    warnings: [],
    empty_message: "",
    search_freshness: { expires_display: "Results expire in 25 minutes" },
    ...overrides,
  };
}

async function gotoResults(page: import("@playwright/test").Page, query?: string) {
  await page.route("**/laravel/flights/results/data**", async (route) => {
    const url = new URL(route.request().url());
    if (url.searchParams.get("stops") === "direct") {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(mockResultsBody({ total: 1, offers: [mockOffer({ stops: 0 })] })),
      });
      return;
    }
    if (url.searchParams.get("airline") === "EK") {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(mockResultsBody()),
      });
      return;
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(mockResultsBody()),
    });
  });

  await page.goto(`/flights/results?${query ?? baseResultsQuery()}`, { waitUntil: "load" });
}

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("one-way results load completed search", async ({ page }) => {
  await gotoResults(page);
  await expect(page.getByTestId("results-hero-band")).toBeVisible();
  await expect(page.getByTestId("results-hero-band")).toContainText("Choose Your");
  await expect(page.getByTestId("results-hero-band")).toContainText("Perfect Flight");
  await expect(page.getByTestId("flight-result-card")).toBeVisible();
  await expect(page.getByTestId("search-summary-bar")).toContainText("ISB");
});

test("running search shows skeleton then results", async ({ page }) => {
  let delay = true;
  await page.route("**/laravel/flights/results/data**", async (route) => {
    if (delay) {
      await new Promise((resolve) => setTimeout(resolve, 400));
      delay = false;
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(mockResultsBody()),
    });
  });
  await page.goto(`/flights/results?${baseResultsQuery()}`);
  await expect(page.getByTestId("result-skeleton")).toBeVisible();
  await expect(page.getByTestId("flight-result-card")).toBeVisible({ timeout: 15_000 });
});

test("empty results state", async ({ page }) => {
  await page.route("**/laravel/flights/results/data**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(mockResultsBody({ total: 0, offers: [], empty_message: "No flights found for this route." })),
    });
  });
  await page.goto(`/flights/results?${baseResultsQuery()}`);
  await expect(page.getByTestId("empty-results")).toBeVisible();
});

test("expired search state", async ({ page }) => {
  await page.route("**/laravel/flights/results/data**", async (route) => {
    await route.fulfill({
      status: 410,
      contentType: "application/json",
      body: JSON.stringify({ message: "Search expired", offers: [] }),
    });
  });
  await page.goto(`/flights/results?${baseResultsQuery()}`);
  await expect(page.getByTestId("expired-search")).toBeVisible();
});

test("failed search shows error with retry", async ({ page }) => {
  await page.route("**/laravel/flights/results/data**", async (route) => {
    await route.fulfill({ status: 500, contentType: "application/json", body: JSON.stringify({ message: "Server error" }) });
  });
  await page.goto(`/flights/results?${baseResultsQuery()}`);
  await expect(page.getByTestId("search-error")).toBeVisible();
});

test("stops filter triggers refetch", async ({ page }) => {
  await gotoResults(page);
  await page.getByLabel("Nonstop").check();
  await expect(page.getByTestId("flight-result-card")).toBeVisible();
});

test("airline filter and clear all", async ({ page }) => {
  await gotoResults(page);
  await page.getByRole("radio", { name: "Emirates (1)" }).check();
  await page.getByTestId("clear-all-filters").click();
  await expect(page.getByTestId("flight-result-card")).toBeVisible();
});

test("sort control is present", async ({ page }) => {
  await gotoResults(page);
  await expect(page.getByTestId("sort-control")).toBeVisible();
});

test("recommended sort submits Laravel recommended value", async ({ page }) => {
  const captured: string[] = [];
  await page.route("**/laravel/flights/results/data**", async (route) => {
    captured.push(new URL(route.request().url()).searchParams.get("sort") ?? "");
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(mockResultsBody()),
    });
  });
  await page.goto(`/flights/results?${baseResultsQuery()}&sort=recommended`);
  await expect(page.getByTestId("flight-result-card")).toBeVisible();
  expect(captured.some((value) => value === "recommended")).toBe(true);
});

test("lowest price sort submits Laravel cheapest value", async ({ page }) => {
  const captured: string[] = [];
  await page.route("**/laravel/flights/results/data**", async (route) => {
    captured.push(new URL(route.request().url()).searchParams.get("sort") ?? "");
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(mockResultsBody()),
    });
  });
  await page.goto(`/flights/results?${baseResultsQuery()}&sort=lowest_price`);
  await expect(page.getByTestId("flight-result-card")).toBeVisible();
  expect(captured.some((value) => value === "cheapest")).toBe(true);
  expect(captured.some((value) => value === "recommended")).toBe(false);
});

test("changing sort in UI triggers Laravel refetch with mapped value", async ({ page }) => {
  const captured: string[] = [];
  await page.route("**/laravel/flights/results/data**", async (route) => {
    captured.push(new URL(route.request().url()).searchParams.get("sort") ?? "");
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(mockResultsBody()),
    });
  });
  await page.goto(`/flights/results?${baseResultsQuery()}&sort=recommended`);
  await expect(page.getByTestId("flight-result-card")).toBeVisible();
  await page.getByTestId("sort-control").selectOption("lowest_price");
  await expect.poll(() => captured.filter((value) => value === "cheapest").length).toBeGreaterThan(0);
});

test("price button visible text is price only", async ({ page }) => {
  await gotoResults(page);
  const button = page.getByTestId("result-price-button");
  await expect(button).toHaveText("134,047 PKR");
  await expect(button).toHaveAttribute("aria-label", /Select fare for/);
});

test("airline logo fallback shows initials", async ({ page }) => {
  await gotoResults(page);
  await expect(page.getByTestId("flight-result-card").getByText("EK", { exact: true }).first()).toBeVisible();
});

test("nonstop card label", async ({ page }) => {
  await gotoResults(page);
  await expect(page.getByTestId("flight-result-card").getByText("Nonstop")).toBeVisible();
});

test("one-stop card and layover keyboard tooltip", async ({ page }) => {
  await page.route("**/laravel/flights/results/data**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(
        mockResultsBody({
          offers: [
            mockOffer({
              stops: 1,
              stops_label_display: "1 stop",
              layover_summary_display: ["1h 15m layover · DXB"],
            }),
          ],
        }),
      ),
    });
  });
  await page.goto(`/flights/results?${baseResultsQuery()}`);
  await page.getByRole("button", { name: /layover in DXB/i }).click();
  await expect(page.getByRole("tooltip")).toContainText("DXB");
});

test("branded fare selection stays on results", async ({ page }) => {
  const fares = [
    { option_key: "eco", name: "Economy Saver", displayed_price: 100000, price_display: "100,000 PKR" },
    { option_key: "flex", name: "Economy Flex", displayed_price: 112000, price_display: "112,000 PKR" },
  ];
  await page.route("**/laravel/flights/results/data**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(
        mockResultsBody({
          offers: [
            mockOffer({
              has_branded_fares: true,
              branded_fares_display_options: fares,
              fare_family_options_display: fares,
            }),
          ],
        }),
      ),
    });
  });
  await page.goto(`/flights/results?${baseResultsQuery()}`);
  await page.route("**/laravel/flights/results/offer?**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        search_id: MOCK_SEARCH_ID,
        offer_id: "offer-1",
        offer: {
          offer_id: "offer-1",
          airline_name: "Emirates",
          departure_time: "08:30",
          can_book: true,
          select_url: "/booking/passengers",
          segments: [
            {
              origin_airport_code: "ISB",
              destination_airport_code: "DXB",
              departure_time_display: "08:30",
            },
          ],
          branded_fares_display_options: fares,
        },
      }),
    });
  });
  await page.getByRole("button", { name: /Economy Saver/ }).click();
  await expect(page).not.toHaveURL(/fare-selection/);
  await expect(page.getByTestId("branded-fare-carousel")).toBeVisible();
});

test("branded fare carousel with more than three fares", async ({ page }) => {
  const fares = Array.from({ length: 4 }).map((_, index) => ({
    option_key: `fare-${index}`,
    name: `Fare ${index + 1}`,
    displayed_price: 100000 + index * 1000,
    price_display: `${100000 + index * 1000} PKR`,
  }));
  await page.route("**/laravel/flights/results/data**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(
        mockResultsBody({
          offers: [
            mockOffer({
              has_branded_fares: true,
              has_fare_choice_options: true,
              branded_fares_display_options: fares,
              fare_family_options_display: fares,
            }),
          ],
        }),
      ),
    });
  });
  await page.goto(`/flights/results?${baseResultsQuery()}`);
  await expect(page.getByTestId("branded-fare-carousel")).toBeVisible();
  await expect(page.getByLabel("Next fare options")).toBeVisible();
});

test("mobile filter drawer at 320px", async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 800 });
  await gotoResults(page);
  await page.getByTestId("open-mobile-filters").click();
  await expect(page.getByTestId("mobile-filter-drawer")).toBeVisible();
  await page.getByTestId("drawer-panel").getByRole("button", { name: "Close" }).click();
  await expect(page.getByTestId("mobile-filter-drawer")).not.toBeVisible();
});

test("reduced motion skeleton has no animation class dependency", async ({ page }) => {
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.route("**/laravel/flights/results/data**", async (route) => {
    await new Promise((resolve) => setTimeout(resolve, 300));
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(mockResultsBody()),
    });
  });
  await page.goto(`/flights/results?${baseResultsQuery()}`);
  await expect(page.getByTestId("result-skeleton")).toBeVisible();
});

test("homepage search stays on Next results route", async ({ page }) => {
  await page.route("**/laravel/flights/results/search**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        search_id: MOCK_SEARCH_ID,
        results_page_url: "/flights/results",
        initial_results_url: `/flights/results/data?search_id=${MOCK_SEARCH_ID}`,
      }),
    });
  });
  await page.route("**/laravel/flights/results/data**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(mockResultsBody()),
    });
  });

  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  await page.goto("/", { waitUntil: "load" });
  await page.getByLabel("Departure").fill(tomorrow.toISOString().slice(0, 10));
  await page.getByRole("button", { name: "Search Flights" }).click();
  await page.waitForURL("**/flights/results**", { timeout: 15_000 });
  await expect(page.getByTestId("flight-result-card")).toBeVisible({ timeout: 15_000 });
});

test("public shell header and footer on results", async ({ page }) => {
  await gotoResults(page);
  await expect(page.getByRole("banner")).toBeVisible();
  await expect(page.getByRole("contentinfo")).toBeVisible();
});

test("loading state never shows 0 results", async ({ page }) => {
  await page.route("**/laravel/flights/results/data**", async (route) => {
    await new Promise((resolve) => setTimeout(resolve, 600));
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(mockResultsBody()),
    });
  });
  await page.goto(`/flights/results?${baseResultsQuery()}`);
  await expect(page.getByTestId("results-count-label")).toContainText(/Searching|Finding/i);
  await expect(page.getByTestId("results-count-label")).not.toHaveText(/^0 /);
  await expect(page.getByTestId("flight-result-card")).toBeVisible({ timeout: 15_000 });
});

test("edit search is inline SearchModule", async ({ page }) => {
  await gotoResults(page);
  await page.getByTestId("edit-search-button").click();
  await expect(page.getByTestId("inline-edit-search")).toBeVisible();
  await expect(page.getByTestId("search-module")).toHaveAttribute("data-search-variant", "results");
  await expect(page.getByRole("dialog", { name: "Modify search" })).toHaveCount(0);
  await expect(page.getByLabel("From")).toHaveValue(/ISB/i);
  await expect(page.getByLabel("To")).toHaveValue(/DXB/i);
});

test("return search shows pair/segmented selector", async ({ page }) => {
  const query = new URLSearchParams({
    search_id: MOCK_SEARCH_ID,
    trip_type: "round_trip",
    from: "LHE",
    to: "JED",
    depart: "2026-09-01",
    return_date: "2026-09-10",
    adults: "1",
    cabin: "economy",
  }).toString();
  await page.route("**/laravel/flights/results/data**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(mockResultsBody({ flow: "return_split_outbound", offers: [], outbound_options: [] })),
    });
  });
  await page.goto(`/flights/results?${query}`);
  await expect(page.getByTestId("return-view-selector")).toBeVisible();
  await page.getByTestId("return-view-segmented").click();
  await expect(page).toHaveURL(/view=segmented/);
});

test("one-way never shows return view selector", async ({ page }) => {
  await gotoResults(page);
  await expect(page.getByTestId("return-view-selector")).toHaveCount(0);
});
