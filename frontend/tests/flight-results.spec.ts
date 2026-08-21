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
      stops: [{ value: "direct", label: "Direct", count: 1 }],
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
  await page.getByRole("checkbox", { name: "Direct (1)" }).check();
  await expect(page.getByTestId("flight-result-card")).toBeVisible();
});

test("airline filter and clear all", async ({ page }) => {
  await gotoResults(page);
  await page.getByRole("checkbox", { name: "Emirates (1)" }).check();
  await page.getByTestId("clear-all-filters").click();
  await expect(page.getByTestId("flight-result-card")).toBeVisible();
});

test("filter control types match backend selection semantics", async ({ page }) => {
  await page.route("**/laravel/flights/results/data**", async (route) => route.fulfill({
    status: 200,
    contentType: "application/json",
    body: JSON.stringify(mockResultsBody({ filters: {
      ...mockResultsBody().filters,
      arrival_windows: [{ value: "afternoon", label: "Afternoon", count: 1 }],
      baggage_options: [{ value: "checked_baggage", label: "Checked baggage included", count: 1 }],
      fare_families: [{ value: "Flex", label: "Flex", count: 1 }],
      duration_buckets: [
        { value: "under_6h", label: "Under 6 hours", count: 1 },
        { value: "6_12h", label: "6–12 hours", count: 1 },
        { value: "12_18h", label: "12–18 hours", count: 1 },
        { value: "over_18h", label: "18+ hours", count: 1 },
      ],
      layover_airports: [{ code: "DXB", name: "Dubai", count: 1 }],
    } })),
  }));
  await page.goto(`/flights/results?${baseResultsQuery()}`);
  for (const label of ["Direct (1)", "Emirates (1)", "Morning (1)", "Afternoon (1)", "Non-refundable (1)"]) {
    await expect(page.getByRole("checkbox", { name: label })).toBeVisible();
  }
  for (const label of [
    "Checked baggage included (1)",
    "Flex (1)",
    "Under 6 hours (1)",
    "6–12 hours (1)",
    "12–18 hours (1)",
    "18+ hours (1)",
    "Dubai (1)",
  ]) {
    await expect(page.getByRole("radio", { name: label })).toBeVisible();
  }
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

test("compact result card shows price and Book Now without expanded fare families", async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await gotoResults(page);
  await expect(page.getByTestId("result-price-display")).toHaveText("PKR 134,047");
  await expect(page.getByTestId("book-now-trigger")).toBeVisible();
  await expect(page.getByTestId("branded-fare-carousel")).toHaveCount(0);
  await expect(page.locator("[data-fare-family-card]")).toHaveCount(0);
  await page.screenshot({ path: "tmp/flight-results-fare-travelers-refinement-desktop.png", fullPage: true });
});

test("airline logo fallback shows initials", async ({ page }) => {
  await gotoResults(page);
  await expect(page.getByTestId("flight-result-card").getByText("EK", { exact: true }).first()).toBeVisible();
});

test("zero-stop card uses Direct in the centered route and omits baggage", async ({ page }) => {
  await gotoResults(page);
  const card = page.getByTestId("flight-result-card");
  await expect(card.getByTestId("center-route-block").getByText("Direct")).toBeVisible();
  await expect(card).not.toContainText("Nonstop");
  await expect(card).not.toContainText("30kg checked");
});

test("desktop filters use page scroll, readable multi-selects, and a dual price range", async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.route("**/laravel/flights/results/data**", async (route) => route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(mockResultsBody({ filters: { ...mockResultsBody().filters, price_range: { min: 78812.4, max: 312025.2 } } })) }));
  await page.goto(`/flights/results?${baseResultsQuery()}`);
  const panel = page.getByTestId("results-filter-panel");
  await expect(panel).not.toHaveClass(/overflow-y-auto|max-h-/);
  await expect(panel).toHaveClass(/overflow-x-hidden|min-w-0/);
  await expect(panel.getByRole("checkbox", { name: "Direct (1)" })).toBeVisible();
  await expect(panel.getByRole("checkbox", { name: "Non-refundable (1)" })).toBeVisible();
  await expect(panel.getByTestId("price-range-slider").getByRole("slider")).toHaveCount(2);
  await expect(panel).toContainText("PKR 78,812");
  const overflowX = await panel.evaluate((node) => getComputedStyle(node).overflowX);
  expect(overflowX === "hidden" || overflowX === "clip").toBeTruthy();
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
              stops_label_display: "1 Stop",
              layover_summary_display: ["1h 15m layover · DXB"],
              layovers_display: [{ airport_code: "DXB", airport_city: "Dubai", duration_minutes: 75 }],
            }),
          ],
        }),
      ),
    });
  });
  await page.goto(`/flights/results?${baseResultsQuery()}`);
  const stopTag = page.getByRole("button", { name: /layover/i });
  await stopTag.focus();
  await expect(page.getByRole("tooltip")).toContainText("DXB");
  await expect(page.getByRole("tooltip")).toContainText("1h 15m");
  await stopTag.click();
  await expect(page.getByRole("tooltip")).toBeVisible();
});

test("result card copy and whatsapp share use safe public search URL", async ({ page }) => {
  await page.route("**/laravel/flights/results/data**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(mockResultsBody()),
    });
  });
  await page.goto(`/flights/results?${baseResultsQuery()}`);
  await page.context().grantPermissions(["clipboard-read", "clipboard-write"]);
  const card = page.getByTestId("flight-result-card");
  await expect(card.getByTestId("result-share-actions")).toBeVisible();
  await card.getByTestId("result-copy-share").click();
  await expect(card.getByText("Copied")).toBeVisible();
  const copied = await page.evaluate(() => navigator.clipboard.readText());
  expect(copied).toContain("Your Flight Details");
  expect(copied).toContain("/flights/results?");
  expect(copied).not.toContain("search_id=");
  expect(copied).not.toContain("offer_id=");
  const href = await card.getByTestId("result-whatsapp-share").getAttribute("href");
  expect(href).toMatch(/^https:\/\/wa\.me\/\?text=/);
  expect(decodeURIComponent(href ?? "")).not.toContain("search_id=");
});

test("Book Now opens shared modal and branded fares appear only inside it", async ({ page }) => {
  const fares = [
    { option_key: "eco", name: "Economy Saver", displayed_price: 100000, price_display: "100,000 PKR", selection_key_authoritative: true },
    { option_key: "flex", name: "Economy Flex", displayed_price: 112000, price_display: "112,000 PKR", selection_key_authoritative: true },
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
  await expect(page.locator("[data-fare-family-card]")).toHaveCount(0);
  await page.getByTestId("book-now-trigger").click();
  await expect(page.getByTestId("flight-details-drawer")).toBeVisible();
  await expect(page.getByRole("heading", { name: "Choose your flight & fare" })).toBeVisible();
  await expect(page.locator("[data-fare-family-card]")).toHaveCount(2);
  await expect(page).toHaveURL(/\/flights\/results/);
});

test("branded fare carousel with more than three fares", async ({ page }) => {
  const fares = Array.from({ length: 4 }).map((_, index) => ({
    option_key: `fare-${index}`,
    name: `Fare ${index + 1}`,
    displayed_price: 100000 + index * 1000,
    price_display: `${100000 + index * 1000} PKR`,
    selection_key_authoritative: true,
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
  const detailsUrls: string[] = [];
  await page.route("**/laravel/flights/results/offer?**", async (route) => {
    detailsUrls.push(route.request().url());
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        search_id: MOCK_SEARCH_ID,
        offer_id: "offer-1",
        offer: {
          ...mockOffer(),
          has_branded_fares: true,
          branded_fares_display_options: fares,
          fare_family_options_display: fares,
          can_book: true,
        },
      }),
    });
  });
  await page.goto(`/flights/results?${baseResultsQuery()}`);
  await expect(page.locator("[data-fare-family-card]")).toHaveCount(0);
  await page.getByTestId("book-now-trigger").click();
  await expect(page.locator("[data-fare-family-card]")).toHaveCount(4);
  await expect(page.getByLabel("Next fare options")).toBeVisible();
  const secondCard = page.locator("[data-fare-family-card]").nth(1);
  const secondFare = secondCard.getByRole("button", { name: "Select fare" });
  await secondFare.press("Enter");
  await expect(secondCard.getByRole("button", { name: "Selected" })).toHaveAttribute("aria-pressed", "true");
  await expect.poll(() => detailsUrls.some((url) => url.includes("fare_option_key=fare-1"))).toBe(true);
  for (const [width, height] of [[1440, 900], [1366, 768], [1024, 768], [768, 900], [390, 844], [320, 800]]) {
    await page.setViewportSize({ width, height });
    await expect(page.getByTestId("continue-to-passengers")).toBeVisible();
    const overflows = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflows, `${width}px fare modal should not overflow`).toBe(false);
  }
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.screenshot({ path: "tmp/flight-results-fare-modal-desktop.png", fullPage: true });
});

test("opening Book Now is read-only until fare confirmation", async ({ page }) => {
  let mutationCount = 0;
  await gotoResults(page);
  await page.route("**/laravel/flights/results/offer?**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ success: true, search_id: MOCK_SEARCH_ID, offer_id: "offer-1", offer: { ...mockOffer(), can_book: true } }),
    });
  });
  await page.route("**/laravel/flights/results/select**", async (route) => {
    mutationCount += 1;
    await route.abort();
  });
  await page.getByTestId("book-now-trigger").click();
  await expect(page.getByTestId("flight-details-drawer")).toBeVisible();
  expect(mutationCount).toBe(0);
  await expect(page).toHaveURL(/\/flights\/results/);
});

test("result card remains usable without body overflow at target breakpoints", async ({ page }) => {
  await gotoResults(page);
  for (const [width, height] of [[1440, 900], [1366, 768], [1024, 768], [768, 900], [390, 844], [320, 800]]) {
    await page.setViewportSize({ width, height });
    await expect(page.getByTestId("flight-result-card")).toBeVisible();
    await expect(page.getByTestId("book-now-trigger")).toBeVisible();
    const overflows = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflows, `${width}px viewport should not overflow`).toBe(false);
  }
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
  await expect(page.getByTestId("result-skeleton")).toBeVisible();
  await expect(page.getByTestId("results-count-label")).not.toHaveText(/^0 /);
  await expect(page.getByTestId("flight-result-card")).toBeVisible({ timeout: 15_000 });
});

test("edit search is inline SearchModule", async ({ page }) => {
  await gotoResults(page);
  await page.getByTestId("edit-search-button").click();
  await expect(page.getByTestId("inline-edit-search")).toBeVisible();
  await expect(page.getByTestId("search-module")).toHaveAttribute("data-search-variant", "results");
  await expect(page.getByRole("dialog", { name: "Modify search" })).toHaveCount(0);
  await expect(page.getByTestId("search-module").getByRole("combobox", { name: "From" })).toHaveValue(/ISB/i);
  await expect(page.getByTestId("search-module").getByRole("combobox", { name: "To" })).toHaveValue(/DXB/i);
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
