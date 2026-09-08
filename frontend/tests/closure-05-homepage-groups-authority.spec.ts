import { expect, test } from "@playwright/test";

const mockPackage = {
  id: 1,
  public_id: "CLOSURE05-TEST",
  title: "Closure 05 Package",
  sector_code: "ISB-DXB",
  route_line: "Islamabad → Dubai",
  departure_date_short: "15 Aug 2026",
  airline_name: "Air Arabia",
  airline_code: "G9",
  airline_logo_url: null,
  baggage_line: "Baggage: Checked 30kg",
  price_formatted: "89,000",
  currency: "PKR",
  available_seats: 4,
  seat_label: "4 seats left",
  seats_badge_variant: "ok",
  cta_disabled: false,
  bookable: true,
};

test.describe("Closure-05 groups authority", () => {
  test.beforeEach(async ({ page }) => {
    await page.route("**/laravel/groups/search/facets**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          airlines: [],
          sectors: [{ value: "ISB-DXB", label: "ISB-DXB" }],
          categories: [],
          date_bounds: { minimum: "2026-08-01", maximum: "2026-12-31" },
        }),
      });
    });

    await page.route("**/laravel/groups/search/data**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          filters: { sector: "ISB-DXB", date_from: "2026-08-15" },
          facets: { sectors: ["ISB-DXB"], airlines: [], departure_dates: [], categories: [] },
          cards: [mockPackage],
          total: 1,
          page: 1,
          per_page: 15,
          has_more: false,
          bookable: true,
          count_label: "Showing 1 of 1 group departures",
          lock_state: { locked: false, unpaid_release_count: 0, block_threshold: 3 },
        }),
      });
    });

    await page.route("**/laravel/groups/package/CLOSURE05-TEST**", async (route) => {
      if (route.request().url().includes("format=json")) {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            success: true,
            package: mockPackage,
            available: true,
          }),
        });
        return;
      }
      await route.continue();
    });
  });

  test("groups search deep link preserves query state", async ({ page }) => {
    await page.goto("/groups/search?sector=ISB-DXB&date_from=2026-08-15");
    await expect(page.getByTestId("group-result-card")).toBeVisible();
    expect(page.url()).toContain("sector=ISB-DXB");
    expect(page.url()).toContain("date_from=2026-08-15");
    await page.reload();
    await expect(page.getByTestId("group-result-card")).toBeVisible();
  });

  test("next detail deep link renders group detail shell", async ({ page }) => {
    await page.route("**/laravel/groups/package/CLOSURE05-TEST**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ success: true, package: mockPackage, available: true }),
      });
    });
    await page.goto("/groups/CLOSURE05-TEST");
    await expect(page.getByText("Closure 05 Package")).toBeVisible({ timeout: 15_000 });
    await page.reload();
    await expect(page.getByText("Closure 05 Package")).toBeVisible();
  });

  test("groups hub and search use current next UI markers", async ({ page }) => {
    await page.goto("/groups/search");
    await expect(page.getByRole("heading", { name: /group/i })).toBeVisible();
    await expect(page.locator('[data-testid="group-sector-select"]')).toBeVisible();
  });
});

test.describe("Closure-05 support CTA fixture", () => {
  test("support banner renders CMS image when fixture provides image", async ({ page }) => {
    await page.goto("/");
    const cmsImage = page.locator('[data-media-slot="support-callout-illustration"] img');
    const illustration = page.locator('[data-media-slot="support-callout-illustration"]');
    await expect(illustration).toBeVisible({ timeout: 15_000 });
    const count = await cmsImage.count();
    if (count > 0) {
      await expect(cmsImage.first()).toHaveAttribute("src", /.+/);
    }
  });
});

test.describe("Closure-05 viewports", () => {
  test("mobile group search layout", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/groups/search");
    await expect(page.getByTestId("group-sector-select")).toBeVisible();
  });

  test("desktop group search layout", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto("/groups/search");
    await expect(page.getByTestId("group-sector-select")).toBeVisible();
  });
});
