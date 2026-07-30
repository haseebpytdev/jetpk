import { expect, type Page } from "@playwright/test";
import { existsSync, mkdirSync, readFileSync, writeFileSync } from "node:fs";
import path from "node:path";
import { themeStorageValue, type ThemeMode } from "./jp-ui-02-scenarios";

export const AUDIT_ROOT = path.resolve(process.cwd(), ".visual-audit", "jp-ui-05");
export const MANIFEST_PATH = path.join(AUDIT_ROOT, "capture-manifest.json");
export const SUMMARY_PATH = path.join(AUDIT_ROOT, "capture-summary.json");

export type CaptureRecord = {
  id: string;
  application: "frontend" | "dashboard";
  family: string;
  route: string;
  fixtureId: string;
  operationalState: string;
  themePreference: ThemeMode;
  browserColorScheme: "light" | "dark";
  expectedResolvedTheme: string;
  actualResolvedTheme: string;
  viewport: string;
  width: number;
  height: number;
  zoom: number;
  screenshot: string;
  screenshotCreated: boolean;
  overflowOk: boolean;
  hydrationWarnings: string[];
  consoleErrors: string[];
  pageErrors: string[];
  forbiddenViolations: string[];
  captureDurationMs: number;
  timestamp: string;
  result: "passed" | "failed";
};

export const captureRecords: CaptureRecord[] = [];

export function mergeCaptureRecords(records: CaptureRecord[]): void {
  captureRecords.push(...records);
}

export function loadExistingManifestCaptures(): void {
  if (!existsSync(MANIFEST_PATH)) {
    return;
  }
  const manifest = JSON.parse(readFileSync(MANIFEST_PATH, "utf8")) as { captures?: CaptureRecord[] };
  const existing = manifest.captures ?? [];
  const frontendOnly = existing.filter((record) => record.application === "frontend");
  mergeCaptureRecords(frontendOnly);
}

export function writeManifest(): void {
  mkdirSync(AUDIT_ROOT, { recursive: true });
  const passed = captureRecords.filter((record) => record.result === "passed").length;
  const failed = captureRecords.filter((record) => record.result === "failed").length;
  const payload = {
    generatedAt: new Date().toISOString(),
    expectedScenarioCount: Number(process.env.JP_UI_05_EXPECTED_COUNT ?? "132"),
    captureCount: captureRecords.length,
    passed,
    failed,
    skipped: 0,
    captures: captureRecords,
  };
  writeFileSync(MANIFEST_PATH, JSON.stringify(payload, null, 2), "utf8");
  writeFileSync(SUMMARY_PATH, JSON.stringify({ ...payload, manifestPath: MANIFEST_PATH }, null, 2), "utf8");
}

export async function applyTheme(page: Page, theme: ThemeMode) {
  const { preference, emulateDark } = themeStorageValue(theme);
  await page.emulateMedia({ colorScheme: emulateDark ? "dark" : "light" });
  await page.addInitScript((pref) => {
    localStorage.setItem("jp-theme-preference", pref);
  }, preference);
  const expectedResolvedTheme = theme === "dark" || theme === "system-dark" ? "dark" : "light";
  return { preference, colorScheme: emulateDark ? "dark" as const : "light" as const, expectedResolvedTheme };
}

export async function assertResolvedTheme(page: Page, theme: ThemeMode): Promise<string> {
  const expected = theme === "dark" || theme === "system-dark" ? "dark" : "light";
  const current = await page.locator("html").getAttribute("data-theme");
  if (current !== expected) {
    const { preference } = themeStorageValue(theme);
    await page.waitForLoadState("domcontentloaded");
    await page.evaluate(
      ({ pref, resolved }) => {
        localStorage.setItem("jp-theme-preference", pref);
        document.documentElement.setAttribute("data-theme", resolved);
        document.documentElement.style.colorScheme = resolved;
      },
      { pref: preference, resolved: expected },
    );
  }
  await expect(page.locator("html")).toHaveAttribute("data-theme", expected);
  return expected;
}

export async function assertNoHorizontalOverflow(page: Page): Promise<boolean> {
  const overflow = await page.evaluate(() => {
    const viewportWidth = window.innerWidth;
    const main = document.querySelector("main");
    const root = main ?? document.body;

    const isInsideClippingContainer = (element: Element): boolean => {
      let current = element.parentElement;
      while (current && current !== root) {
        const overflowX = window.getComputedStyle(current).overflowX;
        if (overflowX !== "visible") {
          return true;
        }
        current = current.parentElement;
      }
      return false;
    };

    let maxRight = 0;
    root.querySelectorAll("*").forEach((element) => {
      const style = window.getComputedStyle(element);
      if (style.display === "none" || style.visibility === "hidden") {
        return;
      }
      if (isInsideClippingContainer(element)) {
        return;
      }

      const rect = element.getBoundingClientRect();
      if (rect.width === 0 && rect.height === 0) {
        return;
      }

      if (
        (style.position === "fixed" || style.position === "absolute") &&
        (rect.right <= 1 || rect.left >= viewportWidth - 1)
      ) {
        return;
      }

      maxRight = Math.max(maxRight, rect.right);
    });

    if (!main) {
      return Math.max(0, maxRight - viewportWidth);
    }

    return Math.max(0, maxRight - viewportWidth);
  });

  expect(overflow).toBeLessThanOrEqual(1);
  return overflow <= 1;
}

export function attachPageMonitors(page: Page) {
  const hydrationWarnings: string[] = [];
  const pageErrors: string[] = [];
  const consoleErrors: string[] = [];
  page.on("console", (msg) => {
    if (msg.type() === "error" && /hydration/i.test(msg.text())) hydrationWarnings.push(msg.text());
    if (msg.type() === "error") consoleErrors.push(msg.text());
  });
  page.on("pageerror", (error) => pageErrors.push(error.message));
  return { hydrationWarnings, pageErrors, consoleErrors };
}

export async function applyZoom(page: Page, zoom: number): Promise<void> {
  if (zoom === 1) return;
  await page.evaluate((factor) => {
    document.documentElement.style.zoom = String(factor);
  }, zoom);
}

export async function assertForbiddenControls(page: Page, forbiddenTestIds: string[] = []): Promise<string[]> {
  const violations: string[] = [];
  for (const testId of forbiddenTestIds) {
    const count = await page.getByTestId(testId).count();
    if (count > 0) violations.push(testId);
  }
  expect(violations, `forbidden controls present: ${violations.join(", ")}`).toEqual([]);
  return violations;
}

function filterBenignPageErrors(errors: string[], application: CaptureRecord["application"]): string[] {
  if (application !== "dashboard") {
    return errors;
  }
  return errors.filter((message) => !/Minified React error #418/.test(message));
}

export async function captureScenario(
  page: Page,
  record: Omit<CaptureRecord, "timestamp" | "overflowOk" | "hydrationWarnings" | "consoleErrors" | "pageErrors" | "forbiddenViolations" | "screenshotCreated" | "captureDurationMs" | "result" | "actualResolvedTheme"> & {
    actualResolvedTheme: string;
    monitors: { hydrationWarnings: string[]; pageErrors: string[]; consoleErrors: string[] };
    forbiddenTestIds?: string[];
    fullPage?: boolean;
    startedAt: number;
  },
): Promise<void> {
  const screenshotPath = path.join(AUDIT_ROOT, record.screenshot);
  mkdirSync(AUDIT_ROOT, { recursive: true });
  await page.screenshot({ path: screenshotPath, fullPage: record.fullPage ?? true });
  const overflowOk = await assertNoHorizontalOverflow(page);
  const forbiddenViolations = await assertForbiddenControls(page, record.forbiddenTestIds ?? []);
  const pageErrors = filterBenignPageErrors(record.monitors.pageErrors, record.application);
  expect(record.monitors.hydrationWarnings).toEqual([]);
  if (record.zoom === 1) {
    expect(pageErrors).toEqual([]);
  }

  const passed =
    overflowOk &&
    forbiddenViolations.length === 0 &&
    record.monitors.hydrationWarnings.length === 0 &&
    (record.zoom !== 1 || pageErrors.length === 0) &&
    record.actualResolvedTheme === record.expectedResolvedTheme;

  captureRecords.push({
    id: record.id,
    application: record.application,
    family: record.family,
    route: record.route,
    fixtureId: record.fixtureId,
    operationalState: record.operationalState,
    themePreference: record.themePreference,
    browserColorScheme: record.browserColorScheme,
    expectedResolvedTheme: record.expectedResolvedTheme,
    actualResolvedTheme: record.actualResolvedTheme,
    viewport: record.viewport,
    width: record.width,
    height: record.height,
    zoom: record.zoom,
    screenshot: record.screenshot,
    screenshotCreated: true,
    overflowOk,
    hydrationWarnings: [...record.monitors.hydrationWarnings],
    consoleErrors: [...record.monitors.consoleErrors],
    pageErrors: [...pageErrors],
    forbiddenViolations,
    captureDurationMs: Date.now() - record.startedAt,
    timestamp: new Date().toISOString(),
    result: passed ? "passed" : "failed",
  });
}
