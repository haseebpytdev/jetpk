import { expect, test, type Page } from "@playwright/test";

const mockFacets = {
  airlines: [
    { value: "Emirates", label: "Emirates" },
    { value: "Flydubai", label: "Flydubai" },
  ],
  sectors: [
    { value: "LHE-JED", label: "LHE-JED" },
    { value: "SKT-SHJ", label: "SKT-SHJ" },
  ],
  categories: [
    { value: "ksa", label: "KSA", inventory_count: 4 },
    { value: "uae", label: "UAE", inventory_count: 6 },
  ],
  date_bounds: { minimum: "2026-08-01", maximum: "2026-12-31" },
  travel_date_match: { mode: "EXACT_THEN_NEARBY", tolerance_days: 3 },
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
  await page.route("**/api/public/content/pages/group-search**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        page_key: "group-search",
        source: "cms",
        content: {
          hero: {
            kicker: "Group travel",
            title: "Search group departures",
            description: "Find block-seat group inventory with transparent per-seat pricing.",
          },
        },
      }),
    });
  });
});

test("group search facets loading then Laravel sectors populate dropdown", async ({ page }) => {
  await page.unroute("**/laravel/groups/search/facets**");
  await page.route("**/laravel/groups/search/facets**", async (route) => {
    await new Promise((resolve) => setTimeout(resolve, 800));
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(mockFacets),
    });
  });

  await page.goto("/groups/search");
  await page.evaluate(() => window.__jpResetGroupSearchFacetsCache?.());
  await page.goto("/groups/search");
  const sectorSelect = page.getByTestId("group-sector-select");
  await expect(sectorSelect).toBeEnabled({ timeout: 15_000 });
  await expect(sectorSelect.locator("option")).toHaveCount(3);
  await expect(sectorSelect).toContainText("LHE-JED");
  await expect(sectorSelect).not.toContainText("UK — London");
  await expect(page.getByTestId("group-airline-select")).toContainText("Emirates");
});

test("laravel categories populate dynamic category cards", async ({ page }) => {
  await page.goto("/groups/search");
  await expect(page.getByTestId("group-category-cards")).toContainText("KSA");
  await expect(page.getByTestId("group-category-cards")).toContainText("UAE");
  await expect(page.getByTestId("group-category-cards")).not.toContainText("Muscat");
  await expect(page.getByTestId("group-category-card-ksa")).toContainText("4 departures");
});

test("empty facets state blocks submission", async ({ page }) => {
  await page.route("**/laravel/groups/search/facets**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ sectors: [], airlines: [], categories: [], date_bounds: null }),
    });
  });
  await resetFacetsCache(page);

  await page.goto("/groups/search");
  await expect(page.getByTestId("group-empty-state")).toContainText(
    "No group fares are currently available. Please check again later or contact JetPakistan Groups.",
  );
  await expect(page.getByText("Request failed")).toHaveCount(0);
  await expect(page.getByTestId("group-sector-select").locator("option")).toHaveCount(1);
  await expect(page.getByRole("button", { name: "Search Groups" })).toBeDisabled();
});

test("failed facets request shows retry and blocks submission", async ({ page }) => {
  await page.route("**/laravel/groups/search/facets**", async (route) => {
    await route.fulfill({ status: 500, contentType: "application/json", body: JSON.stringify({ message: "Server error" }) });
  });
  await resetFacetsCache(page);

  await page.goto("/groups/search");
  await expect(page.getByRole("button", { name: "Retry loading filters" })).toBeVisible();
  await expect(page.getByRole("button", { name: "Search Groups" })).toBeDisabled();
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
  await expect(page.getByRole("button", { name: "Retry loading filters" })).toBeVisible();
  fail = false;
  await page.getByRole("button", { name: "Retry loading filters" }).click();
  await expect(page.getByTestId("group-sector-select")).toBeEnabled();
  await expect(page.getByTestId("group-sector-select")).toContainText("LHE-JED");
});

test("stale query sector is cleared when facets load", async ({ page }) => {
  await resetFacetsCache(page);
  await page.goto("/groups/search?sector=INVALID-SECTOR&date_from=2026-08-15");
  await expect(page.getByTestId("group-sector-select")).toBeEnabled();
  await expect(page.getByTestId("group-sector-select")).toHaveValue("");
  await expect(page.getByText("Selected sector is no longer available")).toBeVisible();
});

test("valid sector submits exact Laravel value", async ({ page }) => {
  await page.goto("/groups/search");
  await page.getByTestId("group-sector-select").selectOption("LHE-JED");
  await page.getByLabel("Travel date").fill("2026-08-15");
  await page.getByTestId("group-category-card-uae").click();
  await page.getByRole("button", { name: "Search Groups" }).click();
  await page.waitForURL(/sector=LHE-JED/);
  expect(page.url()).toContain("date_from=2026-08-15");
  expect(page.url()).toContain("category=uae");
});

test("airline-only search is allowed", async ({ page }) => {
  await page.goto("/groups/search");
  await page.getByTestId("group-airline-select").selectOption("Emirates");
  await page.getByRole("button", { name: "Search Groups" }).click();
  await page.waitForURL(/airline=Emirates/);
  expect(page.url()).not.toContain("sector=");
});

test("category All omits category query param", async ({ page }) => {
  await page.goto("/groups/search");
  await page.getByTestId("group-sector-select").selectOption({ index: 1 });
  await page.getByLabel("Travel date").fill("2026-08-15");
  await page.getByRole("button", { name: "Search Groups" }).click();
  await page.waitForURL(/\/groups\/search\?/);
  expect(page.url()).not.toContain("category=");
});

test("clear resets filters", async ({ page }) => {
  await page.goto("/groups/search?sector=LHE-JED&airline=Emirates");
  await expect(page.getByTestId("group-sector-select")).toHaveValue("LHE-JED");
  await page.getByTestId("group-search-clear").click();
  await page.waitForURL((url) => !url.searchParams.has("sector") && !url.searchParams.has("airline"));
  await expect(page.getByTestId("group-sector-select")).toHaveValue("");
  await expect(page.getByTestId("group-airline-select")).toHaveValue("");
});
