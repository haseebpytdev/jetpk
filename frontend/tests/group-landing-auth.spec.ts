import { expect, test } from "@playwright/test";

test.describe("JP-GRP-UI-01 Groups landing + auth gate", () => {
  test("header Groups links to /groups landing", async ({ page }) => {
    await page.route("**/laravel/groups/search/facets**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          sectors: [{ value: "LHE-DXB", label: "LHE-DXB" }],
          airlines: [{ value: "Emirates", label: "Emirates" }],
          categories: [
            { value: "uae", label: "UAE", inventory_count: 4 },
            { value: "ksa-oneway", label: "KSA ONEWAY", inventory_count: 6 },
          ],
          date_bounds: { minimum: "2026-08-27", maximum: "2026-12-31" },
        }),
      });
    });

    await page.goto("/");
    const groupsNav = page.locator('nav[aria-label="Primary"] a[href="/groups"]').first();
    await expect(groupsNav).toBeVisible();
    await groupsNav.click();
    await expect(page).toHaveURL(/\/groups\/?$/);
    await expect(page.getByTestId("groups-landing-page")).toBeVisible();
    await expect(page.getByTestId("groups-landing-search")).toBeVisible();
    await expect(page.getByTestId("group-category-cards")).toBeVisible();
    await expect(page.getByTestId("shared-group-search").getByTestId("group-category-cards")).toHaveCount(0);
  });

  test("homepage Groups tab stays compact without category cards", async ({ page }) => {
    await page.route("**/laravel/groups/search/facets**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          sectors: [{ value: "LHE-DXB", label: "LHE-DXB" }],
          airlines: [{ value: "Emirates", label: "Emirates" }],
          categories: [{ value: "uae", label: "UAE", inventory_count: 2 }],
          date_bounds: null,
        }),
      });
    });

    await page.goto("/");
    await page.getByTestId("product-tab-group").click();
    await expect(page.getByTestId("shared-group-search")).toBeVisible();
    await expect(page.getByTestId("group-search-form")).toBeVisible();
    await expect(page.getByTestId("search-module").getByTestId("group-category-cards")).toHaveCount(0);
  });

  test("category card deep-links to /groups/search", async ({ page }) => {
    await page.route("**/laravel/groups/search/facets**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          sectors: [],
          airlines: [],
          categories: [{ value: "uae", label: "UAE", inventory_count: 3 }],
          date_bounds: null,
        }),
      });
    });
    await page.route("**/laravel/groups/search/data**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          filters: { category: "uae" },
          facets: { sectors: [], airlines: [], departure_dates: [], categories: [] },
          cards: [],
          total: 0,
          page: 1,
          per_page: 15,
          has_more: false,
          bookable: true,
          count_label: "Showing 0 of 0 group departures",
          lock_state: { locked: false },
        }),
      });
    });

    await page.goto("/groups");
    await page.getByTestId("group-category-card-uae").click();
    await expect(page).toHaveURL(/\/groups\/search\?category=uae/);
  });

  test("anonymous Book Now opens login modal", async ({ page }) => {
    await page.route("**/laravel/groups/package/**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          package: {
            public_id: "ALH-1",
            title: "Test Group",
            sector_code: "LHE-DXB",
            route_line: "LHE → DXB",
            airline_name: "Emirates",
            price_formatted: "95,000",
            currency: "PKR",
            available_seats: 5,
            seat_label: "5 seats left",
            bookable: true,
            baggage_line: "20kg",
          },
          available: true,
          lock_state: { locked: false },
          progress: [],
        }),
      });
    });
    await page.route("**/api/public/auth/session**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ authenticated: false }),
      });
    });
    await page.route("**/laravel/api/public/auth/session**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ authenticated: false }),
      });
    });

    await page.goto("/groups/ALH-1");
    await expect(page.getByTestId("group-book-now")).toBeVisible();
    await page.getByTestId("group-book-now").click();
    await expect(page.getByTestId("group-checkout-auth-modal")).toBeVisible();
    await expect(page.getByTestId("login-form")).toBeVisible();
  });
});
