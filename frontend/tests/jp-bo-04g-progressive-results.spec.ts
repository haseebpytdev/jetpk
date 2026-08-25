import { expect, test, type Page, type Route } from "@playwright/test";

const SEARCH_ID = "progressive-search-1";

function offer(id: string, price: number) {
  return {
    offer_id: id,
    displayed_price: price,
    displayed_currency: "PKR",
    price_display: `PKR ${price.toLocaleString("en-PK")}`,
    airline_code: "PK",
    airline_name: "Pakistan International",
    flight_number: `PK${id}`,
    origin: "LHE",
    destination: "DXB",
    departure_time: "08:00",
    arrival_time: "10:30",
    duration_minutes: 150,
    stops: 0,
    cabin: "economy",
    bookable: true,
    supplier_provider: "sabre",
    fare_options: [
      {
        option_key: "basic",
        name: "Economy Basic",
        displayed_price: price,
        displayed_currency: "PKR",
      },
    ],
  };
}

async function mockProgressiveOneWay(page: Page) {
  let poll = 0;

  await page.route("**/flights/results/search**", async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        search_id: SEARCH_ID,
        status: "searching",
        results_page_url: `/flights/results?search_id=${SEARCH_ID}`,
        initial_results_url: `/flights/results/data?search_id=${SEARCH_ID}`,
      }),
    });
  });

  await page.route("**/flights/results/data**", async (route: Route) => {
    poll += 1;
    if (poll === 1) {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          search_id: SEARCH_ID,
          status: "searching",
          page: 1,
          per_page: 12,
          total: 0,
          has_more: false,
          offers: [],
        }),
      });
      return;
    }
    if (poll === 2) {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          search_id: SEARCH_ID,
          status: "partial",
          page: 1,
          per_page: 12,
          total: 1,
          has_more: false,
          offers: [offer("a", 50000)],
        }),
      });
      return;
    }
    if (poll === 3) {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          search_id: SEARCH_ID,
          status: "partial",
          page: 1,
          per_page: 12,
          total: 2,
          has_more: false,
          offers: [offer("a", 50000), offer("b", 62000)],
        }),
      });
      return;
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        search_id: SEARCH_ID,
        status: "ready",
        page: 1,
        per_page: 12,
        total: 2,
        has_more: false,
        offers: [offer("a", 50000), offer("b", 62000)],
      }),
    });
  });
}

test("one-way progressive results: shell then first batch then append", async ({ page }) => {
  await mockProgressiveOneWay(page);

  await page.goto(
    `/flights/results?search_id=${SEARCH_ID}&trip_type=one_way&from=LHE&to=DXB&depart=2026-09-01&cabin=economy&adults=1`,
  );

  // Loading mask must appear before results; cold Next navigation is not SEARCH_TO_SHELL.
  await expect(page.getByTestId("search-progress")).toBeVisible({ timeout: 5000 });
  await expect(
    page.getByText(/Searching live flights|Checking available fares|Still searching|Searching flights/i).first(),
  ).toBeVisible();
  await expect(page.getByTestId("empty-results")).toHaveCount(0);

  await expect(page.getByRole("list", { name: "Flight results" })).toBeVisible({ timeout: 8000 });
  await expect(page.getByTestId("search-progress-compact")).toBeVisible();
  await expect(page.getByTestId("search-progress")).toHaveCount(0);

  await expect.poll(async () => page.getByRole("listitem").count(), { timeout: 10000 }).toBeGreaterThanOrEqual(2);

  // No empty flash while searching — empty state must not appear during progressive polls.
  await expect(page.getByTestId("empty-results")).toHaveCount(0);
});
