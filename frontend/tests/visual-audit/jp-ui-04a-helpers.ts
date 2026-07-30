import { expect, type Page } from "@playwright/test";
import { mkdirSync, writeFileSync } from "node:fs";
import path from "node:path";
import { themeStorageValue, type ThemeMode } from "./jp-ui-02-scenarios";

export const AUDIT_ROOT = path.resolve(process.cwd(), ".visual-audit", "jp-ui-04a");
export const MANIFEST_PATH = path.join(AUDIT_ROOT, "capture-manifest.json");
export const SUMMARY_PATH = path.join(AUDIT_ROOT, "capture-summary.json");

export type CaptureRecord = {
  id: string;
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

export function writeManifest(): void {
  mkdirSync(AUDIT_ROOT, { recursive: true });
  const passed = captureRecords.filter((r) => r.result === "passed").length;
  const failed = captureRecords.filter((r) => r.result === "failed").length;
  const payload = {
    generatedAt: new Date().toISOString(),
    expectedScenarioCount: Number(process.env.JP_UI_04A_EXPECTED_COUNT ?? "120"),
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
  await expect(page.locator("html")).toHaveAttribute("data-theme", expected);
  return expected;
}

export async function assertNoHorizontalOverflow(page: Page): Promise<boolean> {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
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
  expect(record.monitors.hydrationWarnings).toEqual([]);
  expect(record.monitors.pageErrors).toEqual([]);

  const passed =
    overflowOk &&
    forbiddenViolations.length === 0 &&
    record.monitors.hydrationWarnings.length === 0 &&
    record.monitors.pageErrors.length === 0 &&
    record.actualResolvedTheme === record.expectedResolvedTheme;

  captureRecords.push({
    id: record.id,
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
    pageErrors: [...record.monitors.pageErrors],
    forbiddenViolations,
    captureDurationMs: Date.now() - record.startedAt,
    timestamp: new Date().toISOString(),
    result: passed ? "passed" : "failed",
  });
}
