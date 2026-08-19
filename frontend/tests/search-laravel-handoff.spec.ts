import { test, expect } from "@playwright/test";

function tomorrowIso(): string {
  const date = new Date();
  date.setDate(date.getDate() + 1);
  return date.toISOString().slice(0, 10);
}

test("client validation keeps the user on home when departure is missing", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  await page.getByRole("tab", { name: "One Way" }).click();
  await page.getByRole("button", { name: "Search Flights" }).click();
  await expect(page).toHaveURL(/\/$/);
  await expect(page.getByTestId("search-module").getByRole("status")).toBeVisible();
});

test("valid search navigates to results immediately", async ({ page }) => {
  await page.route("**/laravel/flights/results/search**", async (route) => {
    await new Promise((resolve) => setTimeout(resolve, 1500));
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        search_id: "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee",
        results_page_url: "/flights/results",
        initial_results_url: "/flights/results/data",
      }),
    });
  });
  await page.route("**/laravel/flights/results/data**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        search_id: "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee",
        page: 1,
        per_page: 12,
        total: 0,
        offers: [],
        has_more: false,
      }),
    });
  });

  await page.goto("/", { waitUntil: "load" });
  await page.getByRole("tab", { name: "One Way" }).click();
  await page.getByLabel("Departure").fill(tomorrowIso());
  const start = Date.now();
  await page.getByRole("button", { name: "Search Flights" }).click();
  await page.waitForURL("**/flights/results**");
  expect(Date.now() - start).toBeLessThan(1500);
  await expect(page.getByTestId("search-summary-bar")).toBeVisible();
});
