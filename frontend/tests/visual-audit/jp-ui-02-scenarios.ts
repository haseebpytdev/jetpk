/**
 * JP-UI-02 foundation visual audit scenarios (theme + shell).
 */

export type ThemeMode = "light" | "dark" | "system-light" | "system-dark";

export type JpUi02Scenario = {
  id: string;
  route: string;
  pageName: string;
  theme: ThemeMode;
  fullPage: boolean;
  waitForTestId?: string;
  setup?: "public" | "results" | "passengers" | "auth" | "customer" | "agent";
};

export const JP_UI_02_VIEWPORTS = [
  { name: "desktop-1440", width: 1440, height: 1200 },
  { name: "desktop-1024", width: 1024, height: 900 },
  { name: "mobile-390", width: 390, height: 844 },
  { name: "mobile-320", width: 320, height: 700 },
] as const;

export const JP_UI_02_ZOOM_VIEWPORTS = [
  { name: "desktop-1280-zoom-150", width: 1280, height: 900, zoom: 1.5 },
] as const;

export const JP_UI_02_SCENARIOS: JpUi02Scenario[] = [
  { id: "homepage", route: "/", pageName: "Homepage", theme: "light", fullPage: true, waitForTestId: "search-module", setup: "public" },
  { id: "homepage", route: "/", pageName: "Homepage", theme: "dark", fullPage: true, waitForTestId: "search-module", setup: "public" },
  { id: "about-us", route: "/about-us", pageName: "About", theme: "light", fullPage: true, setup: "public" },
  { id: "about-us", route: "/about-us", pageName: "About", theme: "dark", fullPage: true, setup: "public" },
  { id: "support", route: "/support", pageName: "Support", theme: "light", fullPage: true, setup: "public" },
  { id: "login", route: "/login", pageName: "Login", theme: "light", fullPage: true, setup: "auth" },
  { id: "login", route: "/login", pageName: "Login", theme: "dark", fullPage: true, setup: "auth" },
  { id: "flight-results", route: "/flights/results", pageName: "Results", theme: "light", fullPage: true, setup: "results" },
  { id: "passengers", route: "/booking/passengers", pageName: "Passengers", theme: "light", fullPage: true, setup: "passengers" },
  { id: "customer-dashboard", route: "/customer/dashboard", pageName: "Customer", theme: "light", fullPage: true, setup: "customer" },
  { id: "agent-dashboard", route: "/agent/dashboard", pageName: "Agent", theme: "dark", fullPage: true, setup: "agent" },
  { id: "not-found", route: "/this-route-does-not-exist-jp-ui-02", pageName: "404", theme: "light", fullPage: false },
];

export function themeStorageValue(mode: ThemeMode): { preference: string; emulateDark: boolean } {
  switch (mode) {
    case "light":
      return { preference: "light", emulateDark: false };
    case "dark":
      return { preference: "dark", emulateDark: true };
    case "system-light":
      return { preference: "system", emulateDark: false };
    case "system-dark":
      return { preference: "system", emulateDark: true };
  }
}
