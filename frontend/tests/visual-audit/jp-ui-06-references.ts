/**
 * JP-UI-06 canonical reference registry.
 * Mockup files are read-only from Backup Safe; normalized crops live in .visual-audit/jp-ui-06/reference/.
 */

export type ComparisonMode =
  | "exact"
  | "exact_with_operational_substitution"
  | "capability_exception";

export type BlueprintFamily = {
  id: string;
  wave: 1 | 2 | 3;
  route: string;
  mockupFilename: string;
  comparisonMode: ComparisonMode;
  exceptions: string[];
  fixtureId: string;
  viewportDesktop: { width: number; height: number };
};

const BACKUP_SAFE = "C:\\Users\\khadi\\Backup Safe";

export const JP_UI_06_EXPECTED_CAPTURE_COUNT = 65;
export const JP_UI_06_CANONICAL_DESKTOP_COUNT = 13;
export const JP_UI_06_DEFAULT_PORT = 3002;

export const BLUEPRINT_FAMILIES: BlueprintFamily[] = [
  {
    id: "homepage",
    wave: 1,
    route: "/",
    mockupFilename: "ChatGPT Image Jul 27, 2026, 05_14_42 PM (1).png",
    comparisonMode: "exact",
    exceptions: ["D", "E"],
    fixtureId: "homepage",
    viewportDesktop: { width: 1122, height: 1330 },
  },
  {
    id: "about",
    wave: 1,
    route: "/about-us",
    mockupFilename: "ChatGPT Image Jul 27, 2026, 05_14_44 PM (2).png",
    comparisonMode: "exact",
    exceptions: ["D", "E"],
    fixtureId: "about",
    viewportDesktop: { width: 1122, height: 1330 },
  },
  {
    id: "support",
    wave: 1,
    route: "/support",
    mockupFilename: "ChatGPT Image Jul 27, 2026, 05_14_45 PM (3).png",
    comparisonMode: "exact",
    exceptions: ["D", "E"],
    fixtureId: "support",
    viewportDesktop: { width: 1122, height: 1330 },
  },
  {
    id: "flight-results",
    wave: 2,
    route: "/flights/results",
    mockupFilename: "520bfb29-bc9c-432c-88f1-b53cdadb1592.png",
    comparisonMode: "exact",
    exceptions: ["D", "E"],
    fixtureId: "flight-results",
    viewportDesktop: { width: 1122, height: 1330 },
  },
  {
    id: "fare-selection",
    wave: 2,
    route: "/flights/fare-selection",
    mockupFilename: "6ea78679-e345-49ea-a4be-2e2f539940c6.png",
    comparisonMode: "exact_with_operational_substitution",
    exceptions: ["A", "D", "E"],
    fixtureId: "fare-selection",
    viewportDesktop: { width: 1122, height: 1330 },
  },
  {
    id: "passenger-details",
    wave: 2,
    route: "/booking/passengers",
    mockupFilename: "ChatGPT Image Jul 27, 2026, 05_14_46 PM (4).png",
    comparisonMode: "exact",
    exceptions: ["D", "E"],
    fixtureId: "passenger-details",
    viewportDesktop: { width: 1122, height: 1330 },
  },
  {
    id: "seat-selection-capability-unavailable",
    wave: 2,
    route: "/booking/passengers",
    mockupFilename: "45f39a0b-e38f-4ad2-9077-f631217bd185.png",
    comparisonMode: "capability_exception",
    exceptions: ["B"],
    fixtureId: "seat-capability",
    viewportDesktop: { width: 1122, height: 1330 },
  },
  {
    id: "review",
    wave: 2,
    route: "/booking/review",
    mockupFilename: "64460b63-9930-478c-96cb-e7a00345caea.png",
    comparisonMode: "exact",
    exceptions: ["D", "E"],
    fixtureId: "review",
    viewportDesktop: { width: 1122, height: 1330 },
  },
  {
    id: "payment",
    wave: 2,
    route: "/booking/payment",
    mockupFilename: "ab903350-d59f-4b60-b254-9350e4da8f00.png",
    comparisonMode: "exact_with_operational_substitution",
    exceptions: ["C", "D", "E"],
    fixtureId: "payment",
    viewportDesktop: { width: 1122, height: 1330 },
  },
  {
    id: "booking-success",
    wave: 2,
    route: "/booking/confirmation",
    mockupFilename: "ChatGPT Image Jul 27, 2026, 05_14_46 PM (5).png",
    comparisonMode: "exact",
    exceptions: ["D", "E"],
    fixtureId: "booking-success",
    viewportDesktop: { width: 1122, height: 1330 },
  },
  {
    id: "login",
    wave: 3,
    route: "/login",
    mockupFilename: "542ee36d-c542-4eec-b5d4-995d555f8ba6.png",
    comparisonMode: "exact",
    exceptions: ["D", "E"],
    fixtureId: "login",
    viewportDesktop: { width: 1122, height: 1330 },
  },
  {
    id: "signup",
    wave: 3,
    route: "/register",
    mockupFilename: "0896e3e1-8c0f-45f2-a3ac-561cd50e3f7a.png",
    comparisonMode: "exact",
    exceptions: ["D", "E"],
    fixtureId: "signup",
    viewportDesktop: { width: 1122, height: 1330 },
  },
  {
    id: "manage-booking",
    wave: 3,
    route: "/lookup-booking",
    mockupFilename: "678318b0-28f6-4588-ad03-f405f361152e.png",
    comparisonMode: "exact",
    exceptions: ["D", "E"],
    fixtureId: "manage-booking",
    viewportDesktop: { width: 1122, height: 1330 },
  },
];

export function mockupAbsolutePath(filename: string): string {
  return `${BACKUP_SAFE}\\${filename}`;
}

export function familyById(id: string): BlueprintFamily | undefined {
  return BLUEPRINT_FAMILIES.find((f) => f.id === id);
}
