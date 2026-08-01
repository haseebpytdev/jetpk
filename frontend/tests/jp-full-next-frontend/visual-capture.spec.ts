import { mkdirSync, writeFileSync } from "node:fs";
import path from "node:path";
import { test, expect } from "@playwright/test";
import { setupScenarioMocks, resultsQuery } from "../visual-audit/jp-ui-01-fixtures";

const AUDIT_ROOT = path.resolve(process.cwd(), ".visual-audit", "jp-full-next-frontend");
const VIEWPORTS = [
  { name: "desktop", width: 1440, height: 1200 },
  { name: "tablet", width: 768, height: 1024 },
  { name: "mobile", width: 390, height: 844 },
] as const;

const SCENARIOS = [
  { id: "home", route: "/", setup: "public" as const },
  { id: "about", route: "/about-us", setup: "public" as const },
  { id: "support", route: "/support", setup: "public" as const },
  { id: "login", route: "/login", setup: "auth" as const },
  { id: "signup", route: "/register", setup: "auth" as const },
  { id: "results", route: `/flights/results?${resultsQuery()}`, setup: "results" as const },
  { id: "fare-selection", route: "/flights/fare-selection?search_id=jp-ui-01-audit-search-id&offer_id=audit-offer-1", setup: "fare-selection" as const },
  { id: "passengers", route: "/booking/passengers", setup: "passengers" as const },
  { id: "review", route: "/booking/review", setup: "review" as const },
  { id: "payment", route: "/booking/payment", setup: "payment" as const },
  { id: "confirmation", route: "/booking/confirmation", setup: "confirmation" as const },
  { id: "manage-booking", route: "/lookup-booking", setup: "lookup" as const },
] as const;

type CaptureRecord = {
  scenarioId: string;
  viewport: string;
  theme: "light" | "dark";
  screenshot: string;
};

const captures: CaptureRecord[] = [];

test.describe.configure({ mode: "serial" });

test.beforeAll(() => {
  mkdirSync(AUDIT_ROOT, { recursive: true });
});

test.afterAll(() => {
  writeFileSync(
    path.join(AUDIT_ROOT, "capture-manifest.json"),
    JSON.stringify({ generatedAt: new Date().toISOString(), captures, deferred: ["seat-selection"] }, null, 2),
    "utf8",
  );
});

for (const scenario of SCENARIOS) {
  for (const viewport of VIEWPORTS) {
    for (const theme of ["light", "dark"] as const) {
      test(`capture ${scenario.id} ${viewport.name} ${theme}`, async ({ page }) => {
        await setupScenarioMocks(page, scenario.setup);
        if (theme === "dark") {
          await page.addInitScript(() => localStorage.setItem("jp-theme-preference", "dark"));
          await page.emulateMedia({ colorScheme: "dark" });
        }
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        await page.goto(scenario.route, { waitUntil: "load", timeout: 90_000 });
        await page.evaluate(async () => {
          await document.fonts.ready;
        });
        const file = path.join(AUDIT_ROOT, `${scenario.id}-${viewport.name}-${theme}.png`);
        await page.screenshot({ path: file, fullPage: true, animations: "disabled" });
        captures.push({ scenarioId: scenario.id, viewport: viewport.name, theme, screenshot: file });
        expect(await page.title()).not.toEqual("");
      });
    }
  }
}
