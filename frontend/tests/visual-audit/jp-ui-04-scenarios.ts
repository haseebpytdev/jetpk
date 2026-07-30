import type { Page } from "@playwright/test";
import { resultsQuery } from "./jp-ui-01-fixtures";
import type { ThemeMode } from "./jp-ui-02-scenarios";

export type JpUi04Scenario = {
  id: string;
  family: string;
  route: string;
  theme: ThemeMode;
  viewport: { name: string; width: number; height: number };
  zoom: number;
  state: string;
  fullPage?: boolean;
  waitForTestId?: string;
  forbiddenTestIds?: string[];
  setup?: (page: Page) => Promise<void>;
  action?: (page: Page) => Promise<void>;
};

const VP = {
  d1440: { name: "1440x900", width: 1440, height: 900 },
  d1280: { name: "1280x900", width: 1280, height: 900 },
  d1024: { name: "1024x900", width: 1024, height: 900 },
  m390: { name: "390x844", width: 390, height: 844 },
  m320: { name: "320x700", width: 320, height: 700 },
} as const;

function scenario(
  family: string,
  id: string,
  route: string,
  theme: ThemeMode,
  viewport: (typeof VP)[keyof typeof VP],
  state: string,
  extra?: Partial<JpUi04Scenario>,
): JpUi04Scenario {
  return {
    id,
    family,
    route,
    theme,
    viewport,
    zoom: 1,
    state,
    fullPage: true,
    ...extra,
  };
}

const resultsRoute = `/flights/results?${resultsQuery()}`;

const passengersRoute = "/booking/passengers?search_id=audit-search&offer_id=audit-offer&from=LHE&to=DXB&depart=2026-08-15&adults=1";

export const JP_UI_04_SCENARIOS: JpUi04Scenario[] = [
  // Results
  scenario("results", "res-01", resultsRoute, "light", VP.d1440, "results-present", { waitForTestId: "flight-result-card" }),
  scenario("results", "res-02", resultsRoute, "dark", VP.d1440, "results-present", { waitForTestId: "flight-result-card" }),
  scenario("results", "res-03", resultsRoute, "light", VP.m390, "results-present", { waitForTestId: "flight-result-card" }),
  scenario("results", "res-04", resultsRoute, "dark", VP.m390, "results-present", { waitForTestId: "flight-result-card" }),
  scenario("results", "res-05", resultsRoute, "light", VP.d1024, "tablet", { waitForTestId: "flight-result-card" }),
  scenario("results", "res-06", resultsRoute, "light", VP.d1280, "zoom-150", { waitForTestId: "flight-result-card", zoom: 1.5 }),
  scenario("results", "res-07", resultsRoute, "light", VP.d1440, "loading", { waitForTestId: "search-summary-bar", state: "loading" }),
  scenario("results", "res-08", resultsRoute, "light", VP.d1440, "no-results", { state: "empty" }),
  scenario("results", "res-09", resultsRoute, "light", VP.d1440, "partial-failure", { state: "partial" }),
  scenario("results", "res-10", resultsRoute, "light", VP.m390, "filter-drawer", {
    waitForTestId: "flight-result-card",
    action: async (page) => {
      await page.getByTestId("open-mobile-filters").click();
    },
  }),
  scenario("results", "res-11", resultsRoute, "light", VP.d1440, "branded-fare", { state: "branded", waitForTestId: "branded-fare-carousel" }),
  scenario("results", "res-12", resultsRoute, "light", VP.d1440, "expired", { state: "expired" }),

  // Passengers
  scenario("passengers", "pax-01", passengersRoute, "light", VP.d1440, "form", { waitForTestId: "standard-passengers-form" }),
  scenario("passengers", "pax-02", passengersRoute, "dark", VP.d1440, "form", { waitForTestId: "standard-passengers-form" }),
  scenario("passengers", "pax-03", passengersRoute, "light", VP.m390, "form", { waitForTestId: "standard-passengers-form" }),
  scenario("passengers", "pax-04", passengersRoute, "light", VP.d1280, "zoom-150", { waitForTestId: "standard-passengers-form", zoom: 1.5 }),

  // Review
  scenario("review", "rev-01", "/booking/review", "light", VP.d1440, "complete", { waitForTestId: "booking-review-page" }),
  scenario("review", "rev-02", "/booking/review", "dark", VP.d1440, "complete", { waitForTestId: "booking-review-page" }),
  scenario("review", "rev-03", "/booking/review", "light", VP.m390, "complete", { waitForTestId: "booking-review-page" }),

  // Payment
  scenario("payment", "pay-01", "/booking/payment/manual", "light", VP.d1440, "manual", { waitForTestId: "manual-payment-page" }),
  scenario("payment", "pay-02", "/booking/payment/manual", "dark", VP.d1440, "manual", { waitForTestId: "manual-payment-page" }),
  scenario("payment", "pay-03", "/booking/payment/card", "light", VP.d1440, "abhipay", { waitForTestId: "card-payment-page", forbiddenTestIds: ["embedded-card-form"] }),
  scenario("payment", "pay-04", "/booking/payment/manual", "light", VP.m390, "manual", { waitForTestId: "manual-payment-page" }),

  // Success
  scenario("success", "suc-01", "/booking/confirmation", "light", VP.d1440, "confirmed", { waitForTestId: "booking-confirmation-page" }),
  scenario("success", "suc-02", "/booking/confirmation", "dark", VP.d1440, "confirmed", { waitForTestId: "booking-confirmation-page" }),
  scenario("success", "suc-03", "/booking/confirmation", "light", VP.m390, "confirmed", { waitForTestId: "booking-confirmation-page" }),
  scenario("success", "suc-04", "/booking/confirmation", "light", VP.d1280, "zoom-150", { waitForTestId: "booking-confirmation-page", zoom: 1.5 }),

  // Shared progress (passengers page shows progress)
  scenario("shared", "prog-01", passengersRoute, "light", VP.d1440, "progress", { waitForTestId: "booking-progress" }),
];

export const EXPECTED_SCENARIO_COUNT = JP_UI_04_SCENARIOS.length;
