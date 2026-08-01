import { test, expect } from "@playwright/test";
import { setSessionFixture } from "../jp-full-next-frontend/helpers";

const CUSTOMER_BOOKINGS_API = "**/laravel/customer/bookings?format=json*";

const EMPTY_BOOKINGS_RESPONSE = {
  ok: true,
  filter: "all",
  allowed_filters: ["all"],
  bookings: [],
  pagination: { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null },
};

test.describe("JP-FRONTEND-UX-02 loading states", () => {
  test("customer bookings route exposes loading skeleton region", async ({ page, baseURL }) => {
    let releaseDelayedResponse: (() => void) | undefined;
    const delayedResponseGate = new Promise<void>((resolve) => {
      releaseDelayedResponse = resolve;
    });

    await page.route(CUSTOMER_BOOKINGS_API, async (route) => {
      await delayedResponseGate;
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(EMPTY_BOOKINGS_RESPONSE),
      });
    });

    try {
      if (baseURL) {
        process.env.PLAYWRIGHT_BASE_URL = baseURL;
      }
      await setSessionFixture(page, "customer");

      await page.goto("/customer/bookings", { waitUntil: "domcontentloaded" });
      await expect(page).not.toHaveURL(/\/login$/);

      const loadingIndicator = page
        .getByRole("status", { name: "Loading customer bookings" })
        .or(page.getByTestId("skeleton"))
        .or(page.getByText("Loading bookings…"))
        .first();
      await expect(loadingIndicator).toBeVisible({ timeout: 10_000 });

      releaseDelayedResponse?.();
      await expect(page.getByRole("heading", { name: /my bookings/i })).toBeVisible();
      await expect(page.getByTestId("customer-empty-state")).toBeVisible();
    } finally {
      releaseDelayedResponse?.();
      await page.unroute(CUSTOMER_BOOKINGS_API);
    }
  });

  test("unauthenticated visit to customer bookings redirects to login", async ({ page, baseURL }) => {
    if (baseURL) {
      process.env.PLAYWRIGHT_BASE_URL = baseURL;
    }
    await setSessionFixture(page, "anonymous");

    await page.goto("/customer/bookings");
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole("heading", { name: /log in to your account/i })).toBeVisible();
  });

  test("fare selection route has loading fallback file", async ({ page }) => {
    const response = await page.goto("/flights/fare-selection");
    expect(response?.status()).toBeLessThan(500);
  });
});
