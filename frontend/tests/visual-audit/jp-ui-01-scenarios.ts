/**
 * JP-UI-01 visual audit route and viewport scenarios.
 * Screenshots are written to frontend/.visual-audit/jp-ui-01/ (gitignored).
 */

export type VisualAuditScenario = {
  id: string;
  route: string;
  pageName: string;
  mockupKey: string | null;
  fullPage: boolean;
  waitForTestId?: string;
  zoom?: number;
  setup?: "public" | "results" | "results-branded" | "passengers" | "review" | "payment" | "confirmation" | "lookup" | "auth";
};

export type ViewportSpec = {
  name: string;
  width: number;
  height: number;
  isMobile: boolean;
};

export const JP_UI_01_VIEWPORTS: ViewportSpec[] = [
  { name: "desktop-1440", width: 1440, height: 1200, isMobile: false },
  { name: "desktop-1280", width: 1280, height: 900, isMobile: false },
  { name: "desktop-1024", width: 1024, height: 900, isMobile: false },
  { name: "mobile-390", width: 390, height: 844, isMobile: true },
  { name: "mobile-375", width: 375, height: 812, isMobile: true },
  { name: "mobile-320", width: 320, height: 700, isMobile: true },
];

export const JP_UI_01_ZOOM_VIEWPORTS: ViewportSpec[] = [
  { name: "desktop-1280-zoom-125", width: 1280, height: 900, isMobile: false },
  { name: "desktop-1280-zoom-150", width: 1280, height: 900, isMobile: false },
];

export const JP_UI_01_SCENARIOS: VisualAuditScenario[] = [
  {
    id: "homepage",
    route: "/",
    pageName: "Homepage",
    mockupKey: "homepage",
    fullPage: true,
    waitForTestId: "search-module",
    setup: "public",
  },
  {
    id: "about-us",
    route: "/about-us",
    pageName: "About JetPakistan",
    mockupKey: "about",
    fullPage: true,
    setup: "public",
  },
  {
    id: "support",
    route: "/support",
    pageName: "Public Support",
    mockupKey: "support",
    fullPage: true,
    setup: "public",
  },
  {
    id: "login",
    route: "/login",
    pageName: "Login",
    mockupKey: "login",
    fullPage: true,
    setup: "auth",
  },
  {
    id: "register",
    route: "/register",
    pageName: "Sign up",
    mockupKey: "signup",
    fullPage: true,
    setup: "auth",
  },
  {
    id: "flight-results",
    route: "/flights/results",
    pageName: "Flight search results",
    mockupKey: "results",
    fullPage: true,
    waitForTestId: "flight-result-card",
    setup: "results",
  },
  {
    id: "fare-selection",
    route: "/flights/results",
    pageName: "Fare selection (inline on results)",
    mockupKey: "fare-selection",
    fullPage: false,
    waitForTestId: "flight-result-card",
    setup: "results-branded",
  },
  {
    id: "passengers",
    route: "/booking/passengers?search_id=audit-search&offer_id=audit-offer&from=LHE&to=DXB&depart=2026-08-15&adults=1",
    pageName: "Passenger details",
    mockupKey: "passengers",
    fullPage: true,
    waitForTestId: "standard-passengers-form",
    setup: "passengers",
  },
  {
    id: "review",
    route: "/booking/review",
    pageName: "Review and confirm",
    mockupKey: "review",
    fullPage: true,
    waitForTestId: "booking-review-page",
    setup: "review",
  },
  {
    id: "payment-manual",
    route: "/booking/payment/manual",
    pageName: "Payment (manual)",
    mockupKey: "payment",
    fullPage: true,
    waitForTestId: "manual-payment-page",
    setup: "payment",
  },
  {
    id: "confirmation",
    route: "/booking/confirmation",
    pageName: "Booking success",
    mockupKey: "success",
    fullPage: true,
    waitForTestId: "booking-confirmation-page",
    setup: "confirmation",
  },
  {
    id: "lookup-booking",
    route: "/lookup-booking",
    pageName: "Manage booking lookup",
    mockupKey: "manage-booking",
    fullPage: true,
    waitForTestId: "booking-lookup-page",
    setup: "lookup",
  },
];

/** Seat selection has no operational route; documented only in audit matrix. */
export const JP_UI_01_UNSUPPORTED_SCENARIOS = [
  {
    id: "seat-selection",
    pageName: "Seat selection",
    mockupKey: "seat-selection",
    reason: "seat_map_available=false; no Next.js route; conditional future capability",
  },
] as const;
