import type { Page } from "@playwright/test";
import type { ThemeMode } from "./jp-ui-02-scenarios";
import { BLUEPRINT_FAMILIES } from "./jp-ui-06-references";

export type JpUi06Scenario = {
  id: string;
  family: string;
  route: string;
  theme: ThemeMode;
  viewport: { name: string; width: number; height: number };
  zoom: number;
  state: string;
  fixtureId: string;
  comparisonMode: string;
  wave: 1 | 2 | 3;
  fullPage?: boolean;
  waitForTestId?: string;
  action?: (page: Page) => Promise<void>;
};

const CANONICAL_DESKTOP = { name: "1122x1330", width: 1122, height: 1330 };
const MOBILE = { name: "390x844", width: 390, height: 844 };
const ZOOM_BASE = { name: "1122x1330", width: 1122, height: 1330 };

const WAIT_IDS: Record<string, string> = {
  homepage: "homepage-content",
  about: "about-page",
  support: "support-page",
  "flight-results": "flight-results-page",
  "fare-selection": "fare-selection-page",
  "passenger-details": "passenger-details-page",
  "seat-selection-capability-unavailable": "seat-extras-readiness",
  review: "booking-review-page",
  payment: "payment-page",
  "booking-success": "booking-confirmation-page",
  login: "auth-page-shell",
  signup: "auth-page-shell",
  "manage-booking": "booking-lookup-page",
};

function buildFamilyScenarios(family: (typeof BLUEPRINT_FAMILIES)[number]): JpUi06Scenario[] {
  const waitForTestId = WAIT_IDS[family.id];
  const base = {
    family: family.id,
    route: family.route,
    fixtureId: family.fixtureId,
    comparisonMode: family.comparisonMode,
    wave: family.wave,
    fullPage: true,
    waitForTestId,
  };

  const scenarios: JpUi06Scenario[] = [
    {
      id: `${family.id}-canonical-light-desktop`,
      ...base,
      theme: "light",
      viewport: CANONICAL_DESKTOP,
      zoom: 1,
      state: "canonical-light-desktop",
    },
    {
      id: `${family.id}-dark-desktop`,
      ...base,
      theme: "dark",
      viewport: CANONICAL_DESKTOP,
      zoom: 1,
      state: "dark-desktop",
    },
    {
      id: `${family.id}-mobile-light`,
      ...base,
      theme: "light",
      viewport: MOBILE,
      zoom: 1,
      state: "mobile-light",
    },
    {
      id: `${family.id}-mobile-dark`,
      ...base,
      theme: "dark",
      viewport: MOBILE,
      zoom: 1,
      state: "mobile-dark",
    },
    {
      id: `${family.id}-zoom-150-light`,
      ...base,
      theme: "light",
      viewport: ZOOM_BASE,
      zoom: 1.5,
      state: "zoom-150-light",
    },
  ];

  if (family.id === "seat-selection-capability-unavailable") {
    scenarios.forEach((s) => {
      s.action = async (page) => {
        await page.getByTestId("seat-extras-readiness").waitFor({ state: "visible", timeout: 10_000 });
      };
    });
  }

  return scenarios;
}

export const JP_UI_06_SCENARIOS: JpUi06Scenario[] = BLUEPRINT_FAMILIES.flatMap(buildFamilyScenarios);
export const JP_UI_06_WAVE_1_SCENARIOS = JP_UI_06_SCENARIOS.filter((s) => s.wave === 1);
export const EXPECTED_SCENARIO_COUNT = JP_UI_06_SCENARIOS.length;
export const WAVE_1_SCENARIO_COUNT = JP_UI_06_WAVE_1_SCENARIOS.length;
