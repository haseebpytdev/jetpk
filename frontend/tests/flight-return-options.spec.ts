import { expect, test } from "@playwright/test";
import { absoluteLaravelHandoffUrl } from "../features/flight-results/services/flight-results-api";
import { isAllowedInternalHandoffUrl } from "../features/flight-details/utils/handoff";
import { resolveNearbyDateResultsPath } from "../features/flight-results/utils/nearby-dates";

const MOCK_SEARCH_ID = "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee";
const OUTBOUND_KEY = "outbound-key-1";

function mockReturnOptionsBody() {
  return {
    flow: "return_split_return",
    search_id: MOCK_SEARCH_ID,
    outbound_key: OUTBOUND_KEY,
    return_options: [
      {
        combo_id: "combo-1",
        fare_option_key: "fare-1",
        displayed_price: 250000,
        return_journey_display: {
          departure_time_display: "14:00",
          arrival_time_display: "18:30",
        },
      },
    ],
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
  };
}

test.describe("JP-FULLSTACK-01B return-options handoff", () => {
  test("loads return options from Laravel data endpoint", async ({ page }) => {
    await page.route("**/laravel/flights/return-options/data*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(mockReturnOptionsBody()),
      });
    });

    await page.goto(`/flights/return-options?search_id=${MOCK_SEARCH_ID}&outbound_key=${OUTBOUND_KEY}`);
    await expect(page.getByRole("heading", { name: /choose return flight/i })).toBeVisible();
    await expect(page.getByRole("list", { name: "Return flight options" })).toBeVisible();
    await expect(page.getByTestId("result-price-button")).toBeVisible();
  });

  test("select return combo submits authoritative Laravel form POST", async ({ page }) => {
    await page.route("**/laravel/api/public/content/csrf-token", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ csrf_token: "test-csrf-token" }),
        headers: { "set-cookie": "XSRF-TOKEN=test-csrf-token; Path=/" },
      });
    });

    await page.route("**/laravel/flights/return-options/data*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(mockReturnOptionsBody()),
      });
    });

    let postedForm: { action: string; fields: Record<string, string> } | null = null;
    await page.route("**/flights/select-return-combo", async (route) => {
      const request = route.request();
      postedForm = {
        action: request.url(),
        fields: Object.fromEntries(
          request.postData()
            ?.split("&")
            .map((pair) => pair.split("="))
            .map(([key, value]) => [decodeURIComponent(key), decodeURIComponent(value ?? "")]) ?? [],
        ),
      };
      await route.fulfill({
        status: 200,
        contentType: "text/html",
        body: "<html><body>JetPakistan handoff</body></html>",
      });
    });

    await page.goto(`/flights/return-options?search_id=${MOCK_SEARCH_ID}&outbound_key=${OUTBOUND_KEY}`);
    await page.getByTestId("result-price-button").click();

    await expect.poll(() => postedForm).not.toBeNull();
    expect(postedForm!.action).toContain("/flights/select-return-combo");
    expect(postedForm!.fields.search_id).toBe(MOCK_SEARCH_ID);
    expect(postedForm!.fields.combo_id).toBe("combo-1");
    expect(postedForm!.fields.outbound_key).toBe(OUTBOUND_KEY);
    expect(postedForm!.fields._token).toBe("test-csrf-token");
  });

  test("handoff allowlist permits return-combo and inquiry paths", () => {
    expect(isAllowedInternalHandoffUrl("/flights/select-return-combo")).toBe(true);
    expect(isAllowedInternalHandoffUrl("/flights/multicity/inquiry")).toBe(true);
    expect(absoluteLaravelHandoffUrl("/flights/select-return-combo")).toContain("/flights/select-return-combo");
    const nextPath = resolveNearbyDateResultsPath(
      "/flights/results?from=ISB&to=DXB&depart=2026-08-10&trip_type=one_way&adults=1&children=0&infants=0&cabin=economy",
    );
    expect(nextPath).toContain("/flights/results");
    expect(isAllowedInternalHandoffUrl("https://evil.example/phish")).toBe(false);
  });
});
