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
  await expect(page.getByTestId("search-module")).toBeVisible();
  await expect(page.getByRole("heading", { name: "Destinations on the Rise" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Why JetPakistan" })).toBeVisible();
  await expect(page.getByRole("banner")).toBeVisible();
  await expect(page.getByRole("contentinfo")).toBeVisible();
});

test("one way tab submits to Laravel search-init when departure is set", async ({ page }) => {
  let initRequested = false;
  await page.route("**/laravel/flights/results/search**", async (route) => {
    initRequested = true;
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        search_id: "mock-search-id",
        results_page_url: "http://127.0.0.1:8000/flights/results",
        initial_results_url: "http://127.0.0.1:8000/flights/results/data?search_id=mock-search-id",
      }),
    });
  });

  await page.addInitScript(() => {
    window.location.assign = (() => undefined) as typeof window.location.assign;
  });

  await page.goto("/", { waitUntil: "load" });
  await page.getByRole("tab", { name: "One Way" }).click();
  await page.getByLabel("Departure").fill(tomorrowIso());
  await page.getByRole("button", { name: "Search Flights" }).click();

  await expect.poll(() => initRequested).toBe(true);
});

test("return tab shows return date field", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });

  await page.getByRole("tab", { name: "Return" }).click();
  await expect(page.getByRole("textbox", { name: "Return" })).toBeVisible();
  await expect(page.getByRole("textbox", { name: "Departure" })).toBeVisible();
});

test("return tab enforces return date minimum from departure", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });

  await page.getByRole("tab", { name: "Return" }).click();
  const departure = dayAfterTomorrowIso();
  await page.getByRole("textbox", { name: "Departure" }).fill(departure);
  await expect(page.getByRole("textbox", { name: "Return" })).toHaveAttribute("min", departure);
});

test("multi-city add and remove segments", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });

  await page.getByRole("tab", { name: "Multi-City" }).click();
  await expect(page.getByText("Flight 1")).toBeVisible();
  await expect(page.getByText("Flight 2")).toBeVisible();

  await page.getByRole("button", { name: "Add Flight" }).click();
  await expect(page.getByText("Flight 3")).toBeVisible();

  await page.getByRole("button", { name: "Remove Flight" }).first().click();
  await expect(page.getByText("Flight 3")).toBeHidden();
});

test("group ticketing tab renders Laravel search fields only", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });

  await page.getByRole("tab", { name: "Group Ticketing" }).click();
  await expect(page.getByRole("button", { name: "Search Group Fares" })).toBeVisible();
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
  await expect(page.getByRole("tab", { name: "One Way" })).toBeVisible();
  await expect(page.getByRole("tab", { name: "Group Ticketing" })).toBeVisible();
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
