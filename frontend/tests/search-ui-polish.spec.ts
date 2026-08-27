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

function daysFromNowIso(days: number): string {
  const date = new Date();
  date.setDate(date.getDate() + days);
  return date.toISOString().slice(0, 10);
}

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

test.describe("Search UI polish cluster", () => {
  test.describe("airport click-to-replace", () => {
    test.beforeEach(async ({ page }) => {
      await page.route("**/laravel/airports/search**", async (route) => {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify([
            { iata: "ISB", name: "Islamabad International Airport", city: "Islamabad", country: "Pakistan" },
            { iata: "LHE", name: "Allama Iqbal International Airport", city: "Lahore", country: "Pakistan" },
            { iata: "DXB", name: "Dubai International Airport", city: "Dubai", country: "UAE" },
          ]),
        });
      });
    });

    test("click activation clears the populated field and selecting a result replaces it", async ({ page }) => {
      await page.goto("/", { waitUntil: "load" });
      const from = page.getByRole("combobox", { name: "From" });

      await from.click();
      await expect(from).toHaveValue("");
      await expect(from).not.toHaveClass(/pl-14/);

      await from.fill("Lah");
      const lahore = page.getByRole("option", { name: /LHE.*Lahore/i });
      await expect(lahore).toBeVisible();
      await lahore.click();

      await expect(from).toHaveValue("Lahore (LHE)");
    });

    test("blur without a selection restores the previous airport", async ({ page }) => {
      await page.goto("/", { waitUntil: "load" });
      const from = page.getByRole("combobox", { name: "From" });

      await from.click();
      await expect(from).toHaveValue("");
      await page.getByTestId("trip-type-trigger").click();

      await expect(from).toHaveValue("Islamabad (ISB)");
    });

    test("escape restores the previous airport and closes suggestions", async ({ page }) => {
      await page.goto("/", { waitUntil: "load" });
      const from = page.getByRole("combobox", { name: "From" });

      await from.click();
      await expect(from).toHaveValue("");
      await page.keyboard.press("Escape");

      await expect(from).toHaveValue("Islamabad (ISB)");
      await expect(page.getByRole("listbox", { name: "From suggestions" })).toHaveCount(0);
    });

    test("keyboard navigation still selects a replacement airport", async ({ page }) => {
      await page.goto("/", { waitUntil: "load" });
      const from = page.getByRole("combobox", { name: "From" });

      await from.click();
      await from.fill("Lah");
      await expect(page.getByRole("option", { name: /LHE.*Lahore/i })).toBeVisible();
      await page.keyboard.press("ArrowDown");
      await page.keyboard.press("Enter");

      await expect(from).toHaveValue("Lahore (LHE)");
    });
  });

  test("one way renders single departure date field", async ({ page }) => {
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "One Way" }).click();
    await expect(page.getByLabel("Departure", { exact: true })).toBeVisible();
    await expect(page.getByTestId("date-range-trigger")).toHaveCount(0);
  });

  test("return renders one date-range control", async ({ page }) => {
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "Return" }).click();
    await expect(page.getByTestId("date-range-trigger")).toBeVisible();
    await expect(page.getByTestId("date-range-trigger")).toContainText("Select dates");
    await expect(page.getByLabel("Departure", { exact: true })).toHaveCount(0);
    await expect(page.getByLabel("Return", { exact: true })).toHaveCount(0);
  });

  test("return range serializes outbound and return dates on submit", async ({ page }) => {
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "Return" }).click();

    const outbound = daysFromNowIso(10);
    const inbound = daysFromNowIso(17);
    await page.getByTestId("date-range-trigger").click();
    await page.locator(`[data-testid="date-range-panel"] [data-date="${outbound}"]`).click();
    await page.locator(`[data-testid="date-range-panel"] [data-date="${inbound}"]`).click();

    await page.getByRole("button", { name: "Search Flights" }).click();
    await page.waitForURL("**/flights/results**");
    expect(page.url()).toContain(`depart=${outbound}`);
    expect(page.url()).toContain(`return_date=${inbound}`);
  });

  test("multi-city retains per-segment date fields", async ({ page }) => {
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "Multi-City" }).click();
    await expect(page.getByText("Flight 1")).toBeVisible();
    await expect(page.getByText("Flight 2")).toBeVisible();
    await expect(page.getByLabel("Departure").first()).toBeVisible();
  });

  test("trip type dropdown switches modes", async ({ page }) => {
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "Multi-City" }).click();
    await expect(page.getByText("Flight 1")).toBeVisible();

    await page.getByTestId("trip-type-trigger").click();
    await page.getByRole("menuitem", { name: "Return" }).click();
    await expect(page.getByTestId("date-range-trigger")).toBeVisible();
  });

  test("trip type selector is compact without a visible prefix", async ({ page }) => {
    await page.goto("/", { waitUntil: "load" });
    const trigger = page.getByTestId("trip-type-trigger");
    await expect(trigger).toHaveAttribute("aria-label", "Trip type");
    await expect(trigger).toContainText("One Way");
    await expect(trigger).not.toContainText("Trip type:");

    const box = await trigger.boundingBox();
    expect(box).not.toBeNull();
    if (box) expect(box.height).toBeGreaterThanOrEqual(40);
  });

  test("flights and Groups are separate product tabs", async ({ page }) => {
    await page.goto("/", { waitUntil: "load" });
    await expect(page.getByTestId("product-tab-flights")).toHaveAttribute("aria-selected", "true");
    await expect(page.getByTestId("trip-type-trigger")).toBeVisible();

    await page.getByTestId("product-tab-group").click();
    await expect(page.getByTestId("product-tab-group")).toHaveAttribute("aria-selected", "true");
    await expect(page.getByTestId("trip-type-trigger")).toHaveCount(0);
    await expect(page.getByRole("button", { name: "Search Groups" })).toBeVisible();
  });

  test("traveler counts and cabin class remain available", async ({ page }) => {
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("travelers-cabin-trigger").first().click();
    await expect(page.getByTestId("travelers-cabin-panel")).toBeVisible();
    await expect(page.getByText("Cabin Class")).toBeVisible();
    await expect(page.getByRole("radio", { name: "Economy", exact: true })).toBeVisible();
    await expect(page.getByRole("radio", { name: "Premium Economy" })).toBeVisible();
    await expect(page.getByRole("radio", { name: "Business" })).toBeVisible();
    await expect(page.getByRole("radio", { name: "First" })).toBeVisible();
  });

  test("cabin selection survives dropdown close and reopen", async ({ page }) => {
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("travelers-cabin-trigger").first().click();
    await page.getByRole("radio", { name: "Business" }).check();
    await page.keyboard.press("Escape");
    await expect(page.getByTestId("travelers-cabin-trigger").first()).toContainText("Business");

    await page.getByTestId("travelers-cabin-trigger").first().click();
    await expect(page.getByRole("radio", { name: "Business" })).toBeChecked();
  });

  test("search submission carries cabin value", async ({ page }) => {
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("travelers-cabin-trigger").first().click();
    await page.getByRole("radio", { name: "First" }).check();
    await page.keyboard.press("Escape");
    await page.getByLabel("Departure", { exact: true }).fill(tomorrowIso());
    await page.getByRole("button", { name: "Search Flights" }).click();
    await page.waitForURL("**/flights/results**");
    expect(page.url()).toContain("cabin=first");
  });

  test("traveler validation keeps infant constraint", async ({ page }) => {
    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("travelers-cabin-trigger").first().click();
    await page.getByRole("button", { name: "Increase infants" }).click();
    await expect(page.getByRole("button", { name: "Increase infants" })).toBeDisabled();
  });

  test("Groups routing remains intact", async ({ page }) => {
    await page.route("**/laravel/groups/search/facets**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          sectors: [{ value: "JED", label: "KSA — Jeddah" }],
          categories: [{ value: "ksa", label: "KSA" }],
          date_bounds: { minimum: "2026-01-01", maximum: "2027-12-31" },
        }),
      });
    });

    await page.goto("/", { waitUntil: "load" });
    await page.getByTestId("product-tab-group").click();
    await expect(page.getByLabel("Sector")).toBeVisible();
    await expect(page.getByLabel("Travel date")).toBeVisible();
    await expect(page.getByRole("button", { name: "Travelers and cabin" })).toHaveCount(0);
  });
});
