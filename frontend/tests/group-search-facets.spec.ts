import { expect, test, type Page } from "@playwright/test";

const mockFacets = {
  sectors: [
    { value: "LHE-JED", label: "LHE-JED" },
    { value: "SKT-SHJ", label: "SKT-SHJ" },
  ],
  categories: [
    { value: "ksa", label: "KSA" },
    { value: "uae", label: "UAE" },
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
  await expect(sectorSelect.locator("option")).toHaveText("Loading sectors…");
  await navigation;
  await expect(sectorSelect).toBeEnabled();
  await expect(sectorSelect.locator("option")).toHaveCount(3);
  await expect(sectorSelect).toContainText("LHE-JED");
  await expect(sectorSelect).not.toContainText("UK — London");
});

test("laravel categories populate category options", async ({ page }) => {
  await page.goto("/groups/search");
  await expect(page.getByTestId("group-category-options")).toContainText("KSA");
  await expect(page.getByTestId("group-category-options")).toContainText("UAE");
  await expect(page.getByTestId("group-category-options")).not.toContainText("Muscat");
});

test("empty facets state blocks submission", async ({ page }) => {
  await page.route("**/laravel/groups/search/facets**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ sectors: [], categories: [], date_bounds: null }),
    });
  });
  await resetFacetsCache(page);

  await page.goto("/groups/search");
  await expect(page.getByTestId("group-empty-state")).toContainText(
    "No group fares are currently available. Please check again later or contact JetPakistan Groups.",
  );
  await expect(page.getByText("Request failed")).toHaveCount(0);
  await expect(page.getByTestId("group-sector-select").locator("option")).toHaveCount(1);
  await expect(page.getByRole("button", { name: "Search Group Fares" })).toBeDisabled();
});

test("failed facets request shows retry and blocks submission", async ({ page }) => {
  await page.route("**/laravel/groups/search/facets**", async (route) => {
    await route.fulfill({ status: 500, contentType: "application/json", body: JSON.stringify({ message: "Server error" }) });
  });
  await resetFacetsCache(page);

  await page.goto("/groups/search");
  await expect(page.getByRole("button", { name: "Retry loading sectors" })).toBeVisible();
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
  await expect(page.getByRole("button", { name: "Retry loading sectors" })).toBeVisible();
  fail = false;
  await page.getByRole("button", { name: "Retry loading sectors" }).click();
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
  await page.getByRole("radio", { name: "UAE" }).check();
  await page.getByRole("button", { name: "Search Group Fares" }).click();
  await page.waitForURL(/sector=LHE-JED/);
  expect(page.url()).toContain("date_from=2026-08-15");
  expect(page.url()).toContain("category=uae");
});

test("category All omits category query param", async ({ page }) => {
  await page.goto("/groups/search");
  await page.getByTestId("group-sector-select").selectOption({ index: 1 });
  await page.getByLabel("Travel date").fill("2026-08-15");
  await page.getByRole("radio", { name: "All" }).check();
  await page.getByRole("button", { name: "Search Group Fares" }).click();
  await page.waitForURL(/\/groups\/search\?/);
  expect(page.url()).not.toContain("category=");
});
