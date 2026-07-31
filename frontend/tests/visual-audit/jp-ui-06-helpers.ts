import { expect, type Page } from "@playwright/test";
import { existsSync, mkdirSync, readFileSync, writeFileSync } from "node:fs";
import path from "node:path";
import { themeStorageValue, type ThemeMode } from "./jp-ui-02-scenarios";

export const AUDIT_ROOT = path.resolve(process.cwd(), ".visual-audit", "jp-ui-06");
export const MANIFEST_PATH = path.join(AUDIT_ROOT, "capture-manifest.json");
export const SUMMARY_PATH = path.join(AUDIT_ROOT, "capture-summary.json");
export const GEOMETRY_REPORT_DIR = path.join(AUDIT_ROOT, "geometry");

export type CaptureRecord = {
  id: string;
  family: string;
  route: string;
  fixtureId: string;
  comparisonMode: string;
  wave: number;
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
  geometryReport: string;
  screenshotCreated: boolean;
  overflowOk: boolean;
  hydrationWarnings: string[];
  consoleErrors: string[];
  pageErrors: string[];
  serverPort: number;
  captureDurationMs: number;
  timestamp: string;
  result: "passed" | "failed";
};

export const captureRecords: CaptureRecord[] = [];

export function writeManifest(serverPort: number): void {
  mkdirSync(AUDIT_ROOT, { recursive: true });
  const passed = captureRecords.filter((r) => r.result === "passed").length;
  const failed = captureRecords.filter((r) => r.result === "failed").length;
  const payload = {
    generatedAt: new Date().toISOString(),
    phase: "JP-UI-06",
    expectedScenarioCount: Number(process.env.JP_UI_06_EXPECTED_COUNT ?? "65"),
    serverPort,
    captureCount: captureRecords.length,
    passed,
    failed,
    skipped: 0,
    captures: captureRecords,
  };
  writeFileSync(MANIFEST_PATH, JSON.stringify(payload, null, 2), "utf8");
  writeFileSync(SUMMARY_PATH, JSON.stringify({ ...payload, manifestPath: MANIFEST_PATH }, null, 2), "utf8");
}

export function appendThemeToRoute(route: string, theme: ThemeMode): string {
  const { preference } = themeStorageValue(theme);
  const url = route.startsWith("http") ? new URL(route) : new URL(route, "http://127.0.0.1");
  url.searchParams.set("jpThemePref", preference);
  url.searchParams.set("jpAuditReset", "1");
  return route.startsWith("http") ? url.toString() : `${url.pathname}${url.search}`;
}

export async function applyTheme(page: Page, theme: ThemeMode) {
  const { preference, emulateDark } = themeStorageValue(theme);
  await page.emulateMedia({ colorScheme: emulateDark ? "dark" : "light" });
  const expectedResolvedTheme = theme === "dark" || theme === "system-dark" ? "dark" : "light";
  return { preference, colorScheme: emulateDark ? ("dark" as const) : ("light" as const), expectedResolvedTheme };
}

export async function assertResolvedTheme(page: Page, theme: ThemeMode): Promise<string> {
  const expected = theme === "dark" || theme === "system-dark" ? "dark" : "light";
  await expect(page.locator("html")).toHaveAttribute("data-theme", expected, { timeout: 10_000 });
  return expected;
}

export async function assertNoHorizontalOverflow(page: Page, zoom = 1): Promise<boolean> {
  const tolerance = zoom > 1 ? Math.round(zoom * 12) : 1;
  const overflow = await page.evaluate(() => {
    const viewportWidth = window.innerWidth;
    const docOverflow = Math.max(0, document.documentElement.scrollWidth - viewportWidth);
    const bodyOverflow = Math.max(0, document.body.scrollWidth - viewportWidth);
    return Math.max(docOverflow, bodyOverflow);
  });
  expect(overflow).toBeLessThanOrEqual(tolerance);
  return overflow <= tolerance;
}

export function attachPageMonitors(page: Page) {
  const hydrationWarnings: string[] = [];
  const pageErrors: string[] = [];
  const consoleErrors: string[] = [];
  page.on("console", (msg) => {
    const text = msg.text();
    if (msg.type() === "error" && (/hydration/i.test(text) || /Minified React error #418/.test(text))) {
      hydrationWarnings.push(text);
    }
    if (msg.type() === "error") consoleErrors.push(text);
  });
  page.on("pageerror", (error) => {
    pageErrors.push(error.message);
    if (/Minified React error #418|hydration/i.test(error.message)) hydrationWarnings.push(error.message);
  });
  return { hydrationWarnings, pageErrors, consoleErrors };
}

export async function applyZoom(page: Page, zoom: number): Promise<void> {
  await page.evaluate((factor) => {
    document.documentElement.style.zoom = factor === 1 ? "" : String(factor);
  }, zoom);
}

export async function pauseAnimations(page: Page): Promise<void> {
  await page.addStyleTag({
    content: "*, *::before, *::after { animation-duration: 0s !important; transition-duration: 0s !important; }",
  });
}

export async function waitForFontsAndImages(page: Page): Promise<void> {
  await page.evaluate(async () => {
    await document.fonts.ready;
    await Promise.all(
      Array.from(document.images).map((img) =>
        img.complete ? Promise.resolve() : new Promise<void>((resolve) => { img.onload = () => resolve(); img.onerror = () => resolve(); }),
      ),
    );
  });
}

export async function captureDomGeometry(page: Page, family: string): Promise<Record<string, unknown>> {
  return page.evaluate((fam) => {
    const selectors: Record<string, string> = {
      header: "header",
      main: "main",
      footer: "footer",
      searchPanel: "[data-testid='search-module'], [data-testid='fare-selection-page'], [data-testid='booking-lookup-page']",
      orderSummary: "[data-testid='order-summary']",
      progress: "[data-testid='booking-progress']",
    };
    const boxes: Record<string, { x: number; y: number; width: number; height: number } | null> = {};
    for (const [key, sel] of Object.entries(selectors)) {
      const el = document.querySelector(sel);
      if (!el) { boxes[key] = null; continue; }
      const r = el.getBoundingClientRect();
      boxes[key] = { x: Math.round(r.x), y: Math.round(r.y), width: Math.round(r.width), height: Math.round(r.height) };
    }
    return { family: fam, capturedAt: new Date().toISOString(), boxes };
  }, family);
}

export async function captureScenario(
  page: Page,
  record: Omit<CaptureRecord, "timestamp" | "overflowOk" | "hydrationWarnings" | "consoleErrors" | "pageErrors" | "screenshotCreated" | "captureDurationMs" | "result" | "actualResolvedTheme" | "geometryReport"> & {
    actualResolvedTheme: string;
    monitors: ReturnType<typeof attachPageMonitors>;
    fullPage?: boolean;
    startedAt: number;
  },
): Promise<void> {
  const screenshotPath = path.join(AUDIT_ROOT, record.screenshot);
  mkdirSync(AUDIT_ROOT, { recursive: true });
  await page.screenshot({ path: screenshotPath, fullPage: record.fullPage ?? true });
  const geometry = await captureDomGeometry(page, record.family);
  const geometryReport = `${record.id}-geometry.json`;
  mkdirSync(GEOMETRY_REPORT_DIR, { recursive: true });
  writeFileSync(path.join(GEOMETRY_REPORT_DIR, geometryReport), JSON.stringify(geometry, null, 2), "utf8");
  const overflowOk = await assertNoHorizontalOverflow(page, record.zoom);
  expect(record.monitors.hydrationWarnings).toEqual([]);
  expect(record.monitors.pageErrors).toEqual([]);
  const passed = overflowOk && record.monitors.hydrationWarnings.length === 0 && record.monitors.pageErrors.length === 0 && record.actualResolvedTheme === record.expectedResolvedTheme;
  captureRecords.push({
    ...record,
    geometryReport,
    screenshotCreated: existsSync(screenshotPath),
    overflowOk,
    hydrationWarnings: [...record.monitors.hydrationWarnings],
    consoleErrors: [...record.monitors.consoleErrors],
    pageErrors: [...record.monitors.pageErrors],
    captureDurationMs: Date.now() - record.startedAt,
    timestamp: new Date().toISOString(),
    result: passed ? "passed" : "failed",
  });
}
