import { test, expect } from "@playwright/test";

function tomorrowIso(): string {
  const date = new Date();
  date.setDate(date.getDate() + 1);
  return date.toISOString().slice(0, 10);
}

function dayAfterTomorrowIso(): string {
  const date = new Date();
  date.setDate(date.getDate() + 2);
  return date.toISOString().slice(0, 10);
}

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("homepage loads with full hero and search shell", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });

  await expect(page.getByRole("heading", { level: 1, name: /Explore the world with/i })).toBeVisible();
  await expect(page.getByTestId("homepage-hero-image")).toBeVisible();
  await expect(page.getByTestId("homepage-hero-image").locator("img")).toHaveAttribute("src", /hero-pakistan/);
  await expect(page.getByTestId("search-module")).toHaveAttribute("data-search-layout", "compact");
  await expect(page.getByRole("heading", { name: "Destinations on the Rise" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Why JetPakistan" })).toBeVisible();
  await expect(page.getByRole("banner")).toBeVisible();
  await expect(page.getByRole("contentinfo")).toBeVisible();
});

test("one way trip navigates to results immediately without waiting for Laravel init", async ({ page }) => {
  await page.route("**/laravel/flights/results/search**", async (route) => {
    await new Promise((resolve) => setTimeout(resolve, 800));
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        search_id: "mock-search-id",
        results_page_url: "/flights/results",
        initial_results_url: "/flights/results/data?search_id=mock-search-id",
      }),
    });
  });
  await page.route("**/laravel/flights/results/data**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        search_id: "mock-search-id",
        page: 1,
        per_page: 12,
        total: 0,
        has_more: false,
        offers: [],
      }),
    });
  });

  await page.goto("/", { waitUntil: "load" });
  await page.getByTestId("trip-type-trigger").click();
  await page.getByRole("menuitem", { name: "One Way" }).click();
  await page.getByLabel("Departure", { exact: true }).fill(tomorrowIso());
  const start = Date.now();
  await page.getByRole("button", { name: "Search Flights" }).click();
  await page.waitForURL("**/flights/results**", { timeout: 10_000 });
  expect(Date.now() - start).toBeLessThan(1500);
  expect(new URL(page.url()).pathname).toContain("/flights/results");
});

test("return trip shows combined date range field", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });

  await page.getByTestId("trip-type-trigger").click();
  await page.getByRole("menuitem", { name: "Return" }).click();
  await expect(page.getByTestId("date-range-trigger")).toBeVisible();
  await expect(page.getByLabel("Departure", { exact: true })).toHaveCount(0);
});

test("return range prevents return before outbound", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });

  await page.getByTestId("trip-type-trigger").click();
  await page.getByRole("menuitem", { name: "Return" }).click();
  const departure = dayAfterTomorrowIso();
  await page.getByTestId("date-range-trigger").click();
  await page.locator(`[data-testid="date-range-panel"] [data-date="${departure}"]`).click();
  await expect(page.getByTestId("date-range-trigger")).toContainText("Return");
});

test("multi-city add and remove segments", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });

  await page.getByTestId("trip-type-trigger").click();
  await page.getByRole("menuitem", { name: "Multi-City" }).click();
  await expect(page.getByText("Flight 1")).toBeVisible();
  await expect(page.getByText("Flight 2")).toBeVisible();

  await page.getByRole("button", { name: "Add Flight" }).click();
  await expect(page.getByText("Flight 3")).toBeVisible();

  await page.getByRole("button", { name: "Remove Flight" }).first().click();
  await expect(page.getByText("Flight 3")).toBeHidden();
});

test("Groups product tab renders Laravel search fields only", async ({ page }) => {
  await page.route("**/laravel/groups/search/facets**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        sectors: [{ value: "JED", label: "KSA — Jeddah" }],
        categories: [
          { value: "ksa", label: "KSA" },
          { value: "uae", label: "UAE" },
          { value: "muscat", label: "Muscat" },
        ],
        date_bounds: { minimum: "2026-01-01", maximum: "2027-12-31" },
      }),
    });
  });

  await page.goto("/", { waitUntil: "load" });

  await page.getByTestId("product-tab-group").click();
  await expect(page.getByRole("button", { name: "Search Groups" })).toBeVisible();
  await expect(page.getByLabel("Sector")).toBeVisible();
  await expect(page.getByLabel("Travel date")).toBeVisible();
  await expect(page.getByLabel("Group category")).toBeVisible();
  await expect(page.getByRole("radio", { name: "KSA" })).toBeVisible();
  await expect(page.getByRole("radio", { name: "UAE" })).toBeVisible();
  await expect(page.getByRole("radio", { name: "Muscat" })).toBeVisible();
  await expect(page.getByLabel("Origin")).toHaveCount(0);
  await expect(page.getByRole("button", { name: "Travelers and cabin" })).toHaveCount(0);
});

test("airport picker supports keyboard selection", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });

  const fromField = page.getByRole("combobox", { name: "From" });
  await fromField.click();
  await fromField.fill("Lahore");
  await expect(page.getByRole("option", { name: /LHE/i })).toBeVisible();
  await page.keyboard.press("ArrowDown");
  await page.keyboard.press("Enter");

  await expect(fromField).toHaveValue(/LHE/i);
});

test("travelers selector enforces infant constraint", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });

  await page.getByTestId("travelers-cabin-trigger").first().click();
  await page.getByRole("button", { name: "Increase infants" }).click();
  await expect(page.getByRole("button", { name: "Increase infants" })).toBeDisabled();
});

test("mobile homepage search layout remains usable", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/", { waitUntil: "load" });

  await expect(page.getByTestId("search-module")).toBeVisible();
  await expect(page.getByTestId("product-tab-flights")).toBeVisible();
  await expect(page.getByTestId("product-tab-group")).toBeVisible();
  await expect(page.getByTestId("trip-type-trigger")).toBeVisible();
  await expect(page.getByLabel("From")).toBeVisible();
});

test("reduced motion homepage disables flight-path animation", async ({ page }) => {
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.goto("/", { waitUntil: "load" });

  const animationState = await page.getByRole("img", { name: "Decorative flight path" }).first().evaluate((element) => {
    const styles = getComputedStyle(element);
    return {
      animationName: styles.animationName,
      animationDuration: styles.animationDuration,
    };
  });

  expect(
    animationState.animationName === "none" ||
      animationState.animationDuration === "0s" ||
      animationState.animationDuration === "0.01ms",
  ).toBeTruthy();
});
