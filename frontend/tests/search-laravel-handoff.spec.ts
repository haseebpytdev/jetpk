import { test, expect } from "@playwright/test";

function tomorrowIso(): string {
  const date = new Date();
  date.setDate(date.getDate() + 1);
  return date.toISOString().slice(0, 10);
}

test("Laravel validation errors render on the homepage search form", async ({ page }) => {
  await page.route("**/laravel/flights/results/search**", async (route) => {
    await route.fulfill({
      status: 422,
      contentType: "application/json",
      body: JSON.stringify({
        message: "The given data was invalid.",
        errors: { depart: ["The depart field is required."] },
      }),
    });
  });

  await page.goto("/", { waitUntil: "load" });
  await page.getByRole("tab", { name: "One Way" }).click();
  const searchModule = page.getByTestId("search-module");
  await searchModule.getByRole("textbox", { name: "Departure" }).filter({ visible: true }).fill(tomorrowIso());
  await searchModule.getByRole("button", { name: "Search Flights" }).click();

  await expect(page.getByTestId("search-submit-status")).toContainText(/invalid/i);
  await expect(page.getByTestId("search-module").getByRole("status")).toContainText(/depart field is required/i);
});
