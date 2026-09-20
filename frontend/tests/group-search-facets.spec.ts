import { expect, test, type Page } from "@playwright/test";

const mockFacets = {
  airlines: [
    { value: "PIA", label: "PIA" },
    { value: "Airblue", label: "Airblue" },
  ],
  sectors: [
    { value: "LHE-JED", label: "LHE-JED" },
    { value: "SKT-SHJ", label: "SKT-SHJ" },
  ],
  categories: [
    { value: "ksa", label: "KSA Groups", inventory_count: 3, image_url: null },
    { value: "uae", label: "UAE Groups", inventory_count: 2, image_url: null },
  ],
  tiles: [
    {
      key: "all",
      slug: null,
      title: "All Groups",
      image_url: null,
      package_count: 5,
      url: "/groups/search",
    },
    {
      key: "ksa",
      slug: "ksa",
      title: "KSA Groups",
      image_url: null,
      package_count: 3,
      url: "/groups/search?category=ksa",
    },
    {
      key: "uae",
      slug: "uae",
      title: "UAE Groups",
      image_url: null,
      package_count: 2,
      url: "/groups/search?category=uae",
    },
  ],
  date_bounds: { minimum: "2026-08-01", maximum: "2026-12-31" },
};

async function resetFacetsCache(page: Page): Promise<void> {
  await page.goto("/groups/search");
  await page.evaluate(() => window.__jpResetGroupSearchFacetsCache?.());
}

test.beforeEach(async ({ page }) => {
  await page.route("**/laravel/groups/search/facets**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(mockFacets),
    });
  });
});

test("group search facets loading then Laravel sectors populate dropdown", async ({ page }) => {
  await page.unroute("**/laravel/groups/search/facets**");
  await page.route("**/laravel/groups/search/facets**", async (route) => {
    await new Promise((resolve) => setTimeout(resolve, 1200));
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(mockFacets),
    });
  });

  await page.goto("/groups/search");
  await page.evaluate(() => window.__jpResetGroupSearchFacetsCache?.());
  const navigation = page.goto("/groups/search", { waitUntil: "commit" });
  const sectorSelect = page.getByTestId("group-sector-select");
  await expect(sectorSelect).toBeDisabled();
  await expect(sectorSelect.locator("option").first()).toHaveText("Loading sectors…");
  await navigation;
  await expect(sectorSelect).toBeEnabled();
  await expect(sectorSelect.locator("option")).toHaveCount(3);
  await expect(sectorSelect).toContainText("LHE-JED");
  await expect(sectorSelect).not.toContainText("UK — London");
});

test("airline facet populates airline select without category radios", async ({ page }) => {
  await page.goto("/groups/search");
  await expect(page.getByTestId("group-airline-select")).toContainText("PIA");
  await expect(page.getByTestId("group-airline-select")).toContainText("Airblue");
  await expect(page.getByTestId("group-airline-select")).toContainText("All Airlines");
  await expect(page.getByTestId("group-category-options")).toHaveCount(0);
  await expect(page.getByTestId("group-search-form")).toHaveAttribute("data-group-search-fields", "3");
});

test("empty facets state blocks submission", async ({ page }) => {
  await page.route("**/laravel/groups/search/facets**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ airlines: [], sectors: [], categories: [], tiles: [], date_bounds: null }),
    });
  });
  await resetFacetsCache(page);

  await page.goto("/groups/search");
  await expect(page.getByText("No group sectors are currently available")).toBeVisible();
  await expect(page.getByRole("button", { name: "Search Group Fares" })).toBeDisabled();
});

test("failed facets request shows retry and blocks submission", async ({ page }) => {
  await page.route("**/laravel/groups/search/facets**", async (route) => {
    await route.fulfill({ status: 500, contentType: "application/json", body: JSON.stringify({ message: "Server error" }) });
  });
  await resetFacetsCache(page);

  await page.goto("/groups/search");
  await expect(page.getByRole("button", { name: "Retry loading options" })).toBeVisible();
  await expect(page.getByRole("button", { name: "Search Group Fares" })).toBeDisabled();
});

test("successful retry populates options", async ({ page }) => {
  await page.unroute("**/laravel/groups/search/facets**");
  let fail = true;
  await page.route("**/laravel/groups/search/facets**", async (route) => {
    if (fail) {
      await route.fulfill({ status: 500, contentType: "application/json", body: JSON.stringify({ message: "Server error" }) });
      return;
    }
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(mockFacets) });
  });
  await page.goto("/groups/search");
  await expect(page.getByRole("button", { name: "Retry loading options" })).toBeVisible();
  fail = false;
  await page.getByRole("button", { name: "Retry loading options" }).click();
  await expect(page.getByTestId("group-sector-select")).toBeEnabled();
  await expect(page.getByTestId("group-sector-select")).toContainText("LHE-JED");
  await expect(page.getByTestId("group-airline-select")).toContainText("PIA");
});

test("stale query sector is cleared when facets load", async ({ page }) => {
  await resetFacetsCache(page);
  await page.goto("/groups/search?sector=INVALID-SECTOR&date_from=2026-08-15");
  await expect(page.getByTestId("group-sector-select")).toBeEnabled();
  await expect(page.getByTestId("group-sector-select")).toHaveValue("");
  await expect(page.getByText("Selected sector is no longer available")).toBeVisible();
});

test("valid sector and airline submit exact Laravel values", async ({ page }) => {
  await page.goto("/groups/search");
  await page.getByTestId("group-airline-select").selectOption("PIA");
  await page.getByTestId("group-sector-select").selectOption("LHE-JED");
  await page.getByLabel("Travel date").fill("2026-08-15");
  await page.getByRole("button", { name: "Search Group Fares" }).click();
  await page.waitForURL(/sector=LHE-JED/);
  expect(page.url()).toContain("date_from=2026-08-15");
  expect(page.url()).toContain("airline=PIA");
  expect(page.url()).not.toContain("category=");
});

test("category query remains as filter chip outside primary form", async ({ page }) => {
  await page.goto("/groups/search?category=uae&sector=LHE-JED&date_from=2026-08-15");
  await expect(page.getByTestId("group-category-filter-chip")).toContainText("UAE");
  await expect(page.getByTestId("group-category-options")).toHaveCount(0);
  await expect(page.getByTestId("group-airline-select")).toBeVisible();
});

test("all airlines omits airline query param", async ({ page }) => {
  await page.goto("/groups/search");
  await page.getByTestId("group-airline-select").selectOption({ index: 0 });
  await page.getByTestId("group-sector-select").selectOption({ index: 1 });
  await page.getByLabel("Travel date").fill("2026-08-15");
  await page.getByRole("button", { name: "Search Group Fares" }).click();
  await page.waitForURL(/\/groups\/search\?/);
  expect(page.url()).not.toContain("airline=");
  expect(page.url()).toContain("sector=");
});
