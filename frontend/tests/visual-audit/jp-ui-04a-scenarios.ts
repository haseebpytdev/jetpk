import type { Page } from "@playwright/test";
import { resultsQuery } from "./jp-ui-01-fixtures";
import type { ThemeMode } from "./jp-ui-02-scenarios";

export type JpUi04aFamily = "results" | "fare" | "passengers" | "seats" | "review" | "payment" | "success";

export type JpUi04aScenario = {
  id: string;
  family: JpUi04aFamily;
  route: string;
  theme: ThemeMode;
  viewport: { name: string; width: number; height: number };
  zoom: number;
  state: string;
  fixtureId: string;
  fullPage?: boolean;
  waitForTestId?: string;
  forbiddenTestIds?: string[];
  expectedTestIds?: string[];
  action?: (page: Page) => Promise<void>;
};

type Viewport = { name: string; width: number; height: number };

const VP = {
  d1440: { name: "1440x900", width: 1440, height: 900 },
  d1280: { name: "1280x900", width: 1280, height: 900 },
  d1024: { name: "1024x900", width: 1024, height: 900 },
  t768: { name: "768x1024", width: 768, height: 1024 },
  m390: { name: "390x844", width: 390, height: 844 },
  m375: { name: "375x812", width: 375, height: 812 },
  m320: { name: "320x700", width: 320, height: 700 },
} as const;

const RESULTS_BASE = `/flights/results?${resultsQuery()}`;
const PASSENGERS_ROUTE =
  "/booking/passengers?search_id=audit-search&offer_id=audit-offer&from=LHE&to=DXB&depart=2026-08-15&adults=1";
const REVIEW_ROUTE = "/booking/review";
const PAYMENT_MANUAL_ROUTE = "/booking/payment/manual";
const PAYMENT_CARD_ROUTE = "/booking/payment/card";
const SUCCESS_ROUTE = "/booking/confirmation";

function s(
  family: JpUi04aFamily,
  id: string,
  route: string,
  theme: ThemeMode,
  viewport: Viewport,
  state: string,
  fixtureId: string,
  extra?: Partial<JpUi04aScenario>,
): JpUi04aScenario {
  return {
    id,
    family,
    route,
    theme,
    viewport,
    zoom: 1,
    state,
    fixtureId,
    fullPage: true,
    ...extra,
  };
}

function buildResultsBase(): JpUi04aScenario[] {
  const rows: Array<[string, ThemeMode, Viewport]> = [
    ["results-light-1440", "light", VP.d1440],
    ["results-dark-1440", "dark", VP.d1440],
    ["results-system-light-1440", "system-light", VP.d1440],
    ["results-system-dark-1440", "system-dark", VP.d1440],
    ["results-light-1280", "light", VP.d1280],
    ["results-dark-1280", "dark", VP.d1280],
    ["results-light-1024", "light", VP.d1024],
    ["results-dark-1024", "dark", VP.d1024],
    ["results-light-768", "light", VP.t768],
    ["results-dark-768", "dark", VP.t768],
    ["results-light-390", "light", VP.m390],
    ["results-dark-390", "dark", VP.m390],
    ["results-system-light-390", "system-light", VP.m390],
    ["results-system-dark-390", "system-dark", VP.m390],
    ["results-light-375", "light", VP.m375],
    ["results-dark-375", "dark", VP.m375],
    ["results-light-320", "light", VP.m320],
    ["results-dark-320", "dark", VP.m320],
  ];
  return rows.map(([id, theme, vp]) =>
    s("results", id, RESULTS_BASE, theme, vp, "base-layout", "results-present", {
      waitForTestId: "search-summary-bar",
    }),
  );
}

function buildResultsZoom(): JpUi04aScenario[] {
  return [
    { ...s("results", "results-light-125-zoom", RESULTS_BASE, "light", VP.d1280, "zoom-125", "results-present", { waitForTestId: "flight-result-card" }), zoom: 1.25 },
    { ...s("results", "results-dark-125-zoom", RESULTS_BASE, "dark", VP.d1280, "zoom-125", "results-present", { waitForTestId: "flight-result-card" }), zoom: 1.25 },
    { ...s("results", "results-light-150-zoom", RESULTS_BASE, "light", VP.d1280, "zoom-150", "results-present", { waitForTestId: "flight-result-card" }), zoom: 1.5 },
    { ...s("results", "results-dark-150-zoom", RESULTS_BASE, "dark", VP.d1280, "zoom-150", "results-present", { waitForTestId: "flight-result-card" }), zoom: 1.5 },
  ];
}

function buildResultsStates(): JpUi04aScenario[] {
  const q = (params: Record<string, string>) =>
    `/flights/results?${new URLSearchParams({ ...Object.fromEntries(new URLSearchParams(resultsQuery())), ...params }).toString()}`;

  return [
    s("results", "results-loading", RESULTS_BASE, "light", VP.d1440, "loading", "results-loading", {
      waitForTestId: "search-summary-bar",
    }),
    s("results", "results-present", RESULTS_BASE, "light", VP.d1440, "present", "results-present", {
      waitForTestId: "flight-result-card",
    }),
    s("results", "results-empty", RESULTS_BASE, "light", VP.d1440, "empty", "results-empty", {
      waitForTestId: "search-summary-bar",
    }),
    s("results", "results-partial-supplier-failure", RESULTS_BASE, "light", VP.d1440, "partial", "results-partial", {
      waitForTestId: "flight-result-card",
    }),
    s("results", "results-expired-search-session", RESULTS_BASE, "light", VP.d1440, "expired", "results-expired", {
      waitForTestId: "expired-search",
    }),
    s("results", "results-invalid-search", "/flights/results?trip_type=one_way", "light", VP.d1440, "invalid", "results-invalid", {
      waitForTestId: "search-summary-bar",
    }),
    s("results", "results-filter-drawer-open", RESULTS_BASE, "light", VP.m390, "filter-drawer", "results-present", {
      waitForTestId: "flight-result-card",
      action: async (page) => {
        await page.getByTestId("open-mobile-filters").click();
      },
    }),
    s("results", "results-sort-control-open", RESULTS_BASE, "light", VP.d1440, "sort-open", "results-present", {
      waitForTestId: "flight-result-card",
      action: async (page) => {
        await page.getByTestId("results-sort-tabs").getByRole("tab", { name: "Lowest Price" }).click();
      },
    }),
    s("results", "results-direct-only-active", q({ stops: "direct" }), "light", VP.d1440, "direct-only", "results-present", {
      waitForTestId: "search-summary-bar",
    }),
    s("results", "results-nearby-origin-active", q({ include_nearby: "1" }), "light", VP.d1440, "nearby-origin", "results-present", {
      waitForTestId: "search-summary-bar",
    }),
    s("results", "results-flexible-date-active", q({ flexible_dates: "1" }), "light", VP.d1440, "flexible-dates", "results-present", {
      waitForTestId: "search-summary-bar",
    }),
    s("results", "results-layover-popover-open", RESULTS_BASE, "light", VP.d1440, "layover-open", "results-one-stop", {
      waitForTestId: "flight-result-card",
      action: async (page) => {
        await page.getByRole("button", { name: /layover in/i }).first().click();
      },
    }),
    s("results", "results-branded-fare-preview", RESULTS_BASE, "light", VP.d1440, "branded", "results-branded", {
      waitForTestId: "branded-fare-carousel",
    }),
    s("results", "results-return-pair-view-or-honest-unavailable", q({ trip_type: "return", return: "2026-09-01" }), "light", VP.d1440, "pair-view", "results-return-split", {
      waitForTestId: "outbound-option-card",
    }),
    s("results", "results-multi-city-summary", q({ trip_type: "multi_city" }), "light", VP.d1440, "multi-city", "results-present", {
      waitForTestId: "search-summary-bar",
    }),
    s("results", "results-group-ticketing-separation", "/groups/search?sector=SKT-SHJ&date_from=2026-08-15", "light", VP.d1440, "group-separation", "groups-search", {
      waitForTestId: "group-result-card",
    }),
  ];
}

function openFareDrawer(): (page: Page) => Promise<void> {
  return async (page) => {
    await page.getByTestId("flight-details-trigger").first().click();
    await page.getByTestId("flight-details-drawer").waitFor({ state: "visible", timeout: 30_000 });
  };
}

function openFareDrawerAndContinue(): (page: Page) => Promise<void> {
  return async (page) => {
    await openFareDrawer()(page);
    await page.getByTestId("continue-to-passengers").click();
  };
}

function buildFareBase(): JpUi04aScenario[] {
  const fare = (id: string, theme: ThemeMode, vp: Viewport, state: string, fixtureId: string, extra?: Partial<JpUi04aScenario>) =>
    s("fare", id, RESULTS_BASE, theme, vp, state, fixtureId, {
      waitForTestId: "flight-result-card",
      action: openFareDrawer(),
      ...extra,
    });

  return [
    fare("fare-light-1440", "light", VP.d1440, "base", "fare-present"),
    fare("fare-dark-1440", "dark", VP.d1440, "base", "fare-present"),
    fare("fare-system-light-1440", "system-light", VP.d1440, "base", "fare-present"),
    fare("fare-system-dark-1440", "system-dark", VP.d1440, "base", "fare-present"),
    fare("fare-light-mobile", "light", VP.m390, "mobile", "fare-present"),
    fare("fare-dark-mobile", "dark", VP.m390, "mobile", "fare-present"),
    { ...fare("fare-light-150-zoom", "light", VP.d1280, "zoom-150", "fare-present"), zoom: 1.5 },
    { ...fare("fare-dark-150-zoom", "dark", VP.d1280, "zoom-150", "fare-present"), zoom: 1.5 },
    fare("fare-one-family", "light", VP.d1440, "one-family", "fare-one-family"),
    fare("fare-three-families", "light", VP.d1440, "three-families", "fare-three-families"),
    fare("fare-more-than-three-carousel", "light", VP.d1440, "carousel", "fare-four-families"),
    fare("fare-selected-state", "light", VP.d1440, "selected", "fare-three-families", {
      action: async (page) => {
        await openFareDrawer()(page);
        await page.getByTestId("fare-family-details").waitFor({ state: "visible" });
        await page.getByTestId("fare-family-details").getByRole("button", { name: /Economy Flex/i }).click();
      },
    }),
    fare("fare-revalidating", "light", VP.d1440, "revalidating", "fare-revalidating", {
      action: async (page) => {
        await openFareDrawerAndContinue()(page);
        await page.getByTestId("revalidation-status").waitFor({ state: "visible" });
      },
    }),
    fare("fare-price-changed", "light", VP.d1440, "price-changed", "fare-price-changed", {
      action: async (page) => {
        await openFareDrawerAndContinue()(page);
        await page.getByTestId("fare-change-dialog").waitFor({ state: "visible" });
      },
    }),
    fare("fare-unavailable", "light", VP.d1440, "unavailable", "fare-unavailable", {
      action: async (page) => {
        await openFareDrawerAndContinue()(page);
        await page.getByTestId("offer-unavailable-state").waitFor({ state: "visible" });
      },
    }),
    fare("fare-expired-session", "light", VP.d1440, "expired", "fare-expired", {
      action: async (page) => {
        await openFareDrawer()(page);
        await page.getByTestId("offer-expired-state").waitFor({ state: "visible" });
      },
    }),
  ];
}

function buildPassengersBase(): JpUi04aScenario[] {
  const pax = (id: string, theme: ThemeMode, vp: Viewport, state: string, fixtureId: string, extra?: Partial<JpUi04aScenario>) =>
    s("passengers", id, PASSENGERS_ROUTE, theme, vp, state, fixtureId, {
      waitForTestId: "standard-passengers-form",
      ...extra,
    });

  return [
    pax("passengers-light-1440", "light", VP.d1440, "base", "passengers-one-adult"),
    pax("passengers-dark-1440", "dark", VP.d1440, "base", "passengers-one-adult"),
    pax("passengers-system-light-1440", "system-light", VP.d1440, "base", "passengers-one-adult"),
    pax("passengers-system-dark-1440", "system-dark", VP.d1440, "base", "passengers-one-adult"),
    pax("passengers-light-mobile", "light", VP.m390, "mobile", "passengers-one-adult"),
    pax("passengers-dark-mobile", "dark", VP.m390, "mobile", "passengers-one-adult"),
    { ...pax("passengers-light-150-zoom", "light", VP.d1280, "zoom-150", "passengers-one-adult"), zoom: 1.5 },
    { ...pax("passengers-dark-150-zoom", "dark", VP.d1280, "zoom-150", "passengers-one-adult"), zoom: 1.5 },
    pax("passengers-one-adult", "light", VP.d1440, "one-adult", "passengers-one-adult"),
    pax("passengers-mixed-adult-child-infant", "light", VP.d1440, "mixed-types", "passengers-mixed"),
    pax("passengers-validation-errors", "light", VP.d1440, "validation-errors", "passengers-one-adult", {
      action: async (page) => {
        await page.getByTestId("save-and-continue").click();
      },
    }),
    pax("passengers-expired-session", "light", VP.d1440, "expired", "passengers-expired", {
      waitForTestId: "offer-expired",
    }),
    pax("passengers-save-failure", "light", VP.d1440, "save-failure", "passengers-save-failure", {
      action: async (page) => {
        await page.getByTestId("save-and-continue").click();
      },
    }),
    pax("passengers-order-summary-expanded", "light", VP.m390, "summary-expanded", "passengers-one-adult", {
      action: async (page) => {
        await page.getByTestId("mobile-order-summary").getByRole("button").click();
      },
    }),
    pax("passengers-order-summary-collapsed", "light", VP.m390, "summary-collapsed", "passengers-one-adult"),
  ];
}

function buildSeats(): JpUi04aScenario[] {
  const seat = (id: string, theme: ThemeMode, vp: Viewport) =>
    s("seats", id, PASSENGERS_ROUTE, theme, vp, "unsupported", "seats-unsupported", {
      waitForTestId: "booking-progress",
      forbiddenTestIds: ["seat-map", "seat-selection-page", "seat-map-canvas"],
    });

  return [
    seat("seats-unsupported-light-desktop", "light", VP.d1440),
    seat("seats-unsupported-dark-desktop", "dark", VP.d1440),
    seat("seats-unsupported-light-mobile", "light", VP.m390),
    seat("seats-unsupported-dark-mobile", "dark", VP.m390),
  ];
}

function buildReviewBase(): JpUi04aScenario[] {
  const rev = (id: string, theme: ThemeMode, vp: Viewport, state: string, fixtureId: string, extra?: Partial<JpUi04aScenario>) =>
    s("review", id, REVIEW_ROUTE, theme, vp, state, fixtureId, {
      waitForTestId: "booking-review-page",
      ...extra,
    });

  return [
    rev("review-light-1440", "light", VP.d1440, "base", "review-complete"),
    rev("review-dark-1440", "dark", VP.d1440, "base", "review-complete"),
    rev("review-system-light-1440", "system-light", VP.d1440, "base", "review-complete"),
    rev("review-system-dark-1440", "system-dark", VP.d1440, "base", "review-complete"),
    rev("review-light-mobile", "light", VP.m390, "mobile", "review-complete"),
    rev("review-dark-mobile", "dark", VP.m390, "mobile", "review-complete"),
    { ...rev("review-light-150-zoom", "light", VP.d1280, "zoom-150", "review-complete"), zoom: 1.5 },
    { ...rev("review-dark-150-zoom", "dark", VP.d1280, "zoom-150", "review-complete"), zoom: 1.5 },
    rev("review-complete", "light", VP.d1440, "complete", "review-complete"),
    rev("review-no-seats", "light", VP.d1440, "no-seats", "review-no-seats"),
    rev("review-consent-error", "light", VP.d1440, "consent-error", "review-blocked", {
      waitForTestId: "booking-review-page",
    }),
    rev("review-fare-change-alert", "light", VP.d1440, "fare-change", "review-fare-change", {
      waitForTestId: "fare-change-panel",
    }),
    rev("review-booking-submit-busy", "light", VP.d1440, "submit-busy", "review-submit-busy"),
    rev("review-booking-creation-failure", "light", VP.d1440, "creation-failure", "review-creation-failure"),
  ];
}

function buildPaymentBase(): JpUi04aScenario[] {
  const pay = (
    id: string,
    route: string,
    theme: ThemeMode,
    vp: Viewport,
    state: string,
    fixtureId: string,
    extra?: Partial<JpUi04aScenario>,
  ) =>
    s("payment", id, route, theme, vp, state, fixtureId, {
      waitForTestId: route.includes("/card") ? "card-payment-page" : "manual-payment-page",
      forbiddenTestIds: ["embedded-card-form"],
      ...extra,
    });

  return [
    pay("payment-light-1440", PAYMENT_MANUAL_ROUTE, "light", VP.d1440, "base", "payment-manual"),
    pay("payment-dark-1440", PAYMENT_MANUAL_ROUTE, "dark", VP.d1440, "base", "payment-manual"),
    pay("payment-system-light-1440", PAYMENT_MANUAL_ROUTE, "system-light", VP.d1440, "base", "payment-manual"),
    pay("payment-system-dark-1440", PAYMENT_MANUAL_ROUTE, "system-dark", VP.d1440, "base", "payment-manual"),
    pay("payment-light-mobile", PAYMENT_MANUAL_ROUTE, "light", VP.m390, "mobile", "payment-manual"),
    pay("payment-dark-mobile", PAYMENT_MANUAL_ROUTE, "dark", VP.m390, "mobile", "payment-manual"),
    { ...pay("payment-light-150-zoom", PAYMENT_MANUAL_ROUTE, "light", VP.d1280, "zoom-150", "payment-manual"), zoom: 1.5 },
    { ...pay("payment-dark-150-zoom", PAYMENT_MANUAL_ROUTE, "dark", VP.d1280, "zoom-150", "payment-manual"), zoom: 1.5 },
    pay("payment-manual-selected", PAYMENT_MANUAL_ROUTE, "light", VP.d1440, "manual", "payment-manual"),
    pay("payment-abhipay-selected", PAYMENT_CARD_ROUTE, "light", VP.d1440, "abhipay", "payment-abhipay"),
    pay("payment-initiating", PAYMENT_CARD_ROUTE, "light", VP.d1440, "initiating", "payment-initiating"),
    pay("payment-pending", PAYMENT_MANUAL_ROUTE, "light", VP.d1440, "pending", "payment-pending"),
    pay("payment-failed", PAYMENT_CARD_ROUTE, "light", VP.d1440, "failed", "payment-failed"),
    pay("payment-canceled", PAYMENT_CARD_ROUTE, "light", VP.d1440, "canceled", "payment-canceled"),
    pay("payment-provider-unavailable", PAYMENT_CARD_ROUTE, "light", VP.d1440, "provider-unavailable", "payment-provider-unavailable"),
    pay("payment-manual-pending", PAYMENT_MANUAL_ROUTE, "light", VP.d1440, "manual-pending", "payment-manual-pending"),
    pay("payment-expired-session", PAYMENT_MANUAL_ROUTE, "light", VP.d1440, "expired", "payment-expired", {
      waitForTestId: "missing-booking-session",
    }),
  ];
}

function buildSuccessBase(): JpUi04aScenario[] {
  const suc = (id: string, theme: ThemeMode, vp: Viewport, state: string, fixtureId: string, extra?: Partial<JpUi04aScenario>) =>
    s("success", id, SUCCESS_ROUTE, theme, vp, state, fixtureId, {
      waitForTestId: "booking-confirmation-page",
      ...extra,
    });

  return [
    suc("success-light-1440", "light", VP.d1440, "base", "success-confirmed"),
    suc("success-dark-1440", "dark", VP.d1440, "base", "success-confirmed"),
    suc("success-system-light-1440", "system-light", VP.d1440, "base", "success-confirmed"),
    suc("success-system-dark-1440", "system-dark", VP.d1440, "base", "success-confirmed"),
    suc("success-light-mobile", "light", VP.m390, "mobile", "success-confirmed"),
    suc("success-dark-mobile", "dark", VP.m390, "mobile", "success-confirmed"),
    { ...suc("success-light-150-zoom", "light", VP.d1280, "zoom-150", "success-confirmed"), zoom: 1.5 },
    { ...suc("success-dark-150-zoom", "dark", VP.d1280, "zoom-150", "success-confirmed"), zoom: 1.5 },
    suc("success-booking-confirmed", "light", VP.d1440, "confirmed", "success-confirmed"),
    suc("success-payment-pending", "light", VP.d1440, "payment-pending", "success-payment-pending"),
    suc("success-pnr-pending", "light", VP.d1440, "pnr-pending", "success-pnr-pending"),
    suc("success-ticketing-pending", "light", VP.d1440, "ticketing-pending", "success-ticketing-pending"),
    suc("success-ticketed", "light", VP.d1440, "ticketed", "success-ticketed"),
    suc("success-no-invoice-action", "light", VP.d1440, "no-invoice", "success-no-invoice", {
      forbiddenTestIds: ["invoice-download-action"],
    }),
    suc("success-booking-not-found", "light", VP.d1440, "not-found", "success-not-found", {
      waitForTestId: "missing-booking-session",
    }),
    suc("success-unauthorized", "light", VP.d1440, "unauthorized", "success-unauthorized", {
      waitForTestId: "missing-booking-session",
    }),
  ];
}

export const JP_UI_04A_SCENARIOS: JpUi04aScenario[] = [
  ...buildResultsBase(),
  ...buildResultsZoom(),
  ...buildResultsStates(),
  ...buildFareBase(),
  ...buildPassengersBase(),
  ...buildSeats(),
  ...buildReviewBase(),
  ...buildPaymentBase(),
  ...buildSuccessBase(),
];

export const EXPECTED_SCENARIO_COUNT = 120;

const FAMILY_COUNTS: Record<JpUi04aFamily, number> = {
  results: 38,
  fare: 16,
  passengers: 15,
  seats: 4,
  review: 14,
  payment: 17,
  success: 16,
};

if (JP_UI_04A_SCENARIOS.length !== EXPECTED_SCENARIO_COUNT) {
  throw new Error(`JP-UI-04A registry count mismatch: expected ${EXPECTED_SCENARIO_COUNT}, got ${JP_UI_04A_SCENARIOS.length}`);
}

const ids = JP_UI_04A_SCENARIOS.map((scenario) => scenario.id);
if (new Set(ids).size !== ids.length) {
  throw new Error("JP-UI-04A duplicate scenario ids detected");
}

for (const [family, expected] of Object.entries(FAMILY_COUNTS)) {
  const actual = JP_UI_04A_SCENARIOS.filter((scenario) => scenario.family === family).length;
  if (actual !== expected) {
    throw new Error(`JP-UI-04A ${family} count mismatch: expected ${expected}, got ${actual}`);
  }
}
